<?php

declare(strict_types=1);

use App\Supports\Queue\Abstracts\AbstractQueueHandler;
use App\Supports\Queue\QueueMessage;
use Illuminate\Support\Facades\Bus;

beforeEach(function () {
    Bus::fake();
});

it('processes the message successfully without dispatching retries', function () {
    $message = QueueMessage::create(
        source: 'test-service',
        queue: 'orders',
        data: ['order_id' => 1],
    );

    $handler = new class ($message) extends AbstractQueueHandler {
        public bool $processed = false;

        protected function process(QueueMessage $message): void
        {
            $this->processed = true;
        }
    };

    $handler->handle();

    expect($handler->processed)->toBeTrue();
    Bus::assertNothingDispatched();
});

it('dispatches to retry queue when process fails and retries remain', function () {
    $message = QueueMessage::create(
        source: 'test-service',
        queue: 'orders',
        data: ['order_id' => 1],
    );

    $handlerClass = get_class(new class ($message) extends AbstractQueueHandler {
        protected int $maxRetries = 3;

        protected function process(QueueMessage $message): void
        {
            throw new RuntimeException('Processing failed', 500);
        }
    });

    Bus::fake();

    $handler = new $handlerClass($message);
    $handler->handle();

    Bus::assertDispatched($handlerClass, function ($job) {
        return $job->queue === 'orders_retry';
    });
});

it('dispatches to dlq queue when retries are exhausted', function () {
    $message = QueueMessage::create(
        source: 'test-service',
        queue: 'orders',
        data: ['order_id' => 1],
    );

    $retriedMessage = $message
        ->withIncrementedRetry()
        ->withIncrementedRetry();

    $handlerClass = get_class(new class ($message) extends AbstractQueueHandler {
        protected int $maxRetries = 3;

        protected function process(QueueMessage $message): void
        {
            throw new RuntimeException('Still failing', 500);
        }
    });

    Bus::fake();

    $handler = new $handlerClass($retriedMessage);
    $handler->handle();

    Bus::assertDispatched($handlerClass, function ($job) {
        return $job->queue === 'orders_dlq';
    });
});

it('calls onDeadLetter hook when message reaches dlq', function () {
    $message = QueueMessage::create(
        source: 'test-service',
        queue: 'payments',
        data: ['payment_id' => 99],
    );

    $retriedMessage = $message
        ->withIncrementedRetry()
        ->withIncrementedRetry();

    $deadLetterCalled = false;

    $handlerClass = get_class(new class ($message) extends AbstractQueueHandler {
        protected int $maxRetries = 3;

        public static bool $deadLetterCalled = false;

        protected function process(QueueMessage $message): void
        {
            throw new RuntimeException('Fatal error');
        }

        protected function onDeadLetter(QueueMessage $message): void
        {
            static::$deadLetterCalled = true;
        }
    });

    Bus::fake();

    $handler = new $handlerClass($retriedMessage);
    $handler->handle();

    expect($handlerClass::$deadLetterCalled)->toBeTrue();
});

it('sets tries and maxExceptions to 1 to prevent laravel auto-retry', function () {
    $message = QueueMessage::create(
        source: 'test-service',
        queue: 'orders',
        data: [],
    );

    $handler = new class ($message) extends AbstractQueueHandler {
        protected function process(QueueMessage $message): void {}
    };

    expect($handler->tries)->toBe(1)
        ->and($handler->maxExceptions)->toBe(1);
});
