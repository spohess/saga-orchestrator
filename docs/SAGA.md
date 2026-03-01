# SAGA Orchestrator — Documentação para Claude

Este documento descreve a implementação do padrão SAGA neste projeto Laravel 12 para ser usado como referência contextual em conversas com o Claude.

---

## Visão Geral

O projeto implementa o padrão **SAGA Orchestration** para gerenciar transações distribuídas com compensação automática. Quando qualquer etapa falha, o orquestrador reverte todas as etapas anteriores em ordem inversa (LIFO).

**Execução síncrona:** `POST /api/orders` → `OrderController`
**Execução assíncrona (fila):** `POST /api/orders/job` → `JobOrderController` → `ProcessOrderSagaJob`

---

## Estrutura de Diretórios

```
app/
├── Domains/
│   └── Order/
│       └── Steps/                    # Passos da saga do domínio Order
│           ├── CreateOrderStep.php
│           ├── ProcessPaymentStep.php
│           └── ConfirmOrderStep.php
├── Http/
│   ├── Controllers/
│   │   ├── OrderController.php       # Execução síncrona
│   │   └── JobOrderController.php    # Execução via fila
│   └── Requests/
│       └── StoreOrderRequest.php
├── Jobs/
│   └── ProcessOrderSagaJob.php
├── Models/
│   ├── Order.php
│   └── SagaFailureLog.php
└── Supports/
    ├── Interfaces/
    │   └── ServicesInterface.php     # Contrato para serviços externos
    ├── Saga/
    │   ├── SagaContext.php           # Container de dados entre passos
    │   ├── SagaOrchestrator.php      # Motor de orquestração
    │   └── SagaStepInterface.php     # Contrato para passos da saga
    └── Services/
        └── PaymentGateway/
            ├── PaymentService.php
            └── RefundService.php
tests/
├── Feature/
│   ├── OrderFlowTest.php
│   └── SagaFailureLogTest.php
└── Fixtures/
    └── FailingStep.php               # Helper para testes de falha
```

---

## Componentes Core

### `SagaStepInterface`

Contrato obrigatório para todo passo de uma saga.

```php
interface SagaStepInterface
{
    public function run(SagaContext $context): void;
    public function rollback(SagaContext $context): void;
}
```

- `run()` — executa a lógica de negócio (forward)
- `rollback()` — desfaz o efeito (compensation)

### `SagaContext`

Container de estado compartilhado entre passos. Usa notação de ponto para chaves aninhadas.

```php
// Definir valores
$context->set('order.id', 42);
$context->setFromArray(['customer_name' => 'João', 'total_price' => 1000]);

// Ler valores
$order = $context->get('order');
$name = $context->get('customer_name', 'desconhecido'); // com default

// Verificar existência
$context->has('payment_id');

// Converter para array (Eloquent models são serializados)
$context->toArray();
```

### `SagaOrchestrator`

Motor de orquestração. Aceita interface fluente para registro de passos.

```php
$orchestrator
    ->addStep(CreateOrderStep::class)
    ->addStep(ProcessPaymentStep::class, retries: 3, sleep: 10) // 4 tentativas, 10s entre cada
    ->addStep(ConfirmOrderStep::class)
    ->execute($context); // retorna SagaContext atualizado
```

**Parâmetros de `addStep()`:**
| Parâmetro | Tipo | Default | Descrição |
|-----------|------|---------|-----------|
| `$step` | `string` | — | Classe ou binding do container |
| `retries` | `int` | `0` | Nº de tentativas extras (0 = sem retry) |
| `sleep` | `int` | `0` | Segundos entre tentativas |

**Comportamento em caso de falha:**
1. Compensa passos executados em ordem inversa
2. Registra `SagaFailureLog` com snapshot completo do estado
3. Relança a exceção original (fail-fast)

---

## Como Criar uma Nova Saga

### 1. Criar os Passos

Cada passo fica em `app/Domains/{Domínio}/Steps/`. Use `final class`.

