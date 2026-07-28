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

use Magento\Framework\MessageQueue\PublisherInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WebtechSolutions\AiAsyncProcessor\Model\Queue\TaskPublisher;

class TaskPublisherTest extends TestCase
{
    private PublisherInterface&MockObject $publisher;
    private TaskPublisher $taskPublisher;

    protected function setUp(): void
    {
        $this->publisher = $this->createMock(PublisherInterface::class);
        $this->taskPublisher = new TaskPublisher($this->publisher);
    }

    public function testPublishSendsCorrectTopicAndShape(): void
    {
        $this->publisher->expects($this->once())
            ->method('publish')
            ->with('ai.task.process', json_encode([
                'task_type'   => 'fraud_check',
                'payload'     => ['order_id' => 42],
                'retry_count' => 0,
            ]));

        $this->taskPublisher->publish('fraud_check', ['order_id' => 42]);
    }

    public function testPublishWithExplicitRetryCount(): void
    {
        $this->publisher->expects($this->once())
            ->method('publish')
            ->with('ai.task.process', json_encode([
                'task_type'   => 'fraud_check',
                'payload'     => ['order_id' => 42],
                'retry_count' => 2,
            ]));

        $this->taskPublisher->publish('fraud_check', ['order_id' => 42], 2);
    }
}
