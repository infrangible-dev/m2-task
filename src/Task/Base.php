<?php

declare(strict_types=1);

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

namespace Infrangible\Task\Task;

use Infrangible\Core\Helper\Registry;
use Infrangible\SimpleMail\Model\MailFactory;
use Infrangible\Task\Helper\Data;
use Infrangible\Task\Logger\SummaryInterface;
use Infrangible\Task\Logger\Record;
use Infrangible\Task\Model\ResourceModel\RunFactory as RunResourceFactory;
use Infrangible\Task\Model\Run;
use Infrangible\Task\Model\RunFactory;
use LogicException;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\MailException;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Phrase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Throwable;

/**
 * Base of all tasks: runs a task with its settings, logs into the log files of the run, keeps the run in the table
 * task_run and sends the summaries by mail.
 *
 * Tasks implement prepare(), runTask(), dismantle() and isEmptyRun(). A task runs only once at the same time, and not
 * while one of the tasks in its setting "Depends On" runs; with "Wait for Predecessor", it waits for the previous run
 * of the same task instead of failing.
 */
abstract class Base
{
    /** Summary with all records from the configured log level on. */
    public const SUMMARY_TYPE_ALL = 'all';

    /** Summary with the errors. */
    public const SUMMARY_TYPE_ERROR = 'error';

    /** Summary with the records below the error level. */
    public const SUMMARY_TYPE_SUCCESS = 'success';

    /** Registry keys of the running task, which the loggers read (see TaskContext). */
    private const REGISTRY_KEYS = [
        'current_task_name',
        'current_task_id',
        'current_task_log_level',
        'current_task_log_warn_as_error',
        'current_task_console'
    ];

    /** @var string */
    private string $storeCode = '';

    /** @var string */
    private string $taskName = '';

    /** @var string */
    private string $taskId = '';

    /** @var bool */
    private bool $test = false;

    /** @var string[] */
    private array $dependencyList = [];

    /** @var bool */
    private bool $waitForPredecessor = false;

    /**
     * @var array
     * @phpstan-var array<string, bool>
     */
    private array $prohibitSummarySending = [];

    /** @var bool */
    private bool $allowAdminStore = true;

    /**
     * @var array
     * @phpstan-var array<string, mixed>
     */
    private array $registryValues = [];

    /** @var Run|null */
    private ?Run $run = null;

    /**
     * @param File $file
     * @param Registry $registryHelper
     * @param Data $helper
     * @param LoggerInterface $logging
     * @param DirectoryList $directoryList
     * @param RunFactory $runFactory
     * @param RunResourceFactory $runResourceFactory
     * @param MailFactory $mailFactory
     */
    public function __construct(
        protected File $file,
        protected Registry $registryHelper,
        protected Data $helper,
        protected LoggerInterface $logging,
        protected DirectoryList $directoryList,
        protected RunFactory $runFactory,
        protected RunResourceFactory $runResourceFactory,
        protected MailFactory $mailFactory
    ) {
    }

    /**
     * Prepares the task, before it runs.
     *
     * @return void
     * @throws \Exception
     */
    abstract protected function prepare(): void;

    /**
     * Runs the task and returns whether it was successful.
     *
     * @return bool
     * @throws \Exception
     */
    abstract protected function runTask(): bool;

    /**
     * Cleans up after the task ran, also if it failed.
     *
     * @param bool $success
     *
     * @return void
     * @throws \Exception
     */
    abstract protected function dismantle(bool $success): void;

    /**
     * Returns whether the run had nothing to do, for example no file to import.
     *
     * @return bool
     */
    abstract public function isEmptyRun(): bool;

