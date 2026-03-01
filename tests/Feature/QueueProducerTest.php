<?php

declare(strict_types=1);

use App\Supports\Queue\QueueJob;
use App\Supports\Queue\QueueMessage;
use App\Supports\Queue\QueueProducer;
use Illuminate\Support\Facades\Bus;

beforeEach(function () {
    Bus::fake();
});

it('creates a QueueMessage with the correct attributes', function () {
    $producer = new QueueProducer(queue: 'orders');

    $message = $producer->publish(
        source: 'checkout-service',
        data: ['order_id' => 123],
        metadata: ['priority' => 'high'],
    );

    expect($message)->toBeInstanceOf(QueueMessage::class)
        ->and($message->getSource())->toBe('checkout-service')
        ->and($message->getQueue())->toBe('orders')
        ->and($message->getData())->toBe(['order_id' => 123])
        ->and($message->getMetadata())->toBe(['priority' => 'high'])
        ->and($message->getRetryCount())->toBe(0)
        ->and($message->getError())->toBeNull()
        ->and($message->getMessageId())->toBeString()->not->toBeEmpty();
});

it('dispatches QueueJob on the correct queue', function () {
    $producer = new QueueProducer(queue: 'orders');

    $producer->publish(
        source: 'checkout-service',
        data: ['order_id' => 456],
    );

    Bus::assertDispatched(QueueJob::class, function (QueueJob $job) {
        return $job->queue === 'orders';
    });
});

it('publishes without metadata by default', function () {
    $producer = new QueueProducer(queue: 'notifications');

    $message = $producer->publish(
        source: 'user-service',
        data: ['user_id' => 1],
    );

    expect($message->getMetadata())->toBe([]);

    Bus::assertDispatched(QueueJob::class);
});
