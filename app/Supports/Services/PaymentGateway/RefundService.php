<?php

declare(strict_types=1);

namespace App\Supports\Services\PaymentGateway;

use App\Supports\Abstracts\Input;
use App\Supports\Interfaces\DTOInterface;
use App\Supports\Interfaces\ServicesInterface;
use Illuminate\Support\Facades\Http;

final class RefundService implements ServicesInterface
{
    public function execute(Input $input): DTOInterface
    {
        /** @var RefundInput $input */
        $response = Http::post('https://external-service.example.com/refund', [
            'amount' => $input->get('amount'),
        ]);

        return new RefundDTO(
            protocol: (string) $response->json('protocol'),
        );
    }
}
