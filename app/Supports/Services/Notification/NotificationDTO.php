<?php

declare(strict_types=1);

namespace App\Supports\Services\Notification;

use App\Supports\Interfaces\DTOInterface;

final class NotificationDTO implements DTOInterface
{
    public function __construct(
        public readonly bool $sent,
    ) {}

    public function toArray(): array
    {
        return ['sent' => $this->sent];
    }
}
