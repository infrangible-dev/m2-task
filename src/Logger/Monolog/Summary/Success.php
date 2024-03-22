<?php

declare(strict_types=1);

namespace Infrangible\Task\Logger\Monolog\Summary;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2023 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Success
    extends AbstractSummary
{
    /**
     * @return string
     */
    protected function getSummaryName(): string
    {
        return 'task_summary_success';
    }

    /**
     * @return string
     */
    protected function getHandlerClass(): string
    {
        return \Infrangible\Task\Logger\Monolog\Handler\Summary\Success::class;
    }
}
