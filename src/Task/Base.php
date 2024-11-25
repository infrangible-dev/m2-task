<?php

declare(strict_types=1);

namespace Infrangible\Task\Task;

use Exception;
use FeWeDev\Base\Files;
use Infrangible\Core\Helper\Registry;
use Infrangible\SimpleMail\Model\MailFactory;
use Infrangible\Task\Helper\Data;
use Infrangible\Task\Logger\ISummary;
use Infrangible\Task\Logger\Monolog\Summary\AbstractSummary;
use Infrangible\Task\Logger\Record;
use Infrangible\Task\Model\RunFactory;
use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\AppInterface;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2024 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
abstract class Base implements AppInterface
{
    /** The Id of the Event Stream for all Log events from Level INFO to FATAL. */
    public const SUMMARY_TYPE_ALL = 'all';

    /** The Id of the Event Stream for all Log events from Level ERROR to FATAL. */
    public const SUMMARY_TYPE_ERROR = 'error';

    /** The Id of the Event Stream for all Log events from Level INFO. */
    public const SUMMARY_TYPE_SUCCESS = 'success';

    /** @var Files */
    protected $files;

    /** @var Registry */
    protected $registryHelper;

    /** @var Data */
    protected $helper;

    /** @var LoggerInterface */
    protected $logging;

    /** @var DirectoryList */
    protected $directoryList;

    /** @var RunFactory */
    protected $runFactory;

    /** @var \Infrangible\Task\Model\ResourceModel\RunFactory */
    protected $runResourceFactory;

    /** @var MailFactory */
    protected $mailFactory;

    /** @var string */
    private $storeCode;

    /** @var string */
    private $taskName;

    /** @var string */
    private $taskId;

    /** @var bool */
    private $test = false;

    /** @var resource */
    private $lockFile;

    /** @var array */
    private $dependencyList = [];

    /** @var bool */
    private $waitForPredecessor = false;

    /** @var array */
    private $prohibitSummarySending = [];

    /** @var bool */
    private $allowAdminStore = true;

    /** @var array */
    private $registryValues = [];

    public function __construct(
        Files $files,
        Registry $registryHelper,
        Data $helper,
        LoggerInterface $logging,
        DirectoryList $directoryList,
        RunFactory $runFactory,
        \Infrangible\Task\Model\ResourceModel\RunFactory $runResourceFactory,
        MailFactory $mailFactory
    ) {
        $this->files = $files;
        $this->registryHelper = $registryHelper;
        $this->helper = $helper;
        $this->logging = $logging;
        $this->directoryList = $directoryList;
        $this->runFactory = $runFactory;
        $this->runResourceFactory = $runResourceFactory;
        $this->mailFactory = $mailFactory;
    }

    abstract protected function prepare(): void;

    abstract protected function dismantle(bool $success): void;

    /**
     * @throws NoSuchEntityException
     */
    public function init(
        string $storeCode,
        string $taskName,
        string $taskId,
        ?string $logLevel = null,
        bool $console = false,
        bool $test = false
    ): void {
        $this->storeRegistryValues();

        $this->storeCode = $storeCode;
        $this->taskName = $taskName;
        $this->taskId = $taskId;
        $this->test = $test;

        $maxMemory = $this->getTaskSetting('max_memory');

        if (! empty($maxMemory)) {
            ini_set(
                'memory_limit',
                sprintf(
                    '%dM',
                    $maxMemory
                )
            );
        }

        $list = $this->getTaskSetting('depends_on');

        if (! empty($list)) {
            $this->dependencyList = explode(
                ';',
                $list
            );
        }

        if ($this->getTaskSetting('wait_for_predecessor')) {
            $this->waitForPredecessor = true;
        }

        if (empty($logLevel)) {
            $logLevel = (string)$this->helper->getTaskConfigValue(
                $this->taskName,
                'logging',
                'log_level',
                LogLevel::INFO
            );
        }

        $logWarnAsError = (int)$this->helper->getTaskConfigValue(
            $this->taskName,
            'logging',
            'log_warn_as_error',
            1
        );

        $this->registryHelper->register(
            'current_task_name',
            $taskName,
            false,
            true
        );
        $this->registryHelper->register(
            'current_task_id',
            $taskId,
            false,
            true
        );
        $this->registryHelper->register(
            'current_task_log_level',
            $logLevel,
            false,
            true
        );
        $this->registryHelper->register(
            'current_task_log_warn_as_error',
            $logWarnAsError,
            false,
            true
        );
        $this->registryHelper->register(
            'current_task_console',
            $console,
            false,
            true
        );
    }

