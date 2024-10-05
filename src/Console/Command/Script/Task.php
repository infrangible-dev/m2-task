<?php

declare(strict_types=1);

namespace Infrangible\Task\Console\Command\Script;

use Exception;
use Infrangible\Core\Console\Command\Script;
use Infrangible\Task\Task\Base;
use Magento\Framework\App\Area;
use Magento\Framework\Phrase;
use Magento\Framework\Phrase\RendererInterface;
use Magento\Store\Model\App\Emulation;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2024 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
abstract class Task extends Script
{
    /** @var \Infrangible\Task\Helper\Task */
    protected $taskHelper;

    /** @var Emulation */
    protected $appEmulation;

    /** @var RendererInterface */
    protected $renderer;

    /** @var Base */
    private $task;

    public function __construct(
        \Infrangible\Task\Helper\Task $taskHelper,
        Emulation $appEmulation,
        RendererInterface $renderer
    ) {
        $this->taskHelper = $taskHelper;
        $this->appEmulation = $appEmulation;
        $this->renderer = $renderer;
    }

    /**
     * Executes the current command.
     *
     * @return int 0 if everything went fine, or an error code
     * @throws Exception
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $storeCode = $input->getOption('store_code');

        $this->appEmulation->startEnvironmentEmulation(
            $storeCode,
            Area::AREA_ADMINHTML,
            true
        );

        Phrase::setRenderer($this->renderer);

        $taskSuccess = $this->taskHelper->launchTask(
            $this->getTask(),
            $storeCode,
            $this->getTaskName(),
            null,
            $input->getOption('log_level'),
            $input->getOption('console'),
            $input->getOption('test')
        );

        $this->appEmulation->stopEnvironmentEmulation();

        return $taskSuccess ? Command::SUCCESS : Command::FAILURE;
    }

    abstract protected function getTaskName(): string;

    abstract protected function getClassName(): string;

    /**
     * @throws Exception
     */
    public function getTask(): Base
    {
        if ($this->task === null) {
            $this->task = $this->taskHelper->getTask($this->getClassName());
        }

        return $this->task;
    }
}
