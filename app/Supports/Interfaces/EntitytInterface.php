<?php

declare(strict_types=1);

namespace App\Supports\Interfaces;

interface EntitytInterface
{
    public function fromArray(array $data): self;

    public function toArray(): array;
}
