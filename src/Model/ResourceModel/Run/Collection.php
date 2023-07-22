<?php

namespace Infrangible\Task\Model\ResourceModel\Run;

use Infrangible\Task\Model\Run;
use Magento\Framework\DB\Select;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Zend_Db_Select;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2023 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Collection
    extends AbstractCollection
{
    /**
     * @return void
     */
    public function _construct()
    {
        $this->_init(Run::class, \Infrangible\Task\Model\ResourceModel\Run::class);
    }

    /**
     * @return Select
     */
    public function getSelectCountSql(): Select
    {
        $selectCount = parent::getSelectCountSql();

        $selectCount->reset(Zend_Db_Select::HAVING);

        return $selectCount;
    }

    /**
     * @return void
     */
    public function addIsRunningFilter()
    {
        $this->addFieldToFilter('finish_at', ['null' => true]);
    }
}
