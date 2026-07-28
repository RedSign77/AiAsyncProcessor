<?php

/**
 * AiAsyncProcessor
 *
 * @package    WebtechSolutions_AiAsyncProcessor
 * @copyright  Copyright (c) 2026 Webtech Solutions
 * @license    Proprietary
 */

declare(strict_types=1);

namespace WebtechSolutions\AiAsyncProcessor\Model\Queue;

use Psr\Log\LoggerInterface;
use WebtechSolutions\AiAsyncProcessor\Model\TaskPool;

class Consumer
{
    public function __construct(
        private readonly TaskPool $taskPool,
        private readonly TaskPublisher $taskPublisher,
        private readonly LoggerInterface $logger,
        private readonly int $maxRetries = 3
    ) {
    }

    public function process(string $message): void
    {
        $decoded = json_decode($message, true);
        if (!is_array($decoded)) {
            $this->logger->error('AiAsyncProcessor: message is not valid JSON, discarding', [
                'message' => $message,
            ]);
            return;
        }

        $taskType   = $decoded['task_type'] ?? null;
        $payload    = $decoded['payload'] ?? [];
        $retryCount = (int)($decoded['retry_count'] ?? 0);

        if (!$taskType) {
            $this->logger->error('AiAsyncProcessor: message missing task_type, discarding', [
                'message' => $decoded,
            ]);
            return;
        }

        try {
            $handler = $this->taskPool->get($taskType);
            $handler->execute($payload);

            $this->logger->info(sprintf(
                'AiAsyncProcessor: task "%s" processed successfully',
                $taskType
            ), ['payload' => $payload]);
        } catch (\Throwable $e) {
            $this->handleFailure($taskType, $payload, $retryCount, $e);
        }
    }

    private function handleFailure(string $taskType, array $payload, int $retryCount, \Throwable $e): void
    {
        $this->logger->error(sprintf(
            'AiAsyncProcessor: task "%s" failed (attempt %d/%d): %s',
            $taskType,
            $retryCount + 1,
            $this->maxRetries,
            $e->getMessage()
        ), ['payload' => $payload]);

        if ($retryCount < $this->maxRetries) {
            $this->taskPublisher->publish($taskType, $payload, $retryCount + 1);
            return;
        }

        $this->logger->critical(sprintf(
            'AiAsyncProcessor: task "%s" exceeded max retries (%d), routing to Dead Letter Queue',
            $taskType,
            $this->maxRetries
        ), ['payload' => $payload]);

        // Let the exception escape: Magento's queue transaction callback catches
        // generic \Exception and calls $queue->reject($message, false, ...) — i.e.
        // requeue=false — which triggers RabbitMQ's x-dead-letter-exchange routing
        // configured in etc/queue_topology.xml.
        throw $e;
    }
}