    /**
     * Initializes the task with its settings and registers it as the running task for the loggers.
     *
     * @param string $storeCode
     * @param string $taskName
     * @param string $taskId
     * @param string|null $logLevel the configured log level, if empty
     * @param bool $console
     * @param bool $test
     *
     * @return void
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

        $maxMemory = $this->stringValue($this->getTaskSetting('max_memory'));

        if (is_numeric($maxMemory) && (int)$maxMemory > 0) {
            // phpcs:ignore Magento2.Functions.DiscouragedFunction -- the memory limit of the task is configurable
            ini_set('memory_limit', sprintf('%dM', (int)$maxMemory));
        }

        // the tasks this task depends on are a setting of the task only
        $dependencies = $this->stringValue($this->getTaskSetting('depends_on', null, false, true));

        $this->dependencyList = array_values(array_filter(array_map('trim', explode(';', $dependencies))));

        $this->waitForPredecessor = (bool)$this->getTaskSetting('wait_for_predecessor', false, true);

        if ($logLevel === null || $logLevel === '') {
            $logLevel = $this->stringValue($this->getConfigValue('logging', 'log_level', LogLevel::INFO));
        }

        $values = [
            'current_task_name'              => $taskName,
            'current_task_id'                => $taskId,
            'current_task_log_level'         => $logLevel,
            'current_task_log_warn_as_error' => (bool)$this->getConfigValue('logging', 'log_warn_as_error', true, true),
            'current_task_console'           => $console
        ];

        foreach ($values as $key => $value) {
            $this->registryHelper->register($key, $value, false, true);
        }
    }

    /**
     * Remembers the registered running task, which is restored after this task, for tasks that start other tasks.
     *
     * @return void
     */
    protected function storeRegistryValues(): void
    {
        foreach (self::REGISTRY_KEYS as $key) {
            $this->registryValues[ $key ] = $this->registryHelper->registry($key);
        }
    }

    /**
     * Restores the registered running task.
     *
     * @return void
     */
    protected function resetRegistryValues(): void
    {
        foreach ($this->registryValues as $key => $value) {
            $this->registryHelper->register($key, $value, false, true);
        }
    }

    /**
     * Runs the task: locks it, prepares, runs and dismantles it, sends the summaries and saves the run.
     *
     * Errors of the task are logged and make the run unsuccessful; the run is always finished.
     *
     * @return bool
     * @throws LocalizedException
     * @throws FileSystemException
     */
    public function launch(): bool
    {
        if (! $this->allowAdminStore && strcasecmp(trim($this->storeCode), 'admin') === 0) {
            throw new LocalizedException(__('Task is not allowed to run with admin store.'));
        }

        // the peak memory usage of this run, not of the process before it; PHP 8.1 has no reset, there the peak of the
        // process is reported
        if (function_exists('memory_reset_peak_usage')) {
            // phpcs:ignore PHPCompatibility.FunctionUse.NewFunctions -- only called where it exists (PHP 8.2 or newer)
            memory_reset_peak_usage();
        }
        $start = time();

        $run = $this->runFactory->create();
        $run->start($this->storeCode, $this->taskName, $this->taskId, $this->test);
        $this->saveRun($run);
        $this->run = $run;

        if ($this->isTest()) {
            $this->logging->info(__('Task is running in test mode')->render());
        }

        try {
            $lock = $this->lock();
        } catch (FileSystemException $exception) {
            $this->logException(__('Could not lock the task because: %1', $exception->getMessage()), $exception);

            $lock = null;
        }

        $success = $lock !== null;

        if ($lock !== null) {
            $success = $this->runSteps();
            $this->sendSummaries();
            $this->unlock($lock);
        }

        $this->logging->info(
            __('Duration: %1 minute(s), %2 second(s)', intdiv(time() - $start, 60), (time() - $start) % 60)->render()
        );

        $maxMemoryUsage = (int)round(memory_get_peak_usage(true) / (1024 * 1024));

        $this->logging->info(__('Max memory usage: %1 MB', $maxMemoryUsage)->render());

        $run->finish($maxMemoryUsage, $success, $this->isEmptyRun());
        $this->saveRun($run);

        $this->resetRegistryValues();

        return $success;
    }

    /**
     * Saves the record of the run. A record that cannot be saved is logged, but does not stop the task.
     *
     * @param Run $run
     *
     * @return void
     */
    private function saveRun(Run $run): void
    {
        try {
            $this->runResourceFactory->create()->save($run);
        } catch (Throwable $exception) {
            $this->logException(__('Could not save the run because: %1', $exception->getMessage()), $exception);
        }
    }

    /**
     * Prepares, runs and dismantles the task, and logs every error.
     *
     * @return bool
     */
    private function runSteps(): bool
    {
        try {
            $this->prepare();
        } catch (Throwable $exception) {
            $this->logException(__('Could not prepare task because: %1', $exception->getMessage()), $exception);

            return $this->dismantleTask(false);
        }

        try {
            $this->logging->info(__('Running task: %1', $this->getTaskName())->render());

            $success = $this->runTask();

            $this->logging->info(__('Finished task: %1', $this->getTaskName())->render());
        } catch (Throwable $exception) {
            $this->logException(__('Could not run task because: %1', $exception->getMessage()), $exception);

            $success = false;
        }

        return $this->dismantleTask($success);
    }

