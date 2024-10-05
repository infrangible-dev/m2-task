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
abstract class Base
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
        $taskName = $this->getTaskName();

        if (empty($taskName)) {
            throw new Exception(__('Please specify a task name!'));
        }

        $task = $this->taskHelper->getTask($this->getClassName());

        $taskSuccess = $this->taskHelper->launchTask(
            $task,
            'admin',
            $taskName,
            date('Y-m-d_H-i-s'),
            null,
            false,
            $this->isTest()
        );

        if (! $taskSuccess) {
            throw new Exception($task->getSummary());
        }

        return $task->getSummary();
    }

    abstract protected function getTaskName(): string;

    abstract protected function getClassName(): string;

    public function isTest(): bool
    {
        return $this->test;
    }

    protected function setTestMode(bool $test = true): void
    {
        $this->test = $test;
    }
}
