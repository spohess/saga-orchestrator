<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Jobs\ProcessOrderSagaJob;
use Illuminate\Http\JsonResponse;

class JobOrderController extends Controller
{
    public function __invoke(StoreOrderRequest $request): JsonResponse
    {
        ProcessOrderSagaJob::dispatch($request->validated());

        return response()->json([
            'message' => 'Order saga dispatched to queue.',
        ], 202);
    }
}
