# AiAsyncProcessor — How to Use

This module is a developer-facing infrastructure layer. There is no admin UI
to click through — "using" it means registering a task handler in your own
module and publishing tasks to it. This guide walks through both.

---

## Step 1 — Confirm the consumer is running

Before publishing anything, make sure the background consumer is active (see
[INSTALLATION.md](INSTALLATION.md) for supervisor setup):

```bash
bin/magento queue:consumers:list
```

You should see `webtechsolutions.ai.task.consumer` in the list. If nothing is
consuming the queue, published tasks will simply accumulate in
`ai.task.process` until a consumer is started.

---

## Step 2 — Implement a task handler

Create a class implementing `TaskHandlerInterface` in your own module:

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Model\Queue;

use WebtechSolutions\AiAsyncProcessor\Api\TaskHandlerInterface;

class MyTaskHandler implements TaskHandlerInterface
{
    public function __construct(
        private readonly SomeService $someService
    ) {}

    public function execute(array $payload): void
    {
        $someId = $payload['some_id'] ?? null;
        if (!is_numeric($someId)) {
            // Throwing triggers the retry/DLQ flow — use it for anything
            // that should be retried or eventually surfaced in the DLQ.
            throw new \InvalidArgumentException('Payload missing "some_id"');
        }

        $this->someService->doSomethingSlow((int)$someId);
    }
}
```

**Important:** `execute()` must throw on any failure you want retried. If it
returns normally, the Consumer treats the task as successfully processed and
acknowledges the message — there is no way to signal "partial success" or
"retry me anyway" after a normal return.

---

## Step 3 — Register the handler

Add it to the shared `TaskPool` in your module's `etc/di.xml`. This uses
Magento's standard array-merge DI pattern, so multiple modules can each
register their own task types without conflict:

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:ObjectManager/etc/config.xsd">
    <type name="WebtechSolutions\AiAsyncProcessor\Model\TaskPool">
        <arguments>
            <argument name="handlers" xsi:type="array">
                <item name="my_task" xsi:type="object">Vendor\Module\Model\Queue\MyTaskHandler</item>
            </argument>
        </arguments>
    </type>
</config>
```

The `item name` (`my_task`) is the **task type** — this is the string other
code will use to publish tasks to your handler.

Also add `WebtechSolutions_AiAsyncProcessor` to your module's `<sequence>` in
`etc/module.xml`, and `webtechsolutions/module-ai-async-processor` to your
`composer.json` `require` block.

Run `bin/magento setup:upgrade && bin/magento cache:flush` after adding the
handler.

---

## Step 4 — Publish a task

Inject `WebtechSolutions\AiAsyncProcessor\Model\Queue\TaskPublisher` wherever
you currently make (or want to defer) a slow AI call — typically an observer,
controller, or another service class:

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;
use WebtechSolutions\AiAsyncProcessor\Model\Queue\TaskPublisher;

class SomeEventObserver implements ObserverInterface
{
    public function __construct(
        private readonly TaskPublisher $taskPublisher,
        private readonly LoggerInterface $logger
    ) {}

    public function execute(Observer $observer): void
    {
        try {
            $this->taskPublisher->publish('my_task', ['some_id' => 42]);
        } catch (\Throwable $e) {
            // Publishing itself should never block the caller — log and move on.
            $this->logger->error('Failed to publish my_task: ' . $e->getMessage());
        }
    }
}
```

This is the exact pattern used by `WebtechSolutions_MageInsightPro`'s
`SalesOrderPlaceAfter` observer for the `fraud_check` task type — use it as a
working reference implementation.

---

## Step 5 — Verify end-to-end

1. Trigger the code path that calls `publish()`.
2. Confirm the message reached the queue:
   ```bash
   curl -s -u guest:guest http://rabbitmq:15672/api/queues/%2F/ai.task.process
   ```
   Look for `"messages": 1` (or more).
3. Run the consumer for one message and check the log:
   ```bash
   bin/magento queue:consumers:start webtechsolutions.ai.task.consumer --max-messages=1
   tail var/log/support_ai_async.log
   ```
   You should see either an `INFO` line ("processed successfully") or an
   `ERROR`/`CRITICAL` line describing the failure and retry state.

---

## Understanding Retry Behavior

- Each task carries a `retry_count`, starting at `0`.
- On handler failure, the Consumer logs the failure and re-publishes the same
  task with `retry_count + 1`, up to a configured maximum (default: **3**).
- Once `retry_count` reaches the maximum, the Consumer logs a `CRITICAL`
  message and lets the exception propagate — Magento's queue framework then
  rejects the message without requeueing, and RabbitMQ's dead-letter exchange
  routes it to `ai.task.process.dlq`.
- To change the retry limit, override the `maxRetries` argument for
  `WebtechSolutions\AiAsyncProcessor\Model\Queue\Consumer` in your own
  `di.xml`.

**Caveat:** retry only helps if your handler actually throws on failure. If
the underlying call you're wrapping swallows its own errors and returns a
"safe" empty/default result (some AI clients do this), the Consumer has no
way to know it failed — inspect the result yourself and throw manually if
retry behavior is important for that task type.

---

## Inspecting the Dead Letter Queue

Tasks that exhausted retries land in `ai.task.process.dlq`. To see what's in
there without discarding it, use the RabbitMQ management UI, or start the
visibility consumer to mirror the contents into the log:

```bash
bin/magento queue:consumers:start webtechsolutions.ai.task.dlq.consumer --max-messages=10
tail var/log/support_ai_async.log
```

This consumer only logs — it does not delete, retry, or re-publish. Messages
remain in the DLQ (acknowledged off the queue, but their contents are now in
the log for manual follow-up) after being read.

---

## Frequently Asked Questions

**Q: Can I publish arbitrary array payloads?**
A: Yes. `TaskPublisher::publish()` accepts a plain array; it is JSON-encoded
internally to satisfy Magento's `communication.xml` schema (which does not
accept a bare `array` request type). Your handler's `execute(array $payload)`
receives the decoded array back — you never deal with JSON directly.

**Q: What happens if I publish a task_type with no registered handler?**
A: `TaskPool::get()` throws an `InvalidArgumentException`, which the Consumer
treats like any other handler failure — it goes through the normal
retry/DLQ flow. Since the handler will never appear no matter how many times
it retries, it will always end up in the DLQ after the retry limit.

**Q: Does this module talk to OpenAI or any AI provider directly?**
A: No. `AiAsyncProcessor` is provider-agnostic queue infrastructure. AI calls
happen inside your task handler, using whatever client you inject (e.g.
`WebtechSolutions\AiCore\Api\AiClientInterface`).

**Q: Can I publish and process on the `db` connection instead of `amqp`?**
A: The `db` connection is declared as a disabled fallback in
`etc/queue_publisher.xml` for parity with other WebtechSolutions modules, but
it is not wired to a consumer or topology binding in this module. Enable it
and add matching topology/consumer entries in your own module if you need it.
