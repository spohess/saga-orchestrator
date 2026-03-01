<?php

declare(strict_types=1);

namespace App\Supports\Queue;

final class QueueProducer
{
    public function __construct(
        private readonly string $queue,
    ) {}

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $metadata
     */
    public function publish(
        string $source,
        array $data,
        array $metadata = [],
    ): QueueMessage {
        $message = QueueMessage::create(
            source: $source,
            queue: $this->queue,
            data: $data,
            metadata: $metadata,
        );

        dispatch(new QueueJob($message))->onQueue($this->queue);

        return $message;
    }
}
