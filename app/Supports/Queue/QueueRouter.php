<?php

declare(strict_types=1);

namespace App\Supports\Queue;

use App\Supports\Queue\Abstracts\AbstractQueueHandler;
use InvalidArgumentException;

final class QueueRouter
{
    /** @var array<string, class-string<AbstractQueueHandler>> */
    private array $routes = [];

    /** @param class-string<AbstractQueueHandler> $handlerClass */
    public function register(string $queue, string $handlerClass): void
    {
        $this->routes[$queue] = $handlerClass;
    }

    /** @return class-string<AbstractQueueHandler> */
    public function resolve(string $queue): string
    {
        if (!isset($this->routes[$queue])) {
            throw new InvalidArgumentException("No handler registered for queue [{$queue}].");
        }

        return $this->routes[$queue];
    }
}
