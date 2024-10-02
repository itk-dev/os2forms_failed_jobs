<?php

namespace Drupal\os2forms_failed_jobs\Plugin\views\field;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\advancedqueue\Job;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\Render\ViewsRenderPipelineMarkup;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;

/**
 * Field handler to render retry operation for a given job.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("advancedqueue_job_retry_operation")
 */
class RetryOperation extends FieldPluginBase {

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<string, mixed> $options
   * @phpstan-return void
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, ?array &$options = NULL): void {
    parent::init($view, $display, $options);

    $this->additional_fields['state'] = 'state';
    $this->additional_fields['queue_id'] = 'queue_id';
    $this->additional_fields['job_id'] = 'job_id';
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-return void
   */
  public function query(): void {
    $this->ensureMyTable();
    $this->addAdditionalFields();
  }

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
  public function render(ResultRow $values): MarkupInterface|string|ViewsRenderPipelineMarkup {
    $operations = [];

    $state = $this->getValue($values, 'state');
    $queue_id = $this->getValue($values, 'queue_id');
    $job_id = $this->getValue($values, 'job_id');

    if ($state === Job::STATE_FAILURE) {
      $operations['retry'] = [
        'title' => $this->t('Retry'),
        'weight' => -10,
        'url' => Url::fromRoute('advancedqueue.job.retry', [
          'advancedqueue_queue' => $queue_id,
          'job_id' => $job_id,
        ]),
      ];
    }

    $renderer = $this->getRenderer();
    $renderArray = [
      '#type' => 'operations',
      '#links' => $operations,
    ];

    return $renderer->render($renderArray);
  }

}
