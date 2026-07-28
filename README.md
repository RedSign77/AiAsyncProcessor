# AiAsyncProcessor

**Asynchronous AI task processing infrastructure for Magento 2.**

`AiAsyncProcessor` decouples slow, external AI API calls (e.g. OpenAI requests made by other WebtechSolutions modules) from the synchronous HTTP request/response cycle. Instead of blocking checkout, admin actions, or any other customer-facing request while waiting on an AI response, modules publish a task to a RabbitMQ queue and return immediately. A background consumer picks up the task, executes it, and handles retries and permanent failures automatically.

This module has no admin UI and no configuration screen — it is a piece of shared backend infrastructure other modules build on top of.

---

## Why This Exists

Modules like `WebtechSolutions_MageInsightPro` perform real-time fraud scoring on every order by calling OpenAI synchronously inside a `sales_order_place_after` observer. On Magento Cloud (and any PHP-FPM deployment with a limited worker pool), a slow or rate-limited AI API call directly threatens checkout throughput — a single stuck request holds a worker until the external call times out.

`AiAsyncProcessor` removes that risk: the observer publishes a small message and returns in milliseconds. The actual AI call happens in a separate consumer process, off the request path entirely.

---

## Key Features

- **Generic task queue** — any module can publish an AI task without adding new queue infrastructure of its own
- **Extensible task handler registry** — new task types are registered via `di.xml` array merge, no changes to this module required
- **Automatic retry** — transient failures (thrown exceptions) are retried up to a configurable limit before being given up on
- **Dead Letter Queue (DLQ)** — tasks that exceed the retry limit are routed to `ai.task.process.dlq` via RabbitMQ's native `x-dead-letter-exchange`, so no message is silently lost
- **Structured logging** — every attempt, retry, and DLQ routing decision is logged to a dedicated channel (`var/log/support_ai_async.log`)
- **Optional DLQ visibility consumer** — drains the dead letter queue into the log for manual inspection

---

## Requirements

- Magento 2.4.x
- PHP 8.1+
- `Magento_MessageQueue` (core module)
- RabbitMQ / AMQP connection configured in `app/etc/env.php`

---

## Architecture

```
Publishing module (e.g. MageInsightPro)
        │
        │  TaskPublisher::publish('fraud_check', ['order_id' => 123])
        ▼
  ai.task.process  (RabbitMQ queue, topic "ai.task.process")
        │
        ▼
      Consumer::process()
        │
        ├── TaskPool::get('fraud_check')  →  resolves the registered handler
        │
        ├── handler->execute($payload)
        │       ├── success  → ack, info log
        │       └── failure  → error log
        │             ├── retry_count < max  → re-publish with retry_count + 1
        │             └── retry_count >= max → critical log, exception re-thrown
        │                                       → RabbitMQ requeue=false
        │                                       → x-dead-letter-exchange
        │                                       → ai.task.process.dlq
        │
      DlqConsumer::process()  (manual/ops use — logs DLQ contents)
```

### Message shape

Messages are transmitted as a JSON-encoded string (the Magento `communication.xml` schema only accepts simple types or service classes, not bare arrays):

```json
{
    "task_type": "fraud_check",
    "payload": {"order_id": 123},
    "retry_count": 0
}
```

`TaskPublisher` and `Consumer` handle the JSON encode/decode internally — publishing and handler code always works with plain PHP arrays.

---

## Registering a New Task Type

1. Implement `WebtechSolutions\AiAsyncProcessor\Api\TaskHandlerInterface`:

   ```php
   class MyTaskHandler implements TaskHandlerInterface
   {
       public function execute(array $payload): void
       {
           // Do the work. Throw on failure to trigger retry/DLQ handling.
       }
   }
   ```

2. Register it in your module's `etc/di.xml` — this merges into the shared `TaskPool` handler array:

   ```xml
   <type name="WebtechSolutions\AiAsyncProcessor\Model\TaskPool">
       <arguments>
           <argument name="handlers" xsi:type="array">
               <item name="my_task" xsi:type="object">Vendor\Module\Model\Queue\MyTaskHandler</item>
           </argument>
       </arguments>
   </type>
   ```

3. Publish tasks from your own code via `TaskPublisher`:

   ```php
   $this->taskPublisher->publish('my_task', ['some' => 'data']);
   ```

See [HOW_TO_USE.md](HOW_TO_USE.md) for a complete walkthrough.

---

## Existing Consumers

| Task type | Module | Handler |
|---|---|---|
| `fraud_check` | `WebtechSolutions_MageInsightPro` | `Model\Queue\FraudCheckHandler` → calls `FraudAnalyzer::analyzeOrder()` |

---

## Support

For issues or feature requests, contact **WebtechSolutions**: info@webtech-solutions.hu
