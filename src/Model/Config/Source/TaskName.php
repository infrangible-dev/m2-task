<?php

namespace Infrangible\Task\Model\Config\Source;

use Infrangible\Core\Helper\Database;
use Magento\Framework\Data\OptionSourceInterface;
use Zend_Db_Expr;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2023 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class TaskName
    implements OptionSourceInterface
{
    /** @var Database */
    protected $databaseHelper;

    /**
     * @param Database $databaseHelper
     */
    public function __construct(Database $databaseHelper)
    {
        $this->databaseHelper = $databaseHelper;
    }

    /**
     * @return string[]
     */
    protected function getTaskNames(): array
    {
        $taskNameQuery = $this->databaseHelper->select($this->databaseHelper->getTableName('task_run'),
            ['task_name' => new Zend_Db_Expr('distinct(task_name)')]);

        $taskNameQuery->order('task_name ASC');

        $taskNames = $this->databaseHelper->fetchCol($taskNameQuery);

        return $taskNames;
    }

    /**
     * Return array of options as value-label pairs
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        $taskNames = $this->getTaskNames();

        $result = [];

        foreach ($taskNames as $taskName) {
            $result[] = [
                'value' => $taskName,
                'label' => $taskName
            ];
        }

        return $result;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        $taskNames = $this->getTaskNames();

        $result = [];

        foreach ($taskNames as $taskName) {
            $result[ $taskName ] = $taskName;
        }

        return $result;
    }
}
