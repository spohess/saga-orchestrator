<?php

declare(strict_types=1);

namespace App\Domains\Order\Steps;

use App\Events\OrderConfirmedEvent;
use App\Models\Order;
use App\Supports\Saga\StepDispatchesEvent;
use App\Supports\Saga\SagaContext;
use App\Supports\Saga\SagaStepInterface;

final class ConfirmOrderStep implements SagaStepInterface, StepDispatchesEvent
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
