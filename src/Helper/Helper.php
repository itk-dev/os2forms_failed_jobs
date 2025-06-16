<?php

namespace Drupal\os2forms_failed_jobs\Helper;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Link;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\advancedqueue\Job;
use Drupal\advancedqueue\Plugin\AdvancedQueue\Backend\Database;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform_submission_log\WebformSubmissionLogManagerInterface;

/**
 * Helper for managing failed jobs.
 */
class Helper {

  use StringTranslationTrait;
  use MessengerTrait;

  /**
   * The helper service constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   The entity manager.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\Core\Logger\LoggerChannelFactory $loggerFactory
   *   The logger factory.
   * @param \Drupal\webform_submission_log\WebformSubmissionLogManagerInterface $webformSubmissionLogManager
   *   The submission log manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   */
  public function __construct(
    protected EntityTypeManager $entityTypeManager,
    protected Connection $connection,
    public LoggerChannelFactory $loggerFactory,
    protected WebformSubmissionLogManagerInterface $webformSubmissionLogManager,
    protected AccountProxyInterface $currentUser,
  ) {}

  /**
   * Get job from job id.
   *
   * @param string $jobId
   *   The job id.
   *
   * @return \Drupal\advancedqueue\Job|null
   *   A list of attributes related to a job.
   *
   * @throws \Exception
   */
  public function getJobFromId(string $jobId): Job|NULL {
    $query = $this->connection->select('advancedqueue', 'a');
    $query->fields('a');
    $query->condition('job_id', $jobId, '=');
    $definition = $query->execute()->fetchAssoc();

    if (empty($definition)) {
      return NULL;
    }
    // Match Job constructor id.
    $definition['id'] = $definition['job_id'];

    // Turn payload into array.
    $definition['payload'] = json_decode($definition['payload'], TRUE);

    return new Job($definition);
  }

  /**
   * Get submission id from job.
   *
   * @param string $jobId
   *   The job id.
   *
   * @return int|null
   *   The id of a form submission from a job.
   *
   * @throws \Exception
   */
  public function getSubmissionIdFromJob(string $jobId): ?int {
    $job = $this->getJobFromId($jobId);
    if (empty($job)) {
      return NULL;
    }
    $payload = $job->getPayload();

    return $payload['submissionId'] ?? $payload['submission']['id'] ?? NULL;
  }

  /**
   * Get submission created from job.
   *
   * @param string $jobId
   *   The job id.
   *
   * @return int|null
   *   The created time of a form submission from a job.
   *
   * @throws \Exception
   */
  public function getSubmissionCreatedFromJob(string $jobId): ?int {
    try {
      $submissionId = $this->getSubmissionIdFromJob($jobId);
      if (empty($submissionId)) {
        return 0;
      }
      $submission = $this->getWebformSubmission($submissionId);

      if (!empty($submission)) {
        /** @var \Drupal\webform\WebformSubmissionInterface $submission */
        return $submission->getCreatedTime();
      }
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      $this->loggerFactory->get('os2forms_failed_jobs_queue_submission_relation')
        ->error($e->getMessage());
    }

    return 0;
  }

  /**
   * Get all jobs that match a specific form.
   *
   * @param string $formId
   *   The form to match.
   *
   * @return array
   *   A list of view parameters.
   *
   * @phpstan-return array<string, mixed>
   *
   * @throws \Exception
   */
  public function getQueueJobIds(string $formId): array {
    $query = $this->connection->select('os2forms_failed_jobs_queue_submission_relation', 'o');
    $query->fields('o', ['job_id']);
    $query->condition('webform_id', $formId, '=');

    return $query->execute()->fetchCol();
  }

  /**
   * Handle a job from the AdvancedQueueProcessSubscriber.
   *
   * @param \Drupal\advancedqueue\Job $job
   *   The job about to be processed.
   *
   * @throws \Exception
   */
  public function handleJob(Job $job): void {
    $data = $this->getDataFromJob($job);
    if ($data) {
      $data['job_id'] = (int) $job->getId();
      if (empty($data['job_id']) || empty($data['submission_id'])) {
        return;
      }

      if (array_key_exists($data['job_id'], $this->getAllRelations())) {
        return;
      }

      try {
        $this->connection->upsert('os2forms_failed_jobs_queue_submission_relation')
          ->key('job_id')
          ->fields($data)
          ->execute();
      }
      catch (\Exception $e) {
        $this->loggerFactory->get('os2forms_failed_jobs_queue_submission_relation')
          ->error(
            'Error adding releation: %message', ['%message' => $e->getMessage()]);
      }
    }

    // Update processed time.
    $queue_id = $job->getQueueId();

    $queue_storage = $this->entityTypeManager->getStorage('advancedqueue_queue');
    /** @var \Drupal\advancedqueue\Entity\QueueInterface $queue */
    $queue = $queue_storage->load($queue_id);

    $queue_backend = $queue->getBackend();
    if ($queue_backend instanceof Database) {
      $queue_backend->onFailure($job);
    }
  }

