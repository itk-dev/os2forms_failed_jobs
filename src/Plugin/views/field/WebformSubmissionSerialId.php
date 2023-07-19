<?php

namespace Drupal\os2forms_failed_jobs\Plugin\views\field;

use Drupal\Component\Render\MarkupInterface;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Render\ViewsRenderPipelineMarkup;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;

/**
 * Field handler to render submission id for a given job.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("advancedqueue_job_webform_submission_serial_id")
 */
class WebformSubmissionSerialId extends WebformSubmissionId {

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<string, mixed> $options
   * @phpstan-return void
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL): void {
    parent::init($view, $display, $options);

    $this->additional_fields['webform_submission_serial_id'] = 'webform_submission_serial_id';
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
    if (isset($values->job_id)) {
      $serialId = $this->helper->getSubmissionSerialIdFromJob($values->job_id);
    }
    $renderer = $this->getRenderer();
    $renderArray = [
      '#markup' => $serialId ?? '',
    ];

    return $renderer->render($renderArray);
  }

}
