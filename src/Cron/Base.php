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
        $taskName = $this->getTaskName();

        if (empty($taskName)) {
            throw new Exception(__('Please specify a task name!'));
        }

        $take = $this->taskHelper->getTask($this->getClassName());

        $this->taskHelper->launchTask(
            $take,
            'admin',
            $taskName,
            date('Y-m-d_H-i-s'),
            null,
            false,
            $this->isTest()
        );

        $take->launch();

        $errorSummary = $take->getSummary(\Infrangible\Task\Task\Base::SUMMARY_TYPE_ERROR);

        if (!empty($errorSummary)) {
            throw new Exception($errorSummary);
        }

        return $take->getSummary();
    }

    /**
     * Returns the name of the task to initialize
     *
     * @return string
     */
    abstract protected function getTaskName(): string;

    /**
     * Returns the name of the task to initialize
     *
     * @return string
     */
    abstract protected function getClassName(): string;

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
