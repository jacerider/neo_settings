<?php

namespace Drupal\neo_settings;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultSingleLazyPluginCollection;

/**
 * Provides a collection of settings plugins.
 */
class SettingsPluginCollection extends DefaultSingleLazyPluginCollection {

  /**
   * The manager used to instantiate the plugins.
   *
   * @var \Drupal\neo_settings\SettingsManagerInterface
   */
  protected $manager;

  /**
   * The settings ID this plugin collection belongs to.
   *
   * @var string
   */
  protected $settingsId;

  /**
   * Constructs a new BlockPluginCollection.
   *
   * @param \Drupal\Component\Plugin\PluginManagerInterface $manager
   *   The manager to be used for instantiating plugins.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param string $instance_id
   *   The ID of the plugin instance.
   * @param array $configuration
   *   An array of configuration.
   * @param string $settings_id
   *   The unique ID of the settings entity using this plugin.
   */
  public function __construct(
    PluginManagerInterface $manager,
    private readonly ModuleHandlerInterface $moduleHandler,
    $instance_id,
    array $configuration,
    $settings_id
  ) {
    $this->settingsId = $settings_id ?: 'new';
    parent::__construct($manager, $instance_id, $configuration);
  }

  /**
   * {@inheritdoc}
   *
   * @return \Drupal\neo_settings\Plugin\SettingsInterface
   *   The settings plugin.
   */
  public function &get($instance_id) { // phpcs:ignore
    return parent::get($instance_id);
  }

  /**
   * {@inheritdoc}
   */
  protected function initializePlugin($instance_id) {
    if (!$instance_id) {
      throw new PluginException("The settings '{$this->settingsId}' did not specify a plugin.");
    }

    try {
      $this->set($instance_id, $this->manager->createVariationInstance($instance_id, $this->settingsId, $this->configuration));
    }
    catch (PluginException $e) {
      $module = $this->configuration['provider'];
      // Ignore settingss belonging to uninstalled modules, but re-throw valid
      // exceptions when the module is installed and the plugin is
      // misconfigured.
      if (!$module || $this->moduleHandler->moduleExists($module)) {
        throw $e;
      }
    }
  }

}
