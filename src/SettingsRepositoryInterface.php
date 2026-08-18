<?php

namespace Drupal\neo_settings;

/**
 * An interface defining neo settings repository classes.
 */
interface SettingsRepositoryInterface {

  /**
   * Return the active settings instance.
   *
   * @param bool $checkAccess
   *   If true, will check access.
   *
   * @return \Drupal\neo_settings\Plugin\SettingsInterface
   *   The active settings instance.
   */
  public function getActive($checkAccess = TRUE);

  /**
   * Return the settings instance by variation ID.
   *
   * Accepts either the full id or the short form without the plugin prefix. A
   * disabled variation counts as a miss.
   *
   * @param string $variationId
   *   The variation ID.
   * @param bool $checkAccess
   *   If true, will check access.
   *
   * @return \Drupal\neo_settings\Plugin\SettingsInterface|null
   *   The settings instance, or NULL when the variation does not resolve.
   */
  public function get($variationId, $checkAccess = TRUE);

  /**
   * Return all settings.
   *
   * @param bool $checkAccess
   *   If true, will check access.
   *
   * @return \Drupal\neo_settings\Plugin\SettingsInterface[]
   *   All settings instances.
   */
  public function getAll($checkAccess = TRUE);

  /**
   * Return the core settings.
   *
   * @return \Drupal\neo_settings\Plugin\SettingsInterface
   *   The core settings instance.
   */
  public function getCore();

  /**
   * Return all settings variations.
   *
   * @param bool $checkAccess
   *   If true, will check access.
   *
   * @return \Drupal\neo_settings\Plugin\SettingsInterface[]
   *   All settings variations.
   */
  public function getVariations($checkAccess = TRUE);

  /**
   * Return all settings variation entities.
   *
   * @return \Drupal\neo_settings\SettingsInterface[]
   *   All settings variation entities.
   */
  public function getVariationEntities();

}
