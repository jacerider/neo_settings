<?php

namespace Drupal\neo_settings\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Render\Attribute\RenderElement;
use Drupal\Core\Render\Element\RenderElementBase;

/**
 * Provides a settings element.
 *
 * Usage example:
 * @code
 * $form['settings'] = [
 *   '#type' => 'neo_settings',
 *   '#title' => $this->t('Settings'),
 *   '#settings_id' => 'neo_settings',
 * ];
 * @endcode
 */
#[RenderElement('neo_settings')]
class NeoSettings extends RenderElementBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = static::class;
    return [
      '#title' => '',
      '#settings_id' => '',
      // Useful for passing in configuration to the settings form.
      '#settings_config' => '',
      '#default_value' => [],
      '#process' => [
        [$class, 'processGroup'],
        [$class, 'processAjaxForm'],
        [$class, 'processSettings'],
      ],
      '#pre_render' => [
        [$class, 'preRenderGroup'],
      ],
      '#value' => NULL,
      '#open' => FALSE,
      '#theme_wrappers' => ['details'],
    ];
  }

  /**
   * Processes a modal element.
   *
   * @param array $element
   *   An associative array containing the properties and children of the
   *   modal.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   *
   * @return array
   *   The processed element.
   */
  public static function processSettings(&$element, FormStateInterface $form_state, &$complete_form) {
    if (empty($element['#settings_id'])) {
      return [];
    }
    /** @var \Drupal\neo_settings\SettingsManagerInterface $manager */
    $manager = \Drupal::service('plugin.manager.neo_settings');
    if (!$manager->hasDefinition($element['#settings_id'])) {
      return [];
    }
    /** @var \Drupal\neo_settings\Plugin\SettingsInterface $settings */
    $settings = $manager->createInstance($element['#settings_id'], $element['#default_value']);
    /** @var \Drupal\neo_image\Settings\Settings $settings */
    $element = $settings->buildSettingsForm($element, $form_state);
    $element['#element_validate'][] = [static::class, 'elementValidate'];
    // Open the detail if specified or if a child has an error.
    if (!empty($element['#open']) || !empty($element['#children_errors'])) {
      $element['#attributes']['open'] = 'open';
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function elementValidate($element, FormStateInterface $form_state, $form) {
    /** @var \Drupal\neo_settings\SettingsManagerInterface $manager */
    $manager = \Drupal::service('plugin.manager.neo_settings');
    /** @var \Drupal\neo_settings\Plugin\SettingsInterface $settings */
    $settings = $manager->createInstance($element['#settings_id'], $element['#default_value']);

    $subform_state = SubformState::createForSubform($element, $form, $form_state);
    $settings->validateSettingsForm($element, $subform_state);
    $form_state->setValueForElement($element, $subform_state->getValues());
  }

}
