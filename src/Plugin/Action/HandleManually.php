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
 * Handle a job manually.
 *
 * @Action(
 *   id = "advancedqueue_queue_handle_manually_action",
 *   label = @Translation("Handle manually"),
 *   type = "advancedqueue_queue"
 * )
 */
final class HandleManually extends ActionBase implements ContainerFactoryPluginInterface {

  /**
   * The queue.
   *
   * @var \Drupal\advancedqueue\Entity\QueueInterface
   */
  protected QueueInterface $queue;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The queue processor.
   *
   * @var \Drupal\advancedqueue\ProcessorInterface
   */
  protected ProcessorInterface $processor;

  /**
   * Failed jobs helper.
   *
   * @var \Drupal\os2forms_failed_jobs\Helper\Helper
   */
  protected Helper $helper;

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<string, mixed> $configuration
   */
  public function __construct($configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, ProcessorInterface $processor, Helper $helper) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entity_type_manager;
    $this->processor = $processor;
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
    if (!empty($job)) {
      $queue_id = $job->getQueueId();

      $queue_storage = $this->entityTypeManager->getStorage('advancedqueue_queue');
      /** @var \Drupal\advancedqueue\Entity\QueueInterface $queue */
      $queue = $queue_storage->load($queue_id);

      $queue_backend = $queue->getBackend();
      if ($queue_backend instanceof Database) {
        $job->setState('success');
        $queue_backend->onSuccess($job);
      }
    }

    $job = $this->helper->getJobFromId($jobId);
    if (empty($job)) {
      return;
    }
    $queue_id = $job->getQueueId();

    $queue_storage = $this->entityTypeManager->getStorage('advancedqueue_queue');
    /** @var \Drupal\advancedqueue\Entity\QueueInterface $queue */
    $queue = $queue_storage->load($queue_id);

    $queue_backend = $queue->getBackend();
    if ($queue_backend instanceof Database) {
      $this->helper->handleManually($job, $queue_backend);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    return TRUE;
  }

}
