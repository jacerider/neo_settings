<?php

namespace Drupal\neo_settings\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\neo_settings\SettingsManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Derivative class that provides the menu links for the Products.
 */
class DynamicMenuLinks extends DeriverBase implements ContainerDeriverInterface {

  /**
   * The settings manager.
   *
   * @var \Drupal\neo_settings\SettingsManagerInterface
   */
  protected $settingsManager;

  /**
   * Creates a DynamicLocalTasks object.
   *
   * @param \Drupal\neo_settings\SettingsManagerInterface $settings_manager
   *   The settings manager.
   */
  public function __construct(SettingsManagerInterface $settings_manager) {
    $this->settingsManager = $settings_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new self(
      $container->get('plugin.manager.neo_settings')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $links = [];
    foreach ($this->settingsManager->getDefinitions() as $definition) {
      if (!empty($definition['menu_title'])) {
        $links[$definition['id']] = [
          'title' => $definition['menu_title'],
          'description' => 'Configure ' . $definition['label'] . '.',
          'route_name' => 'neo.settings.plugin.' . $definition['id'] . '.config',
          'parent' => 'system.admin_config_neo',
        ] + $base_plugin_definition;
      }
    }
    return $links;
  }

}
