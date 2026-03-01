<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\OrderConfirmedEvent;
use App\Supports\Queue\QueueProducer;

final class SendOrderConfirmedNotificationListener
{
    public function handle(OrderConfirmedEvent $event): void
    {
        $order = $event->context->get('order');

        $producer = new QueueProducer(queue: 'notifications');

        $producer->publish(
            source: 'order-confirmed',
            data: [
                'email' => $order->customer_email,
                'subject' => 'Order Confirmed',
                'message' => "Your order for {$order->product} has been confirmed.",
            ],
        );
    }
}
