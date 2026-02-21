<?php

declare(strict_types=1);

namespace App\Supports\Saga;

use App\Models\SagaFailureLog;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Throwable;

final class SagaOrchestrator
{
    /** @var array<int, array{step: class-string<SagaStepInterface>, retries: int, sleep: int}> */
    private array $steps = [];

    /** @param class-string<SagaStepInterface> $step */
    public function addStep(string $step, int $retries = 0, int $sleep = 0): self
    {
        $this->steps[] = [
            'step' => $step,
            'retries' => $retries,
            'sleep' => $sleep,
        ];

        return $this;
    }

    public function execute(?SagaContext $context = null): SagaContext
    {
        $context ??= new SagaContext;

        /** @var array<int, SagaStepInterface> $executedSteps */
        $executedSteps = [];
        $failedStep = null;

        try {
            foreach ($this->steps as $stepConfig) {
                $failedStep = $stepConfig['step'];
                $executedSteps[] = $this->runWithRetries($stepConfig, $context);
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

    /** @param array{step: class-string<SagaStepInterface>, retries: int, sleep: int} $stepConfig */
    private function runWithRetries(array $stepConfig, SagaContext $context): SagaStepInterface
    {
        $attempts = $stepConfig['retries'] + 1;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                if ($attempt > 1) {
                    Sleep::for($stepConfig['sleep'])->seconds();
                }

                $instance = app($stepConfig['step']);
                $instance->run($context);

                return $instance;
            } catch (Throwable $exception) {
                if ($attempt === $attempts) {
                    throw $exception;
                }
            }
        }
    }
}
