# OS2Forms failed jobs

Provides list of failed jobs to each form through drupal views.

## Installation

Require it with composer:

```shell
composer require "itk-dev/os2forms_failed_jobs"
```

Enable it with drush:

```shell
drush pm:enable os2forms_failed_jobs
```

## Usage

A new tab should appear in each webforms results menu.

- All failed jobs from advanced queue related to the webform are displayed.
- Each job can be retried which will re-queue the job in the advanced queue.

## Development

We use [DDEV Drupal Contrib](https://github.com/ddev/ddev-drupal-contrib) for development[^1].

[^1]: The DDEV environment has been created by running

      ```shell
      ddev config --project-type=drupal11 --docroot=web
      ddev dotenv set .ddev/.env.web --drupal-core '^11.4.10'
      ```

Start the show by running

```shell
task ddev:start
```

Run `task` to see other useful development tasks.
