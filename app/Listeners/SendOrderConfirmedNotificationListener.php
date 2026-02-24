<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\OrderConfirmedEvent;
use App\Supports\Services\Notification\NotificationInput;
use App\Supports\Services\Notification\NotificationService;

final class SendOrderConfirmedNotificationListener
{
    public function __construct(
        private NotificationService $notificationService,
    ) {}

    public function handle(OrderConfirmedEvent $event): void
    {
        $order = $event->context->get('order');

        $this->notificationService->execute(NotificationInput::fromArray([
            'email' => $order->customer_email,
            'subject' => 'Order Confirmed',
            'message' => "Your order for {$order->product} has been confirmed.",
        ]));
    }
}
