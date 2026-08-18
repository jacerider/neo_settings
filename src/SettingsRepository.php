<?php

namespace Drupal\neo_settings;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\AdminContext;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\neo_settings\Plugin\SettingsInterface as SettingsPluginInterface;

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
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The admin route context.
   *
   * @var \Drupal\Core\Routing\AdminContext
   */
  protected $adminContext;

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
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Routing\AdminContext $admin_context
   *   The admin route context.
   * @param string $plugin_id
   *   The plugin id. Must stay last: child services append it to the abstract
   *   parent's argument list.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    SettingsManagerInterface $settings_manager,
    RouteMatchInterface $route_match,
    AccountInterface $current_user,
    AdminContext $admin_context,
    $plugin_id,
  ) {
    $this->pluginId = $plugin_id;
    $this->pluginDefinition = $settings_manager->getDefinition($plugin_id);
    $this->storage = $entity_type_manager->getStorage('neo_settings');
    $this->coreSettings = $settings_manager->createInstance($plugin_id);
    $this->routeMatch = $route_match;
    $this->currentUser = $current_user;
    $this->adminContext = $admin_context;
  }

  /**
   * Applies front/back scope to a resolved settings instance.
   *
   * Scope merges a designated variation's values in depending on whether the
   * request is being served to an administrator on an admin route. It belongs
   * here rather than on the plugin because it is a property of "which settings
   * apply to this request", which is what this class decides.
   *
   * @param \Drupal\neo_settings\Plugin\SettingsInterface $plugin
   *   The resolved settings instance.
   *
   * @return \Drupal\neo_settings\Plugin\SettingsInterface
   *   The same instance, with scope applied when it is enabled and resolvable.
   */
  protected function applyScope(SettingsPluginInterface $plugin) {
    $scopeKey = $plugin->getVariationScopeKey();
    if (!$scopeKey || !$plugin->getValue($scopeKey)) {
      return $plugin;
    }
    $targetId = $this->useBackScope()
      ? $plugin->getValue('neo_settings_scope_back')
      : $plugin->getValue('neo_settings_scope_front');
    if (!$targetId || $targetId === $plugin->id()) {
      return $plugin;
    }
    /** @var \Drupal\neo_settings\SettingsInterface|null $target */
    $target = $this->storage->load($targetId);
    if (!$target) {
      return $plugin;
    }
    $settings = $target->getSettings();
    // Never copy the target's own enable flag onto the consumer, or scope would
    // chain from one variation to the next.
    unset($settings[$scopeKey]);
    $plugin->extendConfigValues($settings);
    return $plugin;
  }

  /**
   * Whether the backend scope target applies to this request.
   *
   * @return bool
   *   TRUE when the current user is on an admin route and may see the admin
   *   theme, in which case the backend target is used.
   */
  protected function useBackScope() {
    if (!$this->currentUser->hasPermission('view the administration theme')) {
      return FALSE;
    }
    return $this->adminContext->isAdminRoute();
  }

  /**
   * {@inheritDoc}
   */
  public function getActive($checkAccess = TRUE) {
    if (!isset($this->settings)) {
      // Return settings directly from route if found.
      $settings_from_route = $this->routeMatch->getParameter('neo_settings');
      if ($settings_from_route instanceof SettingsInterface && $settings_from_route->getPluginId() == $this->pluginId) {
        $this->settings = $this->applyScope($settings_from_route->getPlugin());
        return $this->settings;
      }
      // Return the core settings if route is flagged as such.
      $routeObject = $this->routeMatch->getRouteObject();
      if ($routeObject && $routeObject->getDefault('neo_settings_core')) {
        $this->settings = $this->applyScope($this->getCore());
        return $this->settings;
      }
      // When conditions are not allowed, return the core settings. Use ::get()
      // to load a specific variation.
      if (empty($this->pluginDefinition['variation_conditions'])) {
        // When variation conditions are not allowed, return the clone core
        // settings. We clone them to prevent changes to the core settings.
        $this->settings = $this->applyScope($this->getCore());
      }
      else {
        $settings = $this->getAll($checkAccess);
        $this->settings = $this->applyScope(reset($settings));
      }
    }
    return $this->settings;
  }

  /**
   * {@inheritDoc}
   */
  public function get($variationId, $checkAccess = TRUE) {
    $settings = $this->getAll($checkAccess);
    // Try the id as given first, so the bare plugin id still resolves to the
    // core instance, then fall back to the short form without the plugin
    // prefix. Testing the prefix with substr() instead would treat a variation
    // whose own name begins with the plugin id as already-prefixed and miss it.
    if (isset($settings[$variationId])) {
      return $this->applyScope($settings[$variationId]);
    }
    $prefixed = $settings[$this->getCore()->id() . '_' . $variationId] ?? NULL;
    return $prefixed ? $this->applyScope($prefixed) : NULL;
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
