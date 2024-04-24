<?php

declare(strict_types=1);

namespace Infrangible\Task\Plugin;

use Infrangible\Log\Logger\Wrapper;
use Infrangible\Task\Logger\Monolog\ConsoleLog;
use Infrangible\Task\Logger\Monolog\ErrorLog;
use Infrangible\Task\Logger\Monolog\Log;
use Infrangible\Task\Logger\Monolog\Summary\All;
use Infrangible\Task\Logger\Monolog\Summary\Error;
use Infrangible\Task\Logger\Monolog\Summary\Success;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2024 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Logging
{
    /** @var Log */
    protected $taskLog;

    /** @var ErrorLog */
    protected $taskErrorLog;

    /** @var ConsoleLog */
    protected $taskConsoleLog;

    /** @var All */
    protected $taskSummaryAll;

    /** @var Success */
    protected $taskSummarySuccess;

    /** @var Error */
    protected $taskSummaryError;

    /**
     * @param Log        $taskLog
     * @param ErrorLog   $taskErrorLog
     * @param ConsoleLog $taskConsoleLog
     * @param All        $taskSummaryAll
     * @param Success    $taskSummarySuccess
     * @param Error      $taskSummaryError
     */
    public function __construct(
        Log $taskLog,
        ErrorLog $taskErrorLog,
        ConsoleLog $taskConsoleLog,
        All $taskSummaryAll,
        Success $taskSummarySuccess,
        Error $taskSummaryError
    ) {
        $this->taskLog = $taskLog;
        $this->taskErrorLog = $taskErrorLog;
        $this->taskConsoleLog = $taskConsoleLog;
        $this->taskSummaryAll = $taskSummaryAll;
        $this->taskSummarySuccess = $taskSummarySuccess;
        $this->taskSummaryError = $taskSummaryError;
    }

    /**
     * @param Wrapper $wrapper
     */
    public function afterInitialize(Wrapper $wrapper)
    {
        $wrapper->addLoggers([
                                 $this->taskLog,
                                 $this->taskErrorLog,
                                 $this->taskConsoleLog,
                                 $this->taskSummaryAll,
                                 $this->taskSummarySuccess,
                                 $this->taskSummaryError
                             ]);
    }
}
