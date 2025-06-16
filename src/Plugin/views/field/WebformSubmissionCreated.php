<?php

namespace Drupal\os2forms_failed_jobs\Plugin\views\field;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\os2forms_failed_jobs\Helper\Helper;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\field\Date;
use Drupal\views\Render\ViewsRenderPipelineMarkup;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Field handler to render submission created for a given job.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("advancedqueue_job_submission_created")
 */
class WebformSubmissionCreated extends Date {

  /**
   * Class constructor.
   *
   * @phpstan-param array<string, mixed> $configuration
   */
  public function __construct(
    $configuration,
    $plugin_id,
    $plugin_definition,
    DateFormatterInterface $date_formatter,
    EntityStorageInterface $date_format_storage,
    protected Helper $helper,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $date_formatter, $date_format_storage);
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
      $container->get('date.formatter'),
      $container->get('entity_type.manager')->getStorage('date_format'),
      $container->get(Helper::class),
    );
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<string, mixed> $options
   * @phpstan-return void
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, ?array &$options = NULL): void {
    parent::init($view, $display, $options);

    $this->additional_fields['webform_submission_id'] = [
      'table' => 'os2forms_failed_jobs_queue_submission_relation',
      'field' => 'submission_id',
    ];
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-return void
   */
  public function query() {}

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account) {
    return $account->hasPermission('access webform advanced queue overview');
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function render(ResultRow $values): MarkupInterface|string|ViewsRenderPipelineMarkup {

    $value = isset($values->job_id) ? $this->helper->getSubmissionCreatedFromJob($values->job_id) : NULL;

    if (is_null($value)) {
      return '';
    }

    // Lifted from Drupal\views\Plugin\views\field\Date.
    $format = $this->options['date_format'];

    $custom_format = in_array($format, [
      'custom',
      'raw time ago',
      'time ago',
      'raw time hence',
      'time hence',
      'raw time span',
      'time span',
      'raw time span',
      'inverse time span',
      'time span',
    ]) ? $this->options['custom_date_format'] : NULL;

    $timezone = !empty($this->options['timezone']) ? $this->options['timezone'] : NULL;
    // Will be positive for a datetime in the past (ago), and negative for a
    // datetime in the future (hence).
    $time_diff = $this->time->getRequestTime() - $value;
    switch ($format) {
      case 'raw time ago':
        return $this->dateFormatter->formatTimeDiffSince($value, [
          'granularity' => is_numeric($custom_format) ? $custom_format : 2,
        ]);

      case 'time ago':
        return $this->t('%time ago', [
          '%time' => $this->dateFormatter->formatTimeDiffSince($value, [
            'granularity' => is_numeric($custom_format) ? $custom_format : 2,
          ]),
        ]);

      case 'raw time hence':
        return $this->dateFormatter->formatTimeDiffUntil($value, [
          'granularity' => is_numeric($custom_format) ? $custom_format : 2,
        ]);

      case 'time hence':
        return $this->t('%time hence', [
          '%time' => $this->dateFormatter->formatTimeDiffUntil($value, [
            'granularity' => is_numeric($custom_format) ? $custom_format : 2,
          ]),
        ]);

      case 'raw time span':
        return ($time_diff < 0 ? '-' : '') . $this->dateFormatter->formatTimeDiffSince($value, [
          'strict' => FALSE,
          'granularity' => is_numeric($custom_format) ? $custom_format : 2,
        ]);

      case 'inverse time span':
        return ($time_diff > 0 ? '-' : '') . $this->dateFormatter->formatTimeDiffSince($value, [
          'strict' => FALSE,
          'granularity' => is_numeric($custom_format) ? $custom_format : 2,
        ]);

      case 'time span':
        $time = $this->dateFormatter->formatTimeDiffSince($value, [
          'strict' => FALSE,
          'granularity' => is_numeric($custom_format) ? $custom_format : 2,
        ]);
        return ($time_diff < 0) ? $this->t('%time hence', [
          '%time' => $time,
        ]) : $this->t('%time ago', ['%time' => $time]);

      case 'custom':
        if ($custom_format == 'r') {
          return $this->dateFormatter->format($value, $format, $custom_format, $timezone, 'en');
        }
        return $this->dateFormatter->format($value, $format, $custom_format, $timezone);

      default:
        return $this->dateFormatter->format($value, $format, '', $timezone);
    }
  }

}
