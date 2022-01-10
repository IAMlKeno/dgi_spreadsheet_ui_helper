<?php

namespace Drupal\dgi_spreadsheet_ui_helper\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

use Drupal\Core\Entity\EntityTypeManagerInterface;

use Symfony\Component\HttpFoundation\BinaryFileResponse;


/**
 * Form to generate a spreadsheet template.
 */
class GenerateOptions extends FormBase {

  const MANDATORY_HEADERS = [
    'ID' => [
      'display_label' => 'ID',
      'example' => 1
    ],
    'title' => [
      'display_label' => 'Title',
      'example' => 'Lorum Ipsum|abbreviated',
    ],
    'model' => [
      'display_label' => 'Model',
      'example' => 'Audio',
    ],
    'resource_type' => [
      'display_label' => 'Resource Type',
      'example' => 'Collection',
    ],
  ];

  /**
   * An array of header options for user to select from.
   *
   * @var array
   */
  protected $headerOptions;

  /**
   * An array of examples to be optionally included in the CSV.
   *
   * @var array
   */
  protected $examples;

  /**
   * An associative array of fields parsed from the included fields.json.
   *
   * @var array
   */
  private $field_json_info;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'islandora_spreadsheet_ingest_generate_template_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $path = drupal_get_path('module', 'dgi_spreadsheet_ui_helper') . "/fixtures/fields.json";
    if ($content = file_get_contents($path)) {
      $this->field_json_info = json_decode($content, TRUE);

      // If null or FALSE is returned, bail out with error.
      if (is_null($this->field_json_info) || !$this->field_json_info) {
        \Drupal::messenger()->addMessage('Failed to build form.');
        return;
      }
      unset($content);
    }

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('CSV name'),
      '#maxlength' => 255,
      '#default_value' => "Sample CSV template",
      '#description' => $this->t("Label for the Example."),
      '#required' => TRUE,
    ];

    $form['include_examples'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include examples?'),
    ];

    $form['display_descriptions'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display descriptions?'),
      '#attributes' => [
        'class' => ['_display-descriptions'],
      ],
    ];

    $mandatory_str = implode(', ', array_keys(self::MANDATORY_HEADERS));
    $this->prepareOptions();
    $form['headers'] = [
      '#type' => 'checkboxes',
      '#options' => $this->headerOptions,
      '#title' => $this->t('The headers of the spreadsheet'),
      '#description' => $this->t('Mandaory fields included: @mandatory', ['@mandatory' => $mandatory_str]),
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate'),
      '#button_type' => 'primary',
    ];

    $form['#attached'] = [
      'library' => ['dgi_spreadsheet_ui_helper/dgi_spreadsheet_ui_helper'],
      'drupalSettings' => [],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $file = NULL;
    $values = $form_state->getValues();
    $file_label = preg_replace('/\W+/', '_', $values['label']);

    try {
      // Output into a csv file
      $filename = "$file_label.csv";
      $public = \Drupal::service('file_system')->realpath("public://");
      $file_path = "$public/$filename";

      $keys = array_keys(self::MANDATORY_HEADERS);
      $filtered_opts = array_filter($values['headers'], function ($value) {
        return $value;
      });
      $headers = array_merge($keys, array_keys($filtered_opts));

      if ($file = fopen("{$file_path}", 'w+')) {
        $length = fputcsv($file, $headers);
        if ($length) {
          // If include_examples is checked, prepare the values and write it.
          if ($values['include_examples']) {
            $this->prepareExamples();
            fputcsv($file, $this->examples);
          }

          $file_data = stream_get_meta_data($file);
          $uri = $file_data['uri'];
          $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment;filename="' . $filename . '"',
          ];
          $form_state->setResponse(new \Symfony\Component\HttpFoundation\BinaryFileResponse($uri, 200, $headers, true));
        }
      }
    }
    catch (\Exception $e) {
      \Drupal::messenger()->addMessage($e->getMessage());
    }
    finally {
      fclose($file);
    }

  }

  /**
   * Prepared CSV header options to be displayed.
   */
  protected function prepareOptions() {
    $headers = [];
    foreach($this->field_json_info as $option_value => $data) {
      $headers[$option_value] = "<span title='{$data['description']}'>" . $data['display_label'] . "<br /><span class='option-description'>{$data['description']}</span></span>";
    }
    $this->headerOptions = $headers;
  }

  /**
   * Prepare examples to be added to the CSV file.
   */
  protected function prepareExamples() {
    $success = FALSE;
    $examples = [];
    // Prep mandatory fields.
    foreach (self::MANDATORY_HEADERS as $key => $value) {
      $examples[$key] = $value['example'];
    }
    foreach($this->field_json_info as $option_value => $data) {
      $examples[$option_value] = $data['example'];
    }
    $this->examples = $examples;
    $success = TRUE;

    if (!$success) {
      \Drupal::messenger()->addMessage('Failed to prepare examples.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $headers = $form_state->getValue('headers');
    // Verify that at least on member of field is selected.
    if (
      !$headers['member_of'] &&
      !$headers['member_of_existing_entity'] &&
      !$headers['member_of_existing_entity_id']
    ) {
      $form_state->setErrorByName(
        'headers',
        $this->t('At least one of the member of fields must be selected.')
      );
    }
  }
}