  /**
   * Handle importing advanced queue jobs through a command.
   */
  public function handleImport(): void {
    $jobs = $this->getAllQueueJobs();
    foreach ($jobs as $job) {
      $this->handleJob($job);
    }
  }

  /**
   * Get webform id from job queue id.
   *
   * @param string $jobId
   *   The id of a queue job.
   *
   * @return string|null
   *   The webform id.
   *
   * @throws \Exception
   */
  public function getWebformIdFromQueue(string $jobId): ?string {
    $query = $this->connection->select('os2forms_failed_jobs_queue_submission_relation', 'o');
    $query->condition('job_id', $jobId, '=');
    $query->fields('o', ['webform_id']);

    $result = $query->execute()?->fetchObject();

    return $result ? $result->webform_id : NULL;
  }

  /**
   * Get webform serial id from job queue id.
   *
   * @param string $jobId
   *   The id of a queue job.
   *
   * @return int
   *   The serial id.
   *
   * @throws \Exception
   */
  public function getSubmissionSerialIdFromJob(string $jobId): int {
    try {
      $submissionId = $this->getSubmissionIdFromJob($jobId);
      if (empty($submissionId)) {
        return 0;
      }
      $submission = $this->getWebformSubmission($submissionId);

      if (!empty($submission)) {
        /** @var \Drupal\webform\WebformSubmissionInterface $submission */
        return $submission->serial();
      }
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      $this->loggerFactory->get('os2forms_failed_jobs_queue_submission_relation')
        ->error($e->getMessage());
    }

    return 0;
  }

  /**
   * Determine if a job was submitted between two Drupal DateTime objects.
   *
   * @param string $jobId
   *   The id of a queue job.
   * @param array $input
   *   The input from a form filter.
   *
   * @return bool
   *   True if date is between two DateTime objects or if no input was given.
   *
   * @phpstan-param array<string, mixed> $input
   *
   * @throws \Exception
   */
  public function submissionInCreatedFilterRange(string $jobId, array $input): bool {
    try {
      $submissionId = $this->getSubmissionIdFromJob($jobId);
      if (empty($submissionId)) {
        return FALSE;
      }
      /** @var \Drupal\webform\WebformSubmissionInterface|null $submission */
      $submission = $this->getWebformSubmission($submissionId);

      if ($submission && !empty($input['min']) && !empty($input['max'])) {
        $created = $submission->getCreatedTime();
        $created = (new DrupalDateTime())->createFromTimestamp($created);
        if ($input['min'] <= $created  && $created <= $input['max']) {
          return TRUE;
        }
        return FALSE;
      }
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      $this->loggerFactory->get('os2forms_failed_jobs_queue_submission_relation')
        ->error($e->getMessage());
    }

    return TRUE;
  }

  /**
   * Given a submission id get all matching jobs from advanced queue table.
   *
   * @param string $submissionId
   *   The submission id.
   *
   * @return array
   *   A list of matching jobs.
   *
   * @phpstan-return array<int, mixed>
   *
   * @throws \Exception
   */
  public function getQueueJobIdsFromSubmissionId(string $submissionId): array {
    $query = $this->connection->select('os2forms_failed_jobs_queue_submission_relation', 'o');
    $query->fields('o', ['job_id']);
    $query->condition('submission_id', $submissionId, '=');

    return $query->execute()->fetchCol();
  }

