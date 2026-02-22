<?php

declare(strict_types=1);

namespace App\Supports\Saga;

interface SagaContextDTOInterface
{
    public static function fromContext(SagaContext $context): static;

    /** @return array<string, mixed> */
    public function toSnapshot(): array;
}
