<?php

declare(strict_types=1);

namespace App\Supports\Interfaces;

interface ServicesInterface
{
    public function execute(array $data): mixed;
}
