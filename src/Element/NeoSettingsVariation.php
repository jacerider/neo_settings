<?php

namespace Drupal\neo_settings\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\Render\Element\Select;

/**
 * Provides a form element for a drop-down menu for variation selection.
 */
#[FormElement('neo_settings_variation')]
class NeoSettingsVariation extends Select {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $options = parent::getInfo() + [
      '#settings_repository_id' => '',
    ];
    unset($options['#options']);
    return $options;
  }

  /**
   * {@inheritDoc}
   */
  public static function processSelect(&$element, FormStateInterface $form_state, &$complete_form) {
    if (empty($element['#settings_repository_id']) || !\Drupal::hasService($element['#settings_repository_id'])) {
      $element['#access'] = FALSE;
      return $element;
    }
    if ($options = static::getOptions($element['#settings_repository_id'])) {
      $element['#options'] = $options;
    }
    else {
      $element['#access'] = FALSE;
    }
    $element = parent::processSelect($element, $form_state, $complete_form);
    return $element;
  }

  /**
   * {@inheritDoc}
   */
  public static function preRenderAjaxForm($element) {
    if (isset($element['#ajax']) && !isset($element['#ajax']['event'])) {
      $element['#ajax']['event'] = 'change';
    }
    return parent::preRenderAjaxForm($element);
  }

  /**
   * Get options.
   *
   * @param string $settings_repository_id
   *   The settings repository ID.
   *
   * @return array
   *   An array of options.
   */
  public static function getOptions($settings_repository_id):array {
    $options = [];
    /** @var \Drupal\neo_settings\SettingsRepositoryInterface $repository */
    $repository = \Drupal::service($settings_repository_id);
    $variations = $repository->getVariationEntities();
    if ($variations) {
      $options = [
        '' => t('- Default -'),
      ];
      foreach ($variations as $settings) {
        $options[$settings->id()] = $settings->label();
      }
    }
    return $options;
  }

}
