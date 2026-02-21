<?php

declare(strict_types=1);

namespace App\Domains\Order\Steps;

use App\Models\Order;
use App\Supports\Saga\SagaContext;
use App\Supports\Saga\SagaStepInterface;

final class CreateOrderStep implements SagaStepInterface
{
    public function run(SagaContext $context): void
    {
        $order = Order::create([
            'customer_name' => $context->get('customer_name'),
            'customer_email' => $context->get('customer_email'),
            'product' => $context->get('product'),
            'quantity' => $context->get('quantity'),
            'total_price' => $context->get('total_price'),
            'status' => 'pending',
        ]);

        $context->set('order', $order);
    }

    public function rollback(SagaContext $context): void
    {
        /** @var Order|null $order */
        $order = $context->get('order');

        $order?->update(['status' => 'failed']);
    }
}
