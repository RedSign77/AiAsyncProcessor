<?php

/**
 * AiAsyncProcessor
 *
 * @package    WebtechSolutions_AiAsyncProcessor
 * @copyright  Copyright (c) 2026 Webtech Solutions
 * @license    Proprietary
 */

declare(strict_types=1);

namespace WebtechSolutions\AiAsyncProcessor\Logger;

use Magento\Framework\Logger\Handler\Base;
use Monolog\Logger;

class Handler extends Base
{
    protected $loggerType = Logger::DEBUG;
    protected $fileName   = '/var/log/support_ai_async.log';
}
