<?php

declare(strict_types=1);

namespace App\Supports\Queue;

use App\Supports\Queue\Interfaces\QueueMessageInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class QueueMessage implements QueueMessageInterface
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $metadata
     * @param array{message: string, code: string, trace: string|null}|null $error
     */
    public function __construct(
        private readonly string $messageId,
        private readonly string $timestamp,
        private readonly string $version,
        private readonly string $source,
        private readonly string $queue,
        private readonly array $data,
        private readonly array $metadata = [],
        private readonly ?array $error = null,
        private readonly int $retryCount = 0,
    ) {}

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $metadata
     */
    public static function create(
        string $source,
        string $queue,
        array $data,
        array $metadata = [],
    ): static {
        return new static(
            messageId: (string) Str::uuid(),
            timestamp: CarbonImmutable::now()->toIso8601String(),
            version: '1.0',
            source: $source,
            queue: $queue,
            data: $data,
            metadata: $metadata,
        );
    }

    public function getMessageId(): string
    {
        return $this->messageId;
    }

    public function getTimestamp(): string
    {
        return $this->timestamp;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getQueue(): string
    {
        return $this->queue;
    }

    /** @return array<string, mixed> */
    public function getData(): array
    {
        return $this->data;
    }

    /** @return array<string, mixed> */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /** @return array{message: string, code: string, trace: string|null}|null */
    public function getError(): ?array
    {
        return $this->error;
    }

    public function getRetryCount(): int
    {
        return $this->retryCount;
    }

    public function withError(
        string $message,
        string $code,
        ?string $trace,
    ): static {
        return new static(
            messageId: $this->messageId,
            timestamp: $this->timestamp,
            version: $this->version,
            source: $this->source,
            queue: $this->queue,
            data: $this->data,
            metadata: $this->metadata,
            error: [
                'message' => $message,
                'code' => $code,
                'trace' => $trace,
            ],
            retryCount: $this->retryCount,
        );
    }

    public function withIncrementedRetry(): static
    {
        return new static(
            messageId: $this->messageId,
            timestamp: $this->timestamp,
            version: $this->version,
            source: $this->source,
            queue: $this->queue,
            data: $this->data,
            metadata: $this->metadata,
            error: $this->error,
            retryCount: $this->retryCount + 1,
        );
    }

    public function withReset(): static
    {
        return new static(
            messageId: $this->messageId,
            timestamp: $this->timestamp,
            version: $this->version,
            source: $this->source,
            queue: $this->queue,
            data: $this->data,
            metadata: $this->metadata,
            error: null,
            retryCount: 0,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'message_id' => $this->messageId,
            'timestamp' => $this->timestamp,
            'version' => $this->version,
            'source' => $this->source,
            'queue' => $this->queue,
            'data' => $this->data,
            'metadata' => $this->metadata,
            'error' => $this->error,
            'retry_count' => $this->retryCount,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): static
    {
        return new static(
            messageId: $data['message_id'],
            timestamp: $data['timestamp'],
            version: $data['version'],
            source: $data['source'],
            queue: $data['queue'],
            data: $data['data'],
            metadata: $data['metadata'] ?? [],
            error: $data['error'] ?? null,
            retryCount: $data['retry_count'] ?? 0,
        );
    }
}
