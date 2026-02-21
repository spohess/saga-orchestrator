<?php

declare(strict_types=1);

namespace App\Supports\Saga;

interface SagaStepInterface
{
    public function run(SagaContext $context): void;

    public function rollback(SagaContext $context): void;
}