    protected function storeRegistryValues(): void
    {
        $this->registryValues[ 'current_task_name' ] = $this->registryHelper->registry('current_task_name');
        $this->registryValues[ 'current_task_id' ] = $this->registryHelper->registry('current_task_id');
        $this->registryValues[ 'current_task_log_level' ] = $this->registryHelper->registry('current_task_log_level');
        $this->registryValues[ 'current_task_log_warn_as_error' ] =
            $this->registryHelper->registry('current_task_log_warn_as_error');
        $this->registryValues[ 'current_task_console' ] = $this->registryHelper->registry('current_task_console');
    }

    /**
     * @throws Exception
     */
    public function launch(): bool
    {
        $memoryUsageStart = $this->getCurrentMemoryUsage();

        if (! $this->allowAdminStore && strcasecmp(
                trim($this->storeCode),
                'admin'
            ) === 0) {
            throw new Exception(__('Task is not allowed to run with admin store.'));
        }

        $run = $this->runFactory->create();

        $run->start(
            $this->storeCode,
            $this->taskName,
            $this->taskId,
            $this->test
        );

        $this->runResourceFactory->create()->save($run);

        $start = time();

        $success = true;

        // add Current task to the dependency list
        if (! $this->waitForPredecessor) {
            $this->dependencyList[] = $this->taskName;
        }

        if ($this->checkDependencyTask($this->dependencyList)) {
            if ($this->waitForPredecessor) {
                flock(
                    $this->getLockFile($this->taskName),
                    LOCK_EX
                );
            } else {
                flock(
                    $this->getLockFile($this->taskName),
                    LOCK_EX | LOCK_NB
                );
            }
        } else {
            $success = false;
        }

        if ($this->isTest()) {
            $this->logging->info(__('Task is running in test mode'));
        }

        try {
            $this->prepare();
        } catch (Exception $exception) {
            $this->logging->error(
                sprintf(
                    __('Could not prepare task because: %s')->render(),
                    $exception->getMessage()
                )
            );
            $this->logging->error($exception);

            $success = false;
        }

        if ($success) {
            try {
                $this->logging->info(
                    sprintf(
                        __('Running task: %s')->render(),
                        $this->getTaskName()
                    )
                );

                $success = $this->runTask();

                $this->logging->info(
                    sprintf(
                        __('Finished task: %s')->render(),
                        $this->getTaskName()
                    )
                );
            } catch (Exception $exception) {
                $this->logging->error(
                    sprintf(
                        __('Could not run task because: %s')->render(),
                        $exception->getMessage()
                    )
                );
                $this->logging->error($exception);

                $success = false;
            }
        }

        try {
            $this->dismantle($success);
        } catch (Exception $exception) {
            $this->logging->error(
                sprintf(
                    __('Could not dismantle task because: %s')->render(),
                    $exception->getMessage()
                )
            );
            $this->logging->error($exception);
        }

        try {
            $this->sendSummary(static::SUMMARY_TYPE_SUCCESS);
        } catch (Exception $exception) {
            $this->logging->error(
                sprintf(
                    __('Could not send success summary because: %s')->render(),
                    $exception->getMessage()
                )
            );
            $this->logging->error($exception);
        }

        try {
            $this->sendSummary(static::SUMMARY_TYPE_ERROR);
        } catch (Exception $exception) {
            $this->logging->error(
                sprintf(
                    __('Could not send error summary because: %s')->render(),
                    $exception->getMessage()
                )
            );
            $this->logging->error($exception);
        }

        $this->unLockFile($this->taskName);

        $duration = time() - $start;

        $minutes = intval(floor($duration / 60));
        $seconds = $duration % 60;

        $memoryUsageEnd = $this->getCurrentMemoryUsage();

        $this->logging->info(
            sprintf(
                __('Duration: %d minute(s), %d second(s)')->render(),
                $minutes,
                $seconds
            )
        );
        $this->logging->info(
            sprintf(
                __('Max memory usage: %s MB')->render(),
                number_format(
                    floatval(
                        bcsub(
                            strval($memoryUsageEnd),
                            strval($memoryUsageStart)
                        )
                    ),
                    0,
                    ',',
                    '.'
                )
            )
        );

        $run->finish(
            intval(
                bcsub(
                    strval($memoryUsageEnd),
                    strval($memoryUsageStart)
                )
            ),
            $success,
            $this->isEmptyRun()
        );

        $this->runResourceFactory->create()->save($run);

        $this->resetRegistryValues();

        return $success;
    }

