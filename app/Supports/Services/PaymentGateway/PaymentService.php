<?php

declare(strict_types=1);

namespace App\Supports\Services\PaymentGateway;

use App\Supports\Abstracts\Input;
use App\Supports\Interfaces\DTOInterface;
use App\Supports\Interfaces\ServicesInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class PaymentService implements ServicesInterface
{
    public function execute(Input $input): DTOInterface
    {
        /** @var PaymentInput $input */
        $response = Http::post('https://external-service.example.com/pay', [
            'order_id' => $input->get('order_id'),
            'customer_email' => $input->get('customer_email'),
            'product' => $input->get('product'),
        ]);

        if ($response->failed()) {
            throw new RuntimeException('External service payment failed.');
        }

        return new PaymentDTO(
            amount: $response->json('amount'),
        );
    }
}
