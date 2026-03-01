<?php

declare(strict_types=1);

use App\Domains\Order\Handlers\SendOrderNotificationHandler;
use App\Supports\Queue\QueueJob;
use App\Supports\Queue\QueueMessage;
use App\Supports\Services\Notification\NotificationService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

it('calls NotificationService with correct data when processing message', function () {
    Bus::fake();
    Http::fake([
        'https://external-service.example.com/notify' => Http::response(['status' => 'sent'], 200),
    ]);

    $message = QueueMessage::create(
        source: 'order-confirmed',
        queue: 'notifications',
        data: [
            'email' => 'customer@example.com',
            'subject' => 'Order Confirmed',
            'message' => 'Your order has been confirmed.',
        ],
    );

    $handler = new SendOrderNotificationHandler();
    $handler->handle($message);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://external-service.example.com/notify'
            && $request['email'] === 'customer@example.com'
            && $request['subject'] === 'Order Confirmed'
            && $request['message'] === 'Your order has been confirmed.';
    });
});

it('dispatches QueueJob to retry queue when NotificationService throws', function () {
    Bus::fake();

    $this->app->bind(NotificationService::class, function () {
        throw new RuntimeException('Service unavailable');
    });

    $message = QueueMessage::create(
        source: 'order-confirmed',
        queue: 'notifications',
        data: [
            'email' => 'customer@example.com',
            'subject' => 'Order Confirmed',
            'message' => 'Your order has been confirmed.',
        ],
    );

    $handler = new SendOrderNotificationHandler();
    $handler->handle($message);

    Bus::assertDispatched(QueueJob::class, function (QueueJob $job) {
        return $job->queue === 'notifications_retry';
    });
});
