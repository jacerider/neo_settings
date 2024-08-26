<?php

namespace Drupal\neo_settings\Plugin;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\neo\Helpers\NestedArray;
use Drupal\neo\ValuesTrait;
use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\RefinableCacheableDependencyTrait;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for Neo Settings plugins.
 *
 * @phpstan-consistent-constructor
 */
abstract class SettingsBase extends PluginBase implements SettingsInterface, TrustedCallbackInterface, ContainerFactoryPluginInterface {
  use StringTranslationTrait;
  use ValuesTrait;
  use RefinableCacheableDependencyTrait;
  use DependencySerializationTrait;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The core configuration.
   *
   * @var array
   *   The core configuration.
   */
  protected $configConfiguration;

  /**
   * The original core configuration.
   *
   * @var array
   *   The core configuration.
   */
  protected $configConfigurationOriginal;

  /**
   * The extended configuration.
   *
   * @var array
   *   The extended configuration.
   */
  protected $extendedConfiguration = [];

  /**
   * The variation configuration.
   *
   * @var array
   *   The variation configuration.
   */
  protected $variationConfiguration;

  /**
   * The form configuration.
   *
   * @var array
   *   The form configuration.
   */
  protected $formConfiguration = [];

  /**
   * Variation id.
   *
   * @var bool
   */
  protected $variationId;

  /**
   * Flag indicating if settings have been extended.
   *
   * @var bool
   */
  protected $extended = FALSE;

  /**
   * The strict parents.
   *
   * An array of #parents arrays. Values defined by these parents will be used
   * as-is instead of deep merging.
   *
   * @var array
   */
  protected $strictParents = [];

  /**
   * Constructs a \Drupal\Component\Plugin\PluginBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    MessengerInterface $messenger,
    FormBuilderInterface $form_builder
  ) {
    $this->pluginId = $plugin_id;
    $this->configuration = [];

    // Prepare settings for use.
    $plugin_definition['configuration'] = $this->prepareValues($plugin_definition['configuration'], '_default');
    $configuration['config'] = $this->prepareValues($configuration['config'], 'config');
    $configuration['variation'] = $this->prepareValues($configuration['variation'], 'variation');
    $this->pluginDefinition = $plugin_definition;
    $this->configConfiguration = $configuration['config'];
    $this->configConfigurationOriginal = $configuration['config'];
    $this->variationConfiguration = $configuration['variation'];
    $this->variationId = $configuration['variation_id'];
    $this->setValues($this->variationConfiguration);
    $this->messenger = $messenger;
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('messenger'),
      $container->get('form_builder')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function id() {
    return $this->variationId ?: $this->getPluginId();
  }

  /**
   * {@inheritDoc}
   */
  public function save() {}

  /**
   * {@inheritDoc}
   */
  public function delete() {}

  /**
   * {@inheritDoc}
   */
  public static function trustedCallbacks() {
    return ['preRenderSettingsFormToggles'];
  }

