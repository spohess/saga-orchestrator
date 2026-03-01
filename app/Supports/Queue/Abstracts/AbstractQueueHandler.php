<?php

declare(strict_types=1);

namespace App\Supports\Queue\Abstracts;

use App\Supports\Queue\QueueJob;
use App\Supports\Queue\QueueMessage;
use Throwable;

abstract class AbstractQueueHandler
{
    protected int $maxRetries = 3;

    public function handle(QueueMessage $message): void
    {
        try {
            $this->process($message);
        } catch (Throwable $e) {
            $trace = implode("\n", array_map(
                fn($f) => sprintf('%s:%s', $f['file'] ?? '[internal]', $f['line'] ?? '?'),
                array_slice($e->getTrace(), 0, 5),
            ));

            $updated = $message
                ->withError($e->getMessage(), (string) $e->getCode(), $trace)
                ->withIncrementedRetry();

            if ($updated->getRetryCount() < $this->maxRetries) {
                dispatch(new QueueJob($updated))->onQueue($message->getQueue() . '_retry');
            } else {
                dispatch(new QueueJob($updated))->onQueue($message->getQueue() . '_dlq');
                $this->onDeadLetter($updated);
            }
        }
    }

    abstract protected function process(QueueMessage $message): void;

    protected function onDeadLetter(QueueMessage $message): void {}
}
