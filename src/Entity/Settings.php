<?php

namespace Drupal\neo_settings\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityWithPluginCollectionInterface;
use Drupal\neo\VisibilityEntityTrait;
use Drupal\neo_settings\SettingsInterface;
use Drupal\neo_settings\SettingsPluginCollection;

/**
 * Defines the Settings entity.
 *
 * @ConfigEntityType(
 *   id = "neo_settings",
 *   label = @Translation("Settings"),
 *   label_plural = @Translation("Settings"),
 *   label_singular = @Translation("Settings"),
 *   handlers = {
 *     "access" = "Drupal\neo_settings\SettingsAccessControlHandler",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\neo_settings\SettingsListBuilder",
 *     "form" = {
 *       "default" = "Drupal\neo_settings\Form\SettingsForm",
 *       "delete" = "Drupal\neo_settings\Form\SettingsDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\neo_settings\SettingsHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "variation",
 *   admin_permission = "administer site configuration",
 *   static_cache = TRUE,
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "status" = "status",
 *     "weight" = "weight"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "parent",
 *     "plugin",
 *     "settings",
 *     "visibility",
 *     "weight",
 *     "lock",
 *   }
 * )
 */
class Settings extends ConfigEntityBase implements SettingsInterface, EntityWithPluginCollectionInterface {
  use VisibilityEntityTrait;

  /**
   * The Settings ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Settings label.
   *
   * @var string
   */
  protected $label;

  /**
   * The entity this entity extends.
   *
   * @var string
   */
  protected $parent;

  /**
   * The settings plugin id.
   *
   * @var string
   */
  protected $plugin;

  /**
   * The plugin instance settings.
   *
   * @var array
   */
  protected $settings = [];

  /**
   * The weight of these settings in relation to other settings.
   *
   * @var int
   */
  protected $weight = 0;

  /**
   * The lock status of these settings.
   *
   * @var bool
   */
  protected $lock = FALSE;

  /**
   * The plugin collection that holds the block plugin for this entity.
   *
   * @var \Drupal\neo_settings\SettingsPluginCollection
   */
  protected $pluginCollection;

  /**
   * {@inheritdoc}
   */
  public function save() {
    if (empty($this->id)) {
      $this->id = $this->generateId();
    }
    return parent::save();
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);
    $this->pluginCollection = NULL;
    $this->getPlugin()->save();
    if (!isset($this->settingsPluginOperationSkip)) {
      foreach ($this->getChildren() as $child) {
        $child->settingsPluginOperationSkip = TRUE;
        $child->save();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities) {
    /** @var \Drupal\neo_settings\SettingsInterface[] $entities */
    parent::postDelete($storage, $entities);
    foreach ($entities as $entity) {
      $entity->getPlugin()->delete();
      if (!isset($entity->settingsPluginOperationSkip)) {
        // If we are deleting settings that have a parent, use its parent and
        // set it to its children.
        $parent_id = $entity->getParentId();
        foreach ($entity->getChildren() as $child) {
          $child->settingsPluginOperationSkip = TRUE;
          if ($child->getParentId() === $entity->id()) {
            $child->setParentId($parent_id);
          }
          $child->save();
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getParentId() {
    return $this->parent;
  }

  /**
   * {@inheritdoc}
   */
  public function setParentId($parent_id) {
    $this->parent = $parent_id;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getParent() {
    $parent_id = $this->getParentId();
    if ($parent_id) {
      return \Drupal::entityTypeManager()->getStorage('neo_settings')->load($parent_id);
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getParentRoot() {
    $parent_id = $this->getParentId();
    if ($parent_id) {
      if ($parent = \Drupal::entityTypeManager()->getStorage('neo_settings')->load($parent_id)) {
        /** @var \Drupal\neo_settings\SettingsInterface $parent */
        if ($nested_parent = $parent->getParent()) {
          return $nested_parent;
        }
        return $parent;
      }
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getParentIds() {
    $parents = [];
    foreach ($this->getParents() as $parent) {
      $parents[$parent->id()] = $parent->label();
    }
    return $parents;
  }

  /**
   * {@inheritdoc}
   */
  public function getParents() {
    $parents = [];
    if ($parent = $this->getParent()) {
      $parents = array_merge([$parent->id() => $parent], $parent->getParents());
    }
    return $parents;
  }

  /**
   * {@inheritdoc}
   */
  public function getChildren() {
    /** @var \Drupal\neo_settings\SettingsInterface[] $children */
    $children = \Drupal::entityTypeManager()->getStorage('neo_settings')->loadByProperties([
      'plugin' => $this->plugin,
      'parent' => $this->id(),
    ]);
    foreach ($children as $child) {
      $children = array_merge($children, $child->getChildren());
    }
    return $children;
  }

  /**
   * {@inheritdoc}
   */
  public function getChildrenIds() {
    $children = [];
    foreach ($this->getChildren() as $child) {
      $children[$child->id()] = $child->label();
    }
    return $children;
  }

  /**
   * {@inheritdoc}
   */
  public function getPlugin() {
    return $this->getPluginCollection()->get($this->plugin);
  }

  /**
   * Encapsulates the creation of the setting's LazyPluginCollection.
   *
   * @return \Drupal\Component\Plugin\LazyPluginCollection
   *   The settings's plugin collection.
   */
  protected function getPluginCollection() {
    if (!$this->pluginCollection) {
      $settings = $this->getSettings();
      $this->pluginCollection = new SettingsPluginCollection(\Drupal::service('plugin.manager.neo_settings'), \Drupal::service('module_handler'), $this->plugin, $settings, $this->id());
      // Extend the core configuration of the settings plugin. We use each
      // parent's settings and overlay them on top of the base settings. This
      // allows variables to use other variables.
      $plugin = $this->pluginCollection->get($this->plugin);
      $plugin->addCacheableDependency($this);
      foreach (array_reverse($this->getParents()) as $parent) {
        $plugin->extendConfigValues($parent->getSettings());
      }
    }
    return $this->pluginCollection;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginCollections() {
    return [
      'visibility' => $this->getVisibilityConditions(),
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function getPluginId() {
    return $this->plugin;
  }

  /**
   * {@inheritDoc}
   */
  public function setSettings(array $settings) {
    $this->settings = $settings;
    return $this;
  }

  /**
   * {@inheritDoc}
   */
  public function getSettings() {
    return $this->settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight() {
    return $this->weight;
  }

  /**
   * Generate the id.
   *
   * @return string
   *   The id.
   */
  public function generateId() {
    $id = strtolower($this->label);
    $id = preg_replace("/[^A-Za-z0-9 ]/", '', $id);
    $id = preg_replace('/[^a-z0-9_]+/', '_', $id);
    $id = preg_replace('/_+/', '_', $id);
    return $this->plugin . '_' . $id;
  }

  /**
   * Sorts active settings by weight; sorts inactive settings by name.
   */
  public static function sort(ConfigEntityInterface $a, ConfigEntityInterface $b) {
    // Separate enabled from disabled.
    $status = (int) $b->status() - (int) $a->status();
    if ($status !== 0) {
      return $status;
    }

    // Sort by weight.
    /** @var \Drupal\neo_settings\SettingsInterface $a */
    /** @var \Drupal\neo_settings\SettingsInterface $b */
    $weight = $a->getWeight() - $b->getWeight();
    if ($weight) {
      return $weight;
    }

    // Sort by label.
    return strcmp($a->label(), $b->label());
  }

}
