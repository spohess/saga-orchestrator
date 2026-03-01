<?php

declare(strict_types=1);

use App\Events\OrderConfirmedEvent;
use App\Listeners\SendOrderConfirmedNotificationListener;
use App\Supports\Queue\QueueJob;
use App\Supports\Saga\SagaContext;
use Illuminate\Support\Facades\Bus;

beforeEach(function () {
    Bus::fake();
});

it('dispatches QueueJob to notifications queue', function () {
    $order = (object) [
        'customer_email' => 'john@example.com',
        'product' => 'Widget',
    ];

    $context = new SagaContext();
    $context->set('order', $order);

    $event = new OrderConfirmedEvent($context);

    $listener = new SendOrderConfirmedNotificationListener();
    $listener->handle($event);

    Bus::assertDispatched(QueueJob::class, function (QueueJob $job) {
        return $job->queue === 'notifications';
    });
});

it('publishes message with correct order data', function () {
    $order = (object) [
        'customer_email' => 'jane@example.com',
        'product' => 'Gadget',
    ];

    $context = new SagaContext();
    $context->set('order', $order);

    $event = new OrderConfirmedEvent($context);

    $listener = new SendOrderConfirmedNotificationListener();
    $listener->handle($event);

    Bus::assertDispatched(QueueJob::class, function (QueueJob $job) {
        $data = $job->message->getData();

        return $data['email'] === 'jane@example.com'
            && $data['subject'] === 'Order Confirmed'
            && $data['message'] === 'Your order for Gadget has been confirmed.'
            && $job->message->getSource() === 'order-confirmed';
    });
});