    /**
     * Dismantles the task and returns whether the run was successful.
     *
     * @param bool $success
     *
     * @return bool
     */
    private function dismantleTask(bool $success): bool
    {
        try {
            $this->dismantle($success);
        } catch (Throwable $exception) {
            $this->logException(__('Could not dismantle task because: %1', $exception->getMessage()), $exception);
        }

        return $success;
    }

    /**
     * Sends the success and the error summary.
     *
     * @return void
     */
    private function sendSummaries(): void
    {
        foreach ([static::SUMMARY_TYPE_SUCCESS, static::SUMMARY_TYPE_ERROR] as $type) {
            try {
                $this->sendSummary($type);
            } catch (Throwable $exception) {
                $this->logException(
                    __('Could not send summary of type: %1 because: %2', $type, $exception->getMessage()),
                    $exception
                );
            }
        }
    }

    /**
     * Logs the message and the exception with its trace.
     *
     * @param Phrase $message
     * @param Throwable $exception
     *
     * @return void
     */
    private function logException(Phrase $message, Throwable $exception): void
    {
        $this->logging->error($message->render());
        $this->logging->error($exception);
    }

    /**
     * Locks the task; null if a task it depends on runs, or another run of this task (unless it waits for it).
     *
     * @return resource|null
     * @throws FileSystemException
     */
    private function lock()
    {
        foreach ($this->dependencyList as $dependency) {
            $dependencyLock = $this->openLockFile($dependency);
            $locked = $this->tryLock($dependencyLock, LOCK_EX | LOCK_NB);

            if ($locked) {
                $this->file->fileUnlock($dependencyLock);
            }

            $this->file->fileClose($dependencyLock);

            if (! $locked) {
                $this->logBlocked($dependency);

                return null;
            }
        }

        $lock = $this->openLockFile($this->taskName);

        if (! $this->tryLock($lock, $this->waitForPredecessor ? LOCK_EX : LOCK_EX | LOCK_NB)) {
            $this->logBlocked($this->taskName);
            $this->file->fileClose($lock);

            return null;
        }

        return $lock;
    }

    /**
     * Tries to lock a lock file; a non-blocking lock fails if another process holds it.
     *
     * @param resource $lock
     * @param int $lockMode
     *
     * @return bool
     */
    private function tryLock($lock, int $lockMode): bool
    {
        try {
            return $this->file->fileLock($lock, $lockMode);
        } catch (FileSystemException) {
            return false;
        }
    }

    /**
     * Logs that a running task blocks this task.
     *
     * @param string $taskName
     *
     * @return void
     */
    private function logBlocked(string $taskName): void
    {
        $this->logging->error(
            __(
                'The task: %1 is still running and blocks the process of this task: %2.',
                $taskName,
                $this->taskName
            )->render()
        );
    }

    /**
     * Releases the lock of the task; an error is logged, the lock ends with the process anyway.
     *
     * @param resource $lock
     *
     * @return void
     */
    private function unlock($lock): void
    {
        try {
            $this->file->fileUnlock($lock);
            $this->file->fileClose($lock);
        } catch (FileSystemException $exception) {
            $this->logException(__('Could not unlock the task because: %1', $exception->getMessage()), $exception);
        }
    }

    /**
     * Opens the lock file of a task, var/tmp/task_<task name>.lock, and creates it, if it does not exist.
     *
     * Mode "c" keeps an existing file, so that two runs can open it at the same time.
     *
     * @param string $taskName
     *
     * @return resource
     * @throws FileSystemException
     */
    private function openLockFile(string $taskName)
    {
        $tempPath = $this->directoryList->getPath(DirectoryList::TMP);

        $this->file->createDirectory($tempPath);

        return $this->file->fileOpen(
            sprintf('%s/task_%s.lock', $tempPath, preg_replace('/[^A-Za-z0-9_.-]/', '_', $taskName)),
            'c'
        );
    }

    /**
     * Runs the task from the admin.
     *
     * @param string $storeCode
     * @param string $taskName
     * @param bool $test
     *
     * @return bool
     * @throws LocalizedException
     * @throws FileSystemException
     */
    public function launchFromAdmin(string $storeCode, string $taskName, bool $test = false): bool
    {
        $this->init($storeCode, $taskName, date('Y-m-d_H-i-s'), null, false, $test);

        return $this->launch();
    }

