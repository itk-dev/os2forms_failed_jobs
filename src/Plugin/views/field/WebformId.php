<?php

namespace Drupal\os2forms_failed_jobs\Plugin\views\field;

use Drupal\Core\Session\AccountInterface;
use Drupal\os2forms_failed_jobs\Helper\Helper;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Field handler to render webform id for a given job.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("advancedqueue_job_webform_id")
 */
final class WebformId extends FieldPluginBase {

  /**
   * The helper service.
   *
   * @var \Drupal\os2forms_failed_jobs\Helper\Helper
   */
  protected Helper $helper;

  /**
   * Class constructor.
   *
   * @phpstan-param array $configuration
   */
  public function __construct($configuration, $plugin_id, $plugin_definition, Helper $helper) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->helper = $helper;
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array $configuration
   */
  public static function create(ContainerInterface $container, $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get(Helper::class),
    );
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<string, mixed> $options
   * @phpstan-return void
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL): void {
    parent::init($view, $display, $options);

    $this->additional_fields['webform_id'] = [
      'table' => 'os2forms_failed_jobs_queue_submission_relation',
      'field' => 'webform_id',
    ];
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-return void
   */
  public function query() {}

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account) {
    return $account->hasPermission('access webform advanced queue overview');
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function render(ResultRow $values) {
    if (isset($values->job_id)) {
      $webformId = $this->helper->getWebformIdFromQueue($values->job_id);
    }
    $renderer = $this->getRenderer();
    $renderArray = [
      '#markup' => $webformId ?? '',
    ];

    return $renderer->render($renderArray);
  }

}
