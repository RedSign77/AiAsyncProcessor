<?php

/**
 * AiAsyncProcessor
 *
 * @package    WebtechSolutions_AiAsyncProcessor
 * @copyright  Copyright (c) 2026 Webtech Solutions
 * @license    Proprietary
 */

declare(strict_types=1);

namespace WebtechSolutions\AiAsyncProcessor\Test\Unit\Model\Queue;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use WebtechSolutions\AiAsyncProcessor\Api\TaskHandlerInterface;
use WebtechSolutions\AiAsyncProcessor\Model\Queue\Consumer;
use WebtechSolutions\AiAsyncProcessor\Model\Queue\TaskPublisher;
use WebtechSolutions\AiAsyncProcessor\Model\TaskPool;

class ConsumerTest extends TestCase
{
    private TaskPool&MockObject $taskPool;
    private TaskPublisher&MockObject $taskPublisher;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->taskPool      = $this->createMock(TaskPool::class);
        $this->taskPublisher = $this->createMock(TaskPublisher::class);
        $this->logger        = $this->createMock(LoggerInterface::class);
    }

    private function createConsumer(int $maxRetries = 3): Consumer
    {
        return new Consumer($this->taskPool, $this->taskPublisher, $this->logger, $maxRetries);
    }

    public function testSuccessfulTaskIsExecutedAndAcked(): void
    {
        $handler = $this->createMock(TaskHandlerInterface::class);
        $handler->expects($this->once())->method('execute')->with(['order_id' => 1]);

        $this->taskPool->method('get')->with('fraud_check')->willReturn($handler);
        $this->taskPublisher->expects($this->never())->method('publish');
        $this->logger->expects($this->once())->method('info');

        $consumer = $this->createConsumer();
        $consumer->process(json_encode([
            'task_type'   => 'fraud_check',
            'payload'     => ['order_id' => 1],
            'retry_count' => 0,
        ]));
    }

    public function testMissingTaskTypeIsLoggedAndDropped(): void
    {
        $this->taskPool->expects($this->never())->method('get');
        $this->taskPublisher->expects($this->never())->method('publish');
        $this->logger->expects($this->once())->method('error');

        $consumer = $this->createConsumer();
        $consumer->process(json_encode(['payload' => []]));
    }

    public function testInvalidJsonIsLoggedAndDropped(): void
    {
        $this->taskPool->expects($this->never())->method('get');
        $this->taskPublisher->expects($this->never())->method('publish');
        $this->logger->expects($this->once())->method('error');

        $consumer = $this->createConsumer();
        $consumer->process('not valid json');
    }

    public function testTransientFailureRepublishesWithIncrementedRetryCount(): void
    {
        $handler = $this->createMock(TaskHandlerInterface::class);
        $handler->method('execute')->willThrowException(new \RuntimeException('AI call failed'));
        $this->taskPool->method('get')->willReturn($handler);

        $this->taskPublisher->expects($this->once())
            ->method('publish')
            ->with('fraud_check', ['order_id' => 1], 2);

        $consumer = $this->createConsumer(3);
        $consumer->process(json_encode([
            'task_type'   => 'fraud_check',
            'payload'     => ['order_id' => 1],
            'retry_count' => 1,
        ]));
    }

    public function testFinalFailureLogsCriticalAndRethrows(): void
    {
        $handler = $this->createMock(TaskHandlerInterface::class);
        $exception = new \RuntimeException('AI call failed');
        $handler->method('execute')->willThrowException($exception);
        $this->taskPool->method('get')->willReturn($handler);

        $this->taskPublisher->expects($this->never())->method('publish');
        $this->logger->expects($this->once())->method('critical');

        $consumer = $this->createConsumer(3);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('AI call failed');

        $consumer->process(json_encode([
            'task_type'   => 'fraud_check',
            'payload'     => ['order_id' => 1],
            'retry_count' => 3,
        ]));
    }
}
