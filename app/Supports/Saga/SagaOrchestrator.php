<?php

declare(strict_types=1);

namespace App\Supports\Saga;

use App\Models\SagaFailureLog;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Throwable;

final class SagaOrchestrator
{
    /** @var array<int, array{step: class-string<SagaStepInterface>, retries: int, sleep: int}> */
    private array $steps = [];

    /** @var array<int, SagaStepInterface> */
    private array $executedSteps = [];

    /** @var array<int, mixed> */
    private array $collectedEvents = [];

    private ?string $failedStep = null;

    private SagaContext $context;

    /** @param class-string<SagaStepInterface> $step */
    public function addStep(
        string $step,
        int $retries = 0,
        int $sleep = 0,
    ): self {
        $this->steps[] = [
            'step' => $step,
            'retries' => $retries,
            'sleep' => $sleep,
        ];

        return $this;
    }

    public function execute(
        ?SagaContext $context = null,
    ): SagaContext {
        $this->context = $context ?? new SagaContext();
        $this->executedSteps = [];
        $this->collectedEvents = [];
        $this->failedStep = null;

        try {
            foreach ($this->steps as $stepConfig) {
                $this->failedStep = Arr::get($stepConfig, 'step');
                $instance = $this->runWithRetries($stepConfig);
                $this->executedSteps[] = $instance;

                if ($instance instanceof StepDispatchesEventInterface) {
                    $this->collectedEvents[] = $instance->event($this->context);
                }
            }
        } catch (Throwable $exception) {
            $this->compensate($exception);

            throw $exception;
        }

        $this->dispatchEvents();

        return $this->context;
    }

    private function compensate(Throwable $exception): void
    {
        $compensatedSteps = [];
        $compensationFailures = [];

        foreach (array_reverse($this->executedSteps) as $executedStep) {
            try {
                $executedStep->rollback($this->context);
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
            'failed_step' => $this->failedStep,
            'exception_class' => $exception::class,
            'exception_message' => $exception->getMessage(),
            'executed_steps' => array_map(fn(SagaStepInterface $s): string => $s::class, $this->executedSteps),
            'compensated_steps' => $compensatedSteps,
            'compensation_failures' => $compensationFailures,
            'context_snapshot' => $this->context->toArray(),
        ]);
    }

    /** @param array{step: class-string<SagaStepInterface>, retries: int, sleep: int} $stepConfig */
    private function runWithRetries(array $stepConfig): SagaStepInterface
    {
        $attempts = Arr::get($stepConfig, 'retries', 0) + 1;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                if ($attempt > 1 && Arr::get($stepConfig, 'sleep', 0) > 0) {
                    Sleep::for(Arr::get($stepConfig, 'sleep', 0))->seconds();
                }

                $instance = app(Arr::get($stepConfig, 'step'));
                $instance->run($this->context);

                return $instance;
            } catch (Throwable $exception) {
                if ($attempt === $attempts) {
                    throw $exception;
                }
            }
        }

        throw $exception;
    }

    private function dispatchEvents(): void
    {
        foreach ($this->collectedEvents as $event) {
            try {
                Event::dispatch($event);
            } catch (Throwable $e) {
                report($e);
            }
        }
    }
}
