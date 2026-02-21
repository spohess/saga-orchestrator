<?php

declare(strict_types=1);

namespace App\Domains\Order\Steps;

use App\Models\Order;
use App\Supports\Saga\SagaContext;
use App\Supports\Saga\SagaStepInterface;

final class ConfirmOrderStep implements SagaStepInterface
{
    public function run(SagaContext $context): void
    {
        /** @var Order $order */
        $order = $context->get('order');

        $order->update(['status' => 'confirmed']);
    }

    public function rollback(SagaContext $context): void
    {
        /** @var Order $order */
        $order = $context->get('order');

        $order->update(['status' => 'failed']);
    }
}
