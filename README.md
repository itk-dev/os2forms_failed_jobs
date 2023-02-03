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
- All failed jobs from advanced queue related to the webform will be displayed.
- Each job can be retried which will re-queue the job in the advanced queue.

## Coding standards

Run phpcs with the provided configuration:

```shell
composer coding-standards-check

// Apply coding standards
composer coding-standards-apply
```
