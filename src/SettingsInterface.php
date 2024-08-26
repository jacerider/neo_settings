<?php

namespace Drupal\neo_settings;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\neo\VisibilityEntityInterface;
use Drupal\neo\VisibilityEntityPluginInterface;

/**
 * Provides an interface for defining Settings entities.
 */
interface SettingsInterface extends ConfigEntityInterface, VisibilityEntityInterface, VisibilityEntityPluginInterface {

  /**
   * Return the parent entity id of the settings.
   *
   * @return string
   *   The parent entity id.
   */
  public function getParentId();

  /**
   * Sets the parent entity id of the settings.
   *
   * @param string $parent_id
   *   The parent entity id.
   *
   * @return $this
   */
  public function setParentId($parent_id);

  /**
   * Return the parent entity of the settings.
   *
   * @return \Drupal\neo_settings\SettingsInterface
   *   The parent entity.
   */
  public function getParent();

  /**
   * Return the parent entity after following all parents.
   *
   * @return \Drupal\neo_settings\SettingsInterface
   *   The parent entity.
   */
  public function getParentRoot();

  /**
   * Return the parent path as ids.
   *
   * @return array
   *   An array of ids.
   */
  public function getParentIds();

  /**
   * Return the parent path as entities.
   *
   * @return \Drupal\neo_settings\SettingsInterface[]
   *   An array of entities.
   */
  public function getParents();

  /**
   * Return the child path as ids.
   *
   * @return array
   *   An array of ids.
   */
  public function getChildrenIds();

  /**
   * Return the child path as entities.
   *
   * @return \Drupal\neo_settings\SettingsInterface[]
   *   An array of children.
   */
  public function getChildren();

  /**
   * Returns the plugin instance.
   *
   * @return \Drupal\neo_settings\Plugin\SettingsInterface
   *   The plugin instance for these settings.
   */
  public function getPlugin();

  /**
   * Get plugin id.
   *
   * @return string
   *   The plugin id.
   */
  public function getPluginId();

  /**
   * Set the settings.
   *
   * @param array $settings
   *   The settings.
   *
   * @return $this
   */
  public function setSettings(array $settings);

  /**
   * Get the settings.
   *
   * @return array
   *   The settings.
   */
  public function getSettings();

  /**
   * Generate the id.
   *
   * @return string
   *   The id.
   */
  public function generateId();

  /**
   * Returns the weight of these settings (used for sorting).
   *
   * @return int
   *   The settings weight.
   */
  public function getWeight();

}
