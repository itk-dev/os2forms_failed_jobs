<?php

namespace Drupal\os2forms_failed_jobs\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
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
   * Failed jobs constructor.
   */
  public function __construct(EntityTypeManager $entityTypeManager, Helper $helper, RequestStack $requestStack, RendererInterface $renderer) {
    $this->entityTypeManager = $entityTypeManager;
    $this->helper = $helper;
    $this->requestStack = $requestStack;
    $this->renderer = $renderer;
  }

  /**
   * Instantiates a new instance of os2forms_failed_jobs controller.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get(Helper::class),
      $container->get('request_stack'),
      $container->get('renderer')
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
   * Get the message related to an advanced queue job.
   *
   * @return array
   *   The rendered message.
   *
   * @phpstan-return array<string, array>
   */
  public function jobMessage(): array {
    $jobId = $this->requestStack->getCurrentRequest()->get('job_id');
    $job = $this->helper->getJobFromId($jobId);

    $render_array['content'] = [
      '#type' => 'markup',
      '#markup' => '<p>' . $job->getMessage() . '</p>',
    ];

    return $render_array;
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

}
