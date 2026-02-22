<?php

declare(strict_types=1);

namespace App\Supports\Services\PaymentGateway;

use App\Supports\Interfaces\ServicesInterface;
use Illuminate\Support\Facades\Http;

final class RefundService implements ServicesInterface
{
    public function execute(array $data): mixed
    {
        $response = Http::post('https://external-service.example.com/refund', $data);

        return $response->json('protocol');
    }
}
