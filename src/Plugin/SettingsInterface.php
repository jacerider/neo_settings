<?php

namespace Drupal\neo_settings\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\neo\ValuesInterface;

/**
 * Defines an interface for Neo Settings plugins.
 */
interface SettingsInterface extends ValuesInterface, PluginInspectionInterface, RefinableCacheableDependencyInterface {

  /**
   * The id of this settings instance.
   */
  public function id();

  /**
   * Called when settings are saved.
   *
   * This method is not responsible for the actual saving of the data.
   */
  public function save();

  /**
   * Called when settings are deleted.
   *
   * This method is not responsible for the actual deleting of the data.
   */
  public function delete();

  /**
   * Returns the base settings form elements specific to this plugin.
   *
   * Most plugins should not override this method.
   *
   * @param array $form
   *   A nested array of form elements comprising the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function buildBaseSettingsForm(array $form, FormStateInterface $form_state);

  /**
   * Validation callback for base settings form.
   *
   * Most plugins should not override this method.
   *
   * @param array $form
   *   A nested array of form elements comprising the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function validateBaseSettingsForm(array $form, FormStateInterface $form_state);

  /**
   * Extract the base settings form values.
   *
   * Most plugins should not override this method.
   *
   * @param array $form
   *   A nested array of form elements comprising the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The array of submitted values.
   */
  public function extractBaseSettingsFormValues(array $form, FormStateInterface $form_state);

  /**
   * Returns the configuration form elements specific to this settings plugin.
   *
   * Most plugins should not override this method.
   *
   * @param array $form
   *   A nested array of form elements comprising the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function buildSettingsForm(array $form, FormStateInterface $form_state);

  /**
   * Validation callback for settings form.
   *
   * Most plugins should not override this method.
   *
   * @param array $form
   *   A nested array of form elements comprising the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function validateSettingsForm(array $form, FormStateInterface $form_state);

  /**
   * Extract the settings form values.
   *
   * Most plugins should not override this method.
   *
   * @param array $form
   *   A nested array of form elements comprising the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The array of submitted values.
   */
  public function extractSettingsFormValues(array $form, FormStateInterface $form_state);

  /**
   * Reset the form values.
   *
   * @param array $values
   *   The reset values.
   * @param array $form
   *   A nested array of form elements comprising the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The reset values.
   */
  public function resetSettingsFormValues(array $values, array $form, FormStateInterface $form_state);

  /**
   * Get the default configuration of the plugin.
   *
   * @return array
   *   The default configuration.
   */
  public function getDefaultValues();

  /**
   * Get a default configuration value.
   *
   * @param string|array $key
   *   Values are stored as a multi-dimensional associative array. If $key is a
   *   string, it will return $values[$key]. If $key is an array, each element
   *   of the array will be used as a nested key. If $key = array('foo', 'bar')
   *   it will return $values['foo']['bar'].
   * @param mixed $default
   *   (optional) The default value if the specified key does not exist.
   *
   * @return mixed
   *   The value for the given key, or NULL.
   */
  public function getDefaultValue($key, $default = NULL);

  /**
   * Get the core configuration of the plugin.
   *
   * @return array
   *   The core configuration.
   */
  public function getConfigValues();

  /**
   * Get a core configuration value.
   *
   * @param string|array $key
   *   Values are stored as a multi-dimensional associative array. If $key is a
   *   string, it will return $values[$key]. If $key is an array, each element
   *   of the array will be used as a nested key. If $key = array('foo', 'bar')
   *   it will return $values['foo']['bar'].
   * @param mixed $default
   *   (optional) The default value if the specified key does not exist.
   *
   * @return mixed
   *   The value for the given key, or NULL.
   */
  public function getConfigValue($key, $default = NULL);

  /**
   * Get the diff between the current configuration and core configuration.
   *
   * @return array
   *   The different configuration.
   */
  public function getDiffValues();

  /**
   * Returns the value for a specific diff key between the current and core.
   *
   * @param string|array $key
   *   Values are stored as a multi-dimensional associative array. If $key is a
   *   string, it will return $values[$key]. If $key is an array, each element
   *   of the array will be used as a nested key. If $key = array('foo', 'bar')
   *   it will return $values['foo']['bar'].
   * @param mixed $default
   *   (optional) The default value if the specified key does not exist.
   *
   * @return mixed
   *   The value for the given key, or NULL.
   */
  public function getDiffValue($key, $default = NULL);

