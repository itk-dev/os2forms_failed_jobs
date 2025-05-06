<?php

namespace Drupal\os2forms_failed_jobs\Plugin\views\filter;

use Drupal\Core\Database\Connection;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\os2forms_failed_jobs\Helper\Helper;
use Drupal\views\Plugin\views\filter\StringFilter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filter by submission id.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("advancedqueue_job_submission_id")
 */
final class SubmissionIdFilter extends StringFilter {

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

    $input = $this->value;

    $jobs = $this->helper->getQueueJobIdsFromSubmissionId($input);
    if (empty($jobs)) {
      // The 'IN' operator requires a non empty array.
      $jobs = [0];

      $query->addWhere($this->options['group'], $table . '.job_id', $jobs, 'IN');
    }
  }

}
