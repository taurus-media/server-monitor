# Server Monitor
Server Monitor facilitates an easy of use scripts for the purpose of monitoring VPSs. Each script available can be triggered periodically on the target VPS. Each individual script is designed to work on any VPS that has PHP available. 

## Requirements
* You must be running at least PHP 7.2

## Installation
Clone the repository
```sh
git clone git@github.com:NivanoHQ/server-monitor.git
```

Install composer dependencies
```sh
composer install --no-dev
```

### Environment file
Copy the example file
```sh
cp .env.example .env
```

**Variables explained:**
* `DEBUG_MODE` Enable or disable error reporting (useful for testing locally) (`true` or `false`)
* `SENTRY_DSN`  Sentry DSN integration, so that error events are sent to Sentry
* `PROJECT_NAME` Displayed in Sentry along-side with the recorded event
* `YOUTRACK_PROJECT_CODE` Displayed in Sentry along-side with the recorded event

## Usage
The path to `bin/console.php` needs to be updated relative to the storage location of the server monitor project respectively.

```sh
# List all available commands
/usr/bin/php bin/console.php list

# List all cronjobs
/usr/bin/php bin/console.php cronjob:list

# Find out how to run cronjobs
/ussr/bin/php bin/console.php cronjob:run -h
```

## CronJobs

### Lock Collector
Sometimes Magento 2 CronJobs leave stale lock files in the home directory on a Hypernode. There is no clear evidence to why this happens, but to be notified about this, we built a new CronJob that does the following:
* Search for `.lock` files in the specified directory and remove them if they are over 30 minutes old
* Create a message event in Sentry which lets us know that a lock has been collected.

**How to use**
* Parameters are separated by comma, first parameter is the path to the directory in which you wish to scan for .lock files. The second parameter specifies old the file must be in minutes.

Enable a cronjob for lock files older than `30 minutes` like this:
```sh
*/5 * * * * /usr/bin/php bin/console.php cronjob:run LockCollector /path/to/scan/directory
```

You are also able to specify a custom max-age `2 hours` like this:
```sh
*/5 * * * * /usr/bin/php bin/console.php cronjob:run LockCollector /path/to/scan/directory,120
```
