<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

beforeEach(function () {
    Sleep::fake();
});

it('creates a confirmed order when all steps succeed', function () {
    Http::fake([
        'external-service.example.com/pay' => Http::response(['amount' => 'sub_123'], 200),
        'external-service.example.com/notify' => Http::response([], 200),
    ]);

    $response = $this->postJson('/api/orders', [
        'customer_name' => 'John Doe',
        'customer_email' => 'john@example.com',
        'product' => 'Laravel Course',
        'quantity' => 1,
        'total_price' => 9900,
    ]);

    $response->assertStatus(201)
        ->assertJson([
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'product' => 'Laravel Course',
            'quantity' => 1,
            'total_price' => 9900,
            'status' => 'confirmed',
            'amount' => 'sub_123',
        ]);

    $this->assertDatabaseHas('orders', [
        'customer_email' => 'john@example.com',
        'status' => 'confirmed',
    ]);
});

it('rolls back the order to failed when the external service fails', function () {
    Http::fake([
        'external-service.example.com/pay' => Http::response([], 500),
    ]);

    $response = $this->postJson('/api/orders', [
        'customer_name' => 'Jane Doe',
        'customer_email' => 'jane@example.com',
        'product' => 'Laravel Course',
        'quantity' => 2,
        'total_price' => 19800,
    ]);

    $response->assertStatus(500);

    $this->assertDatabaseHas('orders', [
        'customer_email' => 'jane@example.com',
        'status' => 'failed',
    ]);
});

it('calls the external service refund endpoint on rollback', function () {
    Http::fake([
        'external-service.example.com/pay' => Http::response([
            'amount' => 'sub_456',
        ], 200),
        'external-service.example.com/refund' => Http::response([], 200),
    ]);

    // Bind a ConfirmOrderStep that throws to force rollback after subscription
    $this->app->bind(
        \App\Domains\Order\Steps\ConfirmOrderStep::class,
        \Tests\Fixtures\FailingStep::class,
    );

    $response = $this->postJson('/api/orders', [
        'customer_name' => 'Bob Smith',
        'customer_email' => 'bob@example.com',
        'product' => 'Premium Plan',
        'quantity' => 1,
        'total_price' => 29900,
    ]);

    $response->assertStatus(500);

    Http::assertSent(function (Request $request) {
        return $request->url() === 'https://external-service.example.com/refund'
            && $request['amount'] === 'sub_456';
    });
});

it('validates required fields', function () {
    $response = $this->postJson('/api/orders', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors([
            'customer_name',
            'customer_email',
            'product',
            'quantity',
            'total_price',
        ]);
});
