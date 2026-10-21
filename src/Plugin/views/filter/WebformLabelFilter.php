<?php

namespace Drupal\os2forms_failed_jobs\Plugin\views\filter;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\os2forms_failed_jobs\Helper\Helper;
use Drupal\views\Plugin\views\filter\StringFilter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filter by webform label.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("advancedqueue_job_webform_label")
 */
final class WebformLabelFilter extends StringFilter {

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
    protected EntityTypeManagerInterface $entityTypeManager,
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
      $container->get('entity_type.manager'),
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
    $webformQuery = $this->entityTypeManager
      ->getStorage('webform')
      ->getQuery()
      ->accessCheck(FALSE);
    $webformQuery->condition('title', $input, 'CONTAINS');
    $webforms = $webformQuery->execute();
    $jobs = ['0'];

    if ($webforms) {
      foreach ($webforms as $webform) {
        $jobs = [...$jobs, ...$this->helper->getQueueJobIds($webform)];
      }
    }

    $query->addWhere($this->options['group'], $table . '.job_id', $jobs, 'IN');
  }

}
