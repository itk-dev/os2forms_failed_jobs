<?php

namespace Drupal\os2forms_failed_jobs\Plugin\views\field;

use Drupal\advancedqueue\JobTypeManager;
use Drupal\Component\Render\MarkupInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\Render\ViewsRenderPipelineMarkup;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Field handler to render the retry strategy for a job.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("advancedqueue_job_type_retry_strategy")
 */
class JobTypeRetryStrategy extends FieldPluginBase {

  /**
   * Class constructor.
   *
   * @phpstan-param array<string, mixed> $configuration
   */
  public function __construct($configuration, $plugin_id, $plugin_definition, protected JobTypeManager $jobTypeManager) {
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
      $container->get('plugin.manager.advancedqueue_job_type'),
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
    $renderArray = [];
    $renderer = $this->getRenderer();
    if (!empty($values->advancedqueue_type)) {
      $jobTypePlugin = $this->jobTypeManager->createInstance($values->advancedqueue_type);
      $renderArray = [
        '#theme' => 'job_type_retry_strategy',
        '#data' => [
          'label' => $jobTypePlugin->getLabel(),
          'plugin' => $jobTypePlugin->getPluginDefinition(),
        ],
      ];
    }

    return $renderer->render($renderArray);
  }

}
