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

    public function __construct(Task $taskHelper)
    {
        $this->taskHelper = $taskHelper;
    }

    /**
     * @throws Exception
     */
    public function run(): string
    {
        $allSummaries = '';

        $listSuccess = true;

        foreach ($this->getTaskList() as $taskName => $className) {
            $task = $this->taskHelper->getTask($className);

            $taskSuccess = $this->taskHelper->launchTask(
                $task,
                'admin',
                $taskName,
                date('Y-m-d_H-i-s'),
                null,
                false,
                $this->isTest()
            );

            $listSuccess = $listSuccess && $taskSuccess;

            $allSummaries .= $task->getSummary();
        }

        if (! $listSuccess) {
            throw new Exception($allSummaries);
        }

        return $allSummaries;
    }

    abstract protected function getTaskList(): array;

    public function isTest(): bool
    {
        return $this->test;
    }

    protected function setTestMode(bool $test = true): void
    {
        $this->test = $test;
    }
}
