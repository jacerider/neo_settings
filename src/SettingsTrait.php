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
   * @var \Drupal\neo_settings\Plugin\SettingsInterface
   */
  protected SettingsInterface $settings;

  /**
   * Gets the settings.
   *
   * @return \Drupal\neo_settings\Plugin\SettingsInterface
   *   The settings.
   */
  protected function getSettings(array $settings = [], string $variationId = NULL): SettingsInterface {
    if (!isset($this->settings)) {
      assert(isset($this->settingsId), 'Settings ID is not set.');
      /** @var \Drupal\neo_settings\SettingsRepositoryInterface $repository */
      $repository = \Drupal::service($this->settingsId);
      $this->settings = isset($variationId) ? $repository->get($variationId) : $repository->getActive();
      if ($settings) {
        // If settings are supplied, we overlay them on top of all other
        // settings.
        $this->settings = clone $this->settings;
        $this->settings->extendInstanceValues($settings);
      }
    }
    return $this->settings;
  }

}
