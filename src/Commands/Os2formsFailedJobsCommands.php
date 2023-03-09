<?php

namespace Drupal\os2forms_failed_jobs\Commands;

use Drupal\os2forms_failed_jobs\Helper\Helper;
use Drush\Commands\DrushCommands;

/**
 * Drush commands related to os2forms_failed_jobs module.
 */
class Os2formsFailedJobsCommands extends DrushCommands {

  /**
   * The os2forms_failed_jobs helper.
   *
   * @var \Drupal\os2forms_failed_jobs\Helper\Helper
   */
  protected Helper $helper;

  /**
   * The AdvancedQueueProcessSubscriber constructor.
   *
   * @param \Drupal\os2forms_failed_jobs\Helper\Helper $helper
   *   The helper service for os2forms_failed_jobs module.
   */
  public function __construct(Helper $helper) {
    $this->helper = $helper;
  }

  /**
   * Import all entries from advanced queue table.
   *
   * @command os2forms_failed_jobs:import
   */
  public function import() {
    $this->helper->handleImport();
  }

}
