<?php

declare(strict_types=1);

namespace App\Supports\Abstracts;

use App\Supports\Interfaces\InputInterface;

abstract class Input implements InputInterface
{
    protected array $data = [];

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public static function fromArray(array $data): self
    {
        $instance = new static;
        $instance->data = $data;

        return $instance;
    }
}
