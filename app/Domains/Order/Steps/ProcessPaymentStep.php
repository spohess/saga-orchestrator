<?php

declare(strict_types=1);

namespace App\Domains\Order\Steps;

use App\Models\Order;
use App\Supports\Saga\SagaContext;
use App\Supports\Saga\SagaStepInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class ProcessPaymentStep implements SagaStepInterface
{
    public function run(SagaContext $context): void
    {
        /** @var Order $order */
        $order = $context->get('order');

        $response = Http::post('https://external-service.example.com/pay', [
            'order_id' => $order->id,
            'customer_email' => $order->customer_email,
            'product' => $order->product,
        ]);

        if ($response->failed()) {
            throw new RuntimeException('External service payment failed.');
        }

        $amount = $response->json('amount');

        $order->update(['amount' => $amount]);

        $context->set('amount', $amount);
    }

    public function rollback(SagaContext $context): void
    {
        $amount = $context->get('amount');

        if ($amount) {
            Http::post('https://external-service.example.com/refund', [
                'amount' => $amount,
            ]);
        }
    }
}
