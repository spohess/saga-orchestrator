<?php

declare(strict_types=1);

namespace App\Supports\Queue\Abstracts;

use App\Supports\Queue\QueueMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

abstract class AbstractQueueHandler implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $maxExceptions = 1;

    protected int $maxRetries = 3;

    public function __construct(protected QueueMessage $message) {}

    public function handle(): void
    {
        try {
            $this->process($this->message);
        } catch (Throwable $e) {
            $updated = $this->message
                ->withError($e->getMessage(), (string) $e->getCode(), $e->getTraceAsString())
                ->withIncrementedRetry();

            if ($updated->getRetryCount() < $this->maxRetries) {
                dispatch(new static($updated))->onQueue($this->message->getQueue() . '_retry');
            } else {
                dispatch(new static($updated))->onQueue($this->message->getQueue() . '_dlq');
                $this->onDeadLetter($updated);
            }
        }
    }

    abstract protected function process(QueueMessage $message): void;

    protected function onDeadLetter(QueueMessage $message): void {}
}
