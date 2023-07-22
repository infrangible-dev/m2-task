<?php

namespace Infrangible\Task\Logger;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2023 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
interface ISummary
{
    /**
     * @return Record[]
     */
    public function getRecords(): array;

    /**
     * @param Record $record
     */
    public function addRecord(Record $record);
}
