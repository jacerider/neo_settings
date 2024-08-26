<?php

namespace Drupal\neo_settings\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\neo_settings\SettingsManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides local action definitions for all config plugins.
 */
class DynamicLocalActions extends DeriverBase implements ContainerDeriverInterface {
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
      if (empty($definition['variation_allow'])) {
        continue;
      }
      $plugin_id = $definition['id'];
      $this->derivatives["neo.settings.plugin.{$plugin_id}.variations.add"] = [
        'route_name' => "neo.settings.plugin.{$plugin_id}.variations.add",
        'title' => $this->t('Add @label', ['@label' => ucwords($definition['variation_label'])]),
        'appears_on' => ["neo.settings.plugin.{$plugin_id}.variations"],
      ] + $base_plugin_definition;
    }
    return $this->derivatives;
  }

}
