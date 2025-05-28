<?php

namespace Drupal\os2forms_failed_jobs\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\Core\Url;
use Drupal\os2forms_failed_jobs\Helper\Helper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Confirmation form for bulk actions action.
 */
class BulkConfirmForm extends ConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    protected PrivateTempStore $tempStore,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected Helper $helper,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): BulkConfirmForm {
    return new static(
      $container->get('tempstore.private')->get('os2forms_failed_jobs_bulk_confirmation'),
      $container->get('entity_type.manager'),
      $container->get(Helper::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'os2forms_failed_jobs_bulk_confirm_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return Url::fromRoute('entity.webform.error_log.personalized');
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->t('Continue?');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): TranslatableMarkup {
    $selections = $this->tempStore->get($this->helper->getCurrentUser()->id() . ':selection');
    if ($selections) {
      return $this->t('You are about to perform "@action_label" on @count queue errors. Are you sure you want to continue?', [
        '@action_label' => $selections['action']->get('label'),
        '@count' => count($selections['jobIds']),
      ]);
    }
    else {
      return $this->t('No selections');
    }
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<string, mixed> $form
   * @phpstan-param array<string, mixed> $form_state
 */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $selections = $this->tempStore->get($this->helper->getCurrentUser()->id() . ':selection');

    $batch = [
      'title' => $this->t('Processing "@action_label"', [
        '@action_label' => $selections['action']->get('label'),
      ]),
      'operations' => [],
      'finished' => [get_class($this), 'batchFinished'],
    ];

    foreach ($selections['jobIds'] as $jobId) {
      if (!empty($jobId)) {
        $batch['operations'][] = [
          [get_class($this), 'batchProcess'],
          [$jobId, $selections['action']->id()],
        ];
      }
    }

    batch_set($batch);

    $this->tempStore->delete($this->helper->getCurrentUser()->id() . ':selection');
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

  /**
   * Batch operation callback.
   */
  public static function batchProcess($jobId, $action_id, &$context) {
    if ($jobId) {
      // Execute the action.
      $action = \Drupal::service('plugin.manager.action')->createInstance($action_id);
      $action->execute($jobId);

      // Track progress.
      $context['results']['processed'][] = $jobId;
      $context['message'] = t('Processing @job_id', [
        '@job_id' => $jobId,
      ]);
    }
  }

  /**
   * Batch finished callback.
   */
  public static function batchFinished($success, $results, $operations) {
    if ($success) {
      $count = count($results['processed']);
      \Drupal::messenger()->addStatus(t('Processed @count items.', ['@count' => $count]));
    }
    else {
      \Drupal::messenger()->addError(t('An error occurred during processing.'));
    }
  }

}
