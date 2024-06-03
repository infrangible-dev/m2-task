<?php

declare(strict_types=1);

namespace Infrangible\Task\Cron;

use Exception;
use Infrangible\Task\Helper\Task;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2024 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
abstract class TaskList
{
    /** @var Task */
    protected $taskHelper;

    /** @var bool */
    private $test = false;

    /**
     * @param Task $taskHelper
     */
    public function __construct(Task $taskHelper)
    {
        $this->taskHelper = $taskHelper;
    }

    /**
     * @return string
     * @throws Exception
     */
    public function run(): string
    {
        $allSummaries = '';

        foreach ($this->getTaskList() as $taskName => $className) {
            $task = $this->taskHelper->getTask($className);

            $this->taskHelper->launchTask(
                $task,
                'admin',
                $taskName,
                date('Y-m-d_H-i-s'),
                null,
                false,
                $this->isTest()
            );

            $errorSummary = $task->getSummary(\Infrangible\Task\Task\Base::SUMMARY_TYPE_ERROR);

            if (!empty($errorSummary)) {
                throw new Exception($errorSummary);
            }

            $allSummaries .= $task->getSummary();
        }

        return $allSummaries;
    }

    abstract protected function getTaskList(): array;

    /**
     * @return bool
     */
    public function isTest(): bool
    {
        return $this->test;
    }

    /**
     * @param bool $test
     *
     * @return void
     * @throws Exception
     */
    protected function setTestMode(bool $test = true)
    {
        $this->test = $test;
    }
}
