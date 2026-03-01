<?php

declare(strict_types=1);

use App\Supports\Queue\Abstracts\AbstractQueueHandler;
use App\Supports\Queue\QueueJob;
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

    $handler = new class extends AbstractQueueHandler {
        public bool $processed = false;

        protected function process(QueueMessage $message): void
        {
            $this->processed = true;
        }
    };

    $handler->handle($message);

    expect($handler->processed)->toBeTrue();
    Bus::assertNothingDispatched();
});

it('dispatches QueueJob to retry queue when process fails and retries remain', function () {
    $message = QueueMessage::create(
        source: 'test-service',
        queue: 'orders',
        data: ['order_id' => 1],
    );

    $handler = new class extends AbstractQueueHandler {
        protected int $maxRetries = 3;

        protected function process(QueueMessage $message): void
        {
            throw new RuntimeException('Processing failed', 500);
        }
    };

    $handler->handle($message);

    Bus::assertDispatched(QueueJob::class, function (QueueJob $job) {
        return $job->queue === 'orders_retry';
    });
});

it('dispatches QueueJob to dlq queue when retries are exhausted', function () {
    $message = QueueMessage::create(
        source: 'test-service',
        queue: 'orders',
        data: ['order_id' => 1],
    );

    $retriedMessage = $message
        ->withIncrementedRetry()
        ->withIncrementedRetry();

    $handler = new class extends AbstractQueueHandler {
        protected int $maxRetries = 3;

        protected function process(QueueMessage $message): void
        {
            throw new RuntimeException('Still failing', 500);
        }
    };

    $handler->handle($retriedMessage);

    Bus::assertDispatched(QueueJob::class, function (QueueJob $job) {
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

    $handlerClass = get_class(new class extends AbstractQueueHandler {
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

    $handler = new $handlerClass();
    $handler->handle($retriedMessage);

    expect($handlerClass::$deadLetterCalled)->toBeTrue();
});
