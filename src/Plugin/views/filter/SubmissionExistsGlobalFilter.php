<?php

namespace Drupal\os2forms_failed_jobs\Plugin\views\filter;

use Drupal\Core\Database\Connection;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\os2forms_failed_jobs\Helper\Helper;
use Drupal\views\Plugin\views\filter\StringFilter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filter by submission exists.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("advancedqueue_job_submission_exists_global")
 */
final class SubmissionExistsGlobalFilter extends StringFilter {

  /**
   * Class constructor.
   *
   * @phpstan-param array<string, mixed> $configuration
   */
  public function __construct(
    $configuration,
    $plugin_id,
    $plugin_definition,
    Connection $connection,
    protected Helper $helper,
    protected RouteMatchInterface $routeMatch,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $connection);
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

    $webformSubQuery = \Drupal::database()->select('webform', 'w')
      ->fields('w', ['webform_id'])
      ->condition('w.webform_id', $this->helper->getAllWebformsWithUpdateAccess(), 'IN');

    $jobIdsSubQuery = \Drupal::database()->select('os2forms_failed_jobs_queue_submission_relation', 'r')
      ->fields('r', ['job_id'])
      ->condition('r.webform_id', $webformSubQuery, 'IN');

    $query->addWhere($this->options['group'], $table . '.job_id', $jobIdsSubQuery, 'IN');
  }

}
