<?php

declare(strict_types=1);

namespace Infrangible\Task\Cron;

use FeWeDev\Base\Files;
use Infrangible\Core\Helper\Stores;
use Infrangible\Task\Model\ResourceModel\Run\CollectionFactory;
use Infrangible\Task\Model\ResourceModel\RunFactory;
use Infrangible\Task\Model\Run;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2025 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Clean
{
    /** @var Stores */
    protected $storeHelper;

    /** @var Files */
    protected $fileHelper;

    /** @var CollectionFactory */
    protected $runCollectionFactory;

    /** @var RunFactory */
    protected $runResourceFactory;

    public function __construct(
        Stores $storeHelper,
        Files $fileHelper,
        CollectionFactory $runCollectionFactory,
        RunFactory $runResourceFactory
    ) {
        $this->storeHelper = $storeHelper;
        $this->fileHelper = $fileHelper;
        $this->runCollectionFactory = $runCollectionFactory;
        $this->runResourceFactory = $runResourceFactory;
    }

    /**
     * @throws \Exception
     */
    public function execute(): void
    {
        $collection = $this->runCollectionFactory->create();

        $collection->addStartAtFilter();

        $runResource = $this->runResourceFactory->create();

        /** @var Run $run */
        foreach ($collection as $run) {
            $taskName = $run->getTaskName();
            $taskId = $run->getTaskId();

            foreach ($this->storeHelper->getStores(true) as $store) {
                $storeCode = $store->getCode();

                $logName = implode(
                    '/',
                    [
                        BP,
                        'var',
                        'log',
                        'task',
                        $taskName,
                        $storeCode,
                        sprintf(
                            '%s.log',
                            $taskId
                        )
                    ]
                );

                if (file_exists($logName)) {
                    $this->fileHelper->removeFile($logName);
                }

                $errorLog = implode(
                    '/',
                    [
                        BP,
                        'var',
                        'log',
                        'task',
                        $taskName,
                        $storeCode,
                        sprintf(
                            '%s.err',
                            $taskId
                        )
                    ]
                );

                if (file_exists($errorLog)) {
                    $this->fileHelper->removeFile($errorLog);
                }
            }

            $runResource->delete($run);
        }
    }
}
