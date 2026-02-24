<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Order\Steps\ConfirmOrderStep;
use App\Domains\Order\Steps\CreateOrderStep;
use App\Domains\Order\Steps\ProcessPaymentStep;
use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use App\Supports\Saga\SagaContext;
use App\Supports\Saga\SagaOrchestrator;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function __construct(
        private SagaContext $context,
        private SagaOrchestrator $orchestrator,
    ) {}

    public function __invoke(StoreOrderRequest $request): JsonResponse
    {
        $this->context->setFromArray($request->validated());

        $this->orchestrator->addStep(CreateOrderStep::class)
            ->addStep(ProcessPaymentStep::class, retries: 3, sleep: 10)
            ->addStep(ConfirmOrderStep::class)
            ->execute($this->context);

        /** @var Order $order */
        $order = $this->context->get('order');

        return response()->json($order->fresh(), 201);
    }
}
