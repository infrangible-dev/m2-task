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
abstract class File extends Files
{
    /** @var array */
    private $importFiles;

    /** @var bool[] */
    private $importedFiles = [];

    /** @var bool */
    private $emptyRun = true;

    /**
     * @throws Exception
     */
    protected function prepare(): void
    {
        $this->validatePaths();
    }

    /**
     * Load import files and executes for all these files the import method.
     *
     * @throws Exception
     */
    protected function runTask(): bool
    {
        $suppressEmptyMails = $this->isSuppressEmptyMails();

        $importFiles = $this->determineImportFiles(false);

        $fileCounter = count($importFiles);

        $success = true;

        if ($fileCounter) {
            $this->setEmptyRun(false);

            for ($i = 0; $i < $fileCounter; $i++) {
                if (array_key_exists(
                    $i,
                    $importFiles
                )) {
                    $this->logging->debug(
                        sprintf(
                            'Importing file %d/%d: %s',
                            $i + 1,
                            $fileCounter,
                            $importFiles[ $i ]
                        )
                    );

                    try {
                        $result = $this->importFile($importFiles[ $i ]);
                        $this->logging->debug(
                            sprintf(
                                'Successfully finished import of file: %s',
                                $importFiles[ $i ]
                            )
                        );
                        $this->importedFiles[ $importFiles[ $i ] ] = $result;
                    } catch (Exception $exception) {
                        $this->logging->debug(
                            sprintf(
                                'Could not finish import of file: %s because; %s',
                                $importFiles[ $i ],
                                $exception->getMessage()
                            )
                        );
                        $this->logging->error($exception);
                        $this->importedFiles[ $importFiles[ $i ] ] = false;

                        $success = false;
                    }
                }
            }
        } else {
            $this->setProhibitSummarySending(
                self::SUMMARY_TYPE_ALL,
                $suppressEmptyMails
            );

            $this->logging->info('Nothing to import');
        }

        return $success;
    }

    /**
     * @throws Exception
     */
    protected function dismantle(bool $success): void
    {
        $this->logging->info(
            sprintf(
                'Archiving %s imported file(s)',
                count($this->importedFiles)
            )
        );

        foreach ($this->importedFiles as $importedFile => $result) {
            $this->archiveImportFile(
                $importedFile,
                $result
            );
        }
    }

    /**
     * Determine the files to import and returns them.
     *
     * @return string[]             a list of import files
     * @throws Exception
     */
    protected function determineImportFiles(bool $quiet = true): array
    {
        if ($this->importFiles === null) {
            $path = $this->getImportPath();

            if (! $quiet) {
                $this->logging->info(
                    sprintf(
                        'Checking for files to import in path: %s',
                        $path
                    )
                );
            }

            $this->importFiles = $this->coreFilesHelper->determineFilesFromFilePath($path);

            $filePattern = $this->getFilePattern();

            if (! $this->variables->isEmpty($filePattern)) {
                if (! $quiet) {
                    $this->logging->info(
                        sprintf(
                            'Checking files to import in path: %s for pattern: %s',
                            $path,
                            $filePattern
                        )
                    );
                }

                $filteredImportFiles = [];

                $filePattern = preg_replace(
                    '/\//',
                    '\\\/',
                    $filePattern
                );

                foreach ($this->importFiles as $importFile) {
                    if (preg_match(
                        sprintf(
                            '/%s/',
                            $filePattern
                        ),
                        $importFile
                    )) {
                        $filteredImportFiles[] = $importFile;
                    }
                }

                $this->importFiles = $filteredImportFiles;
            }
        }

        if (! $quiet) {
            $this->logging->info(
                sprintf(
                    'Found %s file(s) to import',
                    count($this->importFiles)
                )
            );
        }

        return $this->importFiles;
    }

    /**
     * @throws Exception
     */
    protected function hasImportFiles(): bool
    {
        $importFiles = $this->determineImportFiles();

        return count($importFiles) > 0;
    }

    /**
     * @throws Exception
     */
    abstract protected function importFile(string $importFile): bool;

    /**
     * @throws NoSuchEntityException
     */
    protected function getFilePattern(): string
    {
        return $this->getTaskSetting('file_pattern');
    }

    public function isEmptyRun(): bool
    {
        return $this->emptyRun;
    }

    public function setEmptyRun(bool $emptyRun): void
    {
        $this->emptyRun = $emptyRun;
    }
}
