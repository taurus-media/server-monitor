# Server Monitor

A small set of console checks for monitoring a VPS (e.g. a Hypernode). Each check is a separate command that is meant to be scheduled with cron. Detected issues are printed to the shell and, when requested, sent to Slack or Sentry.

- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
- [Commands](#commands)
  - [check:cpu](#checkcpu)
  - [check:memory](#checkmemory)
  - [check:large-files](#checklarge-files)
  - [check:cron](#checkcron)
  - [check:cron-job-execution](#checkcron-job-execution)
  - [cron:install](#croninstall)
- [Scheduling with cron](#scheduling-with-cron)
- [Notifications](#notifications)
- [Development](#development)

## Requirements

- PHP 8.2 or newer with the `curl` extension
- Composer
- `free` (package `procps`), used by `check:memory`
- The `pdo_mysql` extension and a `~/.my.cnf` with MySQL credentials, used by `check:cron-job-execution`
- For notifications, one of:
  - outbound HTTPS access to `hooks.slack.com` to post to Slack
  - a Sentry project

## Installation

```sh
cd ~
git clone https://github.com/taurus-media/server-monitor.git taurus-monitoring
cd taurus-monitoring
composer install --no-dev
cp .env.example .env
```

Edit `.env` (see [Configuration](#configuration)), then verify the installation:

```sh
php bin/console list check
```

Finally, set up automated monitoring by adding all checks to the crontab (see [Scheduling with cron](#scheduling-with-cron)):

```sh
php bin/console cron:install
```

## Configuration

All settings live in the `.env` file in the project root.

| Variable                | Required | Default                       | Description                                                                                                                                                 |
|-------------------------|----------|-------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `YOUTRACK_PROJECT_CODE` | yes      |                               | YouTrack project code, included in notifications.                                                                                                           |
| `MAGENTO_ROOT`          | yes      | `/data/web/magento2/current`  | Absolute path to the Magento root.                                                                                                                          |
| `DB_NAME`               | yes      |                               | Magento database name. Host, user and password are read from the `[client]` section of `~/.my.cnf`.                                                         |
| `NOTIFIER`              | no       | `slack`                       | Where `--notify` sends issues to: `slack` or `sentry`.                                                                                                      |
| `SLACK_WEBHOOK_URL`     | no       |                               | [Slack incoming webhook](https://api.slack.com/messaging/webhooks) URL of the channel, stored in the password manager. Required only when `NOTIFIER=slack`. |
| `SENTRY_DSN`            | no       |                               | Sentry DSN (`https://...`). Required only when `NOTIFIER=sentry`.                                                                                           |

## Usage

```sh
php bin/console <command> [arguments] [options]
```

```sh
# List all checks
php bin/console list check

# Show the arguments and options of a check
php bin/console check:large-files --help
```

Every check behaves the same way:

- Without options, it only **prints** the result to the shell (`No issues detected.` or one line per issue).
- With `--notify`, detected issues are **also sent** to the configured [notifier](#notifications). Nothing is sent when there are no issues.
- The exit code is `0` when the check ran, whether or not issues were found. A non-zero exit code means the check itself failed (e.g. an invalid argument or a missing directory).

Standard Symfony Console options such as `--help`, `--quiet` and `-v` are available on every command.

## Commands

### check:cpu

Reports when the 15 minute load average exceeds a percentage of the available CPUs. The current load is always printed.

```sh
php bin/console check:cpu [--threshold=THRESHOLD] [--notify]
```

| Option        | Default | Description                                                    |
|---------------|---------|----------------------------------------------------------------|
| `--threshold` | `85`    | Load threshold in percent of the available CPUs (`1`–`1000`).  |
| `--notify`    |         | Send detected issues to the configured notifier.               |

```console
$ php bin/console check:cpu
CPU load (15 min average): 0.2% (load 0.02 on 10 CPUs, threshold 85%)
No issues detected.
```

### check:memory

Samples RAM usage once per second for 60 seconds and reports when the average exceeds the threshold. The current usage is always printed. Requires `/usr/bin/free`.

```sh
php bin/console check:memory [--threshold=THRESHOLD] [--notify]
```

| Option        | Default | Description                                  |
|---------------|---------|----------------------------------------------|
| `--threshold` | `95`    | RAM usage threshold in percent (`1`–`100`).  |
| `--notify`    |         | Send detected issues to the configured notifier. |

```console
$ php bin/console check:memory
Sampling RAM usage for 60 seconds...
RAM usage (1 min average): 37.4% (threshold 95%)
No issues detected.
```

### check:large-files

Recursively scans a directory and reports files larger than the threshold, e.g. forgotten backups, database dumps or oversized logs. Files are listed from largest to smallest. Symlinks are not followed and unreadable directories are skipped.

```sh
php bin/console check:large-files [<path>] [--threshold=THRESHOLD] [--notify]
```

| Argument | Default     | Description                        |
|----------|-------------|------------------------------------|
| `path`   | `/data/web` | Directory to scan (recursive).     |

| Option        | Default | Description                                                                   |
|---------------|---------|-------------------------------------------------------------------------------|
| `--threshold` | `100M`  | Report files larger than this size. Accepts bytes or `K`, `M`, `G`, `T` units (e.g. `500K`, `200Mb`, `1.5G`). |
| `--notify`    |         | Send detected issues to the configured notifier.                              |

```console
$ php bin/console check:large-files
Files larger than 100 MB in /data/web: 2
Large file (1.4 GB) found: /data/web/magento2/backup.sql.gz for LLTQ
Large file (312.5 MB) found: /data/web/magento2/current/var/log/system.log for LLTQ
```

### check:cron

Magento writes to `<MAGENTO_ROOT>/var/log/cron.log` on every cron run. This check reports when the file hasn't been updated for longer than the threshold, which usually means the Magento cron is stuck or not running. A missing `cron.log` is reported as well. The time since the last update is always printed. Requires `MAGENTO_ROOT` in `.env`.

```sh
php bin/console check:cron [--threshold=THRESHOLD] [--notify]
```

| Option        | Default | Description                                                  |
|---------------|---------|--------------------------------------------------------------|
| `--threshold` | `10`    | Report when `cron.log` is older than this many minutes.      |
| `--notify`    |         | Send detected issues to the configured notifier.             |

```console
$ php bin/console check:cron
Magento cron.log last updated 42 min ago (threshold 10 min)
Magento cron seems stuck: /data/web/magento2/current/var/log/cron.log last updated 42 minutes ago for LLTQ
```

### check:cron-job-execution

Reports Magento cron jobs from the `cron_schedule` table, started (`executed_at`) within the last 24 hours, whose run (`finished_at` − `executed_at`) took longer than the threshold. Runs are grouped by `job_code`, and each matching job is reported with the number of slow runs and the longest duration. Requires `DB_NAME` in `.env` and MySQL credentials in `~/.my.cnf`.

Magento only keeps a limited cron history (see *Stores > Configuration > Advanced > System > Cron*), so the check covers at most that period.

```sh
php bin/console check:cron-job-execution [--threshold=THRESHOLD] [--notify]
```

| Option        | Default | Description                                               |
|---------------|---------|-----------------------------------------------------------|
| `--threshold` | `120`   | Report cron jobs that ran longer than this many seconds.  |
| `--notify`    |         | Send detected issues to the configured notifier.          |

```console
$ php bin/console check:cron-job-execution
Magento cron jobs running longer than 120 sec in the last 24h: 2
Magento cron job indexer_reindex_all_invalid ran longer than 120 sec 2 time(s), max 300 sec, for LLTQ
Magento cron job sales_clean_quotes ran longer than 120 sec 1 time(s), max 200 sec, for LLTQ
```

### cron:install

Adds all checks with `--notify` to the crontab of the current user. Checks that are already scheduled (an active, not commented out, line running `bin/console <command>`) are reported and left untouched, so the command is safe to run repeatedly.

```sh
php bin/console cron:install
```

Checks are scheduled with their default arguments and options. To change them (e.g. the `check:large-files` path or threshold), edit the crontab afterwards.

```console
$ php bin/console cron:install
check:cpu added to the crontab.
check:memory added to the crontab.
check:cron is already added to the crontab.
check:cron-job-execution added to the crontab.
check:large-files added to the crontab.
```

## Scheduling with cron

Run [`cron:install`](#croninstall) to add the checks below to the crontab. To schedule them manually, use absolute paths, as cron does not run from the project directory, and remember `--notify`, otherwise issues are only written to the cron output.

```cron
# Server Monitor
*/15 * * * * '/usr/bin/php' '/data/web/taurus-monitoring/bin/console' check:cpu --notify
*/15 * * * * '/usr/bin/php' '/data/web/taurus-monitoring/bin/console' check:memory --notify
*/10 * * * * '/usr/bin/php' '/data/web/taurus-monitoring/bin/console' check:cron --notify
0 7 * * * '/usr/bin/php' '/data/web/taurus-monitoring/bin/console' check:cron-job-execution --notify
0 3 * * * '/usr/bin/php' '/data/web/taurus-monitoring/bin/console' check:large-files --notify
```

## Notifications

The notifier is selected with `NOTIFIER` in `.env` and is only used when a command runs with `--notify`.

| Notifier          | Behaviour                                                                                                                  |
|-------------------|----------------------------------------------------------------------------------------------------------------------------|
| `slack` (default) | Posts one message per run to `SLACK_WEBHOOK_URL`, titled `[<YOUTRACK_PROJECT_CODE>] <command>`. The message lists all issues.                   |
| `sentry`          | Creates one Sentry message per issue, tagged with the command name and the project context.                                |

If Slack messages don't arrive, check that the server can reach `hooks.slack.com` over HTTPS and that the webhook hasn't been revoked. A failed request makes the command exit with an error.

## Development

```sh
composer install

# Static analysis
vendor/bin/phpstan analyse

# Code style
vendor/bin/php-cs-fixer fix --config=.php_cs.dist.php
```

To add a check, create a command in `src/Console/Command/Check/` that extends `AbstractMonitorCommand`, set its name with `#[AsCommand(name: 'check:...')]` and implement `check()`, which returns the list of detected issues. `bin/console` registers it automatically. To schedule it, add it to `Install::JOBS` in `src/Console/Command/Cron/Install.php`.
