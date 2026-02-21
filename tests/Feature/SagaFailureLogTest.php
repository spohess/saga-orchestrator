<?php

use App\Models\SagaFailureLog;
use App\Supports\Saga\SagaContext;
use App\Supports\Saga\SagaOrchestrator;
use App\Supports\Saga\SagaStepInterface;
use Illuminate\Support\Facades\Http;
use Tests\Fixtures\FailingStep;

it('does not create a failure log when all steps succeed', function () {
    Http::fake([
        'external-service.example.com/subscribe' => Http::response([
            'subscription_id' => 'sub_123',
        ], 200),
    ]);

    $this->postJson('/api/orders', [
        'customer_name' => 'John Doe',
        'customer_email' => 'john@example.com',
        'product' => 'Laravel Course',
        'quantity' => 1,
        'total_price' => 9900,
    ])->assertSuccessful();

    expect(SagaFailureLog::count())->toBe(0);
});

it('creates a failure log when a step fails', function () {
    Http::fake([
        'external-service.example.com/subscribe' => Http::response([], 500),
    ]);

    $this->postJson('/api/orders', [
        'customer_name' => 'Jane Doe',
        'customer_email' => 'jane@example.com',
        'product' => 'Laravel Course',
        'quantity' => 2,
        'total_price' => 19800,
    ]);

    $log = SagaFailureLog::first();

    expect($log)->not->toBeNull()
        ->and($log->saga_id)->toBeUuid()
        ->and($log->failed_step)->toBe(\App\Domains\Order\Steps\SubscribeExternalServiceStep::class)
        ->and($log->exception_class)->toBe(RuntimeException::class)
        ->and($log->exception_message)->toBe('External service subscription failed.')
        ->and($log->executed_steps)->toBe([
            \App\Domains\Order\Steps\CreateOrderStep::class,
        ])
        ->and($log->compensated_steps)->toBe([
            \App\Domains\Order\Steps\CreateOrderStep::class,
        ])
        ->and($log->compensation_failures)->toBeEmpty();
});

it('logs the context snapshot at time of failure', function () {
    Http::fake([
        'external-service.example.com/subscribe' => Http::response([], 500),
    ]);

    $this->postJson('/api/orders', [
        'customer_name' => 'Jane Doe',
        'customer_email' => 'jane@example.com',
        'product' => 'Laravel Course',
        'quantity' => 2,
        'total_price' => 19800,
    ]);

    $log = SagaFailureLog::first();

    expect($log->context_snapshot)
        ->toHaveKey('customer_name', 'Jane Doe')
        ->toHaveKey('customer_email', 'jane@example.com')
        ->toHaveKey('order');
});

it('logs compensation failures when rollback itself fails', function () {
    $failingRollbackStep = new class implements SagaStepInterface
    {
        public function run(SagaContext $context): void {}

        public function rollback(SagaContext $context): void
        {
            throw new RuntimeException('Rollback exploded.');
        }
    };

    $this->app->bind('step.failing-rollback', fn () => $failingRollbackStep);

    $orchestrator = new SagaOrchestrator;
    $orchestrator->addStep('step.failing-rollback');
    $orchestrator->addStep(FailingStep::class);

    try {
        $orchestrator->execute();
    } catch (RuntimeException) {
        // expected
    }

    $log = SagaFailureLog::first();

    expect($log->compensation_failures)->toHaveCount(1)
        ->and($log->compensation_failures[0]['message'])->toBe('Rollback exploded.');
});

it('still throws the original exception after logging', function () {
    $orchestrator = new SagaOrchestrator;
    $orchestrator->addStep(FailingStep::class);

    $orchestrator->execute();
})->throws(RuntimeException::class, 'Step failed intentionally.');
