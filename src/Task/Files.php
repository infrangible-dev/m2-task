<?php

declare(strict_types=1);

namespace Infrangible\Task\Task;

use Exception;
use FeWeDev\Base\Variables;
use Infrangible\Core\Helper\Registry;
use Infrangible\SimpleMail\Model\MailFactory;
use Infrangible\Task\Helper\Data;
use Infrangible\Task\Model\RunFactory;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Mail\Template\TransportBuilder;
use Psr\Log\LoggerInterface;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2024 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
abstract class Files
    extends Base
{
    /** @var Variables */
    protected $variables;

    /** @var \Infrangible\Core\Helper\Files */
    protected $coreFilesHelper;

    /**
     * @param \FeWeDev\Base\Files                              $files
     * @param Registry                                         $registryHelper
     * @param Data                                             $helper
     * @param LoggerInterface                                  $logging
     * @param DirectoryList                                    $directoryList
     * @param TransportBuilder                                 $transportBuilder
     * @param RunFactory                                       $runFactory
     * @param \Infrangible\Task\Model\ResourceModel\RunFactory $runResourceFactory
     * @param Variables                                        $variableHelper
     * @param \Infrangible\Core\Helper\Files                   $coreFilesHelper
     * @param MailFactory                                      $mailFactory
     */
    public function __construct(
        \FeWeDev\Base\Files $files,
        Registry $registryHelper,
        Data $helper,
        LoggerInterface $logging,
        DirectoryList $directoryList,
        TransportBuilder $transportBuilder,
        RunFactory $runFactory,
        \Infrangible\Task\Model\ResourceModel\RunFactory $runResourceFactory,
        Variables $variableHelper,
        \Infrangible\Core\Helper\Files $coreFilesHelper,
        MailFactory $mailFactory
    ) {
        parent::__construct(
            $files,
            $registryHelper,
            $helper,
            $logging,
            $directoryList,
            $runFactory,
            $runResourceFactory,
            $mailFactory
        );

        $this->variables = $variableHelper;
        $this->coreFilesHelper = $coreFilesHelper;
    }

    /**
     * @return void
     * @throws Exception
     */
    protected function validatePaths()
    {
        if ($this->variables->isEmpty($this->getImportPath())) {
            throw new Exception('No path to import specified');
        }

        if ($this->variables->isEmpty($this->getArchivePath())) {
            throw new Exception('No archive path specified');
        }

        if ($this->variables->isEmpty($this->getErrorPath())) {
            throw new Exception('No error path specified');
        }
    }

    /**
     * @return string|null
     * @throws NoSuchEntityException
     */
    protected function getImportPath(): ?string
    {
        return $this->getTaskSetting('path');
    }

    /**
     * @return string|null
     * @throws NoSuchEntityException
     */
    protected function getArchivePath(): ?string
    {
        return $this->getTaskSetting('archive_path');
    }

    /**
     * @return string|null
     * @throws NoSuchEntityException
     */
    protected function getErrorPath(): ?string
    {
        return $this->getTaskSetting('error_path');
    }

    /**
     * @return bool
     * @throws NoSuchEntityException
     */
    protected function isSuppressEmptyMails(): bool
    {
        return $this->getTaskSetting('suppress_empty_mails', false, true);
    }

    /**
     * @param string $importedFile
     * @param bool   $result
     * @param bool   $keepFile
     *
     * @return void
     * @throws NoSuchEntityException
     * @throws Exception
     */
    protected function archiveImportFile(string $importedFile, bool $result, bool $keepFile = false): void
    {
        $archivePath = $this->getArchivePath();
        $errorPath = $this->getErrorPath();

        $importedFileArchivePath = $this->coreFilesHelper->determineFilePath($result ? $archivePath : $errorPath);

        $importedFileArchiveFileName = $this->files->determineFilePath(
            $this->getArchiveFileName(basename($importedFile)),
            $importedFileArchivePath,
            true
        );

        if (!file_exists($importedFileArchivePath)) {
            if (mkdir($importedFileArchivePath, 0777, true)) {
                $this->logging->info(sprintf('Archive path %s successful created', $importedFileArchivePath));
            } else {
                $this->logging->error(sprintf('Cannot create archive path %s', $importedFileArchivePath));
            }
        }

        $this->logging->debug(
            sprintf(
                'Moving import file: %s to archive file: %s',
                $importedFile,
                $importedFileArchiveFileName
            )
        );

        if (!$this->isTest() && !$keepFile) {
            if (!rename($importedFile, $importedFileArchiveFileName)) {
                throw new Exception(
                    sprintf(
                        'Could not move import file: %s to archive file: %s',
                        $importedFile,
                        $importedFileArchiveFileName
                    )
                );
            }
        } else {
            if (!copy($importedFile, $importedFileArchiveFileName)) {
                throw new Exception(
                    sprintf(
                        'Could not copy import file: %s to archive file: %s',
                        $importedFile,
                        $importedFileArchiveFileName
                    )
                );
            }
        }
    }

    /**
     * @param string $importFileName
     *
     * @return string
     */
    abstract protected function getArchiveFileName(string $importFileName): string;
}
