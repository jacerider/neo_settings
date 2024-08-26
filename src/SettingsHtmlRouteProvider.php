<?php

namespace Drupal\neo_settings;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;

/**
 * Provides routes for settings entities.
 *
 * @see Drupal\Core\Entity\Routing\AdminHtmlRouteProvider
 * @see Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider
 */
class SettingsHtmlRouteProvider extends AdminHtmlRouteProvider {
  use StringTranslationTrait;

  /**
   * The settings manager.
   *
   * @var \Drupal\neo_settings\SettingsManagerInterface
   */
  protected $settingsManager;

  /**
   * Constructs a new DefaultHtmlRouteProvider.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\neo_settings\SettingsManagerInterface $settings_manager
   *   The settings manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, SettingsManagerInterface $settings_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->settingsManager = $settings_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new self(
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('plugin.manager.neo_settings')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getRoutes(EntityTypeInterface $entity_type) {
    $collection = parent::getRoutes($entity_type);
    if ($admin_permission = $entity_type->getAdminPermission()) {
      $entity_type_id = $entity_type->id();
      foreach ($this->settingsManager->getDefinitions() as $definition) {
        $plugin_id = $definition['id'];
        if (isset($definition['admin_permission'])) {
          $admin_permission = $definition['admin_permission'];
        }

        // Base settings route.
        $route = new Route($definition['route']);
        $route->setDefaults([
          '_controller' => '\Drupal\neo_settings\Controller\SettingsController::settingsConfigForm',
          '_title_callback' => '\Drupal\neo_settings\Controller\SettingsController::settingsConfigFormTitle',
          'neo_settings_core' => TRUE,
          'plugin_id' => $plugin_id,
        ])
          ->setRequirements([
            '_permission' => $admin_permission,
          ])
          ->setOption('_admin_route', TRUE);
        $collection->add("neo.settings.plugin.{$plugin_id}.config", $route);

        if (!empty($definition['variation_allow'])) {
          // Variations route.
          $route = new Route($definition['route'] . '/variations');
          $route->setDefaults([
            '_controller' => '\Drupal\neo_settings\Controller\SettingsController::settingsEntityCollection',
            '_title_callback' => '\Drupal\neo_settings\Controller\SettingsController::settingsEntityCollectionTitle',
            'plugin_id' => $plugin_id,
          ])
            ->setRequirements([
              '_permission' => $admin_permission,
            ])
            ->setOption('_admin_route', TRUE);
          $collection->add("neo.settings.plugin.{$plugin_id}.variations", $route);

          // Add route.
          $route = new Route($definition['route'] . '/variations/add');
          $route->setDefaults([
            '_controller' => '\Drupal\neo_settings\Controller\SettingsController::settingsEntityAddForm',
            '_title_callback' => '\Drupal\neo_settings\Controller\SettingsController::settingsEntityAddFormTitle',
            'neo_settings_core' => TRUE,
            'plugin_id' => $plugin_id,
          ])
            ->setRequirements([
              '_permission' => $admin_permission,
            ])
            ->setOption('_admin_route', TRUE);
          $collection->add("neo.settings.plugin.{$plugin_id}.variations.add", $route);

          // Edit route.
          $route = new Route($definition['route'] . '/variations/{neo_settings}/edit');
          $route->setDefaults([
            '_controller' => '\Drupal\neo_settings\Controller\SettingsController::settingsEntityEditForm',
            '_title_callback' => '\Drupal\neo_settings\Controller\SettingsController::settingsEntityEditFormTitle',
            'plugin_id' => $plugin_id,
          ])
            ->setRequirement('_entity_access', "{$entity_type_id}.update")
            ->setOption('parameters', [
              $entity_type_id => ['type' => 'entity:' . $entity_type_id],
            ])
            ->setOption('_admin_route', TRUE);
          $collection->add("neo.settings.plugin.{$plugin_id}.variations.edit", $route);

          // Delete route.
          $route = new Route($definition['route'] . '/variations/{neo_settings}/delete');
          $route->setDefaults([
            '_controller' => '\Drupal\neo_settings\Controller\SettingsController::settingsEntityDeleteForm',
            '_title_callback' => '\Drupal\neo_settings\Controller\SettingsController::settingsEntityDeleteFormTitle',
            'plugin_id' => $plugin_id,
          ])
            ->setRequirement('_entity_access', "{$entity_type_id}.delete")
            ->setOption('parameters', [
              $entity_type_id => ['type' => 'entity:' . $entity_type_id],
            ])
            ->setOption('_admin_route', TRUE);
          $collection->add("neo.settings.plugin.{$plugin_id}.variations.delete", $route);
        }
      }
    }
    return $collection;
  }

}