    /**
     * Sends the summary of the type by mail, if it is enabled and has records.
     *
     * @param string $type
     *
     * @return void
     * @throws MailException
     */
    protected function sendSummary(string $type): void
    {
        $section = 'summary_' . $type;

        if (! $this->getConfigValue($section, 'send', false, true) || $this->getSummary($type) === '') {
            return;
        }

        $subject = $this->stringValue($this->getConfigValue($section, 'subject'));
        $recipients = $this->getAddresses($section, 'recipients');

        if ($subject === '') {
            $subject = __('Task: %1 | Summary: %2', $this->taskName, $type)->render();
        }

        $storeDefaultTitle = $this->stringValue($this->helper->getStoreConfig('design/head/default_title'));

        if ($storeDefaultTitle !== '') {
            $subject = $storeDefaultTitle . ' - ' . $subject;
        }

        $sender = $this->stringValue($this->getConfigValue($section, 'sender', 'general'));
        $senderEmail = $this->stringValue($this->helper->getStoreConfig('trans_email/ident_' . $sender . '/email'));
        $senderName = $this->stringValue($this->helper->getStoreConfig('trans_email/ident_' . $sender . '/name'));

        if ($senderEmail === '') {
            $this->logging->error(
                __('Could not send summary of type: %1 because no sender was configured', $type)->render()
            );

            return;
        }

        if ($this->isProhibitSummarySending($type)) {
            $this->logging->debug(
                __(
                    'Suppress sending summary of type: %1 with subject: %2 to recipients: %3',
                    $type,
                    $subject,
                    implode(', ', $recipients)
                )->render()
            );

            return;
        }

        if (count($recipients) === 0) {
            $this->logging->error(
                __('Could not send summary of type: %1 because no recipients were configured', $type)->render()
            );

            return;
        }

        $mail = $this->mailFactory->create();

        $mail->setSubject($subject);
        $mail->setBody($this->getSummary($type, true));
        $mail->addSender($senderEmail, $senderName === '' ? null : $senderName);

        foreach ($recipients as $recipient) {
            $mail->addReceiver($recipient);
        }

        foreach ($this->getAddresses($section, 'copy_recipients') as $recipient) {
            $mail->addCopyReceiver($recipient);
        }

        foreach ($this->getAddresses($section, 'blind_copy_recipients') as $recipient) {
            $mail->addBlindCopyReceiver($recipient);
        }

        $mail->addAdditionalHeader('x-task-name', $this->getTaskName());
        $mail->addAdditionalHeader('x-task-id', $this->getTaskId());
        $mail->addAdditionalHeader('x-summary-type', $type);

        $this->logging->debug(
            __(
                'Sending summary of type: %1 with subject: %2 to recipients: %3',
                $type,
                $subject,
                implode(', ', array_keys($mail->getReceivers() + $mail->getCopyReceivers()))
            )->render()
        );

        $mail->send();
    }

    /**
     * Returns the e-mail addresses of a setting, which are separated by semicolons.
     *
     * @param string $section
     * @param string $field
     *
     * @return string[]
     */
    private function getAddresses(string $section, string $field): array
    {
        return array_values(
            array_filter(array_map('trim', explode(';', $this->stringValue($this->getConfigValue($section, $field)))))
        );
    }

    /**
     * Returns whether the task runs in test mode.
     *
     * @return bool
     */
    public function isTest(): bool
    {
        return $this->test;
    }

    /**
     * Returns the summary of the type as text, one record per line.
     *
     * @param string $type
     * @param bool $addSummaryInformation adds the name and the ID of the task at the beginning
     *
     * @return string
     */
    public function getSummary(string $type = self::SUMMARY_TYPE_ALL, bool $addSummaryInformation = false): string
    {
        $lines = [];

        if ($addSummaryInformation && count($this->getSummaryRecords($type)) > 0) {
            foreach ($this->getSummaryInformation() as $record) {
                $lines[] = trim($record->getMessage());
            }

            $lines[] = '';
        }

        foreach ($this->getSummaryRecords($type) as $record) {
            $lines[] = trim($record->getMessage());
        }

        return trim(implode("\n", $lines));
    }

    /**
     * Returns the records of the summary of the type.
     *
     * @param string $type
     * @param bool $addSummaryInformation adds the name and the ID of the task at the beginning
     *
     * @return Record[]
     */
    public function getSummaryRecords(string $type = self::SUMMARY_TYPE_ALL, bool $addSummaryInformation = false): array
    {
        $summary = $this->registryHelper->registry('task_summary_' . $type);

        if (! $summary instanceof SummaryInterface) {
            return [];
        }

        $records = $summary->getRecords($this->taskName, $this->taskId);

        return $addSummaryInformation ? array_merge($this->getSummaryInformation(), $records) : $records;
    }

