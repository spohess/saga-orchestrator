<?php

declare(strict_types=1);

namespace App\Events;

use App\Supports\Saga\SagaContext;
use Illuminate\Foundation\Events\Dispatchable;

final class OrderConfirmedEvent
{
    use Dispatchable;

    public function __construct(
        public readonly SagaContext $context,
    ) {}
}
