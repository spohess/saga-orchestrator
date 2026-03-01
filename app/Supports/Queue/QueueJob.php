<?php

declare(strict_types=1);

namespace App\Supports\Queue;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class QueueJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $maxExceptions = 1;

    public function __construct(public readonly QueueMessage $message) {}

    public function handle(): void
    {
        $handlerClass = app(QueueRouter::class)->resolve($this->message->getQueue());
        $handler = app($handlerClass);
        $handler->handle($this->message);
    }
}
