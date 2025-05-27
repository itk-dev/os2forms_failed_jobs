<?php

namespace Drupal\os2forms_failed_jobs\Form;

use Drupal\advancedqueue\Plugin\AdvancedQueue\Backend\Database;
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
   *
   * @phpstan-param array<string, mixed> $configuration
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
  public static function create(ContainerInterface $container) {
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
    $selections = $this->tempStore->get($this->helper->getCurrentUser()->id() . ':selection');
    return $this->t('Continue?');
  }


  /**
   * {@inheritdoc}
   */
  public function getDescription(): TranslatableMarkup {
    $selections = $this->tempStore->get($this->helper->getCurrentUser()->id() . ':selection');
    return $this->t('You are about to perform "@action_label" on @count queue errors. Are you sure you want to continue?', [
      '@action_label' => $selections['action']->get('label'),
      '@count' => count($selections['entities'])
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $selections = $this->tempStore->get($this->helper->getCurrentUser()->id() . ':selection');
    foreach ($selections['entities'] as $entity) {
      $job = $this->helper->getJobFromId($entity);
      if (!empty($job)) {
        $queue_id = $job->getQueueId();

        $queue_storage = $this->entityTypeManager->getStorage('advancedqueue_queue');
        /** @var \Drupal\advancedqueue\Entity\QueueInterface $queue */
        $queue = $queue_storage->load($queue_id);

        $queue_backend = $queue->getBackend();
        if ($queue_backend instanceof Database) {
          switch ('action') {
            case 'handle_manually':
              $this->helper->handleManually($job, $queue_backend);
              break;
            case 'retry_job':
              $this->helper->retryJob($job, $queue_backend);
              break;
          }
        }
      }
    }

    $this->tempStore->delete($this->helper->getCurrentUser()->id() . ':selection');
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
