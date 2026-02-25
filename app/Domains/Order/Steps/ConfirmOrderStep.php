<?php

declare(strict_types=1);

namespace App\Domains\Order\Steps;

use App\Events\OrderConfirmedEvent;
use App\Models\Order;
use App\Supports\Saga\SagaContext;
use App\Supports\Saga\SagaStepInterface;
use App\Supports\Saga\StepDispatchesEventInterface;

final class ConfirmOrderStep implements SagaStepInterface, StepDispatchesEventInterface
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

    public function event(SagaContext $context): object
    {
        return new OrderConfirmedEvent($context);
    }
}
