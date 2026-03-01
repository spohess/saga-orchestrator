<?php

declare(strict_types=1);

use App\Supports\Queue\QueueJob;
use App\Supports\Queue\QueueMessage;
use Illuminate\Contracts\Queue\Job as QueueJobContract;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Support\Facades\Bus;

function createFakeQueueJob(QueueMessage $message): QueueJobContract
{
    $queueJob = new QueueJob($message);
    $queueJob->onQueue($message->getQueue() . '_dlq');

    $job = Mockery::mock(QueueJobContract::class);
    $job->shouldReceive('payload')->andReturn([
        'data' => ['command' => serialize($queueJob)],
    ]);
    $job->shouldReceive('delete');

    return $job;
}

it('reprocesses jobs from DLQ back to the original queue', function () {
    Bus::fake();

    $message = QueueMessage::create(
        source: 'test-service',
        queue: 'notifications',
        data: ['email' => 'test@example.com'],
    )->withError('Connection refused', '500', 'trace')->withIncrementedRetry()
        ->withIncrementedRetry()->withIncrementedRetry();

    $fakeJob = createFakeQueueJob($message);

    $queue = Mockery::mock(Queue::class);
    $queue->shouldReceive('pop')
        ->with('notifications_dlq')
        ->andReturn($fakeJob, null);

    $this->app->instance(Queue::class, $queue);

    $this->artisan('queue:reprocess', ['--queue' => 'notifications_dlq'])
        ->expectsOutputToContain('Reprocessed 1 job(s)')
        ->assertSuccessful();

    Bus::assertDispatched(QueueJob::class, function (QueueJob $job) {
        return $job->message->getQueue() === 'notifications'
            && $job->message->getError() === null
            && $job->message->getRetryCount() === 0;
    });
});

it('resets error and retry_count on reprocessed messages', function () {
    Bus::fake();

    $message = QueueMessage::create(
        source: 'test-service',
        queue: 'orders',
        data: ['order_id' => 42],
    )->withError('Timeout', '504', 'stack trace')
        ->withIncrementedRetry()
        ->withIncrementedRetry();

    $fakeJob = createFakeQueueJob($message);

    $queue = Mockery::mock(Queue::class);
    $queue->shouldReceive('pop')
        ->with('orders_dlq')
        ->andReturn($fakeJob, null);

    $this->app->instance(Queue::class, $queue);

    $this->artisan('queue:reprocess', ['--queue' => 'orders_dlq'])
        ->assertSuccessful();

    Bus::assertDispatched(QueueJob::class, function (QueueJob $job) {
        return $job->message->getError() === null
            && $job->message->getRetryCount() === 0
            && $job->message->getData() === ['order_id' => 42]
            && $job->message->getMessageId() !== '';
    });
});

it('rejects queue names that do not end with _dlq', function () {
    $this->artisan('queue:reprocess', ['--queue' => 'notifications'])
        ->expectsOutputToContain('must end with "_dlq"')
        ->assertFailed();
});

it('requires the --queue option', function () {
    $this->artisan('queue:reprocess')
        ->expectsOutputToContain('--queue option is required')
        ->assertFailed();
});

it('outputs informative message when DLQ is empty', function () {
    $queue = Mockery::mock(Queue::class);
    $queue->shouldReceive('pop')
        ->with('notifications_dlq')
        ->andReturn(null);

    $this->app->instance(Queue::class, $queue);

    $this->artisan('queue:reprocess', ['--queue' => 'notifications_dlq'])
        ->expectsOutputToContain('No jobs found')
        ->assertSuccessful();
});

it('reprocesses multiple jobs from DLQ', function () {
    Bus::fake();

    $message1 = QueueMessage::create(
        source: 'test-service',
        queue: 'orders',
        data: ['order_id' => 1],
    )->withError('Error 1', '500', null)->withIncrementedRetry();

    $message2 = QueueMessage::create(
        source: 'test-service',
        queue: 'orders',
        data: ['order_id' => 2],
    )->withError('Error 2', '503', null)->withIncrementedRetry();

    $queue = Mockery::mock(Queue::class);
    $queue->shouldReceive('pop')
        ->with('orders_dlq')
        ->andReturn(createFakeQueueJob($message1), createFakeQueueJob($message2), null);

    $this->app->instance(Queue::class, $queue);

    $this->artisan('queue:reprocess', ['--queue' => 'orders_dlq'])
        ->expectsOutputToContain('Reprocessed 2 job(s)')
        ->assertSuccessful();

    Bus::assertDispatched(QueueJob::class, 2);
});
