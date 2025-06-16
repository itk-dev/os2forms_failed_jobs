<?php

namespace Drupal\os2forms_failed_jobs\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\advancedqueue\JobTypeManager;
use Drupal\os2forms_failed_jobs\Helper\Helper;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Controller for handling failed jobs.
 */
final class Controller extends ControllerBase {

  /**
   * Failed jobs helper.
   *
   * @var \Drupal\os2forms_failed_jobs\Helper\Helper
   */
  protected Helper $helper;

  /**
   * Request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $requestStack;

  /**
   * Request stack.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected RendererInterface $renderer;

  /**
   * Request stack.
   *
   * @var \Drupal\advancedqueue\JobTypeManager
   */
  protected JobTypeManager $jobTypeManager;

  /**
   * Failed jobs constructor.
   */
  public function __construct(EntityTypeManager $entityTypeManager, Helper $helper, RequestStack $requestStack, RendererInterface $renderer, JobTypeManager $jobTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
    $this->helper = $helper;
    $this->requestStack = $requestStack;
    $this->renderer = $renderer;
    $this->jobTypeManager = $jobTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): Controller {
    return new static(
      $container->get('entity_type.manager'),
      $container->get(Helper::class),
      $container->get('request_stack'),
      $container->get('renderer'),
      $container->get('plugin.manager.advancedqueue_job_type'),
    );
  }

  /**
   * Renders the failed jobs view page.
   *
   * @return array
   *   The renderable array.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Exception
   *
   * @phpstan-return array<string, mixed>
   */
  public function render(): array {
    $view = Views::getView('os2forms_failed_jobs');
    $view->setDisplay('block_1');
    // Add custom argument that the views_ui cannot provide.
    $formId = $this->requestStack->getCurrentRequest()->get('webform')->id();
    $view->setArguments([implode(',', $this->helper->getQueueJobIds($formId))]);
    $view->execute();

    return $view->render() ?? ['#markup' => $this->t('No failed jobs')];
  }

  /**
   * Renders the failed jobs view page.
   *
   * @return array
   *   The renderable array.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Exception
   *
   * @phpstan-return array<string, mixed>
   */
  public function myFormErrors(): array {
    $webforms = $this->entityTypeManager->getStorage('webform')->loadMultiple();
    $jobIds = [];
    foreach ($webforms as $webform) {
      if ($webform->access('update')) {
        $formJobIds = $this->helper->getQueueJobIds($webform->id());
        array_push($jobIds, ...$formJobIds);
      }
    }

    $jobIds = array_unique($jobIds);
    $view = Views::getView('os2forms_failed_jobs_personalized');
    $view->setDisplay('block_1');
    $view->setArguments([implode(',', $jobIds)]);

    $view->execute();
    $renderedView = $view->render() ?? ['#markup' => $this->t('No failed jobs')];

    $renderedView['#prefix'] = $this->t('List of failed queue jobs across all forms you have access to. Retrying a job puts it back into the queue for reprocessing shortly. Manual handling cancels further attempts to process the job, and any futher work on the submission must be managed personally.');

    return $renderedView;
  }

  /**
   * Get the message related to an advanced queue job.
   *
   * @return array<string, mixed>
   *   The rendered message.
   *
   * @throws \Exception
   */
  public function jobMessage(): array {
    $jobId = $this->requestStack->getCurrentRequest()->get('job_id');
    $job = $this->helper->getJobFromId($jobId);

    if (empty($job)) {
      $message = $this->t('Job not found');
    }
    else {
      $message = $job->getMessage();
    }

    $renderArray['content'] = [
      '#type' => 'markup',
      '#markup' => '<p>' . $message . '</p>',
    ];

    return $renderArray;
  }

  /**
   * Get the message explaining the retry strategy.
   *
   * @return array<string, mixed>
   *   The rendered message.
   *
   * @throws \Exception
   */
  public function retryStrategyMessage(): array {
    $jobId = $this->requestStack->getCurrentRequest()->get('job_id');
    $job = $this->helper->getJobFromId($jobId);

    if (empty($job)) {
      return [
        '#type' => 'markup',
        '#markup' => '<p>' . $this->t('Job not found') . '</p>',
      ];
    }

    $jobTypePlugin = $this->jobTypeManager->createInstance($job->getType());
    $renderArray['content'] = [
      '#theme' => 'job_type_retry_strategy',
      '#data' => [
        'label' => $jobTypePlugin->getLabel(),
        'plugin' => $jobTypePlugin->getPluginDefinition(),
      ],
    ];

    return $renderArray;
  }

  /**
   * Add title.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   A translatable string.
   */
  public function title(): TranslatableMarkup {
    return $this->t('Failed jobs');
  }

  /**
   * Add title.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   A translatable string.
   */
  public function myTitle(): TranslatableMarkup {
    return $this->t('Failed jobs on my forms');
  }

}
