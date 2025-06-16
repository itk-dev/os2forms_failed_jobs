<?php

namespace Drupal\os2forms_failed_jobs\Plugin\views\field;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\os2forms_failed_jobs\Helper\Helper;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\Render\ViewsRenderPipelineMarkup;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Field handler to display countdown for next attempt on queue processing.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("advancedqueue_job_next_attempt")
 */
class NextAttempt extends FieldPluginBase {

  /**
   * The helper service.
   *
   * @var \Drupal\os2forms_failed_jobs\Helper\Helper
   */
  protected Helper $helper;

  /**
   * Class constructor.
   *
   * @phpstan-param array<string, mixed> $configuration
   */
  public function __construct($configuration, $plugin_id, $plugin_definition, Helper $helper) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->helper = $helper;
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
      $container->get(Helper::class),
    );
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-return void
   */
  public function query() {}

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function render(ResultRow $values): MarkupInterface|string|ViewsRenderPipelineMarkup {
    $renderer = $this->getRenderer();
    $renderArray = [];
    $title = '';

    if (!empty($values->advancedqueue_available)) {
      $dateAvailable = DrupalDateTime::createFromTimestamp($values->advancedqueue_available);
      $now = new DrupalDateTime();

      if ($now > $dateAvailable) {
        $title = $this->t('Job awaiting cron');
      }
      else {
        $diff = $dateAvailable->diff($now);
        $title .= (int) $diff->format('%a') > 0 ? $diff->format('%a days') . ' ' : '';
        $title .= (int) $diff->format('%h') > 0 ? $diff->format('%h hours') . ' ' : '';
        $title .= (int) $diff->format('%i') > 0 ? $diff->format('%i minutes') . ' ' : '';
        if (empty($title)) {
          $title = $this->t('< 1 minute');
        }
      }
    }

    if (!empty($values->advancedqueue_state)) {
      if ('failure' === $values->advancedqueue_state) {
        $title = $this->t('Job failed');
      }
    }

    if (isset($values->job_id)) {
      $webformId = $this->helper->getWebformIdFromQueue($values->job_id);
      if ($webformId) {
        $renderArray = [
          '#markup' => $title,
        ];
      }
    }

    return $renderer->render($renderArray);
  }

}
