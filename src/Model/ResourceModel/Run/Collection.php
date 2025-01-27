<?php

declare(strict_types=1);

namespace Infrangible\Task\Model\ResourceModel\Run;

use Infrangible\Task\Model\Run;
use Magento\Framework\DB\Select;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Zend_Db_Select;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2025 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Collection extends AbstractCollection
{
    public function _construct(): void
    {
        $this->_init(
            Run::class,
            \Infrangible\Task\Model\ResourceModel\Run::class
        );
    }

    public function getSelectCountSql(): Select
    {
        $selectCount = parent::getSelectCountSql();

        $selectCount->reset(Zend_Db_Select::HAVING);

        return $selectCount;
    }

    public function addIsRunningFilter(): void
    {
        $this->addFieldToFilter(
            'finish_at',
            ['null' => true]
        );
    }

    public function addStartAtFilter(int $days = 90): void
    {
        $startDate = new \DateTime();
        $startDate->sub(
            \DateInterval::createFromDateString(
                sprintf(
                    '%d day',
                    $days
                )
            )
        );

        $this->addFieldToFilter(
            'start_at',
            ['lt' => $startDate->format('Y-m-d H:i:s')]
        );
    }
}
