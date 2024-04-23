<?php

declare(strict_types=1);

namespace Infrangible\Task\Helper;

use FeWeDev\Base\Variables;
use Infrangible\Core\Helper\Instances;
use Infrangible\Task\Task\Base;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2024 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Task
{
    /** @var Variables */
    protected $variables;

    /** @var Instances */
    protected $instanceHelper;

    public function __construct(Variables $variables, Instances $instanceHelper)
    {
        $this->variables = $variables;
        $this->instanceHelper = $instanceHelper;
    }

    /**
     * @throws \Exception
     */
    public function runTask(
        string $storeCode,
        string $taskName,
        string $className,
        string $taskId = null,
        string $logLevel = null,
        bool $console = false,
        bool $test = false
    ) {
        if ($this->variables->isEmpty($taskName)) {
            throw new \Exception('Please specify a task name!');
        }

        $task = $this->getTask($className);

        $this->launchTask($task, $storeCode, $taskName, $taskId, $logLevel, $console, $test);
    }

    /**
     * @throws \Exception
     */
    public function launchTask(
        Base $task,
        string $storeCode,
        string $taskName,
        string $taskId = null,
        string $logLevel = null,
        bool $console = false,
        bool $test = false
    ) {
        if ($this->variables->isEmpty($taskId)) {
            $taskId = date('Y-m-d_H-i-s');
        }

        $task->init(
            $storeCode,
            $taskName,
            $taskId,
            $logLevel,
            $console,
            $test
        );

        $task->launch();
    }

    /**
     * @throws \Exception
     */
    public function getTask(string $className): Base
    {
        $task = $this->instanceHelper->getInstance($className);

        if (!($task instanceof Base)) {
            throw new \Exception(sprintf('Task must extend class: %s', Base::class));
        }

        return $task;
    }
}
