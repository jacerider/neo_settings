<?php

namespace Drupal\neo_settings\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\neo_settings\SettingsManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Generates neo settings-related local tasks.
 */
class DynamicLocalTasks extends DeriverBase implements ContainerDeriverInterface {
  use StringTranslationTrait;

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
    foreach ($this->settingsManager->getDefinitions() as $definition) {
      $plugin_id = $definition['id'];
      $this->derivatives["neo.settings.plugin.{$plugin_id}.config"] = [
        'route_name' => "neo.settings.plugin.{$plugin_id}.config",
        'base_route' => "neo.settings.plugin.{$plugin_id}.config",
        'title' => $this->t('Settings'),
        'weight' => -10,
      ] + $base_plugin_definition;
      if (!empty($definition['variation_allow'])) {
        $this->derivatives["neo.settings.plugin.{$plugin_id}.variations"] = [
          'route_name' => "neo.settings.plugin.{$plugin_id}.variations",
          'base_route' => "neo.settings.plugin.{$plugin_id}.config",
          'title' => ucwords($definition['variation_label_plural']),
          'weight' => -5,
        ] + $base_plugin_definition;
      }
    }

    return $this->derivatives;
  }

}
