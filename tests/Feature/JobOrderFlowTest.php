<?php

use App\Jobs\ProcessOrderSagaJob;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

it('dispatches a saga job from the endpoint', function () {
    Queue::fake();

    $response = $this->postJson('/api/orders/job', [
        'customer_name' => 'John Doe',
        'customer_email' => 'john@example.com',
        'product' => 'Laravel Course',
        'quantity' => 1,
        'total_price' => 9900,
    ]);

    $response->assertAccepted()
        ->assertJson([
            'message' => 'Order saga dispatched to queue.',
        ]);

    Queue::assertPushed(ProcessOrderSagaJob::class, function (ProcessOrderSagaJob $job): bool {
        return $job->orderData['customer_email'] === 'john@example.com'
            && $job->orderData['product'] === 'Laravel Course';
    });
});

it('executes the saga when the job runs', function () {
    Http::fake([
        'external-service.example.com/pay' => Http::response([
            'amount' => 'sub_job_123',
        ], 200),
    ]);

    ProcessOrderSagaJob::dispatchSync([
        'customer_name' => 'Jane Doe',
        'customer_email' => 'jane@example.com',
        'product' => 'Premium Plan',
        'quantity' => 2,
        'total_price' => 19800,
    ]);

    $this->assertDatabaseHas('orders', [
        'customer_email' => 'jane@example.com',
        'status' => 'confirmed',
        'amount' => 'sub_job_123',
    ]);
});