```php
<?php

declare(strict_types=1);

namespace App\Domains\Order\Steps;

use App\Supports\Saga\SagaContext;
use App\Supports\Saga\SagaStepInterface;

final class CreateOrderStep implements SagaStepInterface
{
    public function run(SagaContext $context): void
    {
        $order = Order::create([
            'customer_name' => $context->get('customer_name'),
            'status' => 'pending',
        ]);

        $context->set('order', $order);
    }

    public function rollback(SagaContext $context): void
    {
        $order = $context->get('order');
        $order?->update(['status' => 'failed']);
    }
}
```

**Regras para passos:**
- Classes `final`
- Declarar `strict_types=1`
- Usar injeção de dependência no construtor para serviços externos
- O `rollback()` deve ser idempotente e seguro (não lançar exceção se não houver nada para compensar)
- O que é produzido em `run()` deve ser armazenado no contexto para uso no `rollback()`

### 2. Criar Serviços Externos (quando necessário)

Serviços externos ficam em `app/Supports/Services/{Domínio}/`. Implementam `ServicesInterface`.

```php
<?php

declare(strict_types=1);

namespace App\Supports\Services\PaymentGateway;

use App\Supports\Interfaces\ServicesInterface;
use Illuminate\Support\Facades\Http;

final class PaymentService implements ServicesInterface
{
    public function execute(array $data): mixed
    {
        $response = Http::post('https://external-service.example.com/pay', $data);

        if ($response->failed()) {
            throw new \RuntimeException('External service payment failed.');
        }

        return $response->json('amount');
    }
}
```

### 3. Criar o Controller (execução síncrona)

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Order\Steps\CreateOrderStep;
use App\Domains\Order\Steps\ConfirmOrderStep;
use App\Http\Requests\StoreOrderRequest;
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

        $this->orchestrator
            ->addStep(CreateOrderStep::class)
            ->addStep(ConfirmOrderStep::class)
            ->execute($this->context);

        return response()->json($this->context->get('order')->fresh(), 201);
    }
}
```

### 4. Criar o Job (execução assíncrona)

```php
<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domains\Order\Steps\CreateOrderStep;
use App\Supports\Saga\SagaContext;
use App\Supports\Saga\SagaOrchestrator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessOrderSagaJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private array $orderData,
    ) {}

    public function handle(SagaOrchestrator $orchestrator, SagaContext $context): void
    {
        $context->setFromArray($this->orderData);

        $orchestrator
            ->addStep(CreateOrderStep::class)
            ->execute($context);
    }
}
```

---

## Fluxo de Execução

### Sucesso

```
Request → Controller → setFromArray(dados) → addStep(s) → execute()
  → Step1.run() ✓ (armazenado em executedSteps)
  → Step2.run() ✓
  → Step3.run() ✓
  → retorna SagaContext atualizado
```

### Falha com Compensação

```
→ Step1.run() ✓
→ Step2.run() ✗ FALHA (após retries esgotados)
  ↓ FASE DE COMPENSAÇÃO (ordem inversa)
→ Step1.rollback() ✓ (armazenado em compensatedSteps)
  ↓
→ Cria SagaFailureLog com snapshot completo
→ Relança exceção original
```

### Lógica de Retry

- `retries: 3` significa **4 tentativas no total** (1 inicial + 3 extras)
- Sleep só ocorre **entre tentativas**, nunca na primeira
- Se o passo eventualmente tem sucesso, **nenhum log de falha é criado**
- A compensação só é acionada se **todas as tentativas falharem**

---

## `SagaFailureLog` — Modelo de Log de Falha

Registra automaticamente toda falha com:

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `saga_id` | uuid | Identificador único da execução |
| `failed_step` | string | Classe do passo que falhou |
| `exception_class` | string | Classe da exceção |
| `exception_message` | text | Mensagem da exceção |
| `executed_steps` | json | Passos que executaram com sucesso |
| `compensated_steps` | json | Passos que compensaram com sucesso |
| `compensation_failures` | json | Falhas durante a compensação |
| `context_snapshot` | json | Estado completo do contexto na falha |

---

## Testes

### Convenções

- **Todos os testes** são Feature tests usando Pest 4
- Banco de dados: SQLite in-memory com `RefreshDatabase`
- Fila: `sync` (executa imediatamente, sem workers)
- HTTP externo: sempre mockado com `Http::fake()`
- Sleep: mockado automaticamente pelo Laravel — verificar com `Sleep::assertSleptTimes(n)`

### Estrutura de Teste para uma Saga

```php
<?php

