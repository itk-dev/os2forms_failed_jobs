<?php

namespace Drupal\os2forms_failed_jobs\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\advancedqueue\Plugin\AdvancedQueue\Backend\Database;
use Drupal\os2forms_failed_jobs\Helper\Helper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a confirmation form for retrying a job.
 */
final class RetryJob extends ConfirmFormBase {
  /**
   * The job ID to release.
   *
   * @var int
   */
  protected int $jobId;

  /**
   * Retry job constructor.
   */
  public function __construct(
    protected Connection $database,
    protected EntityTypeManager $entityTypeManager,
    protected Helper $helper,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): RetryJob {
    return new static(
      $container->get('database'),
      $container->get('entity_type.manager'),
      $container->get(Helper::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'advancedqueue_retry_job';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    $job = $this->helper->getJobFromId((string) $this->jobId);

    if (empty($job)) {
      return $this->t('Job not found');
    }

    $webformId = $this->helper->getWebformIdFromQueue($job->getId());

    if (NULL === $webformId) {
      return $this->t('Are you sure you want to retry queue job: @jobId', ['@jobId' => $job->getId()]);
    }
    else {
      return $this->t('Are you sure you want to retry queue job related to Webform: @webformId, Submission id: @serialId', [
        '@serialId' => $this->helper->getSubmissionSerialIdFromJob($job->getId()),
        '@webformId' => $this->entityTypeManager->getStorage('webform')->load($webformId)->label(),
      ]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    $webform = $this->helper->getWebformIdFromQueue((string) $this->jobId);

    if (NULL === $webform) {
      $job = $this->helper->getJobFromId((string) $this->jobId);

      return Url::fromRoute('view.advancedqueue_jobs.page_1', ['arg_0' => $job->getQueueId()]);
    }
    else {
      return Url::fromRoute('entity.webform.error_log', ['webform' => $webform]);
    }

  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<string, mixed> $form
   * @phpstan-return array<string, mixed>
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?int $job_id = NULL): array {
    $this->jobId = $job_id;

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<string, mixed> $form
   * @phpstan-return void
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $job = $this->helper->getJobFromId((string) $this->jobId);
    if (!empty($job)) {
      $queue_id = $job->getQueueId();

      $queue_storage = $this->entityTypeManager->getStorage('advancedqueue_queue');
      /** @var \Drupal\advancedqueue\Entity\QueueInterface $queue */
      $queue = $queue_storage->load($queue_id);

      $queue_backend = $queue->getBackend();
      if ($queue_backend instanceof Database) {
        $this->helper->retryJob($job, $queue_backend);
      }
    }
  }

}
