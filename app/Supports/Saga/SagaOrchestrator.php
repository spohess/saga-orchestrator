<?php

declare(strict_types=1);

namespace App\Supports\Saga;

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

        try {
            foreach ($this->steps as $step) {
                $instance = app($step);
                $instance->run($context);
                $executedSteps[] = $instance;
            }
        } catch (Throwable $exception) {
            foreach (array_reverse($executedSteps) as $executedStep) {
                $executedStep->rollback($context);
            }

            throw $exception;
        }

        return $context;
    }
}
