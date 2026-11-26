# Changelog

## 3.0.1

### Changed

- Supports PHP 8.1 again, like Magento 2.4.6: the Adobe Commerce Marketplace requires modules to allow every PHP
  version of the Magento versions they support.
- On PHP 8.1, the peak memory usage of a run includes the memory the process used before it, because PHP 8.1 cannot
  reset it (`memory_reset_peak_usage()` exists since PHP 8.2).

## 3.0.0

### Breaking changes

- Requires Magento 2.4.6 or newer and PHP 8.2 or newer. Magento 2.4.4 and 2.4.5 and PHP 7.1 to 8.1 are no longer
  supported; use version 2.x for these.
- Requires `infrangible/m2-backend-widget` 4.0, `infrangible/m2-core` 5.0, `infrangible/m2-foundation` 3.0,
  `infrangible/m2-log` 3.0, `infrangible/m2-simple-mail` 2.0 and `fewedev/base` 2.4.
- The loggers of tasks are PSR-3 loggers without Monolog (`Infrangible\Task\Logger\*` instead of
  `Infrangible\Task\Logger\Monolog\*`). Log lines read `[date] LEVEL: message`, and the records of the summaries
  contain the message only.
- All methods have native types, and the constructors use constructor property promotion. Changed constructors:
  `Controller\Adminhtml\Run` (the emulation instead of its factory, a locale validator), `Task\Files` (without the
  unused transport builder, with Magento's file driver), the scripts of the console commands (the store helper and
  `Helper\Values`),
  `Helper\Data` (no longer a Magento helper).
- `Task\Base` no longer implements `Magento\Framework\AppInterface`. `getSummary()` returns the summary as text,
  `getSummaryRecords()` as records; `launch()` throws `LocalizedException` and `FileSystemException` only.
- The table `task_run` is created by declarative schema (`db_schema.xml`); a table `job_run` of version 1 is no longer
  renamed.

### Fixed

- Tasks can be installed and run with Magento 2.4.8 and newer, which use Monolog 3; before, the installation of Magento
  failed with a fatal error.
- A task no longer runs twice at the same time when a second run starts while the first one takes its lock, and lock
  files that are created by two runs at the same time no longer fail the run.
- Errors of PHP in a task (for example a `TypeError`) fail the run like exceptions; before, the run stayed "running"
  and no summary was sent.
- `bin/magento task:<task name>` fails if the task failed; before, it always succeeded.
- Cron jobs of tasks without summary no longer fail with a `TypeError`.
- The setting "Overwrite Task General" applies to the settings group (max memory, wait for predecessor, suppress empty
  mails) as well; before, the values of the task were always used.
- Runs whose process ended are shown as broken; before, they were shown as finished. They are found without
  `shell_exec` now, and marked as unsuccessful.
- The memory usage of a run is the peak memory of PHP in MB; before, it was the difference of the memory at start and
  end, read with `shell_exec`.
- The error page of a task shows why the task could not run; before, it failed, because its block had another name
  in the layout. It is shown to admin users who may run the task; before, users with restricted roles were denied.
- A lock file that cannot be opened (for example with other file permissions) fails the run with the reason; before,
  the run stayed "running".
- The log view of a run shows why a log file cannot be read; before, the page failed.
- The parameter `test=0` no longer runs a task in test mode.
- The link of the result message leads back to the page that ran the task; before, it led to the route of another
  project.
- The environment emulation of the store ends when a task fails in the admin.
- The fields of the task groups keep their IDs when they are sorted, and the summary fields keep their dependency on
  "Send" when a task overwrites the general settings.

### Security

- The back button of a run only accepts admin routes and escapes the URL; before, a crafted link could run script in
  the admin.
- The locale of the request is validated before it is used.
- The error page shows the message of the error, no longer its stack trace.
- Log files of a task ID with slashes (option `--id`) stay in the log directory.

### Added

- Indexes on the start and finish date of runs, which the cron jobs filter by.
- Translations into 24 locales, also of the log levels.

### Changed

- The module is loaded after the Infrangible modules it uses, Magento_Config and Magento_Cron.
- Checks with PHPStan level 9, the Magento coding standard and unit tests on every push.