  /**
   * Prepare values.
   *
   * @param array $values
   *   The values to prepare.
   * @param string $type
   *   The type. Either default, config or variation.
   *
   * @return array
   *   The prepared values.
   */
  protected function prepareValues(array $values, $type) {
    unset($values['_core']);
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function buildBaseSettingsForm(array $form, FormStateInterface $form_state) {
    $form = $this->buildBaseForm($form, $form_state);
    $form['#tree'] = TRUE;
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function buildBaseForm(array $form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateBaseSettingsForm(array $form, FormStateInterface $form_state) {
    $this->validateBaseForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function validateBaseForm(array $form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public function extractBaseSettingsFormValues(array $form, FormStateInterface $form_state) {
    $values = $this->extractBaseFormValues($form, $form_state);
    return NestedArray::mergeDeepStrict(
      NestedArray::insersectKeyDeepArray($this->getDefaultValues(), $values),
      NestedArray::insersectKeyDeepArray($values, $this->getDefaultValues()),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function extractBaseFormValues(array $form, FormStateInterface $form_state) {
    return $form_state->getValues();
  }

  /**
   * {@inheritdoc}
   */
  public function buildSettingsForm(array $form, FormStateInterface $form_state) {
    if (!isset($form['#parents'])) {
      $this->messenger->addWarning($this->t('The form is not properly nested. It requires a #parent property.'));
    }

    // Provide helper when using #states and other properties that rely on the
    // field name.
    $input_selector = $form['#parents'];
    $first = array_shift($input_selector);
    $input_selector = $first . ($input_selector ? '[' . implode('][', $input_selector) . ']' : '');
    $form['#input_selector'] = $input_selector;

    // Set the form configuration values.
    $this->setFormConfigValues(isset($form['#settings_config']) ? ($form['#settings_config'] ?: []) : []);

    $mode = $this->getFormConfigValue('mode');
    $method = 'build' . ucfirst($mode) . 'Form';
    if (method_exists($this, $method)) {
      $form = $this->{$method}($form, $form_state);
    }
    else {
      $form = $this->buildForm($form, $form_state);
    }

    if ($this->isVariation()) {
      $form['#after_build'][] = [$this, 'attachSettingsFormToggles'];
    }

    $form['#tree'] = TRUE;
    return $form;
  }

  /**
   * Move the form elements into a child so they can be toggled.
   *
   * @param array $form
   *   A nested array of form elements comprising the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   A nested array of form elements comprising the form.
   */
  public function attachSettingsFormToggles(array $form, FormStateInterface $form_state) {
    $form['#element_validate'][] = [__CLASS__, 'removeDefaultValues'];
    $elements = $this->overrideParentsElements($form, $form_state);
    foreach ($elements as $element) {
      $parents = $element['#parents'];
      array_shift($parents);
      $array_parents = $element['#array_parents'];
      array_shift($array_parents);
      $setting_parents = $element['#setting_parents'] ?? $parents;
      $element = NestedArray::getValue($form, $array_parents);
      if ($element) {
        $id = Html::getId('neo-setting-override-' . implode('-', $element['#parents']));
        $element['#states']['visible']['#' . $id] = ['checked' => FALSE];
        if (!$form_state->isRebuilding()) {
          $element['#neo_settings_override'] = [
            '#type' => 'checkbox',
            '#title' => t('@label: Use Default', [
              '@label' => $element['#title'],
            ]),
            '#parents' => array_merge(['_override'], [
              implode('_', $parents),
            ]),
            '#return_value' => implode(':', $element['#parents']),
            '#id' => $id,
            '#default_value' => !$this->hasVariationValue($setting_parents),
            '#margin' => 0,
          ];
          $element['#pre_render'][] = [$this, 'preRenderSettingsFormToggles'];
          $this->formBuilder->doBuildForm('neo_settings_override', $element['#neo_settings_override'], $form_state);
        }
        NestedArray::setValue($form, $array_parents, $element);
      }
    }

    return $form;
  }

  /**
   * Get the parents of the form elements that can be overridable per instance.
   *
   * Extending classes should override this method.
   *
   * @param array $form
   *   A nested array of form elements comprising the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   An array of parents.
   */
  protected function overrideParentsElements(array $form, FormStateInterface $form_state) {
    $elements = [];
    foreach (Element::children($form) as $key) {
      $element = $form[$key];
      // Element is empty.
      if (empty($element)) {
        continue;
      }
      // Element is not a defined type.
      if (empty($element['#type'])) {
        continue;
      }
      // Element is not a defined type.
      if ($element['#type'] === 'hidden') {
        continue;
      }
      // Element is not an input.
      if (!isset($element['#input'])) {
        // Include children.
        if ($nested_elements = $this->overrideParentsElements($element, $form_state)) {
          $elements = array_merge($elements, $nested_elements);
        }
        continue;
      }
      // Element does not have a value.
      if (!isset($element['#value'])) {
        continue;
      }
      $elements[implode('][', $element['#parents']) . ']'] = $element;
    }
    return $elements;
  }

  /**
   * Pre render callback for settings form toggle.
   *
   * @param array $element
   *   A nested array of form elements comprising the element.
   */
  public function preRenderSettingsFormToggles(array $element) {
    $element['#theme_wrappers'][] = 'fieldset';
    return $element;
  }

  /**
   * Remove default values from element values.
   *
   * @param array $element
   *   A nested array of form elements comprising the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public static function removeDefaultValues(array $element, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    foreach (array_filter($values['_override']) as $parents) {
      $parents = explode(':', $parents);
      NestedArray::unsetValue($values, $parents);
    }
    $form_state->setValues($values);
  }

  /**
   * All children of a toggle element need to be moved up one level.
   *
   * @param array $element
   *   A toggle element.
   * @param int $parents_key
   *   The parent key to remove.
   * @param int $array_parents_key
   *   The array parents key to remove.
   *
   * @return array
   *   A nested array of form elements comprising the form.
   */
  protected function detachSettingsFormToggleParents(array $element, $parents_key = NULL, $array_parents_key = NULL) {
    $parents_key = $parents_key ?: array_key_last($element['#parents']);
    $array_parents_key = $array_parents_key ?: array_key_last($element['#array_parents']);
    unset($element['#parents'][$parents_key]);
    unset($element['#array_parents'][$array_parents_key]);
    $element['#parents'] = array_values($element['#parents']);
    $element['#array_parents'] = array_values($element['#array_parents']);
    foreach (Element::children($element) as $key) {
      $element[$key] = $this->detachSettingsFormToggleParents($element[$key], $parents_key, $array_parents_key);
    }
    return $element;
  }

  /**
   * Returns the configuration form elements specific to this settings plugin.
   *
   * Should be used by most plugins when implementing the settings form.
   *
   * @param array $form
   *   A nested array of form elements comprising the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  protected function buildForm(array $form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateSettingsForm(array $form, FormStateInterface $form_state) {
    $this->validateForm($form, $form_state);
  }

  /**
   * Validation callback for settings form.
   *
   * Should be used by most plugins when implementing the settings form.
   *
   * @param array $form
   *   A nested array of form elements comprising the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  protected function validateForm(array $form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public function extractSettingsFormValues(array $form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $values = $this->extractFormValues($values, $form, $form_state) ?: [];
    $merged_values = NestedArray::mergeDeepStrict(
      NestedArray::insersectKeyDeepArray($this->getValues(), $values),
      NestedArray::insersectKeyDeepArray($values, $this->getValues())
    );
    $this->mergeStrictParentValues($merged_values, $values);
    return $merged_values;
  }

  /**
   * Extract the settings form values.
   *
   * Should be used by most plugins when implementing the settings form.
   *
   * @param mixed $values
   *   The values.
   * @param array $form
   *   A nested array of form elements comprising the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The array of submitted values.
   */
  protected function extractFormValues($values, array $form, FormStateInterface $form_state) {
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function resetSettingsFormValues(array $values, array $form, FormStateInterface $form_state) {
    return $this->resetFormValues($values, $form, $form_state);
  }

  /**
   * Reset the settings form values.
   *
   * Should be used by most plugins when implementing the settings form.
   *
   * @param array $values
   *   The values.
   * @param array $form
   *   A nested array of form elements comprising the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The array of submitted values.
   */
  public function resetFormValues(array $values, array $form, FormStateInterface $form_state) {
    return $values;
  }

  /**
   * Returns the strict parents.
   *
   * @return array
   *   An array of #parent arrays.
   */
  protected function getStrictParents() {
    return $this->strictParents;
  }

  /**
   * {@inheritdoc}
   */
  public function mergeValuesWithCurrent(array $value_arrays) {
    $values = NestedArray::mergeDeepArrayStrict($value_arrays);
    $merged_values = NestedArray::mergeDeepArrayStrict([
      $this->getValues(),
      $values,
    ]);
    $this->mergeStrictParentValues($merged_values, $values);
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function mergeStrictParentValues(array &$all_values, array $values) {
    foreach ($this->getStrictParents() as $parents) {
      $exists = FALSE;
      $strictValue = NestedArray::getValue($values, $parents, $exists);
      if ($exists) {
        NestedArray::setValue($all_values, $parents, $strictValue);
      }
    }
  }

  /**
   * Implements \Drupal\neo\ValuesInterface::setValues()
   */
  public function setValues(array $values) {
    $existingValues = &$this->getValues();
    $configValues = $this->getConfigValues();
    $existingValues = NestedArray::mergeDeepStrict(
      $configValues,
      $values,
    );
    // Allow NULL values for default values only.
    $existingValues = NestedArray::mergeDeepArrayStrict([
      $this->getDefaultValues(),
      $existingValues,
    ], FALSE);
    // Merge in strict from config values.
    $this->mergeStrictParentValues($existingValues, $configValues);
    // Merge in strict from values.
    $this->mergeStrictParentValues($existingValues, $values);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function &getValues() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultValues() {
    return $this->pluginDefinition['configuration'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultValue($key, $default = NULL) {
    $exists = NULL;
    $values = $this->getDefaultValues();
    $value = NestedArray::getValue($values, (array) $key, $exists);
    if (!$exists) {
      $value = $default;
    }
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigValues() {
    return $this->configConfiguration;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigValue($key, $default = NULL) {
    $exists = NULL;
    $values = $this->getConfigValues();
    $value = NestedArray::getValue($values, (array) $key, $exists);
    if (!$exists) {
      $value = $default;
    }
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFormConfig() {
    return [
      'mode' => 'full',
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function setFormConfigValues(array $values) {
    $this->formConfiguration = NestedArray::mergeDeepArrayStrict([
      static::defaultFormConfig(),
      $values,
    ]);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  protected function getFormConfigValues() {
    return $this->formConfiguration;
  }

  /**
   * {@inheritdoc}
   */
  protected function getFormConfigValue($key, $default = NULL) {
    $exists = NULL;
    $values = $this->getFormConfigValues();
    $value = NestedArray::getValue($values, (array) $key, $exists);
    if (!$exists) {
      $value = $default;
    }
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function getDiffValues() {
    // We use original config as we want to allow extended config to be returned
    // when it has been set.
    $to = $this->configConfigurationOriginal ?: $this->getDefaultValues();
    $from = array_intersect_key($this->getValues(), $to);
    return NestedArray::diffDeep($from, $to);
  }

  /**
   * {@inheritdoc}
   */
  public function getDiffValue($key, $default = NULL) {
    $exists = NULL;
    $values = $this->getDiffValues();
    $value = NestedArray::getValue($values, (array) $key, $exists);
    if (!$exists) {
      $value = $default;
    }
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function getDiffConfig() {
    // We use original config as we do not want extended config to be factored
    // in the results.
    return NestedArray::diffDeep($this->configConfigurationOriginal, $this->getDefaultValues());
  }

  /**
   * {@inheritdoc}
   */
  public function getDiffConfigValue($key, $default = NULL) {
    $exists = NULL;
    $values = $this->getDiffConfig();
    $value = NestedArray::getValue($values, (array) $key, $exists);
    if (!$exists) {
      $value = $default;
    }
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function extendConfigValues($configuration) {
    // Only allow values that exist in the default configuration.
    $this->extendedConfiguration = array_intersect_key($configuration, $this->getDefaultValues());
    $this->configConfiguration = NestedArray::mergeDeepStrict(
      $this->getConfigValues(),
      $this->extendedConfiguration,
    );
    $this->setValues($this->variationConfiguration);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function unextendConfigValues() {
    $this->extendedConfiguration = [];
    $this->configConfiguration = $this->configConfigurationOriginal;
    $this->setValues($this->variationConfiguration);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getExtendedValues() {
    return $this->extendedConfiguration;
  }

  /**
   * {@inheritdoc}
   */
  public function isExtended() {
    return !empty($this->extendedConfiguration);
  }

  /**
   * {@inheritdoc}
   */
  public function getExtendedVariationValues() {
    return NestedArray::mergeDeepStrict(
      $this->getExtendedValues(),
      $this->getVariationValues(),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getExtendedVariationValue($key, $default = NULL) {
    $exists = NULL;
    $values = $this->getExtendedVariationValues();
    $value = NestedArray::getValue($values, (array) $key, $exists);
    if (!$exists) {
      $value = $default;
    }
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function getVariationValues() {
    return $this->variationConfiguration;
  }

  /**
   * {@inheritdoc}
   */
  public function getVariationValue($key, $default = NULL) {
    $exists = NULL;
    $values = $this->getVariationValues();
    $value = NestedArray::getValue($values, (array) $key, $exists);
    if (!$exists) {
      $value = $default;
    }
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function hasVariationValue($key) {
    $exists = NULL;
    $values = $this->getVariationValues();
    NestedArray::getValue($values, (array) $key, $exists);
    return $exists;
  }

  /**
   * {@inheritdoc}
   */
  public function isVariation() {
    return !empty($this->variationId);
  }

  /**
   * {@inheritdoc}
   */
  public function allowVariationConditions() {
    return !empty($this->getPluginDefinition()['variation_conditions']);
  }

}
