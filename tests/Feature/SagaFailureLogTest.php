<?php

use App\Models\SagaFailureLog;
use App\Supports\Saga\SagaContext;
use App\Supports\Saga\SagaOrchestrator;
use App\Supports\Saga\SagaStepInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\Fixtures\FailingStep;

beforeEach(function () {
    Sleep::fake();
});

it('does not create a failure log when all steps succeed', function () {
    Http::fake([
        'external-service.example.com/pay' => Http::response([
            'amount' => 'sub_123',
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
        'external-service.example.com/pay' => Http::response([], 500),
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
        ->and($log->failed_step)->toBe(\App\Domains\Order\Steps\ProcessPaymentStep::class)
        ->and($log->exception_class)->toBe(RuntimeException::class)
        ->and($log->exception_message)->toBe('External service payment failed.')
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
        'external-service.example.com/pay' => Http::response([], 500),
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

it('retries a step before triggering compensation', function () {
    $callCount = 0;

    $flakeyStep = new class($callCount) implements SagaStepInterface
    {
        public function __construct(private int &$callCount) {}

        public function run(SagaContext $context): void
        {
            $this->callCount++;

            if ($this->callCount < 3) {
                throw new RuntimeException('Transient failure.');
            }

            $context->set('flakey_result', 'ok');
        }

        public function rollback(SagaContext $context): void {}
    };

    $this->app->bind('step.flakey', fn () => $flakeyStep);

    $orchestrator = new SagaOrchestrator;
    $context = $orchestrator
        ->addStep('step.flakey', retries: 3, sleep: 2)
        ->execute();

    expect($callCount)->toBe(3)
        ->and($context->get('flakey_result'))->toBe('ok')
        ->and(SagaFailureLog::count())->toBe(0);

    Sleep::assertSleptTimes(2);
    Sleep::assertSequence([
        Sleep::for(2)->seconds(),
        Sleep::for(2)->seconds(),
    ]);
});

it('triggers compensation after all retries are exhausted', function () {
    $orchestrator = new SagaOrchestrator;
    $orchestrator->addStep(FailingStep::class, retries: 2, sleep: 1);

    try {
        $orchestrator->execute();
    } catch (RuntimeException) {
        // expected
    }

    $log = SagaFailureLog::first();

    expect($log)->not->toBeNull()
        ->and($log->failed_step)->toBe(FailingStep::class);

    Sleep::assertSleptTimes(2);
});

it('does not sleep on the first attempt', function () {
    $orchestrator = new SagaOrchestrator;
    $orchestrator->addStep(FailingStep::class, retries: 0, sleep: 5);

    try {
        $orchestrator->execute();
    } catch (RuntimeException) {
        // expected
    }

    Sleep::assertNeverSlept();
});
