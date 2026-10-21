<?php

namespace Drupal\os2forms_failed_jobs\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\advancedqueue\Entity\QueueInterface;
use Drupal\advancedqueue\Plugin\AdvancedQueue\Backend\Database;
use Drupal\advancedqueue\ProcessorInterface;
use Drupal\os2forms_failed_jobs\Helper\Helper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Retry a job.
 *
 * @Action(
 *   id = "advancedqueue_queue_retry_action",
 *   label = @Translation("Retry processing"),
 *   type = "advancedqueue_queue",
 *   confirm_form_route_name = "os2forms_failed_jobs.bulk_confirm_form"
 * )
 */
final class RetryJob extends ActionBase implements ContainerFactoryPluginInterface {

  /**
   * The queue.
   *
   * @var \Drupal\advancedqueue\Entity\QueueInterface
   */
  protected QueueInterface $queue;

  /**
   * The queue processor.
   *
   * @var \Drupal\advancedqueue\ProcessorInterface
   */
  protected ProcessorInterface $processor;

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<string, mixed> $configuration
   */
  public function __construct($configuration, $plugin_id, $plugin_definition, protected EntityTypeManagerInterface $entityTypeManager, ProcessorInterface $processor, protected Helper $helper) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->processor = $processor;
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<string, mixed> $configuration
   */
  #[\Override]
  public static function create(ContainerInterface $container, $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('advancedqueue.processor'),
      $container->get(Helper::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?string $jobId = NULL): void {
    $job = $this->helper->getJobFromId((string) $jobId);
    if (empty($job)) {
      return;
    }
    $queue_id = $job->getQueueId();

    $queue_storage = $this->entityTypeManager->getStorage('advancedqueue_queue');
    /** @var \Drupal\advancedqueue\Entity\QueueInterface $queue */
    $queue = $queue_storage->load($queue_id);

    $queue_backend = $queue->getBackend();
    if ($queue_backend instanceof Database) {
      $this->helper->retryJob($job, $queue_backend);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    return TRUE;
  }

}
