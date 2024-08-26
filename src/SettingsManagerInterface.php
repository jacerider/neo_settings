<?php

namespace Drupal\neo_settings;

use Drupal\Component\Plugin\Discovery\CachedDiscoveryInterface;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Cache\CacheableDependencyInterface;

/**
 * Provides the Neo Settings plugin manager.
 */
interface SettingsManagerInterface extends PluginManagerInterface, CachedDiscoveryInterface, CacheableDependencyInterface {

  /**
   * Return definition by config name.
   *
   * @param string $config_name
   *   The config name.
   *
   * @return array
   *   The definition.
   */
  public function getDefinitionByConfigName($config_name);

  /**
   * {@inheritdoc}
   *
   * @param string $plugin_id
   *   The ID of the plugin being instantiated.
   * @param array $configuration
   *   An array of configuration relevant to the plugin instance.
   * @param bool $variation_id
   *   Will flag the plugin as a variation instance.
   *
   * @return \Drupal\neo_settings\Plugin\SettingsInterface
   *   The neo settings.
   */
  public function createInstance($plugin_id, array $configuration = [], $variation_id = FALSE);

  /**
   * Creates a pre-configured instance of a variation plugin.
   *
   * @param string $plugin_id
   *   The ID of the plugin being instantiated.
   * @param string $variation_id
   *   The variation id. This should be used to differentiate instances of the
   *   same plugin from it's different usages.
   * @param array $configuration
   *   An array of configuration relevant to the plugin instance.
   *
   * @return \Drupal\neo_settings\Plugin\SettingsInterface
   *   The neo settings.
   */
  public function createVariationInstance($plugin_id, $variation_id, array $configuration = []);

  /**
   * Get the default settings for a plugin.
   *
   * The config_name of the plugin should reference a YAML file located in
   * MODULE_NAME/config/install/CONFIG_NAME.yml.
   *
   * @return array
   *   An array of default settings.
   */
  public function getDefinitionDefaultSettings($definition);

}
