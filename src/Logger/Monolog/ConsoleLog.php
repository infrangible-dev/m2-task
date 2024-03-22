<?php

declare(strict_types=1);

namespace Infrangible\Task\Logger\Monolog;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2023 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class ConsoleLog
    extends AbstractLog
{
    /**
     * @return string
     */
    protected function getLogName(): string
    {
        return 'task_log_console';
    }

    /**
     * @return string
     */
    protected function getHandlerClass(): string
    {
        return Handler\ConsoleLog::class;
    }
}