  /**
   * Given a submission serial id and webform id get all matching queues.
   *
   * @param string $serial
   *   A submission serial id.
   * @param string $webformId
   *   A webform id.
   *
   * @return array
   *   A list of matching jobs.
   *
   * @phpstan-return array<int, mixed>
   *
   * @throws \Exception
   */
  public function getQueueJobIdsFromSerial(string $serial, string $webformId): array {
    $query = $this->connection->select('webform_submission', 'w');
    $query->fields('w', ['sid']);
    $query->condition('serial', $serial, '=');
    $query->condition('webform_id', $webformId, '=');

    $submissionId = $query->execute()->fetchField();

    return $this->getQueueJobIdsFromSubmissionId($submissionId);

  }

  /**
   * Retrieve data from advanced queue job.
   *
   * @param \Drupal\advancedqueue\Job $job
   *   The job about to be processed.
   *
   * @return array|null
   *   An array containing submission id and webform id.
   *
   * @phpstan-return array<string, mixed>
   */
  public function getDataFromJob(Job $job): ?array {
    $payload = $job->getPayload();
    $submissionId = $this->getSubmissionId($payload);
    if (empty($submissionId)) {
      return NULL;
    }

    try {
      /** @var \Drupal\webform\WebformSubmissionInterface $submission */
      $submission = $this->getWebformSubmission($submissionId);

      // @phpstan-ignore-next-line
      if (is_null($submission)) {
        return [];
      }

      return [
        'submission_id' => $submissionId,
        'webform_id' => $submission->getWebform()->id(),
      ];
    }
    catch (\Exception $e) {
      return NULL;
    }
  }

  /**
   * Get a list of queue submission relations without existing submission.
   *
   * @param string|null $submissionId
   *   A specific webform submission id.
   *
   * @return array
   *   A list of queue submission relations.
   *
   * @phpstan-return array<string, mixed>
   *
   * @throws \Exception
   */
  public function getDetachedQueueSubmissionRelations(?string $submissionId = NULL): array {
    $query = $this->connection->select('os2forms_failed_jobs_queue_submission_relation', 'o');
    $query->fields('o', ['job_id', 'submission_id']);
    if ($submissionId) {
      $query->condition('submission_id', $submissionId, '=');
    }

    return $query->execute()->fetchAll();
  }