    abstract public function isEmptyRun(): bool;

    protected function resetRegistryValues(): void
    {
        foreach ($this->registryValues as $key => $value) {
            $this->registryHelper->register(
                $key,
                $value,
                false,
                true
            );
        }
    }

    /**
     * Ability to handle exceptions that may have occurred during bootstrap and launch
     *
     * Return values:
     * - true: exception has been handled, no additional action is needed
     * - false: exception has not been handled - pass the control to Bootstrap
     */
    public function catchException(Bootstrap $bootstrap, Exception $exception): bool
    {
        $this->logging->emergency($exception);

        return true;
    }

    /**
     * @return resource
     * @throws FileSystemException
     */
    private function createLockFile(string $taskName)
    {
        $tempPath = $this->directoryList->getPath(DirectoryList::TMP);

        $this->files->createDirectory($tempPath);

        $file = sprintf(
            '%s/task_%s.lock',
            $tempPath,
            $taskName
        );

        $lockFile = fopen(
            $file,
            is_file($file) ? 'w' : 'x'
        );

        fwrite(
            $lockFile,
            date('c')
        );

        return $lockFile;
    }

    /**
     * @return resource
     * @throws FileSystemException
     */
    private function getLockFile(string $taskName)
    {
        if ($this->lockFile === null) {
            $this->lockFile = $this->createLockFile($taskName);
        }

        return $this->lockFile;
    }

    /**
     * @throws FileSystemException
     */
    private function unLockFile(string $taskName): void
    {
        $lockFile = $this->getLockFile($taskName);

        flock(
            $lockFile,
            LOCK_UN
        );

        if ($lockFile) {
            fclose($lockFile);
        }

        $this->lockFile = null;
    }

    /**
     * @throws FileSystemException
     */
    private function checkDependencyTask(array $dependencyList): bool
    {
        foreach ($dependencyList as $taskName) {
            if (! flock(
                $this->createLockFile($taskName),
                LOCK_EX | LOCK_NB
            )) {
                $this->logging->error(
                    sprintf(
                        __('The task: %s is still running and block the process of this task: %s.')->render(),
                        $taskName,
                        $this->taskName
                    )
                );

                return false;
            } else {
                flock(
                    $this->createLockFile($taskName),
                    LOCK_UN
                );

                if ($this->createLockFile($taskName)) {
                    fclose($this->createLockFile($taskName));
                }
            }
        }

        return true;
    }

    /**
     * @throws Exception
     */
    public function launchFromAdmin(string $storeCode, string $taskName, bool $test = false): array
    {
        $this->init(
            $storeCode,
            $taskName,
            date('Y-m-d_H-i-s'),
            null,
            false,
            $test
        );

        $this->launch();

        return $this->getSummary(
            static::SUMMARY_TYPE_ALL,
            false,
            true
        );
    }

    abstract protected function runTask(): bool;

