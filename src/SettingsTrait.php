<?php

namespace Drupal\neo_settings;

use Drupal\neo_settings\Plugin\SettingsInterface;

/**
 * Implements MarkupInterface and Countable for rendered objects.
 *
 * @see \Drupal\Component\Render\MarkupInterface
 */
trait SettingsTrait {

  /**
   * The settings.
   *
   * @var \Drupal\neosettings\Plugin\SettingsInterface
   */
  protected SettingsInterface $settings;

  /**
   * The settings variation ID.
   *
   * @var string
   */
  protected string $settingsVariationId;

  /**
   * Gets the settings.
   *
   * @return \Drupal\neo_settings\Plugin\SettingsInterface
   *   The settings.
   */
  protected function getSettings() {
    if (!isset($this->settings)) {
      if (!isset($this->settingsId)) {
        throw new \Exception('Settings ID is not set.');
      }
      /** @var \Drupal\neo_settings\SettingsRepositoryInterface $repository */
      $repository = \Drupal::service($this->settingsId);
      $this->settings = isset($this->settingsVariationId) ? $repository->get($this->settingsVariationId) : $repository->getActive();
    }

    return $this->settings;
  }

  /**
   * Sets the setting variation id.
   *
   * @param string $variationId
   *   The settings.
   *
   * @return $this
   */
  protected function setSettingsVariationId($variationId):self {
    $this->settingsVariationId = $variationId;
    return $this;
  }

}
