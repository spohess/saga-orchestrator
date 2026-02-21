<?php

declare(strict_types=1);

namespace App\Supports\Saga;

use App\Models\SagaFailureLog;
use Illuminate\Support\Str;
use Throwable;

final class SagaOrchestrator
{
    /** @var array<int, class-string<SagaStepInterface>> */
    private array $steps = [];

    /** @param class-string<SagaStepInterface> $step */
    public function addStep(string $step): self
    {
        $this->steps[] = $step;

        return $this;
    }

    public function execute(?SagaContext $context = null): SagaContext
    {
        $context ??= new SagaContext;

        /** @var array<int, SagaStepInterface> $executedSteps */
        $executedSteps = [];
        $failedStep = null;

        try {
            foreach ($this->steps as $step) {
                $failedStep = $step;
                $instance = app($step);
                $instance->run($context);
                $executedSteps[] = $instance;
            }
        } catch (Throwable $exception) {
            $compensatedSteps = [];
            $compensationFailures = [];

            foreach (array_reverse($executedSteps) as $executedStep) {
                try {
                    $executedStep->rollback($context);
                    $compensatedSteps[] = $executedStep::class;
                } catch (Throwable $rollbackException) {
                    $compensationFailures[] = [
                        'step' => $executedStep::class,
                        'exception_class' => $rollbackException::class,
                        'message' => $rollbackException->getMessage(),
                    ];
                }
            }

            SagaFailureLog::create([
                'saga_id' => (string) Str::uuid(),
                'failed_step' => $failedStep,
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
                'executed_steps' => array_map(fn (SagaStepInterface $s): string => $s::class, $executedSteps),
                'compensated_steps' => $compensatedSteps,
                'compensation_failures' => $compensationFailures,
                'context_snapshot' => $context->toArray(),
            ]);

            throw $exception;
        }

        return $context;
    }
}
