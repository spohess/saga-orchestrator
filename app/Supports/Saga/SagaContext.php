<?php

declare(strict_types=1);

namespace App\Supports\Saga;

final class SagaContext
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    /** @param array<string, mixed> $data */
    public function setFromArray(array $data): void
    {
        $this->data = array_merge($this->data, $data);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }
}
