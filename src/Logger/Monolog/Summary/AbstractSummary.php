<?php

declare(strict_types=1);

namespace Infrangible\Task\Logger\Monolog\Summary;

use Exception;
use Infrangible\Core\Helper\Instances;
use Infrangible\Core\Helper\Registry;
use Infrangible\Task\Logger\Monolog\Handler\Summary\AbstractHandler;
use Infrangible\Task\Logger\Record;
use Magento\Framework\Logger\Monolog;
use Monolog\DateTimeImmutable;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2024 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
abstract class AbstractSummary
    extends Monolog
{
    /** @var Instances */
    protected $instanceHelper;

    /** @var Registry */
    protected $registryHelper;

    /** @var AbstractHandler[] */
    private $taskHandlers = [];

    /**
     * @param Instances $instanceHelper
     * @param Registry  $registryHelper
     */
    public function __construct(Instances $instanceHelper, Registry $registryHelper)
    {
        $this->instanceHelper = $instanceHelper;
        $this->registryHelper = $registryHelper;

        parent::__construct($this->getSummaryName());

        $this->registryHelper->register($this->getSummaryName(), $this);
    }

    /**
     * @return string
     */
    abstract protected function getSummaryName(): string;

    /**
     * @return AbstractHandler
     * @throws Exception
     */
    public function prepareTaskHandler(): AbstractHandler
    {
        $taskName = $this->registryHelper->registry('current_task_name');
        $taskId = $this->registryHelper->registry('current_task_id');

        $taskKey = md5(json_encode([$taskName, $taskId]));

        if (!array_key_exists($taskKey, $this->taskHandlers)) {
            /** @var AbstractHandler $handler */
            $handler = $this->instanceHelper->getInstance($this->getHandlerClass());

            if ($handler === null) {
                throw new Exception(sprintf('Invalid handler class: %s', $this->getHandlerClass()));
            }

            $this->pushHandler($handler);

            $this->taskHandlers[$taskKey] = $handler;
        }

        return $this->taskHandlers[$taskKey];
    }

    /**
     * @return string
     */
    abstract protected function getHandlerClass(): string;

    /**
     * @param int                    $level    The logging level
     * @param string                 $message  The log message
     * @param array                  $context  The log context
     * @param DateTimeImmutable|null $datetime Optional log date to log into the past or future
     *
     * @return Boolean Whether the record has been processed
     * @throws Exception
     */
    public function addRecord(
        int $level,
        string $message,
        array $context = [],
        DateTimeImmutable $datetime = null
    ): bool {
        $this->prepareTaskHandler();

        return parent::addRecord($level, $message, $context);
    }

    /**
     * @param Record $record
     *
     * @throws Exception
     */
    public function addRecordToTaskHandler(Record $record)
    {
        $handler = $this->prepareTaskHandler();

        $handler->addRecord($record);
    }
}