  /**
   * Remove a list relations.
   *
   * @param array $entries
   *   List of entries with job_id and submission_id.
   *
   * @phpstan-param array<string, mixed> $entries
   *
   * @throws \Exception
   */
  public function removeRelations(array $entries): void {
    foreach ($entries as $entry) {
      try {
        $submission = $this->entityTypeManager->getStorage('webform_submission')
          ->getQuery()
          ->accessCheck(FALSE)
          ->condition('sid', $entry->submission_id)
          ->execute();
        if (empty($submission)) {
          $this->removeQueueSubmissionRelation($entry->submission_id);
        }
      }
      catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      }

    }
  }

  /**
   * Cleanup relations.
   *
   * @param \Drupal\webform\WebformSubmissionInterface|null $submission
   *   A webform submission.
   *
   * @throws \Exception
   */
  public function cleanUp(?WebformSubmissionInterface $submission = NULL): void {
    $relations = $this->getDetachedQueueSubmissionRelations($submission?->id());
    $this->removeRelations($relations);
  }

  /**
   * Mark a submission from advanced queue to be handled manually.
   *
   * @param \Drupal\advancedqueue\Job $job
   *   The job.
   * @param \Drupal\advancedqueue\Plugin\AdvancedQueue\Backend\Database $queue_backend
   *   The queue backend.
   */
  public function handleManually($job, Database $queue_backend): void {
    try {
      $job->setState('success');

      $this->createSubmissionLogEntry([
        'webform_id' => $this->getWebformIdFromQueue($job->getId()),
        'sid' => $this->getSubmissionIdFromJob($job->getId()),
        'handler_id' => '',
        'operation' => 'selected for manual handling',
        'uid' => $this->getCurrentUser()->id(),
        'message' => $this->t('Submission removed from error log. Selected for manual handling.'),
        'variables' => serialize([]),
        'data' => serialize([]),
        'timestamp' => (new DrupalDateTime())->getTimestamp(),
      ]);

      $queue_backend->onSuccess($job);

      $link = Link::createFromRoute('Go to submission log', 'entity.webform_submission.log', [
        'webform' => $this->getWebformIdFromQueue($job->getId()),
        'webform_submission' => $this->getSubmissionIdFromJob($job->getId()),
      ])->toString();

      $this->messenger()->addMessage($this->t('Submission removed from error log and added to submission log for manual handling. @link', ['@link' => $link]));
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('os2forms_failed_jobs_queue_submission_relation')
        ->error($e->getMessage());
    }
  }

  /**
   * Retry a job.
   *
   * @param \Drupal\advancedqueue\Job $job
   *   The job.
   * @param \Drupal\advancedqueue\Plugin\AdvancedQueue\Backend\Database $queue_backend
   *   The queue backend.
   */
  public function retryJob($job, Database $queue_backend): void {
    try {
      $job->setState('failure');
      $job->setNumRetries(0);
      $job->setProcessedTime(0);
      $queue_backend->retryJob($job);
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('os2forms_failed_jobs_queue_submission_relation')
        ->error($e->getMessage());
    }
  }

  /**
   * Create a submission log entry.
   *
   * @param array<string, mixed> $fields
   *   List of fields.
   */
  public function createSubmissionLogEntry(array $fields): void {
    $this->webformSubmissionLogManager->insert($fields);
  }

  /**
   * Act after a job was processed.
   *
   * @param \Drupal\advancedqueue\Job $job
   *   The job.
   */
  public function onJobPostProcess(Job $job): void {
    try {
      $queue_id = $job->getQueueId();

      $queue_storage = $this->entityTypeManager->getStorage('advancedqueue_queue');
      /** @var \Drupal\advancedqueue\Entity\QueueInterface $queue */
      $queue = $queue_storage->load($queue_id);

      $queue_backend = $queue->getBackend();
      if ($job->getState() === 'failure') {
        $queue_backend->onFailure($job);
      }
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('os2forms_failed_jobs_queue_submission_relation')
        ->error($e->getMessage());
    }
  }

  /**
   * Get the current user.
   *
   * @return \Drupal\Core\Session\AccountProxyInterface
   *   The user account.
   */
  public function getCurrentUser(): AccountProxyInterface {
    return $this->currentUser;
  }

  /**
   * Get all entries from the advancedqueue table.
   *
   * @return array
   *   A list of all entries from the advanced queue table.
   *
   * @phpstan-return array<int, mixed>
   *
   * @throws \Exception
   */
  private function getAllQueueJobs(): array {
    $query = $this->connection->select('advancedqueue', 'q');
    $query->fields('q');
    $jobs = [];

    $jobEntries = $query->execute()->fetchAllAssoc('job_id');

    foreach ($jobEntries as $entry) {
      $definition = (array) $entry;
      // Match Job constructor id.
      $definition['id'] = $entry->job_id;

      // Turn payload into array.
      $definition['payload'] = json_decode($entry->payload, TRUE);
      $jobs[$entry->job_id] = new Job($definition);
    }

    return $jobs;
  }

  /**
   * Get submission id from advanced queue job payload.
   *
   * @param array $payload
   *   The payload of an advanced queue job.
   *
   * @return int|null
   *   A webform submission id.
   *
   * @phpstan-param array<string, mixed> $payload
   */
  private function getSubmissionId(array $payload): ?int {
    return $payload['submissionId'] ?? $payload['submission']['id'] ?? NULL;
  }

  /**
   * Get webform id from submission.
   *
   * @param int $submissionId
   *   Id of a submission.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   A webform id.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function getWebformSubmission(int $submissionId): ?EntityInterface {
    return $this->entityTypeManager->getStorage('webform_submission')->load($submissionId);
  }

  /**
   * Get all relations from the table.
   *
   * @return array
   *   A list of all relations.
   *
   * @phpstan-return array<int, mixed>
   *
   * @throws \Exception
   */
  private function getAllRelations(): array {
    $query = $this->connection->select('os2forms_failed_jobs_queue_submission_relation', 'o');
    $query->fields('o');

    return $query->execute()->fetchAllAssoc('job_id');
  }

  /**
   * Delete advanced queue submission relation.
   *
   * @param string $submissionId
   *   The advanced queue submission id.
   *
   * @throws \Exception
   */
  private function removeQueueSubmissionRelation(string $submissionId): void {
    // Delete os2forms_failed_jobs_queue_submission_relation.
    if ($this->connection->schema()->tableExists('os2forms_failed_jobs_queue_submission_relation')) {
      $this->connection->delete('os2forms_failed_jobs_queue_submission_relation')
        ->condition('submission_id', $submissionId)
        ->execute();
    }
  }

}
