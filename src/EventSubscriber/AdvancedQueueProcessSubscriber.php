<?php

namespace Drupal\os2forms_failed_jobs\EventSubscriber;

use Drupal\advancedqueue\Event\AdvancedQueueEvents;
use Drupal\advancedqueue\Event\JobEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\os2forms_failed_jobs\Helper\Helper;

/**
 * Class AdvancedQueueProcessSubscriber.
 *
 * @package Drupal\os2forms_failed_jobs\EventSubscriber
 */
class AdvancedQueueProcessSubscriber implements EventSubscriberInterface {

  /**
   * The os2forms_failed_jobs helper.
   *
   * @var \Drupal\os2forms_failed_jobs\Helper\Helper
   */
  protected Helper $helper;

  /**
   * The AdvancedQueueProcessSubscriber constructor.
   *
   * @param \Drupal\os2forms_failed_jobs\Helper\Helper $helper
   *   The helper service for os2forms_failed_jobs module.
   */
  public function __construct(Helper $helper) {
    $this->helper = $helper;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      AdvancedQueueEvents::PRE_PROCESS => 'onQueuePreProcess',
    ];
  }

  /**
   * Act when advanced queue runs its preprocess event.
   *
   * @param \Drupal\advancedqueue\Event\JobEvent $event
   *   The job that is about to be processed.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function onQueuePreProcess(JobEvent $event) {
    $this->helper->handleJob($event->getJob());
  }

}
