<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domains\Order\DTOs\OrderSagaContextData;
use App\Domains\Order\Steps\ConfirmOrderStep;
use App\Domains\Order\Steps\CreateOrderStep;
use App\Domains\Order\Steps\ProcessPaymentStep;
use App\Supports\Saga\SagaContext;
use App\Supports\Saga\SagaOrchestrator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessOrderSagaJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param array{
     *     customer_name: string,
     *     customer_email: string,
     *     product: string,
     *     quantity: int,
     *     total_price: int
     * } $orderData
     */
    public function __construct(
        private array $orderData,
    ) {}

    public function handle(SagaOrchestrator $orchestrator): void
    {
        $context = new SagaContext(OrderSagaContextData::class);
        $context->setFromArray($this->orderData);

        $orchestrator
            ->addStep(CreateOrderStep::class)
            ->addStep(ProcessPaymentStep::class, retries: 3, sleep: 10)
            ->addStep(ConfirmOrderStep::class)
            ->execute($context);
    }
}
