<?php

declare(strict_types=1);

namespace App\Supports\Services\PaymentGateway;

use App\Supports\Interfaces\ServicesInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class PaymentService implements ServicesInterface
{
    public function execute(array $data): mixed
    {
        $response = Http::post('https://external-service.example.com/pay', $data);

        if ($response->failed()) {
            throw new RuntimeException('External service payment failed.');
        }

        return $response->json('amount');
    }
}
