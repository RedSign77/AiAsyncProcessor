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

/**
 * Drains the Dead Letter Queue for visibility only — does not retry or
 * re-publish. Start manually via `bin/magento queue:consumers:start
 * webtechsolutions.ai.task.dlq.consumer` as an operational/incident-response
 * step, it is not cron-scheduled by default.
 */
class DlqConsumer
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    public function process(string $message): void
    {
        $this->logger->critical('AiAsyncProcessor: message landed in Dead Letter Queue', [
            'message' => json_decode($message, true) ?? $message,
        ]);
    }
}