    /**
     * @throws NoSuchEntityException
     */
    protected function sendSummary(string $type): void
    {
        $sendSummary = $this->helper->getTaskConfigValue(
            $this->taskName,
            'summary_' . $type,
            'send',
            false,
            true
        );

        if ($sendSummary) {
            $content = $this->getSummary($type);

            if (! empty($content)) {
                $content = $this->getSummary(
                    $type,
                    true,
                    true
                );

                $sender = $this->helper->getTaskConfigValue(
                    $this->taskName,
                    'summary_' . $type,
                    'sender',
                    'general'
                );
                $subject = $this->helper->getTaskConfigValue(
                    $this->taskName,
                    'summary_' . $type,
                    'subject'
                );
                $recipients = $this->helper->getTaskConfigValue(
                    $this->taskName,
                    'summary_' . $type,
                    'recipients'
                );
                $copyRecipients = $this->helper->getTaskConfigValue(
                    $this->taskName,
                    'summary_' . $type,
                    'copy_recipients'
                );
                $blindCopyRecipients = $this->helper->getTaskConfigValue(
                    $this->taskName,
                    'summary_' . $type,
                    'blind_copy_recipients'
                );

                $senderEmail = $this->helper->getStoreConfig('trans_email/ident_' . $sender . '/email');
                $senderName = $this->helper->getStoreConfig('trans_email/ident_' . $sender . '/name');

                if (empty($subject)) {
                    $subject = __('Task: ') . $this->taskName . ' | ' . __('Summary: ') . $type;
                }

                $storeDefaultTitle = $this->helper->getStoreConfig('design/head/default_title');

                if (! empty($storeDefaultTitle)) {
                    $subject = $storeDefaultTitle . ' - ' . $subject;
                }

                if (! empty($senderEmail) && ! empty($senderName)) {
                    if ($this->isProhibitSummarySending($type)) {
                        $this->logging->debug(
                            sprintf(
                                __('Suppress sending summary of type: %s with subject: %s to recipients: %s')->render(),
                                $type,
                                $subject,
                                $recipients
                            )
                        );
                    } elseif (! empty($recipients)) {
                        $mail = $this->mailFactory->create();

                        $mail->setSubject($subject);
                        $mail->setBody($content);
                        $mail->addSender(
                            $senderEmail,
                            $senderName
                        );

                        $recipientEmails = explode(
                            ';',
                            $recipients
                        );

                        foreach ($recipientEmails as $recipientEmail) {
                            $mail->addReceiver(trim($recipientEmail));
                        }

                        if (! empty($copyRecipients)) {
                            $copyRecipientEmails = explode(
                                ';',
                                $copyRecipients
                            );

                            foreach ($copyRecipientEmails as $recipientEmail) {
                                $mail->addCopyReceiver(trim($recipientEmail));
                            }
                        }

                        if (! empty($blindCopyRecipients)) {
                            $blindCopyRecipientEmails = explode(
                                ';',
                                $blindCopyRecipients
                            );

                            foreach ($blindCopyRecipientEmails as $recipientEmail) {
                                $mail->addBlindCopyReceiver(trim($recipientEmail));
                            }
                        }

                        try {
                            if (! empty($copyRecipients)) {
                                if (! empty($blindCopyRecipients)) {
                                    $this->logging->debug(
                                        sprintf(
                                            __(
                                                'Sending summary of type: %s with subject: %s to recipients: %s, copy to: %s and blind copy to: %s'
                                            )->render(),
                                            $type,
                                            $subject,
                                            $recipients,
                                            $copyRecipients,
                                            $blindCopyRecipients
                                        )
                                    );
                                } else {
                                    $this->logging->debug(
                                        sprintf(
                                            __(
                                                'Sending summary of type: %s with subject: %s to recipients: %s, copy to: %s'
                                            )->render(),
                                            $type,
                                            $subject,
                                            $recipients,
                                            $copyRecipients
                                        )
                                    );
                                }
                            } else {
                                $this->logging->debug(
                                    sprintf(
                                        __('Sending summary of type: %s with subject: %s to recipients: %s')->render(),
                                        $type,
                                        $subject,
                                        $recipients
                                    )
                                );
                            }

                            $mail->addAdditionalHeader(
                                'x-task-name',
                                $this->getTaskName()
                            );
                            $mail->addAdditionalHeader(
                                'x-task-id',
                                $this->getTaskId()
                            );
                            $mail->addAdditionalHeader(
                                'x-summary-type',
                                $type
                            );

                            $mail->send();
                        } catch (Exception $exception) {
                            $this->logging->error(
                                sprintf(
                                    __('Could not send summary of type: %s because: %s')->render(),
                                    $type,
                                    $exception->getMessage()
                                )
                            );
                            $this->logging->error($exception);
                        }
                    } else {
                        $this->logging->error(
                            sprintf(
                                __('Could not send summary of type: %s because no recipients were configured')->render(
                                ),
                                $type
                            )
                        );
                    }
                } else {
                    $this->logging->error(
                        sprintf(
                            __('Could not send summary of type: %s because no sender was configured')->render(),
                            $type
                        )
                    );
                }
            }
        }
    }

    public function isTest(): bool
    {
        return $this->test === true;
    }

    /**
     * @return string|array|null
     */
    public function getSummary(
        string $type = self::SUMMARY_TYPE_ALL,
        bool $flat = true,
        bool $addSummaryInformation = false
    ) {
        $taskKey = md5(json_encode([$this->getTaskName(), $this->getTaskId()]));

        /** @var ISummary $summary */
        $summary = $this->registryHelper->registry(
            sprintf(
                'task_summary_%s_%s',
                $type,
                $taskKey
            )
        );

        if ($summary) {
            $records = $summary->getRecords();
            if ($flat) {
                $flatSummary = '';

                if ($addSummaryInformation) {
                    foreach ($this->getSummaryInformation() as $record) {
                        $flatSummary .= "\n" . trim($record->getMessage());
                    }

                    $flatSummary .= "\n";
                }

                foreach ($records as $record) {
                    $flatSummary .= "\n" . trim($record->getMessage());
                }

                return trim($flatSummary);
            } else {
                return $addSummaryInformation ? array_merge(
                    $this->getSummaryInformation(),
                    $records
                ) : $records;
            }
        }

        return null;
    }

