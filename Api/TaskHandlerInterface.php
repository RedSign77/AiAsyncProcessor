<?php

/**
 * AiAsyncProcessor
 *
 * @package    WebtechSolutions_AiAsyncProcessor
 * @copyright  Copyright (c) 2026 Webtech Solutions
 * @license    Proprietary
 */

declare(strict_types=1);

namespace WebtechSolutions\AiAsyncProcessor\Api;

interface TaskHandlerInterface
{
    /**
     * Execute the given AI task.
     *
     * Implementations must throw on failure so the Consumer can decide
     * whether to retry or route the message to the Dead Letter Queue.
     *
     * @param array $payload
     * @return void
     */
    public function execute(array $payload): void;
}
