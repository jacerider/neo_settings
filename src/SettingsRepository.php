<?php

namespace Drupal\neo_settings;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Provides an neo settings repository.
 */
class SettingsRepository implements SettingsRepositoryInterface {

  /**
   * The plugin id.
   *
   * @var string
   */
  protected $pluginId;

  /**
   * The plugin definitions.
   *
   * @var array
   */
  protected $pluginDefinition;

  /**
   * The settings entity storage.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface
   */
  protected $storage;

  /**
   * The Neo settings.
   *
   * @var \Drupal\neo_settings\Plugin\SettingsInterface
   */
  protected $coreSettings;

  /**
   * The route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The active settings.
   *
   * @var \Drupal\neo_settings\Plugin\SettingsInterface
   */
  protected $settings;

  /**
   * Static cache of variations.
   *
   * @var array
   */
  protected $variations;

  /**
   * Constructs a new SettingsRepository object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\neo_settings\SettingsManagerInterface $settings_manager
   *   The neo settings manager.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param string $plugin_id
   *   The plugin id.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    SettingsManagerInterface $settings_manager,
    RouteMatchInterface $route_match,
    $plugin_id
  ) {
    $this->pluginId = $plugin_id;
    $this->pluginDefinition = $settings_manager->getDefinition($plugin_id);
    $this->storage = $entity_type_manager->getStorage('neo_settings');
    $this->coreSettings = $settings_manager->createInstance($plugin_id);
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritDoc}
   */
  public function getActive($checkAccess = TRUE) {
    if (!isset($this->settings)) {
      // Return settings directly from route if found.
      $settings_from_route = $this->routeMatch->getParameter('neo_settings');
      if ($settings_from_route instanceof SettingsInterface && $settings_from_route->getPluginId() == $this->pluginId) {
        $this->settings = $settings_from_route->getPlugin();
        return $this->settings;
      }
      // Return the core settings if route is flagged as such.
      $routeObject = $this->routeMatch->getRouteObject();
      if ($routeObject && $routeObject->getDefault('neo_settings_core')) {
        $this->settings = $this->getCore();
        return $this->settings;
      }
      // When conditions are not allowed, return the core settings. Use ::get()
      // to load a specific variation.
      if (empty($this->pluginDefinition['variation_conditions'])) {
        // When variation conditions are not allowed, return the clone core
        // settings. We clone them to prevent changes to the core settings.
        $this->settings = $this->getCore();
      }
      else {
        $settings = $this->getAvailable($checkAccess);
        $this->settings = reset($settings);
      }
    }
    return $this->settings;
  }

  /**
   * {@inheritDoc}
   */
  public function get($variationId, $checkAccess = TRUE) {
    $coreId = $this->getCore()->id();
    if (substr($variationId, 0, strlen($coreId)) !== $coreId) {
      // Allow provided a variation ID without the core ID.
      $variationId = $coreId . '_' . $variationId;
    }
    $settings = $this->getAvailable($checkAccess);
    return $settings[$variationId] ?? $this->getActive($checkAccess);
  }

  /**
   * {@inheritDoc}
   */
  public function getAvailable($checkAccess = TRUE) {
    return $this->getAll($checkAccess);
  }

  /**
   * {@inheritDoc}
   */
  public function getAll($checkAccess = TRUE) {
    return $this->getVariations($checkAccess) + [
      $this->getCore()->id() => $this->getCore(),
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function getCore() {
    return $this->coreSettings;
  }

  /**
   * {@inheritDoc}
   */
  public function getVariations($checkAccess = TRUE) {
    if (empty($this->pluginDefinition['variation_allow'])) {
      return [];
    }
    $key = $checkAccess ? 'access' : 'all';
    if (!isset($this->variations[$key])) {
      $this->variations[$key] = [];
      foreach ($this->getVariationEntities() as $variation) {
        if (!$checkAccess || $variation->access('view', NULL, TRUE)->isAllowed()) {
          $plugin = $variation->getPlugin();
          $this->variations[$key][$plugin->id()] = $plugin;
        }
      }
    }
    return $this->variations[$key];
  }

  /**
   * {@inheritDoc}
   */
  public function getVariationEntities() {
    /** @var \Drupal\neo_settings\SettingsInterface[] $variations */
    $variations = $this->storage->loadByProperties([
      'status' => 1,
      'plugin' => $this->pluginId,
    ]);
    if (!empty($variations)) {
      uasort($variations, 'Drupal\neo_settings\Entity\Settings::sort');
    }
    return $variations;
  }

}
