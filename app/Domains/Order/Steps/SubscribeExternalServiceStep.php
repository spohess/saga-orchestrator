<?php

declare(strict_types=1);

namespace App\Domains\Order\Steps;

use App\Models\Order;
use App\Supports\Saga\SagaContext;
use App\Supports\Saga\SagaStepInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class SubscribeExternalServiceStep implements SagaStepInterface
{
    public function run(SagaContext $context): void
    {
        /** @var Order $order */
        $order = $context->get('order');

        $response = Http::post('https://external-service.example.com/subscribe', [
            'order_id' => $order->id,
            'customer_email' => $order->customer_email,
            'product' => $order->product,
        ]);

        if ($response->failed()) {
            throw new RuntimeException('External service subscription failed.');
        }

        $subscriptionId = $response->json('subscription_id');

        $order->update(['external_subscription_id' => $subscriptionId]);

        $context->set('external_subscription_id', $subscriptionId);
    }

    public function rollback(SagaContext $context): void
    {
        $subscriptionId = $context->get('external_subscription_id');

        if ($subscriptionId) {
            Http::post('https://external-service.example.com/deactivate', [
                'subscription_id' => $subscriptionId,
            ]);
        }
    }
}
