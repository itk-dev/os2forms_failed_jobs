<?php

namespace Drupal\os2forms_failed_jobs\Helper;

use Drupal\advancedqueue\Job;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Logger\LoggerChannelFactory;

/**
 * Helper for managing failed jobs.
 */
class Helper {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected EntityTypeManager $entityTypeManager;

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $connection;

  /**
   * Messenger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory
   */
  protected LoggerChannelFactory $loggerFactory;

  /**
   * The helper service constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   The entity manager.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\Core\Logger\LoggerChannelFactory $logger_factory
   *   The logger factory.
   */
  public function __construct(EntityTypeManager $entityTypeManager, Connection $connection, LoggerChannelFactory $logger_factory) {
    $this->entityTypeManager = $entityTypeManager;
    $this->connection = $connection;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * Get job from job id.
   *
   * @param string $jobId
   *   The job id.
   *
   * @return \Drupal\advancedqueue\Job
   *   A list of attributes related to a job.
   */
  public function getJobFromId(string $jobId): Job {
    $query = $this->connection->select('advancedqueue', 'a');
    $query->fields('a');
    $query->condition('job_id', (string) $jobId, '=');
    $definition = $query->execute()->fetchAssoc();

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
   */
  public function getSubmissionIdFromJob(string $jobId): ?int {
    $job = $this->getJobFromId($jobId);
    $payload = $job->getPayload();

    return $payload['submissionId'] ?? $payload['submission']['id'] ?? NULL;
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
   */
  public function getWebformIdFromQueue(string $jobId): ?string {
    $query = $this->connection->select('os2forms_failed_jobs_queue_submission_relation', 'o');
    $query->condition('job_id', $jobId, '=');
    $query->fields('o', ['webform_id']);

    return $query->execute()?->fetchObject()?->webform_id;
  }

  /**
   * Get webform serial id from job queue id.
   *
   * @param string $jobId
   *   The id of a queue job.
   *
   * @return int
   *   The serial id.
   */
  public function getSubmissionSerialIdFromJob(string $jobId): int {
    try {
      $submissionId = $this->getSubmissionIdFromJob($jobId);
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
   * Given a submission id get all matching jobs from advanced queue table.
   *
   * @param string $submissionId
   *   The submission id.
   *
   * @return array
   *   A list of matching jobs.
   *
   * @phpstan-return array<int, mixed>
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
   * Get all entries from the advancedqueue table.
   *
   * @return array
   *   A list of all entries from the advanced queue table.
   *
   * @phpstan-return array<int, mixed>
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
   */
  private function getAllRelations(): array {
    $query = $this->connection->select('os2forms_failed_jobs_queue_submission_relation', 'o');
    $query->fields('o');

    return $query->execute()->fetchAllAssoc('job_id');
  }

}
