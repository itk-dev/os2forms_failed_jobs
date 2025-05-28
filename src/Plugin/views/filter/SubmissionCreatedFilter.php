<?php

namespace Drupal\os2forms_failed_jobs\Plugin\views\filter;

use Drupal\Core\Database\Connection;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\os2forms_failed_jobs\Helper\Helper;
use Drupal\views\Plugin\views\filter\Date;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Filter by submission created.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("advancedqueue_job_submission_created")
 */
final class SubmissionCreatedFilter extends Date {

  /**
   * Class constructor.
   *
   * @phpstan-param array<string, mixed> $configuration
   */
  public function __construct(
    $configuration,
    $plugin_id,
    $plugin_definition,
    protected Connection $connection,
    protected Helper $helper,
    protected RouteMatchInterface $routeMatch,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<string, mixed> $configuration
   */
  public static function create(ContainerInterface $container, $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database'),
      $container->get(Helper::class),
      $container->get('current_route_match'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    $this->ensureMyTable();

    /** @var \Drupal\views\Plugin\views\query\Sql $query */
    $query = $this->query;
    $table = array_key_first($query->tables);

    $existsQuery = $this->connection->select('os2forms_failed_jobs_queue_submission_relation', 'o');
    $existsQuery->fields('o', ['job_id']);
    $jobIds = $existsQuery->execute()->fetchCol();
    $input = $this->value;

    foreach ($jobIds as $job) {
      if ($this->helper->submissionInCreatedFilterRange($job, $input) > 0) {
        $jobs[] = $job;
      }
    }
    if (empty($jobs)) {
      // The 'IN' operator requires a non empty array.
      $jobs = [0];
    }

    $query->addWhere($this->options['group'], $table . '.job_id', $jobs, 'IN');
  }
}
