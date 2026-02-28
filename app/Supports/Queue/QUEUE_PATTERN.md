# Padrão de Mensageria com Retry e Dead Letter Queue

## Visão Geral

Padrão genérico para mensageria via filas que:

- Padroniza o layout de mensagens com keys de controle
- Evita que erros caiam na tabela `failed_jobs`
- Redireciona falhas para filas de retry (`{queue}_retry`)
- Após esgotar retries, move para Dead Letter Queue (`{queue}_dlq`)

## Estrutura

```
app/Supports/Queue/
├── Interfaces/
│   └── QueueMessageInterface.php     # Contrato da mensagem
├── Abstracts/
│   └── AbstractQueueHandler.php      # Handler base com try/catch + retry
└── QueueMessage.php                  # Implementação concreta da mensagem (DTO imutável)
```

## Componentes

### QueueMessage

Envelope padronizado e imutável que carrega o payload, metadata e informações de controle (retry, erro). O campo `data` é livre — cada fila define seu próprio payload.

Métodos `withError()` e `withIncrementedRetry()` retornam nova instância (imutabilidade). O `message_id` se mantém o mesmo durante todo o ciclo de vida, permitindo rastrear a mensagem desde a criação até a DLQ.

```php
$message = QueueMessage::create(
    source: 'checkout-service',
    queue: 'orders',
    data: ['order_id' => 123, 'customer_id' => 456, 'total' => 299.90],
    metadata: ['priority' => 'high', 'correlation_id' => 'abc-123'],
);
```

### AbstractQueueHandler

Classe abstrata que implementa `ShouldQueue`. Cada fila terá seu próprio consumer que estende este handler.

- `tries = 1` e `maxExceptions = 1` — Laravel não faz retry automático
- Se `process()` lança exceção, o catch captura, incrementa `retry_count` e registra o erro
- Se `retry_count < maxRetries` → dispatch para `{queue}_retry`
- Se esgotou → dispatch para `{queue}_dlq` + chama hook `onDeadLetter()`

```php
class ProcessOrderHandler extends AbstractQueueHandler
{
    protected int $maxRetries = 5;

    protected function process(QueueMessage $message): void
    {
        $orderId = $message->getData()['order_id'];
        // lógica de negócio
    }

    protected function onDeadLetter(QueueMessage $message): void
    {
        // notificar, logar, etc.
    }
}

// Dispatch
dispatch(new ProcessOrderHandler($message))->onQueue('orders');
```

## Fluxo

```
orders → (falha) → orders_retry → (esgotou) → orders_dlq
```

1. Job executa com `tries=1`
2. Se `process()` lança exceção → catch captura
3. Incrementa `retry_count`, registra erro na mensagem
4. Se `retry_count < maxRetries` → dispatch para `{queue}_retry`
5. Se esgotou → dispatch para `{queue}_dlq` + chama hook `onDeadLetter()`

## Layout da Mensagem

### Mensagem recém-criada

```json
{
    "message_id": "79a50895-f251-455f-9a5c-a3abbf83d707",
    "timestamp": "2026-02-28T22:53:42+00:00",
    "version": "1.0",
    "source": "checkout-service",
    "queue": "orders",
    "data": {
        "order_id": 123,
        "customer_id": 456,
        "total": 299.9
    },
    "metadata": {
        "priority": "high",
        "correlation_id": "abc-123"
    },
    "error": null,
    "retry_count": 0
}
```

### Mensagem após primeira falha (na fila `orders_retry`)

```json
{
    "message_id": "79a50895-f251-455f-9a5c-a3abbf83d707",
    "timestamp": "2026-02-28T22:53:42+00:00",
    "version": "1.0",
    "source": "checkout-service",
    "queue": "orders",
    "data": {
        "order_id": 123,
        "customer_id": 456,
        "total": 299.9
    },
    "metadata": {
        "priority": "high",
        "correlation_id": "abc-123"
    },
    "error": {
        "message": "Connection refused",
        "code": "500",
        "trace": "PDOException: SQLSTATE[HY000] [2002]..."
    },
    "retry_count": 1
}
```

| Campo | Descrição |
|-------|-----------|
| `message_id` | UUID único, mantido durante todo o ciclo de vida |
| `timestamp` | Data/hora de criação da mensagem (ISO 8601) |
| `version` | Versão do layout da mensagem |
| `source` | Serviço de origem |
| `queue` | Fila de destino original |
| `data` | Payload livre — cada fila define sua estrutura |
| `metadata` | Dados auxiliares (prioridade, correlation_id, etc.) |
| `error` | Último erro registrado (`null` quando sem erro) |
| `retry_count` | Número de tentativas realizadas |
