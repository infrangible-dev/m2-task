<?php /** @noinspection PhpDeprecationInspection Magento's configuration value needs the registry, has no repository. */

declare(strict_types=1);

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

namespace Infrangible\Task\Cron\Execution;

use Magento\Cron\Model\ConfigInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\App\Config\ValueFactory;
use LogicException;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Backend model of a configuration field with the cron expression of a task: the expression is stored as the schedule
 * of the cron job task_<task name> (crontab/default/jobs/task_<task name>/schedule/cron_expr), and the field shows the
 * stored expression or the one of crontab.xml.
 */
abstract class Base extends Value
{
    /** @var LoggerInterface */
    protected LoggerInterface $logging;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param ValueFactory $configValueFactory
     * @param ConfigInterface $configInterface
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     * @phpstan-param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        protected ValueFactory $configValueFactory,
        protected ConfigInterface $configInterface,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);

        $this->logging = $context->getLogger();
    }

    /**
     * Shows the stored cron expression, or the one of crontab.xml.
     *
     * @return Base
     * @throws LocalizedException
     */
    public function _afterLoad(): Base
    {
        parent::_afterLoad();

        $config = $this->loadCronExpression();

        if ($config->getId()) {
            $this->setData('value', $config->getData('value'));
        } else {
            $jobs = $this->configInterface->getJobs();
            $job = $jobs[ 'default' ][ $this->getJobName() ] ?? [];

            $this->setData('value', is_array($job) ? ($job[ 'schedule' ] ?? null) : null);
        }

        return $this;
    }

    /**
     * Stores the cron expression as the schedule of the cron job.
     *
     * @return Base
     * @throws LocalizedException
     */
    public function save(): Base
    {
        $config = $this->loadCronExpression();

        $config->setData('path', $this->getCronExpressionPath());
        $config->setData('scope', 'default');
        $config->setData('scope_id', 0);
        $config->setData('value', $this->getData('value'));

        try {
            $config->save();
        } catch (Throwable $exception) {
            $this->logging->error($exception);
        }

        return $this;
    }

    /**
     * Returns the stored cron expression of the job, or a new value.
     *
     * @return Value
     * @throws LocalizedException
     */
    private function loadCronExpression(): Value
    {
        $config = $this->configValueFactory->create();

        if (! $config instanceof Value) {
            throw new LogicException(sprintf('The configuration value must extend %s', Value::class));
        }

        // @phpstan-ignore argument.type (loads by the field "path", not by ID)
        $config->load($this->getCronExpressionPath(), 'path');

        return $config;
    }

    /**
     * Returns the configuration path of the cron expression.
     *
     * @return string
     */
    private function getCronExpressionPath(): string
    {
        return sprintf('crontab/default/jobs/%s/schedule/cron_expr', $this->getJobName());
    }

    /**
     * Returns the name of the cron job.
     *
     * @return string
     */
    private function getJobName(): string
    {
        return 'task_' . $this->getTaskName();
    }

    /**
     * Returns the name of the task.
     *
     * @return string
     */
    abstract protected function getTaskName(): string;
}
