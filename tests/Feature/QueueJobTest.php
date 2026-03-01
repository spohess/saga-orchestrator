<?php

declare(strict_types=1);

use App\Supports\Queue\Abstracts\AbstractQueueHandler;
use App\Supports\Queue\QueueJob;
use App\Supports\Queue\QueueMessage;
use App\Supports\Queue\QueueRouter;

it('resolves handler via QueueRouter and delegates processing', function () {
    $message = QueueMessage::create(
        source: 'test-service',
        queue: 'orders',
        data: ['order_id' => 1],
    );

    $handlerClass = get_class(new class extends AbstractQueueHandler {
        public static bool $processed = false;

        protected function process(QueueMessage $message): void
        {
            static::$processed = true;
        }
    });

    $router = app(QueueRouter::class);
    $router->register('orders', $handlerClass);

    $job = new QueueJob($message);
    $job->onQueue('orders');
    $job->handle();

    expect($handlerClass::$processed)->toBeTrue();
});

it('sets tries and maxExceptions to 1 to prevent laravel auto-retry', function () {
    $message = QueueMessage::create(
        source: 'test-service',
        queue: 'orders',
        data: [],
    );

    $job = new QueueJob($message);

    expect($job->tries)->toBe(1)
        ->and($job->maxExceptions)->toBe(1);
});

it('resolves handler by original queue name when consumed from retry queue', function () {
    $message = QueueMessage::create(
        source: 'test-service',
        queue: 'orders',
        data: ['order_id' => 1],
    );

    $handlerClass = get_class(new class extends AbstractQueueHandler {
        public static bool $processedFromRetry = false;

        protected function process(QueueMessage $message): void
        {
            static::$processedFromRetry = true;
        }
    });

    $router = app(QueueRouter::class);
    $router->register('orders', $handlerClass);

    $job = new QueueJob($message);
    $job->onQueue('orders_retry');
    $job->handle();

    expect($handlerClass::$processedFromRetry)->toBeTrue();
});

it('carries the QueueMessage as a public readonly property', function () {
    $message = QueueMessage::create(
        source: 'test-service',
        queue: 'orders',
        data: ['key' => 'value'],
    );

    $job = new QueueJob($message);

    expect($job->message)->toBe($message)
        ->and($job->message->getData())->toBe(['key' => 'value']);
});
