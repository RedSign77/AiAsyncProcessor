<?php

/**
 * AiAsyncProcessor
 *
 * @package    WebtechSolutions_AiAsyncProcessor
 * @copyright  Copyright (c) 2026 Webtech Solutions
 * @license    Proprietary
 */

declare(strict_types=1);

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'WebtechSolutions_AiAsyncProcessor',
    __DIR__
);
