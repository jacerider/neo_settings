<?php

namespace Drupal\neo_settings_test\Settings;

use Drupal\Core\Form\FormStateInterface;
use Drupal\neo_settings\Plugin\SettingsBase;

/**
 * Module settings.
 *
 * @Settings(
 *   id = "neo_settings_test",
 *   label = @Translation("Neo Settings Test"),
 *   config_name = "neo_settings_test.settings",
 *   menu_title = @Translation("Neo Settings Test"),
 *   route = "/admin/config/neo/neo-settings-test",
 *   admin_permission = "administer neo settings test",
 *   variation_allow = true,
 *   variation_conditions = true,
 *   variation_ordering = true,
 *   variation_scope = NULL,
 * )
 */
class NeoSettingsTestSettings extends SettingsBase {

  /**
   * {@inheritdoc}
   *
   * We define value_multiple as strict as it support multiple values with a
   * numeric key. This means that a deep merge will combine the values instead
   * of overwriting them. Declaring it as strict insures whatever values are
   * saved are used as-is.
   */
  protected $strictParents = [
    ['value_multiple'],
  ];

  /**
   * {@inheritdoc}
   */
  public function getDefaultValues() {
    $values = parent::getDefaultValues();
    return $values;
  }

  /**
   * {@inheritdoc}
   *
   * Base settings are settings that are not specific to a variation. They
   * are defined in the base form and are not editable in the variation form.
   */
  protected function buildBaseForm(array $form, FormStateInterface $form_state) {
    $form['global'] = [
      '#type' => 'select',
      '#title' => $this->t('Global'),
      '#default_value' => $this->getValue('global'),
      '#options' => [
        'one' => 'One',
        'two' => 'Two',
        'three' => 'Three',
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * Instance settings are settings that are set both in the base form and the
   * variation form. They are editable in both forms and the values are merged
   * together.
   */
  protected function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $form['input'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Input'),
      '#default_value' => $this->getValue('input'),
    ];

    $form['value_single'] = [
      '#type' => 'select',
      '#title' => $this->t('Value: Single'),
      '#default_value' => $this->getValue('value_single'),
      '#options' => [
        'one' => 'One',
        'two' => 'Two',
        'three' => 'Three',
      ],
    ];

    $form['value_multiple'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Value: Multiple'),
      '#default_value' => $this->getValue('value_multiple'),
      '#options' => [
        'one' => 'One',
        'two' => 'Two',
        'three' => 'Three',
      ],
    ];

    // Use '1' as the default value for boolean fields.
    $form['value_boolean'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Value: Boolean'),
      '#default_value' => $this->getValue('value_boolean'),
    ];

    $form['nested'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Nested'),
    ];

    $form['nested']['input'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Nested Input'),
      '#default_value' => $this->getValue(['nested', 'input']),
    ];

    $form['nested']['deep'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Deep Nested'),
    ];

    $form['nested']['deep']['input'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Deep Nested Input'),
      '#default_value' => $this->getValue(['nested', 'deep', 'input']),
    ];

    if (!$this->isVariation()) {
      $form['config_diff'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Diff from CORE to DEFAULT'),
        'diff' => [
          '#type' => 'html_tag',
          '#tag' => 'pre',
          '#value' => print_r($this->getDiffConfigValues(), TRUE),
        ],
      ];
    }
    else {
      $form['values_diff'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Diff from DEFAULT to CURRENT'),
        'diff' => [
          '#type' => 'html_tag',
          '#tag' => 'pre',
          '#value' => print_r($this->getDiffValues(), TRUE),
        ],
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function validateForm(array $form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $values = $form_state->getValues();
    if (!empty($values['value_multiple'])) {
      // We need to filter out empty values from the multiple value field and
      // store only the values that are checked.
      $values['value_multiple'] = array_values(array_filter($values['value_multiple']));
    }
    $form_state->setValues($values);
  }

}
