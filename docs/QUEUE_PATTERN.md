# Padrão de Mensageria com Retry e Dead Letter Queue

## Visão Geral

Padrão genérico para mensageria via filas que:

- Padroniza o layout de mensagens com keys de controle
- Evita que erros caiam na tabela `failed_jobs`
- Redireciona falhas para filas de retry (`{queue}_retry`)
- Após esgotar retries, move para Dead Letter Queue (`{queue}_dlq`)
- Desacopla producer e consumer via `QueueJob` genérico e `QueueRouter`

## Estrutura

```
app/Supports/Queue/
├── Interfaces/
│   └── QueueMessageInterface.php     # Contrato da mensagem
├── Abstracts/
│   └── AbstractQueueHandler.php      # Handler base com try/catch + retry (classe pura, sem ShouldQueue)
├── QueueMessage.php                  # Implementação concreta da mensagem (DTO imutável)
├── QueueJob.php                      # Job genérico (ShouldQueue) — transporte na fila
├── QueueProducer.php                 # Producer padronizado para publicação
└── QueueRouter.php                   # Registry que mapeia queue → handler

app/Providers/
└── QueueRouteServiceProvider.php     # Registra o QueueRouter e declara as rotas
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

### QueueJob

Job genérico que implementa `ShouldQueue` e serve como transporte na fila. O producer despacha `QueueJob` sem conhecer o handler. Quando o worker consome o job, ele resolve o handler via `QueueRouter` e delega o processamento.

- `tries = 1` e `maxExceptions = 1` — Laravel não faz retry automático
- A lógica de retry/DLQ fica no `AbstractQueueHandler`, que re-despacha um novo `QueueJob`

```php
// Internamente, o QueueJob faz:
$handlerClass = app(QueueRouter::class)->resolve($this->queue);
$handler = app($handlerClass);
$handler->handle($this->message);
```

### AbstractQueueHandler

Classe abstrata de processamento pura — não implementa `ShouldQueue`. Cada fila terá seu próprio consumer que estende este handler.

- Se `process()` lança exceção, o catch captura, incrementa `retry_count` e registra o erro
- Se `retry_count < maxRetries` → dispatch `QueueJob` para `{queue}_retry`
- Se esgotou → dispatch `QueueJob` para `{queue}_dlq` + chama hook `onDeadLetter()`

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
```

### QueueRouter

Registry singleton que mapeia nomes de fila a handler classes. O binding é feito no consumer side — o producer não precisa saber qual handler consome a fila.

O registro de rotas é feito no `QueueRouteServiceProvider`:

```php
// app/Providers/QueueRouteServiceProvider.php
public function boot(): void
{
    $router = $this->app->make(QueueRouter::class);

    $router->register('notifications', SendOrderNotificationHandler::class);
    $router->register('orders', ProcessOrderHandler::class);
}
```

### QueueProducer

Encapsula a criação do `QueueMessage` e o dispatch do `QueueJob`. Não conhece handlers — apenas publica na fila.

```php
$producer = new QueueProducer(queue: 'orders');

$message = $producer->publish(
    source: 'checkout-service',
    data: ['order_id' => 123, 'customer_id' => 456, 'total' => 299.90],
    metadata: ['priority' => 'high'],
);

// $message->getMessageId() para rastreio
```

- Recebe apenas `queue` no construtor
- `publish()` cria o `QueueMessage`, despacha `QueueJob` na fila e retorna a mensagem

## Fluxo

```
Producer → QueueJob(orders) → QueueRouter → Handler.handle()
                                              ↓ (falha)
                                    QueueJob(orders_retry) → QueueRouter → Handler.handle()
                                                                            ↓ (esgotou)
                                                                  QueueJob(orders_dlq) + onDeadLetter()
```

1. Producer despacha `QueueJob` na fila
2. Worker consome `QueueJob`, resolve handler via `QueueRouter`
3. Handler executa `process()` — se sucesso, fim
4. Se `process()` lança exceção → catch captura, incrementa `retry_count`, registra erro
5. Se `retry_count < maxRetries` → dispatch `QueueJob` para `{queue}_retry`
6. Se esgotou → dispatch `QueueJob` para `{queue}_dlq` + chama hook `onDeadLetter()`

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

## Reprocessamento de Dead Letter Queue

Mensagens que esgotam todas as tentativas de retry são movidas para a fila `{queue}_dlq`. Após corrigir o problema que causou as falhas, é possível reprocessar todas as mensagens da DLQ usando o command artisan:

```bash
php artisan queue:reprocess --queue=notifications_dlq
```

### O que o command faz

1. Consome todos os `QueueJob` da fila DLQ via `Queue::pop()`
2. Para cada job, extrai a `QueueMessage` do payload
3. Reseta `error` e `retry_count` da mensagem (via `withReset()`)
4. Despacha um novo `QueueJob` na fila original (`$message->getQueue()`)
5. Deleta o job da DLQ
6. Exibe a quantidade de jobs reprocessados

### Validações

- A opção `--queue` é obrigatória
- O nome da fila deve terminar com `_dlq` — não é possível reprocessar filas normais ou de retry
- Se a fila estiver vazia, exibe mensagem informativa

### Exemplo de uso

```bash
# Reprocessar mensagens da DLQ de notificações
php artisan queue:reprocess --queue=notifications_dlq

# Reprocessar mensagens da DLQ de pedidos
php artisan queue:reprocess --queue=orders_dlq
```
