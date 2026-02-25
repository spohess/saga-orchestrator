<?php

declare(strict_types=1);

namespace App\Supports\Saga;

interface StepDispatchesEventInterface
{
    public function event(SagaContext $context): object;
}
