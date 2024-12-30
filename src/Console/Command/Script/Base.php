<?php

declare(strict_types=1);

namespace Infrangible\Task\Console\Command\Script;

use Infrangible\Core\Console\Command\Script;
use Symfony\Component\Console\Input\InputInterface;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2024 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
abstract class Base extends Script
{
    /**
     * @return string[]
     */
    protected function getStoreCodes(InputInterface $input): array
    {
        $storeCode = $input->getOption('store_code');

        $storeCodes = explode(
            ',',
            $storeCode
        );

        return array_map(
            'trim',
            $storeCodes
        );
    }

    protected function prepareTask(\Infrangible\Task\Task\Base $task, InputInterface $input)
    {
    }
}
