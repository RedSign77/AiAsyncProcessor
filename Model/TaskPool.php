<?php

/**
 * AiAsyncProcessor
 *
 * @package    WebtechSolutions_AiAsyncProcessor
 * @copyright  Copyright (c) 2026 Webtech Solutions
 * @license    Proprietary
 */

declare(strict_types=1);

namespace WebtechSolutions\AiAsyncProcessor\Model;

use WebtechSolutions\AiAsyncProcessor\Api\TaskHandlerInterface;

class TaskPool
{
    /**
     * @param TaskHandlerInterface[] $handlers Keyed by task_type, injected via di.xml
     */
    public function __construct(
        private readonly array $handlers = []
    ) {
    }

    public function get(string $taskType): TaskHandlerInterface
    {
        if (!isset($this->handlers[$taskType])) {
            throw new \InvalidArgumentException(sprintf(
                'No AI task handler registered for task type "%s"',
                $taskType
            ));
        }

        $handler = $this->handlers[$taskType];
        if (!$handler instanceof TaskHandlerInterface) {
            throw new \InvalidArgumentException(sprintf(
                'Handler for task type "%s" must implement %s',
                $taskType,
                TaskHandlerInterface::class
            ));
        }

        return $handler;
    }
}
