<?php

declare(strict_types=1);

namespace App\Supports\Services\PaymentGateway;

use App\Supports\Interfaces\DTOInterface;

final class RefundDTO implements DTOInterface
{
    public function __construct(
        public readonly string $protocol,
    ) {}

    public function toArray(): array
    {
        return ['protocol' => $this->protocol];
    }
}
