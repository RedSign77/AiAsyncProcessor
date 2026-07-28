<?php

/**
 * AiAsyncProcessor
 *
 * @package    WebtechSolutions_AiAsyncProcessor
 * @copyright  Copyright (c) 2026 Webtech Solutions
 * @license    Proprietary
 */

declare(strict_types=1);

namespace WebtechSolutions\AiAsyncProcessor\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use WebtechSolutions\AiAsyncProcessor\Api\TaskHandlerInterface;
use WebtechSolutions\AiAsyncProcessor\Model\TaskPool;

class TaskPoolTest extends TestCase
{
    public function testGetReturnsRegisteredHandler(): void
    {
        $handler = $this->createMock(TaskHandlerInterface::class);
        $pool = new TaskPool(['fraud_check' => $handler]);

        $this->assertSame($handler, $pool->get('fraud_check'));
    }

    public function testGetThrowsForUnknownTaskType(): void
    {
        $pool = new TaskPool([]);

        $this->expectException(\InvalidArgumentException::class);
        $pool->get('unknown_task');
    }

    public function testGetThrowsWhenHandlerDoesNotImplementInterface(): void
    {
        $pool = new TaskPool(['bad_task' => new \stdClass()]);

        $this->expectException(\InvalidArgumentException::class);
        $pool->get('bad_task');
    }
}
