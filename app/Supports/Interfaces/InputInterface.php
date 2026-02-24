<?php

declare(strict_types=1);

namespace App\Supports\Interfaces;

interface InputInterface
{
    public static function fromArray(array $data): self;
}