  /**
   * Get the diff between the default and core configuration.
   *
   * @return array
   *   The different configuration.
   */
  public function getDiffConfigValues();

  /**
   * Returns the value for a specific diff key between the default and core.
   *
   * @param string|array $key
   *   Values are stored as a multi-dimensional associative array. If $key is a
   *   string, it will return $values[$key]. If $key is an array, each element
   *   of the array will be used as a nested key. If $key = array('foo', 'bar')
   *   it will return $values['foo']['bar'].
   * @param mixed $default
   *   (optional) The default value if the specified key does not exist.
   *
   * @return mixed
   *   The value for the given key, or NULL.
   */
  public function getDiffConfigValue($key, $default = NULL);

  /**
   * Merge an array of settings with the current settings.
   *
   * @param array $value_arrays
   *   An array of settings.
   *
   * @return array
   *   The merged settings.
   */
  public function mergeValuesWithCurrent(array $value_arrays);

  /**
   * Merge strict values over the top of all other values.
   *
   * The reason we do this is an empty array will not merge over top of an array
   * that has values due to the way we merge default values with submitted
   * values.
   *
   * @param array $all_values
   *   The array of all values.
   * @param array $values
   *   The array of values that should overwrite the existing values.
   */
  public function mergeStrictParentValues(array &$all_values, array $values);

  /**
   * Extend the core configuration of the plugin.
   *
   * @param array $configuration
   *   An array of configuration.
   *
   * @return $this
   */
  public function extendConfigValues(array $configuration);

  /**
   * Unextend the core configuration of the plugin.
   *
   * Will restore the original configuration.
   *
   * @return $this
   */
  public function unextendConfigValues();

  /**
   * Get the extended configuration of the plugin.
   *
   * @return array
   *   The extended configuration.
   */
  public function getExtendedValues();

  /**
   * Check if settings have been extended.
   *
   * @return $this
   */
  public function isExtended();

  /**
   * Get the variation and extended configuration of the plugin.
   *
   * @return array
   *   The variation and extended configuration.
   */
  public function getExtendedVariationValues();

  /**
   * Returns the value for a specific extended variation key.
   *
   * @param string|array $key
   *   Values are stored as a multi-dimensional associative array. If $key is a
   *   string, it will return $values[$key]. If $key is an array, each element
   *   of the array will be used as a nested key. If $key = array('foo', 'bar')
   *   it will return $values['foo']['bar'].
   * @param mixed $default
   *   (optional) The default value if the specified key does not exist.
   *
   * @return mixed
   *   The value for the given key, or NULL.
   */
  public function getExtendedVariationValue($key, $default = NULL);

  /**
   * Get the variation configuration of the plugin.
   *
   * @return array
   *   The variation configuration.
   */
  public function getVariationValues();

  /**
   * Returns the value for a specific variation key.
   *
   * @param string|array $key
   *   Values are stored as a multi-dimensional associative array. If $key is a
   *   string, it will return $values[$key]. If $key is an array, each element
   *   of the array will be used as a nested key. If $key = array('foo', 'bar')
   *   it will return $values['foo']['bar'].
   * @param mixed $default
   *   (optional) The default value if the specified key does not exist.
   *
   * @return mixed
   *   The value for the given key, or NULL.
   */
  public function getVariationValue($key, $default = NULL);

  /**
   * Check if variation has value.
   *
   * @param string|array $key
   *   Values are stored as a multi-dimensional associative array. If $key is a
   *   string, it will return $values[$key]. If $key is an array, each element
   *   of the array will be used as a nested key. If $key = array('foo', 'bar')
   *   it will return $values['foo']['bar'].
   *
   * @return bool
   *   TRUE if the variation has the specified key, FALSE otherwise.
   */
  public function hasVariationValue($key);

  /**
   * Check if plugin instance is a variation.
   *
   * @return bool
   *   Returns TRUE if plugin instance is a variation.
   */
  public function isVariation();

  /**
   * Check if plugin instance allow variation visibility conditions.
   *
   * @return bool
   *   Returns TRUE if plugin instance allow variation visibility conditions.
   */
  public function allowVariationConditions();

}