use App\Models\Order;
use App\Models\SagaFailureLog;
use Illuminate\Support\Facades\Http;

it('creates a confirmed order when all steps succeed', function () {
    Http::fake([
        'external-service.example.com/pay' => Http::response(['amount' => 'sub_123'], 200),
    ]);

    $response = $this->postJson('/api/orders', [
        'customer_name' => 'João Silva',
        'customer_email' => 'joao@example.com',
        'product' => 'Produto X',
        'quantity' => 2,
        'total_price' => 5000,
    ]);

    $response->assertStatus(201)->assertJson(['status' => 'confirmed']);
    $this->assertDatabaseHas('orders', ['status' => 'confirmed']);
    expect(SagaFailureLog::count())->toBe(0);
});

it('rolls back when a step fails', function () {
    Http::fake([
        'external-service.example.com/pay' => Http::response([], 500),
    ]);

    $this->postJson('/api/orders', [/* dados */])->assertStatus(500);

    $this->assertDatabaseHas('orders', ['status' => 'failed']);
    expect(SagaFailureLog::count())->toBe(1);
});
```

### Substituir um Passo por um Falso (Binding)

```php
// Forçar falha em um passo específico durante o teste
$this->app->bind(ConfirmOrderStep::class, FailingStep::class);
```

### Testar Retry

```php
it('retries a step before triggering compensation', function () {
    $callCount = 0;

    $flakeyStep = new class($callCount) implements SagaStepInterface {
        public function __construct(private int &$callCount) {}

        public function run(SagaContext $context): void
        {
            $this->callCount++;
            if ($this->callCount < 3) {
                throw new RuntimeException('Transient failure.');
            }
        }

        public function rollback(SagaContext $context): void {}
    };

    $this->app->bind('step.flakey', fn () => $flakeyStep);

    $orchestrator = new SagaOrchestrator;
    $orchestrator->addStep('step.flakey', retries: 3, sleep: 2)->execute();

    expect($callCount)->toBe(3);
    Sleep::assertSleptTimes(2);
});
```

---

## Convenções do Projeto

| Aspecto | Convenção |
|---------|-----------|
| Classes de passos | `final class`, namespace `App\Domains\{Dominio}\Steps\` |
| Classes do core | `final class`, namespace `App\Supports\Saga\` |
| Interfaces | Sufixo `Interface`, ex: `SagaStepInterface` |
| Serviços externos | Implementam `ServicesInterface`, namespace `App\Supports\Services\{Dominio}\` |
| Tipo de retorno | Sempre explícito em todos os métodos |
| Strict types | `declare(strict_types=1)` em todos os arquivos PHP |
| Construtor | Constructor property promotion do PHP 8 |
| Comentários | PHPDoc blocks, não comentários inline |

---

## Comandos Úteis

```bash
# Executar todos os testes
php artisan test --compact

# Executar testes filtrados
php artisan test --compact --filter=OrderFlowTest

# Rodar worker de fila (execução assíncrona)
php artisan queue:work

# Formatar código (obrigatório após modificar PHP)
vendor/bin/pint --dirty --format agent

# Criar novo passo (usar make:class)
php artisan make:class Domains/Order/Steps/MyNewStep --no-interaction
```

---

## Checklist para Nova Saga

- [ ] Criar passos em `app/Domains/{Dominio}/Steps/` implementando `SagaStepInterface`
- [ ] Criar serviços em `app/Supports/Services/` implementando `ServicesInterface` (se necessário)
- [ ] Criar `StoreXRequest` para validação do request
- [ ] Criar controller (síncrono) ou job (assíncrono) que monta e executa o orquestrador
- [ ] Registrar rota em `routes/api.php`
- [ ] Criar migration e model se necessário
- [ ] Criar factory para o model
- [ ] Escrever Feature tests cobrindo: sucesso, falha com rollback, retry
- [ ] Rodar `vendor/bin/pint --dirty --format agent`
- [ ] Rodar `php artisan test --compact`
