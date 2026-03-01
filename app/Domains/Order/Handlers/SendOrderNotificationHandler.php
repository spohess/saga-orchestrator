<?php

declare(strict_types=1);

namespace App\Domains\Order\Handlers;

use App\Supports\Queue\Abstracts\AbstractQueueHandler;
use App\Supports\Queue\QueueMessage;
use App\Supports\Services\Notification\NotificationInput;
use App\Supports\Services\Notification\NotificationService;

final class SendOrderNotificationHandler extends AbstractQueueHandler
{
    protected function process(QueueMessage $message): void
    {
        $data = $message->getData();

        app(NotificationService::class)->execute(NotificationInput::fromArray([
            'email' => $data['email'],
            'subject' => $data['subject'],
            'message' => $data['message'],
        ]));
    }
}
