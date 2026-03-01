<?php

declare(strict_types=1);

use App\Supports\Queue\QueueMessage;

it('creates a message with factory method', function () {
    $message = QueueMessage::create(
        source: 'test-service',
        queue: 'orders',
        data: ['order_id' => 123],
        metadata: ['priority' => 'high'],
    );

    expect($message->getSource())->toBe('test-service')
        ->and($message->getQueue())->toBe('orders')
        ->and($message->getData())->toBe(['order_id' => 123])
        ->and($message->getMetadata())->toBe(['priority' => 'high'])
        ->and($message->getVersion())->toBe('1.0')
        ->and($message->getRetryCount())->toBe(0)
        ->and($message->getError())->toBeNull()
        ->and($message->getMessageId())->toBeString()->not->toBeEmpty()
        ->and($message->getTimestamp())->toBeString()->not->toBeEmpty();
});

it('serializes to array and deserializes from array', function () {
    $message = QueueMessage::create(
        source: 'test-service',
        queue: 'orders',
        data: ['order_id' => 456],
    );

    $array = $message->toArray();
    $restored = QueueMessage::fromArray($array);

    expect($restored->getMessageId())->toBe($message->getMessageId())
        ->and($restored->getTimestamp())->toBe($message->getTimestamp())
        ->and($restored->getVersion())->toBe($message->getVersion())
        ->and($restored->getSource())->toBe($message->getSource())
        ->and($restored->getQueue())->toBe($message->getQueue())
        ->and($restored->getData())->toBe($message->getData())
        ->and($restored->getMetadata())->toBe($message->getMetadata())
        ->and($restored->getError())->toBeNull()
        ->and($restored->getRetryCount())->toBe(0);
});

it('returns a new instance with error preserving immutability', function () {
    $original = QueueMessage::create(
        source: 'test-service',
        queue: 'orders',
        data: ['order_id' => 1],
    );

    $withError = $original->withError('Something failed', '500', 'stack trace here');

    expect($original->getError())->toBeNull()
        ->and($withError->getError())->toBe([
            'message' => 'Something failed',
            'code' => '500',
            'trace' => 'stack trace here',
        ])
        ->and($withError->getMessageId())->toBe($original->getMessageId())
        ->and($withError)->not->toBe($original);
});

it('returns a new instance with incremented retry preserving immutability', function () {
    $original = QueueMessage::create(
        source: 'test-service',
        queue: 'orders',
        data: ['order_id' => 1],
    );

    $retried = $original->withIncrementedRetry();

    expect($original->getRetryCount())->toBe(0)
        ->and($retried->getRetryCount())->toBe(1)
        ->and($retried->getMessageId())->toBe($original->getMessageId())
        ->and($retried)->not->toBe($original);
});

it('chains withError and withIncrementedRetry correctly', function () {
    $original = QueueMessage::create(
        source: 'test-service',
        queue: 'orders',
        data: ['order_id' => 1],
    );

    $updated = $original
        ->withError('Error occurred', '422', null)
        ->withIncrementedRetry();

    expect($updated->getRetryCount())->toBe(1)
        ->and($updated->getError())->toBe([
            'message' => 'Error occurred',
            'code' => '422',
            'trace' => null,
        ])
        ->and($original->getRetryCount())->toBe(0)
        ->and($original->getError())->toBeNull();
});

it('preserves error and retry data through toArray/fromArray roundtrip', function () {
    $message = QueueMessage::create(
        source: 'test-service',
        queue: 'orders',
        data: ['order_id' => 1],
    );

    $updated = $message
        ->withError('Timeout', '504', 'trace')
        ->withIncrementedRetry()
        ->withIncrementedRetry();

    $restored = QueueMessage::fromArray($updated->toArray());

    expect($restored->getRetryCount())->toBe(2)
        ->and($restored->getError())->toBe([
            'message' => 'Timeout',
            'code' => '504',
            'trace' => 'trace',
        ]);
});
