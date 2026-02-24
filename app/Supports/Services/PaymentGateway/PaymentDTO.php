<?php

declare(strict_types=1);

namespace App\Supports\Services\PaymentGateway;

use App\Supports\Interfaces\DTOInterface;

final class PaymentDTO implements DTOInterface
{
    public function __construct(
        public readonly mixed $amount,
    ) {}

    public function toArray(): array
    {
        return ['amount' => $this->amount];
    }
}
