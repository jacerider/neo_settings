<?php

namespace Drupal\neo_settings\Form;

use Drupal\Core\Entity\EntityFormInterface;

/**
 * Defines an interface for Neo Settings plugins.
 */
interface SettingsFormInterface extends EntityFormInterface {

  /**
   * Gets the entity of this form.
   *
   * @return \Drupal\neo_settings\SettingsInterface
   *   The entity.
   */
  public function getEntity();

  /**
   * Return the neo settings.
   *
   * @param string $settings_id
   *   The settings id.
   *
   * @return \Drupal\neo_settings\SettingsInterface
   *   The neo settings.
   */
  public function getSettings($settings_id);

}
