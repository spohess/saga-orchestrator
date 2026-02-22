<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Order\DTOs\OrderSagaContextData;
use App\Domains\Order\Steps\ConfirmOrderStep;
use App\Domains\Order\Steps\CreateOrderStep;
use App\Domains\Order\Steps\ProcessPaymentStep;
use App\Http\Requests\StoreOrderRequest;
use App\Supports\Saga\SagaContext;
use App\Supports\Saga\SagaOrchestrator;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function __construct(
        private SagaOrchestrator $orchestrator,
    ) {}

    public function __invoke(StoreOrderRequest $request): JsonResponse
    {
        $context = new SagaContext(OrderSagaContextData::class);
        $context->setFromArray($request->validated());

        /** @var OrderSagaContextData $dto */
        $dto = $this->orchestrator
            ->addStep(CreateOrderStep::class)
            ->addStep(ProcessPaymentStep::class, retries: 3, sleep: 10)
            ->addStep(ConfirmOrderStep::class)
            ->execute($context);

        return response()->json($dto->order->fresh(), 201);
    }
}