    /**
     * @throws NoSuchEntityException
     */
    public function getConfigValue(
        string $section,
        string $field,
        $defaultValue = null,
        bool $isFlag = false,
        bool $forceTaskConfigValue = false
    ) {
        return $this->helper->getTaskConfigValue(
            $this->taskName,
            $section,
            $field,
            $defaultValue,
            $isFlag,
            $forceTaskConfigValue
        );
    }

    /**
     * @return Record[]
     */
    protected function getSummaryInformation(): array
    {
        return [
            new Record(
                LogLevel::INFO,
                sprintf(
                    __('Task Name: %s')->render(),
                    $this->taskName
                )
            ),
            new Record(
                LogLevel::INFO,
                sprintf(
                    __('Task Id: %s')->render(),
                    $this->taskId
                )
            )
        ];
    }

    /**
     * @throws NoSuchEntityException
     */
    protected function getTaskSetting(
        string $field,
        $defaultValue = null,
        bool $isFlag = false,
        bool $forceTaskConfigValue = true
    ) {
        return $this->getConfigValue(
            'settings',
            $field,
            $defaultValue,
            $isFlag,
            $forceTaskConfigValue
        );
    }

    public function setTestMode(bool $test = true): void
    {
        $this->test = $test;
    }

    protected function isProhibitSummarySending(string $type): bool
    {
        return array_key_exists(
            $type,
            $this->prohibitSummarySending
        ) ? $this->prohibitSummarySending[ $type ] : (array_key_exists(
                static::SUMMARY_TYPE_ALL,
                $this->prohibitSummarySending
            ) && $this->prohibitSummarySending[ static::SUMMARY_TYPE_ALL ]);
    }

    public function setProhibitSummarySending(
        string $type = self::SUMMARY_TYPE_ALL,
        bool $prohibitSummarySending = true
    ) {
        $this->prohibitSummarySending[ $type ] = $prohibitSummarySending;
    }

    public function getTaskName(): string
    {
        return $this->taskName;
    }

    public function getTaskId(): string
    {
        return $this->taskId;
    }

    public function getStoreCode(): string
    {
        return $this->storeCode;
    }

    public function setAllowAdminStore(bool $allowAdminStore)
    {
        $this->allowAdminStore = $allowAdminStore;
    }

    protected function getCurrentMemoryUsage(): float
    {
        if (is_callable('shell_exec') && false === stripos(
                ini_get('disable_functions'),
                'shell_exec'
            )) {
            $memoryUsages = shell_exec(
                sprintf(
                    "cat /proc/%d/status 2>/dev/null | grep -E '^(VmRSS|VmSwap)' | awk '{print $2}' | xargs",
                    getmypid()
                )
            );

            if (preg_match_all(
                '/[0-9]+/',
                $memoryUsages,
                $matches
            )) {
                if (array_key_exists(
                    0,
                    $matches
                )) {
                    $memories = array_map(
                        'intval',
                        $matches[ 0 ]
                    );

                    return round(array_sum($memories) / 1024);
                }
            }
        }

        return round(memory_get_peak_usage() / (1024 * 1024));
    }

    /**
     * @throws Exception
     */
    public function addSummaryFromTask(Base $task): void
    {
        $this->addSummaryTypeFromTask(
            $task,
            static::SUMMARY_TYPE_ALL
        );
        $this->addSummaryTypeFromTask(
            $task,
            static::SUMMARY_TYPE_SUCCESS
        );
        $this->addSummaryTypeFromTask(
            $task,
            static::SUMMARY_TYPE_ERROR
        );
    }

    /**
     * @throws Exception
     */
    protected function addSummaryTypeFromTask(Base $task, string $type)
    {
        /** @var Record[] $taskSummaryRecords */
        $taskSummaryRecords = $task->getSummary(
            $type,
            false
        );

        if ($taskSummaryRecords) {
            /** @var AbstractSummary $summary */
            $summary = $this->registryHelper->registry(
                sprintf(
                    'task_summary_%s',
                    $type
                )
            );


            if ($summary) {
                foreach ($taskSummaryRecords as $taskSummaryRecord) {
                    $summary->addRecordToTaskHandler($taskSummaryRecord);
                }
            }
        }
    }
}
