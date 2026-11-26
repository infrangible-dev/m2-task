# Infrangible Task

Base of background tasks of Infrangible modules, for example product feeds or sitemaps: a task runs from the admin, by
cron or on the console, keeps a record of every run, writes log files and sends summaries by mail.

## Runs

Infrangible Solutions > Tasks > Runs lists every run with store, task name, ID, status (running, finished, broken),
success, start and finish date, duration and memory usage. A run shows its log file and its error log file.

- A run is **broken** if its process ended without finishing it, for example because it was killed. The cron job
  `check_tasks` finds these runs every minute (on servers with `/proc`).
- The cron job `clean_tasks` deletes runs that started more than 90 days ago, with their log files.
- The log files are in `var/log/task/<task name>/<store code>/<task ID>.log` and `.err`.

## Configuration

Each task has a section in the configuration tab "Tasks" (`task_<task name>`); "General" (`task_general`) holds the
values that all tasks use unless they overwrite them ("Overwrite Task General" in each group).

| Group           | Fields                                                                                         |
|-----------------|------------------------------------------------------------------------------------------------|
| Settings        | Max Memory (MB), Wait for Predecessor, Suppress Empty Mails, Depends On (tasks only)           |
| Logging         | Log Level, Log Warning as Error                                                                 |
| Success Summary | Send, Sender, Recipients, Copy Recipients, Blind Copy Recipients, Subject (separated by `;`)   |
| Error Summary   | the same fields for the errors                                                                  |

- A task runs only once at the same time. Another run fails, unless "Wait for Predecessor" is enabled; then it waits.
- A task does not run while one of the tasks of "Depends On" (separated by `;`) runs.
- The error log and the error summary contain the errors whatever log level is set, and the warnings, if "Log
  Warning as Error" is enabled.

## Writing a task

A task extends `Infrangible\Task\Task\Base` and implements four methods:

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Task;

use Infrangible\Task\Task\Base;

class Export extends Base
{
    private bool $emptyRun = true;

    protected function prepare(): void
    {
        // check the settings, open connections
    }

    protected function runTask(): bool
    {
        $this->logging->info('Exporting');

        // the work; the result is the success of the run
        return true;
    }

    protected function dismantle(bool $success): void
    {
        // clean up, also after errors
    }

    public function isEmptyRun(): bool
    {
        return $this->emptyRun;
    }
}
```

- Everything the task logs with `$this->logging` (Magento's logger) goes into the log files of the run, the summaries
  and, on the console, the output.
- Errors and exceptions of the task make the run unsuccessful; the run is always dismantled and finished.
- `getTaskSetting()` and `getConfigValue()` read the configuration of the task, `isTest()` tells the test mode.
- `Infrangible\Task\Task\File` imports the files of the setting `path` (optionally matching the regular expression
  `file_pattern`) one by one with `importFile()` and moves them into `archive_path` or `error_path` afterwards.

### Running a task

- **Admin:** a controller extends `Infrangible\Task\Controller\Adminhtml\Run` and returns the task name, the task
  class and the ACL resource of the task (`getTaskName()`, `getClassName()`, `getTaskResourceId()`). Admin users
  need the resource `Infrangible_Task::infrangible_task` and the resource of the task. Parameters: `store_code`,
  `test`, `locale` and `backend_user` (the locale of the admin user). Without redirect path (`getRedirectPath()`),
  the controller shows the summary.
- **Cron:** a class extends `Infrangible\Task\Cron\Base` (one task) or `Infrangible\Task\Cron\TaskList` (several
  tasks); a failed task fails the cron job with the summary. `Infrangible\Task\Cron\Execution\Base` is the backend
  model of a configuration field with the cron expression of the task.
- **Console:** a command extends `Infrangible\Task\Console\Command\Task` with a script that extends
  `Infrangible\Task\Console\Command\Script\Task` (or `TaskList`):

```bash
bin/magento task:<task name> --store_code=default,second --log_level=debug --console --test --id=my-run
```

The command fails if the task failed with one of the stores.

## Requirements

- Magento Open Source or Adobe Commerce 2.4.6 or newer
- PHP 8.1 or newer

## Installation

```bash
composer require infrangible/m2-task
bin/magento module:enable Infrangible_Task
bin/magento setup:upgrade
```

In production mode, also run `bin/magento setup:di:compile` and `bin/magento setup:static-content:deploy`.

## License

Infrangible Task is licensed under the MIT License - see the LICENSE file for details.
