<?php

namespace Drupal\neo_settings\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Render\Attribute\RenderElement;
use Drupal\Core\Render\Element\RenderElementBase;

/**
 * Provides a settings element.
 *
 * The #settings_variation is optional and can be used to trigger the variation
 * display of the settings form which allows overrides.
 *
 * Usage example:
 * @code
 * $form['settings'] = [
 *   '#type' => 'neo_settings',
 *   '#title' => $this->t('Settings'),
 *   '#settings_id' => 'neo_settings',
 *   '#settings_variation' => 'block',
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
   * Processes a settings element.
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
    $settings = self::getSettings($element);
    if (!$settings) {
      return [];
    }
    /** @var \Drupal\neo_image\Settings\Settings $settings */
    $element = $settings->buildSettingsForm($element, $form_state);
    $element['#element_validate'][] = [static::class, 'elementValidate'];
    $element['#summary_attributes'] = [];
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
    $settings = self::getSettings($element);

    $subform_state = SubformState::createForSubform($element, $form, $form_state);
    $settings->validateSettingsForm($element, $subform_state);
    $values = $subform_state->getValues();
    unset($values['tabs']);
    $form_state->setValueForElement($element, $values);
  }

  /**
   * Get the settings for the element.
   *
   * @param array $element
   *   The element.
   *
   * @return \Drupal\neo_settings\Plugin\SettingsInterface|null
   *   The settings plugin.
   */
  protected static function getSettings($element) {
    /** @var \Drupal\neo_settings\SettingsManagerInterface $manager */
    $manager = \Drupal::service('plugin.manager.neo_settings');
    if (!$manager->hasDefinition($element['#settings_id'])) {
      return NULL;
    }
    /** @var \Drupal\neo_settings\Plugin\SettingsInterface $settings */
    $settings = $manager->createInstance($element['#settings_id'], $element['#default_value'], 'instance');
    if ($element['#settings_variation'] ?? NULL) {
      /** @var \Drupal\neo_settings\SettingsInterface $variation */
      $variation = \Drupal::entityTypeManager()->getStorage('neo_settings')->load($element['#settings_variation']);
      if ($variation) {
        $settings->extendConfigValues($variation->getPlugin()->getExtendedVariationValues());
      }
    }
    return $settings;
  }

}
