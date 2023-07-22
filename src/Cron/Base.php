<?php

namespace Infrangible\Task\Cron;

use Exception;
use Infrangible\Core\Helper\Instances;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2023 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
abstract class Base
{
    /** @var Instances */
    protected $instanceHelper;

    /** @var \Infrangible\Task\Task\Base */
    private $task;

    /** @var bool */
    private $test = false;

    /**
     * @param Instances $instanceHelper
     */
    public function __construct(Instances $instanceHelper)
    {
        $this->instanceHelper = $instanceHelper;
    }

    /**
     * @return void
     * @throws Exception
     */
    protected function init()
    {
        $taskName = $this->getTaskName();

        if (empty($taskName)) {
            throw new Exception(__('Please specify a task name!'));
        }

        $this->getTask()->init('admin', $taskName, date('Y-m-d_H-i-s'), null, false, $this->test);
    }

    /**
     * @return string
     * @throws Exception
     */
    public function run(): string
    {
        $this->init();

        $this->getTask()->launch();

        $errorSummary = $this->getTask()->getSummary(\Infrangible\Task\Task\Base::SUMMARY_TYPE_ERROR);

        if ( ! empty($errorSummary)) {
            throw new Exception($errorSummary);
        }

        return $this->getTask()->getSummary();
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
     * Returns the task to tun
     *
     * @return \Infrangible\Task\Task\Base
     * @throws Exception
     */
    public function getTask(): \Infrangible\Task\Task\Base
    {
        if ($this->task === null) {
            $this->task = $this->instanceHelper->getInstance($this->getClassName());

            if ( ! ($this->task instanceof \Infrangible\Task\Task\Base)) {
                throw new Exception(sprintf('Task must extend %s', \Infrangible\Task\Task\Base::class));
            }
        }

        return $this->task;
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

        if ($this->getTask() !== null) {
            $this->getTask()->setTestMode($test);
        }
    }
}
