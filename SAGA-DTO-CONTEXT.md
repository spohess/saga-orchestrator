# Melhoria: SagaContext com DTO tipado por saga

Branch: `resource-response`

## O que foi feito

Substituição do `SagaContext::toArray()` (retorno genérico `array<string, mixed>`) por um sistema de DTOs tipados por saga. Cada saga define seu próprio DTO que hidrata e serializa o contexto de forma explícita e type-safe.

`SagaOrchestrator::execute()` passou a retornar o DTO diretamente ao invés do `SagaContext`.

---

## Motivação

- `toArray()` retornava dados sem tipagem, forçando o uso de `$context->get('chave')` em todo o codebase
- Nenhuma garantia em tempo de compilação de quais dados existem no contexto
- O snapshot de falha (`SagaFailureLog.context_snapshot`) era um dump cego do array interno
- O resultado de `execute()` era opaco — quem chamava precisava saber as chaves de memória

---

## Arquivos criados

### `app/Supports/Saga/SagaContextDTOInterface.php`
Interface obrigatória para todo DTO de saga:
```php
interface SagaContextDTOInterface
{
    public static function fromContext(SagaContext $context): static;

    /** @return array<string, mixed> */
    public function toSnapshot(): array;
}
```

### `app/Supports/Saga/GenericSagaContextData.php`
DTO utilitário para testes de infraestrutura do core da saga (retry, compensation, logging) onde não há uma saga específica. Expõe `get()` delegando ao contexto para compatibilidade com testes que verificam valores armazenados nos passos.

### `app/Domains/Order/DTOs/OrderSagaContextData.php`
DTO tipado da saga de pedidos:
```php
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
}
```
- `fromContext()` hidrata a partir das chaves do `SagaContext`
- `toSnapshot()` serializa para o `SagaFailureLog.context_snapshot` (models convertidos via `toArray()`)

---

## Arquivos modificados

### `SagaContext`
- Construtor agora exige `class-string<SagaContextDTOInterface> $dtoClass`
- `toArray()` removido, substituído por `toDTO(): SagaContextDTOInterface`
- Import de `Model` removido (serialização delegada ao DTO)

### `SagaOrchestrator`
- `execute(?SagaContext $context = null): SagaContext` → `execute(SagaContext $context): SagaContextDTOInterface`
- Context deixou de ser opcional (forçado pelo chamador)
- Snapshot usa `$context->toDTO()->toSnapshot()`
- Retorna `$context->toDTO()` no caminho de sucesso

### `OrderController`
- `SagaContext` removido do construtor (não mais injetável sem o DTO class)
- Instanciado inline: `new SagaContext(OrderSagaContextData::class)`
- Resultado de `execute()` é `OrderSagaContextData` — `$dto->order->fresh()`

### `ProcessOrderSagaJob`
- `SagaContext` removido da injeção do `handle()`
- Instanciado inline: `new SagaContext(OrderSagaContextData::class)`

### `SagaFailureLogTest`
- Todos os testes que instanciam `SagaOrchestrator` diretamente passam `new SagaContext(GenericSagaContextData::class)`
- Teste de retry usa `$dto->get('flakey_result')` ao invés de `$context->get(...)`

---

## Padrão para criar uma nova saga

```php
// 1. Definir o DTO
final class MyDomainSagaContextData implements SagaContextDTOInterface
{
    public function __construct(
        public readonly string $inputField,
        public readonly ?MyModel $result = null,
    ) {}

    public static function fromContext(SagaContext $context): static
    {
        return new self(
            inputField: $context->get('input_field'),
            result: $context->get('result'),
        );
    }

    public function toSnapshot(): array
    {
        return [
            'input_field' => $this->inputField,
            'result' => $this->result?->toArray(),
        ];
    }
}

// 2. Instanciar o contexto com o DTO
$context = new SagaContext(MyDomainSagaContextData::class);
$context->setFromArray($data);

// 3. Executar e receber o DTO tipado
/** @var MyDomainSagaContextData $dto */
$dto = $orchestrator->addStep(MyStep::class)->execute($context);

// Acesso tipado ao resultado
$dto->result; // ?MyModel — sem get() genérico
```

---

## Estado dos testes

```
Tests: 13 passed (45 assertions)
```

Todos os testes existentes passam sem alteração de comportamento.

---

## Pontos em aberto / decisões futuras

- **`GenericSagaContextData::get()`** expõe acesso ao contexto bruto — necessário para os testes de infraestrutura, mas foge do propósito do DTO. Avaliar se os testes de infraestrutura devem ser refatorados para não depender de valores específicos do contexto.
- **Injeção de `SagaContext` via container** não é mais possível diretamente, pois o construtor exige a classe do DTO. Considerar um factory (`SagaContextFactory`) se o padrão de instanciação inline se tornar repetitivo.
- **Generics via PHPStan** — `execute()` retorna `SagaContextDTOInterface`, não o DTO concreto. Avaliar uso de `@template` para que ferramentas de análise estática inferam o tipo correto sem `@var`.
