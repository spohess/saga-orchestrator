<?php

declare(strict_types=1);

namespace App\Supports\Saga;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

final class SagaContext
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function set(string $key, mixed $value): void
    {
        Arr::set($this->data, $key, $value);
    }

    /** @param array<string, mixed> $data */
    public function setFromArray(array $data): void
    {
        foreach ($data as $key => $value) {
            $this->set($key, $value);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->data, $key, $default);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_map(function (mixed $value): mixed {
            if ($value instanceof Model) {
                return $value->toArray();
            }

            return $value;
        }, $this->data);
    }
}
