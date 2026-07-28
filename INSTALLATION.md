# Installation Guide — WebtechSolutions_AiAsyncProcessor

## Prerequisites

- Module files present under `app/code/WebtechSolutions/AiAsyncProcessor/`.
- A running RabbitMQ instance, reachable from the Magento application (PHP-FPM
  container in this project's Docker setup), with connection details already
  present in `app/etc/env.php` under the `queue` section.

## Steps

1. **Run setup upgrade**

   This registers the module, and — most importantly — declares the RabbitMQ
   topology (`ai.task.process` queue, `magento-ai-dlx` dead letter exchange,
   `ai.task.process.dlq` queue) automatically. No manual RabbitMQ admin steps
   are required.

   ```bash
   bin/magento setup:upgrade
   ```

2. **Compile dependency injection** (required in production mode, recommended
   in all modes)

   ```bash
   bin/magento setup:di:compile
   ```

3. **Flush the cache**

   ```bash
   bin/magento cache:flush
   ```

4. **Verify the consumers are registered**

   ```bash
   bin/magento queue:consumers:list
   ```

   You should see:
   - `webtechsolutions.ai.task.consumer`
   - `webtechsolutions.ai.task.dlq.consumer`

## Starting the consumer

The main consumer must run continuously in the background to process tasks.

**Manual test (processes a single message and exits):**
```bash
bin/magento queue:consumers:start webtechsolutions.ai.task.consumer --max-messages=1
```

**Supervisor configuration** (`/etc/supervisor/conf.d/magento-ai-async-consumer.conf`):
```ini
[program:magento-ai-async-consumer]
command=php /var/www/html/bin/magento queue:consumers:start webtechsolutions.ai.task.consumer --max-messages=1000
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/log/supervisor/ai-async-consumer.log
```

### Dead Letter Queue consumer (optional)

`webtechsolutions.ai.task.dlq.consumer` only logs messages that exceeded the
retry limit — it does not retry or requeue them. It is an operational
visibility tool, not a required always-on process. Run it manually when
investigating failures, or add a lightweight supervisor entry if you want
DLQ contents automatically mirrored into the log:

```bash
bin/magento queue:consumers:start webtechsolutions.ai.task.dlq.consumer
```

## Verification

Check that the RabbitMQ topology was created correctly (via the RabbitMQ
management UI at `http://<host>:15672`, or the HTTP API):

```bash
curl -s -u guest:guest http://rabbitmq:15672/api/queues/%2F/ai.task.process
curl -s -u guest:guest http://rabbitmq:15672/api/queues/%2F/ai.task.process.dlq
```

The `ai.task.process` queue should show these arguments:
```json
{
    "x-dead-letter-exchange": "magento-ai-dlx",
    "x-dead-letter-routing-key": "ai.task.process.dlq"
}
```

Check the log after processing tasks:
```bash
tail -f var/log/support_ai_async.log
```

## Uninstallation

```bash
bin/magento module:disable WebtechSolutions_AiAsyncProcessor
bin/magento setup:upgrade
bin/magento cache:flush
```

Note: any other module registering a task handler in the shared `TaskPool`
(e.g. `WebtechSolutions_MageInsightPro`) depends on this module and must be
disabled or updated first — see that module's own documentation.

To fully remove the module, delete the
`app/code/WebtechSolutions/AiAsyncProcessor/` directory after disabling it.
