<?php

namespace Drupal\neo_settings;

use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Component\Serialization\Yaml;

/**
 * Provides the Neo Settings plugin manager.
 */
class SettingsManager extends DefaultPluginManager implements SettingsManagerInterface {

  /**
   * Provides default values for all neo_style_variable plugins.
   *
   * @var array
   */
  protected $defaults = [
    'id' => '',
    'label' => '',
    'config_name' => '',
    'route' => '',
    'menu_title' => NULL,
    'admin_permission' => 'administer site configuration',
    'variation_allow' => FALSE,
    'variation_label' => 'variation',
    'variation_label_plural' => 'variations',
    'variation_conditions' => TRUE,
    'variation_ordering' => TRUE,
    'variation_scope' => NULL,
    'handlers' => [],
    'configuration' => [],
  ];

  /**
   * Constructs a new SettingsManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
    protected readonly ConfigFactoryInterface $configFactory
  ) {
    parent::__construct('Settings', $namespaces, $module_handler, 'Drupal\neo_settings\Plugin\SettingsInterface', 'Drupal\neo_settings\Annotation\Settings');
    $this->alterInfo('neo_settings_info');
    $this->setCacheBackend($cache_backend, 'neo_settings_plugins');
  }

  /**
   * {@inheritDoc}
   */
  public function getDefinitionByConfigName($config_name) {
    foreach ($this->getDefinitions() as $definition) {
      if ($definition['config_name'] === $config_name) {
        return $definition;
      }
    }
    return NULL;
  }

  /**
   * {@inheritDoc}
   */
  public function processDefinition(&$definition, $plugin_id) {
    parent::processDefinition($definition, $plugin_id);
    $definition['configuration'] = $this->getDefinitionDefaultSettings($definition);
    if (empty($definition['route'])) {
      $definition['route'] = 'admin/config/neo/' . $plugin_id;
    }
  }

  /**
   * {@inheritDoc}
   */
  public function createInstance($plugin_id, array $configuration = [], string $variation_id = '') {
    $definition = $this->getDefinition($plugin_id);
    $configuration = [
      'config' => !empty($definition['config_name']) ? $this->configFactory->get($definition['config_name'])->getRawData() : [],
      'variation' => $configuration,
      'variation_id' => $variation_id,
    ];
    $instance = parent::createInstance($plugin_id, $configuration);
    $instance->addCacheTags(['config:' . $definition['config_name']]);
    return $instance;
  }

  /**
   * {@inheritDoc}
   */
  public function createVariationInstance($plugin_id, $variation_id, array $configuration = []) {
    return $this->createInstance($plugin_id, $configuration, $variation_id);
  }

  /**
   * {@inheritDoc}
   */
  public function getDefinitionDefaultSettings($definition) {
    $settings = [];
    // Base config is not required.
    if (!empty($definition['config_name'])) {
      $file = $this->moduleHandler->getModule($definition['provider'])->getPath() . '/config/install/' . $definition['config_name'] . '.yml';
      if (file_exists($file)) {
        $settings = (Yaml::decode(file_get_contents($file)) ?? []) + $settings;
      }
    }
    return $settings;
  }

}
