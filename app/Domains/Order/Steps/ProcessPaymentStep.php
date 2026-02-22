<?php

declare(strict_types=1);

namespace App\Domains\Order\Steps;

use App\Models\Order;
use App\Supports\Saga\SagaContext;
use App\Supports\Saga\SagaStepInterface;
use App\Supports\Services\PaymentGateway\PaymentService;
use App\Supports\Services\PaymentGateway\RefundService;

final class ProcessPaymentStep implements SagaStepInterface
{
    public function __construct(
        private PaymentService $paymentService,
        private RefundService $refundService,
    ) {}

    public function run(SagaContext $context): void
    {
        /** @var Order $order */
        $order = $context->get('order');

        $amount = $this->paymentService->execute([
            'order_id' => $order->id,
            'customer_email' => $order->customer_email,
            'product' => $order->product,
        ]);

        $order->update(['amount' => $amount]);

        $context->set('amount', $amount);
    }

    public function rollback(SagaContext $context): void
    {
        $amount = $context->get('amount');

        if ($amount) {
            $this->refundService->execute([
                'amount' => $amount,
            ]);
        }
    }
}
