# Lock Collector
Sometimes Magento 2 CronJobs leave stale lock files in the home directory on a Hypernode. There is no clear evidence to why this happens, but to be notified about this, we built a new CronJob that does the following:
* Search for `.lock` files in the specified directory and remove them if they are over 30 minutes old
* Create a message event in Sentry which lets us know that a lock has been collected.

## Requirements
* You must be running at least PHP 7.2

## Installation
Clone the repository
```sh
git clone git@github.com:NivanoHQ/lock-collector.git
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

The Lock Collector comes with a console that allows you to list and run existing CronJobs

```sh
# List all cronjob
/usr/bin/php bin/console.php cronjob:list

# Run a cronjob
/usr/bin/php bin/console.php cronjob:run ScanAndDeleteLockFiles /path/to/scan/directory 
```
