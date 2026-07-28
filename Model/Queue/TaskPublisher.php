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

use Magento\Framework\MessageQueue\PublisherInterface;

class TaskPublisher
{
    private const TOPIC = 'ai.task.process';

    public function __construct(
        private readonly PublisherInterface $publisher
    ) {
    }

    /**
     * Publish an AI task to the async queue.
     *
     * @param string $taskType
     * @param array $payload
     * @param int $retryCount Internal — used by the Consumer when re-publishing after a transient failure.
     * @return void
     */
    public function publish(string $taskType, array $payload, int $retryCount = 0): void
    {
        $this->publisher->publish(self::TOPIC, json_encode([
            'task_type'   => $taskType,
            'payload'     => $payload,
            'retry_count' => $retryCount,
        ]));
    }
}
