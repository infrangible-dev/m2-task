<?php

declare(strict_types=1);

namespace Infrangible\Task\Logger\Monolog;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2024 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Log
    extends AbstractLog
{
    /**
     * @return string
     */
    protected function getLogName(): string
    {
        return 'task_log';
    }

    /**
     * @return string
     */
    protected function getHandlerClass(): string
    {
        return Handler\Log::class;
    }
}
