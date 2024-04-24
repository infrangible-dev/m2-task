<?php

declare(strict_types=1);

namespace Infrangible\Task\Task;

use Exception;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2024 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
abstract class File
    extends Files
{
    /** @var array */
    private $importFiles;

    /** @var bool[] */
    private $importedFiles = [];

    /** @var bool */
    private $emptyRun = true;

    /**
     * @return void
     * @throws Exception
     */
    protected function prepare()
    {
        $this->validatePaths();
    }

    /**
     * Load import files and executes for all these files the import method.
     *
     * @return void
     * @throws Exception
     */
    protected function runTask()
    {
        $suppressEmptyMails = $this->isSuppressEmptyMails();

        $importFiles = $this->determineImportFiles();

        $fileCounter = count($importFiles);

        if ($fileCounter) {
            $this->setEmptyRun(false);

            for ($i = 0; $i < $fileCounter; $i++) {
                if (array_key_exists($i, $importFiles)) {
                    $this->logging->info(sprintf('Importing file %d/%d: %s', $i + 1, $fileCounter, $importFiles[$i]));

                    try {
                        $result = $this->importFile($importFiles[$i]);
                        $this->logging->debug(sprintf('Successfully finished import of file: %s', $importFiles[$i]));
                        $this->importedFiles[$importFiles[$i]] = $result;
                    } catch (Exception $exception) {
                        $this->logging->debug(
                            sprintf(
                                'Could not finish import of file: %s because; %s',
                                $importFiles[$i],
                                $exception->getMessage()
                            )
                        );
                        $this->logging->error($exception);
                        $this->importedFiles[$importFiles[$i]] = false;
                    }
                }
            }
        } else {
            $this->setProhibitSummarySending(self::SUMMARY_TYPE_ALL, $suppressEmptyMails);

            $this->logging->info('Nothing to import');
        }
    }

    /**
     * @param bool $success
     *
     * @return void
     * @throws Exception
     */
    protected function dismantle(bool $success)
    {
        foreach ($this->importedFiles as $importedFile => $result) {
            $this->archiveImportFile($importedFile, $result);
        }
    }

    /**
     * Determine the files to import and returns them.
     *
     * @return string[]             a list of import files
     * @throws Exception
     */
    protected function determineImportFiles(): array
    {
        if ($this->importFiles === null) {
            $path = $this->getImportPath();

            $this->importFiles = $this->coreFilesHelper->determineFilesFromFilePath($path);

            $filePattern = $this->getFilePattern();

            if (!$this->variables->isEmpty($filePattern)) {
                $filteredImportFiles = [];

                $filePattern = preg_replace('/\//', '\\\/', $filePattern);

                foreach ($this->importFiles as $importFile) {
                    if (preg_match(sprintf('/%s/', $filePattern), $importFile)) {
                        $filteredImportFiles[] = $importFile;
                    }
                }

                $this->importFiles = $filteredImportFiles;
            }
        }

        return $this->importFiles;
    }

    /**
     * @return bool
     * @throws Exception
     */
    protected function hasImportFiles(): bool
    {
        $importFiles = $this->determineImportFiles();

        return count($importFiles) > 0;
    }

    /**
     * Executes the task for the given file.
     *
     * @param string $importFile the path to the import file
     *
     * @return bool
     *
     * @throws Exception
     */
    abstract protected function importFile(string $importFile): bool;

    /**
     * @return string
     * @throws NoSuchEntityException
     */
    protected function getFilePattern(): string
    {
        return $this->getTaskSetting('file_pattern');
    }

    /**
     * @return bool
     */
    public function isEmptyRun(): bool
    {
        return $this->emptyRun;
    }

    /**
     * @param bool $emptyRun
     */
    public function setEmptyRun(bool $emptyRun): void
    {
        $this->emptyRun = $emptyRun;
    }
}
