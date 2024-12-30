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
abstract class Task extends Command
{
    protected function getCommandName(): string
    {
        return sprintf(
            'task:%s',
            $this->getTaskName()
        );
    }

    protected function getCommandDefinition(): array
    {
        return [
            new InputOption(
                'store_code',
                null,
                InputOption::VALUE_REQUIRED,
                'Code or codes separated by comma of the store(s) to run task with',
                'admin'
            ),
            new InputOption(
                'id',
                null,
                InputOption::VALUE_OPTIONAL,
                'Id of the task'
            ),
            new InputOption(
                'log_level',
                null,
                InputOption::VALUE_OPTIONAL,
                'Log level'
            ),
            new InputOption(
                'console',
                'c',
                InputOption::VALUE_NONE,
                'Log on the console'
            ),
            new InputOption(
                'test',
                't',
                InputOption::VALUE_NONE,
                'Task runs in test mode'
            )
        ];
    }

    protected function getArea(): string
    {
        return Area::AREA_ADMINHTML;
    }

    abstract protected function getTaskName(): string;
}
