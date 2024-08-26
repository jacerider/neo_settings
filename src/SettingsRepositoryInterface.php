<?php

namespace Drupal\neo_settings;

/**
 * An interface defining neo settings repository classes.
 */
interface SettingsRepositoryInterface {

  /**
   * Return the active settings instance.
   *
   * @return \Drupal\neo_settings\Plugin\SettingsInterface
   *   The active settings instance.
   */
  public function getActive();

  /**
   * Return the settings instance by variation ID.
   *
   * If the variation is not found, the active settings will be returned.
   *
   * @param string $variationId
   *   The variation ID.
   * @param bool $checkAccess
   *   If true, will check access.
   *
   * @return \Drupal\neo_settings\Plugin\SettingsInterface
   *   The settings instance.
   */
  public function get($variationId, $checkAccess = TRUE);

  /**
   * Return the settings instances available as an active instance.
   *
   * @param bool $checkAccess
   *   If true, will check access.
   *
   * @return \Drupal\neo_settings\Plugin\SettingsInterface[]
   *   All available settings instances.
   */
  public function getAvailable($checkAccess = TRUE);

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
