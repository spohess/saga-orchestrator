<?php

declare(strict_types=1);

namespace App\Domains\Order\DTOs;

use App\Models\Order;
use App\Supports\Saga\SagaContext;
use App\Supports\Saga\SagaContextDTOInterface;

final class OrderSagaContextData implements SagaContextDTOInterface
{
    public function __construct(
        public readonly string $customerName,
        public readonly string $customerEmail,
        public readonly string $product,
        public readonly int $quantity,
        public readonly int $totalPrice,
        public readonly ?Order $order = null,
        public readonly ?string $amount = null,
    ) {}

    public static function fromContext(SagaContext $context): static
    {
        return new self(
            customerName: $context->get('customer_name'),
            customerEmail: $context->get('customer_email'),
            product: $context->get('product'),
            quantity: $context->get('quantity'),
            totalPrice: $context->get('total_price'),
            order: $context->get('order'),
            amount: $context->get('amount'),
        );
    }

    /** @return array<string, mixed> */
    public function toSnapshot(): array
    {
        return [
            'customer_name' => $this->customerName,
            'customer_email' => $this->customerEmail,
            'product' => $this->product,
            'quantity' => $this->quantity,
            'total_price' => $this->totalPrice,
            'order' => $this->order?->toArray(),
            'amount' => $this->amount,
        ];
    }
}
