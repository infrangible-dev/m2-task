<?php

declare(strict_types=1);

namespace Infrangible\Task\Console\Command;

use Infrangible\Core\Console\Command\Command;
use Magento\Framework\App\Area;
use Symfony\Component\Console\Input\InputOption;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2024 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
abstract class Task
    extends Command
{
    /**
     * @return string
     */
    protected function getCommandName(): string
    {
        return sprintf('task:%s', $this->getTaskName());
    }

    /**
     * @return array
     */
    protected function getCommandDefinition(): array
    {
        return [
            new InputOption(
                'store_code', null, InputOption::VALUE_REQUIRED, 'Code of the store to run import for', 'admin'
            ),
            new InputOption('id', null, InputOption::VALUE_OPTIONAL, 'Id of the task'),
            new InputOption('log_level', null, InputOption::VALUE_OPTIONAL, 'Log level'),
            new InputOption('console', 'c', InputOption::VALUE_NONE, 'Log on the console'),
            new InputOption('test', 't', InputOption::VALUE_NONE, 'Task runs in test mode')
        ];
    }

    /**
     * @return string
     */
    protected function getArea(): string
    {
        return Area::AREA_ADMINHTML;
    }

    /**
     * @return string
     */
    abstract protected function getTaskName(): string;
}
