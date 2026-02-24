<?php

declare(strict_types=1);

namespace App\Supports\Services\Notification;

use App\Supports\Abstracts\Input;
use App\Supports\Interfaces\DTOInterface;
use App\Supports\Interfaces\ServicesInterface;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

final class NotificationService implements ServicesInterface
{
    public function execute(Input $input): DTOInterface
    {
        throw_if(! $input instanceof NotificationInput, InvalidArgumentException::class);

        $response = Http::post('https://external-service.example.com/notify', [
            'email' => $input->get('email'),
            'subject' => $input->get('subject'),
            'message' => $input->get('message'),
        ]);

        return new NotificationDTO(
            sent: $response->successful(),
        );
    }
}