    /**
     * Returns a configuration value of the task.
     *
     * The value of the general task settings, if the task does not overwrite them.
     *
     * @param string $section
     * @param string $field
     * @param mixed $defaultValue
     * @param bool $isFlag
     * @param bool $forceTaskConfigValue
     *
     * @return mixed
     */
    public function getConfigValue(
        string $section,
        string $field,
        mixed $defaultValue = null,
        bool $isFlag = false,
        bool $forceTaskConfigValue = false
    ): mixed {
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
     * Returns the name and the ID of the task as records.
     *
     * @return Record[]
     */
    protected function getSummaryInformation(): array
    {
        return [
            new Record(LogLevel::INFO, __('Task Name: %1', $this->taskName)->render()),
            new Record(LogLevel::INFO, __('Task Id: %1', $this->taskId)->render())
        ];
    }

    /**
     * Returns a setting of the task.
     *
     * @param string $field
     * @param mixed $defaultValue
     * @param bool $isFlag
     * @param bool $forceTaskConfigValue
     *
     * @return mixed
     */
    protected function getTaskSetting(
        string $field,
        mixed $defaultValue = null,
        bool $isFlag = false,
        bool $forceTaskConfigValue = false
    ): mixed {
        return $this->getConfigValue('settings', $field, $defaultValue, $isFlag, $forceTaskConfigValue);
    }

    /**
     * Switches the test mode.
     *
     * @param bool $test
     *
     * @return void
     */
    public function setTestMode(bool $test = true): void
    {
        $this->test = $test;
    }

    /**
     * Returns whether sending the summary of the type is suppressed.
     *
     * @param string $type
     *
     * @return bool
     */
    protected function isProhibitSummarySending(string $type): bool
    {
        return $this->prohibitSummarySending[ $type ] ??
            ($this->prohibitSummarySending[ static::SUMMARY_TYPE_ALL ] ?? false);
    }

    /**
     * Suppresses sending the summary of the type, or of all types.
     *
     * @param string $type
     * @param bool $prohibitSummarySending
     *
     * @return void
     */
    public function setProhibitSummarySending(
        string $type = self::SUMMARY_TYPE_ALL,
        bool $prohibitSummarySending = true
    ): void {
        $this->prohibitSummarySending[ $type ] = $prohibitSummarySending;
    }

    /**
     * Returns the name of the task.
     *
     * @return string
     */
    public function getTaskName(): string
    {
        return $this->taskName;
    }

    /**
     * Returns the ID of the task.
     *
     * @return string
     */
    public function getTaskId(): string
    {
        return $this->taskId;
    }

    /**
     * Returns the code of the store the task runs with.
     *
     * @return string
     */
    public function getStoreCode(): string
    {
        return $this->storeCode;
    }

    /**
     * Allows or forbids running the task with the admin store.
     *
     * @param bool $allowAdminStore
     *
     * @return void
     */
    public function setAllowAdminStore(bool $allowAdminStore): void
    {
        $this->allowAdminStore = $allowAdminStore;
    }

    /**
     * Adds the summaries of a task that this task started to the summaries of this task.
     *
     * @param Base $task
     *
     * @return void
     */
    public function addSummaryFromTask(Base $task): void
    {
        foreach ([static::SUMMARY_TYPE_ALL, static::SUMMARY_TYPE_SUCCESS, static::SUMMARY_TYPE_ERROR] as $type) {
            $summary = $this->registryHelper->registry('task_summary_' . $type);

            if ($summary instanceof SummaryInterface) {
                foreach ($task->getSummaryRecords($type) as $record) {
                    $summary->addRecord($this->taskName, $this->taskId, $record);
                }
            }
        }
    }

    /**
     * Returns the run of the task, after it was launched.
     *
     * @return Run
     */
    public function getRun(): Run
    {
        if ($this->run === null) {
            throw new LogicException('The task was not launched');
        }

        return $this->run;
    }

    /**
     * Returns a configuration value as string.
     *
     * @param mixed $value
     *
     * @return string
     */
    private function stringValue(mixed $value): string
    {
        return is_scalar($value) ? (string)$value : '';
    }
}
