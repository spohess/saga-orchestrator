<?php

declare(strict_types=1);

namespace App\Supports\Queue\Interfaces;

interface QueueMessageInterface
{
    public function getMessageId(): string;

    public function getTimestamp(): string;

    public function getVersion(): string;

    public function getSource(): string;

    public function getQueue(): string;

    /** @return array<string, mixed> */
    public function getData(): array;

    /** @return array<string, mixed> */
    public function getMetadata(): array;

    /** @return array{message: string, code: string, trace: string|null}|null */
    public function getError(): ?array;

    public function getRetryCount(): int;

    public function withError(string $message, string $code, ?string $trace): static;

    public function withIncrementedRetry(): static;

    public function withReset(): static;

    /** @return array<string, mixed> */
    public function toArray(): array;

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): static;
}
