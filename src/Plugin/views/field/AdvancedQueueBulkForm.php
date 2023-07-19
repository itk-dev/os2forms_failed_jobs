<?php

namespace Drupal\os2forms_failed_jobs\Plugin\views\field;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\os2forms_failed_jobs\Helper\Helper;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\field\BulkForm;
use Drupal\views\ViewExecutable;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a advanced queue operations bulk form element.
 *
 * @ViewsField("advancedqueue_bulk_form")
 */
final class AdvancedQueueBulkForm extends BulkForm {

  /**
   * Failed jobs helper.
   *
   * @var \Drupal\os2forms_failed_jobs\Helper\Helper
   */
  protected Helper $helper;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    LanguageManagerInterface $language_manager,
    MessengerInterface $messenger,
    EntityRepositoryInterface $entity_repository,
    Helper $helper
  ) {
    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $entity_type_manager,
      $language_manager,
      $messenger,
      $entity_repository
    );

    $this->helper = $helper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('language_manager'),
      $container->get('messenger'),
      $container->get('entity.repository'),
      $container->get(Helper::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, &$options = NULL): void {
    parent::init($view, $display, $options);

    $entity_type = $this->getEntityType();
    // Filter the actions to only include those for this entity type.
    // @phpstan-ignore-next-line
    $this->actions = array_filter($this->actionStorage->loadMultiple(), function ($action) use ($entity_type) {
      /** @var \Drupal\system\Entity\Action $action */
      return $action->getType() == $entity_type;
    });
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function viewsForm(&$form, FormStateInterface $form_state): void {
    // Make sure we do not accidentally cache this form.
    $form['#cache']['max-age'] = 0;

    // Add the tableselect javascript.
    $form['#attached']['library'][] = 'core/drupal.tableselect';

    // Only add the bulk form options and buttons if there are results.
    if (!empty($this->view->result)) {
      // Render checkboxes for all rows.
      $form[$this->options['id']]['#tree'] = TRUE;
      foreach ($this->view->result as $row_index => $row) {
        $form[$this->options['id']][$row_index] = [
          '#type' => 'checkbox',
          // We are not able to determine a main "title" for each row, so we can
          // only output a generic label.
          '#title' => $this->t('Update this item'),
          '#title_display' => 'invisible',
          '#default_value' => !empty($form_state->getValue($this->options['id'])[$row_index]) ? 1 : NULL,
          // @phpstan-ignore-next-line
          '#return_value' => $row->job_id,
        ];
      }

      $form['actions']['submit']['#value'] = $this->t('Apply to selected items');

      $form['header'] = [
        '#type' => 'container',
        '#weight' => -100,
      ];

      // Build the bulk operations action widget for the header.
      // Allow themes to apply .container-inline on this separate container.
      $form['header'][$this->options['id']] = [
        '#type' => 'container',
      ];
      $form['header'][$this->options['id']]['action'] = [
        '#type' => 'select',
        '#title' => $this->options['action_title'],
        '#options' => $this->getBulkOptions(),
      ];

      // Duplicate the form actions into the action container in the header.
      $form['header'][$this->options['id']]['actions'] = $form['actions'];
    }
    else {
      // Remove the default actions build array.
      unset($form['actions']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function viewsFormSubmit(&$form, FormStateInterface $form_state): void {
    if ($form_state->get('step') == 'views_form_views_form') {
      // Filter only selected checkboxes. Use the actual user input rather than
      // the raw form values array, since the site data may change before the
      // bulk form is submitted, which can lead to data loss.
      $user_input = $form_state->getUserInput();
      $selected = array_filter($user_input[$this->options['id']]);
      $entities = [];
      $action = $this->actions[$form_state->getValue('action')];
      $count = 0;

      foreach ($selected as $bulk_form_key) {
        // Skip execution if the user did not have access.
        $job = $this->helper->getJobFromId($bulk_form_key);
        if ('failure' !== $job->getState()) {
          $this->messenger->addError($this->t('Element with webform submission id: @webform_submit_id already has state: @state', [
            '@webform_submit_id' => $this->helper->getSubmissionSerialIdFromJob($bulk_form_key),
            '@state' => $job->getState(),
          ]));
          continue;
        }

        $count++;

        $entities[$bulk_form_key] = $bulk_form_key;
      }

      // If there were entities selected but the action isn't allowed on any of
      // them, we don't need to do anything further.
      if (!$count) {
        return;
      }

      /** @var \Drupal\system\Entity\Action $action */
      $action->execute($entities);

      $operation_definition = $action->getPluginDefinition();
      if (!empty($operation_definition['confirm_form_route_name'])) {
        $options = [
          'query' => $this->getDestinationArray(),
        ];
        $form_state->setRedirect($operation_definition['confirm_form_route_name'], [], $options);
      }
      else {
        // Don't display the message unless there are some elements affected and
        // there is no confirmation form.
        $this->messenger->addStatus($this->formatPlural($count, '%action was applied to @count item.', '%action was applied to @count items.', [
          '%action' => $action->label(),
        ]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityType() {
    return 'advancedqueue_queue';
  }

}
