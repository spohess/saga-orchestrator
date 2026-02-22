<?php

declare(strict_types=1);

namespace App\Supports\Saga;

final class GenericSagaContextData implements SagaContextDTOInterface
{
    public function __construct(
        private readonly SagaContext $context,
    ) {}

    public static function fromContext(SagaContext $context): static
    {
        return new self($context);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->context->get($key, $default);
    }

    /** @return array<string, mixed> */
    public function toSnapshot(): array
    {
        return [];
    }
}
