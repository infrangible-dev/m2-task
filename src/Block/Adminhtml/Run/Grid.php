<?php /** @noinspection PhpDeprecationInspection */

declare(strict_types=1);

namespace Infrangible\Task\Block\Adminhtml\Run;

use Exception;
use FeWeDev\Base\Arrays;
use FeWeDev\Base\Variables;
use Infrangible\BackendWidget\Helper\Session;
use Infrangible\Core\Helper\Database;
use Infrangible\Core\Helper\Registry;
use Infrangible\Task\Model\Config\Source\TaskName;
use Infrangible\Task\Model\Run;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Grid\Column;
use Magento\Backend\Block\Widget\Grid\Column\Extended;
use Magento\Backend\Helper\Data;
use Magento\Eav\Model\Config;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Framework\Validator\UniversalFactory;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2024 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Grid
    extends \Infrangible\BackendWidget\Block\Grid
{
    /** @var TaskName */
    protected $taskName;

    /**
     * @param Context                                $context
     * @param Data                                   $backendHelper
     * @param Database                               $databaseHelper
     * @param Arrays                                 $arrays
     * @param Variables                              $variables
     * @param Registry                               $registryHelper
     * @param \Infrangible\BackendWidget\Helper\Grid $gridHelper
     * @param Session                                $sessionHelper
     * @param UniversalFactory                       $universalFactory
     * @param Config                                 $eavConfig
     * @param TaskName                               $taskName
     * @param array                                  $data
     */
    public function __construct(
        Context $context,
        Data $backendHelper,
        Database $databaseHelper,
        Arrays $arrays,
        Variables $variables,
        Registry $registryHelper,
        \Infrangible\BackendWidget\Helper\Grid $gridHelper,
        Session $sessionHelper,
        UniversalFactory $universalFactory,
        Config $eavConfig,
        TaskName $taskName,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $backendHelper,
            $databaseHelper,
            $arrays,
            $variables,
            $registryHelper,
            $gridHelper,
            $sessionHelper,
            $universalFactory,
            $eavConfig,
            $data
        );

        $this->taskName = $taskName;
    }

    /**
     * @return void
     * @throws FileSystemException
     */
    public function _construct()
    {
        parent::_construct();

        $this->setDefaultSort('start_at');
        $this->setDefaultDir('DESC');
    }

    /**
     * @param AbstractDb $collection
     *
     * @return void
     */
    protected function prepareCollection(AbstractDb $collection)
    {
        if ($collection instanceof AbstractCollection) {
            $collection->addExpressionFieldToSelect(
                'status',
                'IF({{0}} IS NULL,1,IF({{1}} IS NOT NULL OR {{2}} > 0,2,3))',
                ['finish_at', 'finish_at', 'max_memory_usage']
            );
            $collection->addExpressionFieldToSelect(
                'duration', 'TIMESTAMPDIFF(SECOND, {{0}}, {{1}})', ['start_at', 'finish_at']
            );
        }
    }

    /**
     * @return void
     * @throws Exception
     */
    protected function prepareFields()
    {
        $this->addTextColumn('store_code', __('Store Code')->render());
        $this->addOptionsColumn('task_name', __('Task Name')->render(), $this->taskName->toArray());
        $this->addTextColumn('task_id', __('Task Id')->render());
        $this->addTextColumn('process_id', __('Process Id')->render());
        $this->addOptionsColumnWithFilterConditionAndFrame(
            'status', __('Status')->render(), [
            1 => __('Running'),
            2 => __('Finished'),
            3 => __('Broken')
        ], [$this, 'filterStatus'], [$this, 'decorateStatus']
        );
        $this->addYesNoColumn('success', __('Success')->render());
        $this->addYesNoColumn('empty_run', __('Empty Run')->render());
        $this->addYesNoColumn('test', __('Test')->render());
        $this->addDatetimeColumn('start_at', __('Start Date')->render());
        $this->addDatetimeColumn('finish_at', __('Finish Date')->render());
        $this->addNumberColumnWithFilterCondition('duration', __('Duration')->render(), [$this, 'filterDuration']);
        $this->addNumberColumn('max_memory_usage', __('Memory')->render());
    }

    /**
     * @return string[]
     */
    protected function getHiddenFieldNames(): array
    {
        return ['process_id', 'empty_run', 'test', 'finish_at', 'duration', 'max_memory_usage'];
    }

    /**
     * @param AbstractCollection $collection
     * @param Column             $column
     *
     * @return void
     * @noinspection PhpDeprecationInspection
     */
    protected function filterStatus(AbstractCollection $collection, Column $column)
    {
        if ($this->getCollection()) {
            $field = $column->getData('filter_index') ? $column->getData('filter_index') : $column->getData('index');

            $filter = $column->getFilter();

            $condition = $filter->getCondition();

            $preparedCondition = $collection->getConnection()->prepareSqlCondition($field, $condition);

            $collection->getSelect()->having($preparedCondition);
        }
    }

    /**
     * @param AbstractCollection $collection
     * @param Column             $column
     *
     * @return void
     * @noinspection PhpDeprecationInspection
     */
    protected function filterDuration(AbstractCollection $collection, Column $column)
    {
        if ($this->getCollection()) {
            $field = $column->getData('filter_index') ? $column->getData('filter_index') : $column->getData('index');

            $filter = $column->getFilter();

            $condition = $filter->getCondition();

            $preparedCondition = $collection->getConnection()->prepareSqlCondition($field, $condition);

            $collection->getSelect()->having($preparedCondition);
        }
    }

    /**
     * Decorate status column values
     *
     * @param string   $value
     * @param Run      $row
     * @param Extended $column
     * @param bool     $isExport
     *
     * @return string
     */
    public function decorateStatus(
        string $value,
        Run $row,
        /** @noinspection PhpUnusedParameterInspection */ Extended $column,
        /** @noinspection PhpUnusedParameterInspection */ bool $isExport
    ): string {
        $class = '';

        switch ($row->getData('status')) {
            case 1:
                $class = 'task-run-status-running';
                break;
            case 2:
                $class = 'task-run-status-finished';
                break;
            case 3:
                $class = 'task-run-status-broken';
                break;
        }

        return '<span class="'.$class.'"><span>'.$value.'</span></span>';
    }
}
