<?php

namespace Drupal\neo_settings\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Neo Settings item annotation object.
 *
 * @see \Drupal\neo_settings\Plugin\SettingsManager
 * @see plugin_api
 *
 * @Annotation
 */
class Settings extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The label of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * The editable config name.
   *
   * @var string
   */
  public $config_name;

  /**
   * The route path.
   *
   * @var string
   */
  public $route;

  /**
   * The admin permission.
   *
   * @var string
   */
  public $admin_permission;

  /**
   * Variations status.
   *
   * @var bool
   */
  public $variation_allow;

  /**
   * Optional handler overrides.
   *
   * @var array
   */
  public $handlers;

}
