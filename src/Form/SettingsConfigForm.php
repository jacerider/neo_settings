<?php

namespace Drupal\neo_settings\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\neo_settings\SettingsManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A base Neo settings form to standard Drupal settings forms.
 */
class SettingsConfigForm extends ConfigFormBase {

  /**
   * The settings manager.
   *
   * @var \Drupal\neo_settings\SettingsManagerInterface
   */
  protected $settingsManager;

  /**
   * The Neo settings instance.
   *
   * @var \Drupal\neo_settings\Plugin\SettingsInterface
   */
  protected $settingsInstance;

  /**
   * The settings plugin id.
   *
   * @var string
   */
  protected $pluginId;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    protected SettingsManagerInterface $settings_manager,
    protected $typedConfigManager = NULL
  ) {
    parent::__construct($config_factory, $typedConfigManager);
    $this->settingsManager = $settings_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new self(
      $container->get('config.factory'),
      $container->get('plugin.manager.neo_settings'),
      $container->get('config.typed')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    $names = [];
    if ($name = $this->getSettingsConfigName()) {
      $names[] = $name;
    }
    return $names;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    // We do not have access to the pluginId at this point.
    return 'neo_settings_form';
  }

  /**
   * Gets the Neo settings id that will be used.
   *
   * @return string
   *   The neo settings id that will be used.
   */
  protected function getSettingsPluginId() {
    return $this->pluginId;
  }

  /**
   * Gets the Neo settings config name.
   *
   * @return string
   *   The Neo settings config name.
   */
  protected function getSettingsConfigName() {
    $definition = $this->settingsManager->getDefinition($this->getSettingsPluginId());
    return !empty($definition['config_name']) ? $definition['config_name'] : NULL;
  }

  /**
   * Build neo settings form.
   *
   * @param array $form
   *   A nested array of form elements comprising the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param string $plugin_id
   *   The settings plugin id.
   */
  public function buildForm(array $form, FormStateInterface $form_state, $plugin_id = NULL) {
    $this->pluginId = $plugin_id;
    $form = parent::buildForm($form, $form_state);
    $plugin = $this->settingsInstance();
    $form['#id'] = Html::getId('neo-settings-' . $plugin_id . '-form');

    $form['messages'] = [
      '#markup' => '<div id="neo-settings-messages"></div>',
      '#weight' => -100,
    ];

    $form['base'] = [
      '#parents' => ['base'],
    ];
    $subform_state = SubformState::createForSubform($form['base'], $form, $form_state);
    $form['base'] = $plugin->buildBaseSettingsForm($form['base'], $subform_state);

    $form['instance'] = [
      '#parents' => ['instance'],
    ];
    $subform_state = SubformState::createForSubform($form['instance'], $form, $form_state);
    $form['instance'] = $plugin->buildSettingsForm($form['instance'], $subform_state);

    $form['actions']['reset'] = $this->getExoFormResetButton();
    return $form;
  }

  /**
   * Validate neo settings form.
   *
   * @param array $form
   *   A nested array of form elements comprising the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $plugin = $this->settingsInstance();

    $subform_state = SubformState::createForSubform($form['base'], $form, $form_state);
    $plugin->validateBaseSettingsForm($form['base'], $subform_state);

    $subform_state = SubformState::createForSubform($form['instance'], $form, $form_state);
    $plugin->validateSettingsForm($form['instance'], $subform_state);

    // Update the internal values with submitted values when the form is being
    // rebuild (e.g. submitted via AJAX), so that subsequent processing (e.g.
    // AJAX callbacks) can rely on it.
    if ($form_state->isProcessingInput()) {
      $plugin = $this->settingsInstance();

      $subform_state = SubformState::createForSubform($form['base'], $form, $form_state);
      $base_values = $plugin->extractBaseSettingsFormValues($form['base'], $subform_state);

      $subform_state = SubformState::createForSubform($form['instance'], $form, $form_state);
      $instance_values = $plugin->extractSettingsFormValues($form['instance'], $subform_state);

      $values = $plugin->mergeValuesWithCurrent([$base_values, $instance_values]);
      $plugin->setValues($values);
    }
  }

  /**
   * Submit neo settings form.
   *
   * @param array $form
   *   A nested array of form elements comprising the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    /** @var \Drupal\Core\Config\Config $settings */
    $settings = $this->config($this->getSettingsConfigName());
    $plugin = $this->settingsInstance();

    $subform_state = SubformState::createForSubform($form['base'], $form, $form_state);
    $base_values = $plugin->extractBaseSettingsFormValues($form['base'], $subform_state);

    $subform_state = SubformState::createForSubform($form['instance'], $form, $form_state);
    $instance_values = $plugin->extractSettingsFormValues($form['instance'], $subform_state);

    // Merge values with current. This allows different parts of the form to
    // be saved seperately without loosing the values of the other parts.
    $values = $plugin->mergeValuesWithCurrent([$base_values, $instance_values]);

    foreach ($values as $key => $value) {
      $settings->set($key, $value);
    }
    $settings->save();
    Cache::invalidateTags($settings->getCacheTags());
  }

  /**
   * Get the reset button for the form.
   */
  protected function getExoFormResetButton() {
    return [
      '#type' => 'submit',
      '#value' => $this->t('Reset'),
      '#limit_validation_errors' => [],
      '#submit' => [
        '::resetExoForm',
      ],
    ];
  }

  /**
   * Submit callback for the reset button.
   *
   * @param array $form
   *   A nested array of form elements comprising the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function resetExoForm(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Config\Config $settings */
    $settings = $this->config($this->getSettingsConfigName());
    $plugin = $this->settingsInstance();
    // We clear the values. If left empty, config will be removed.
    $values = [];
    // Allow plugin to act on settings reset.
    $values = $plugin->resetSettingsFormValues($values, $form, $form_state);
    $settings->setData($values);
    $settings->save();
    Cache::invalidateTags($settings->getCacheTags());
  }

  /**
   * Retrieves the Neo settings instance.
   *
   * @return \Drupal\neo_settings\Plugin\SettingsInterface
   *   The Neo settings instance.
   */
  protected function settingsInstance() {
    if (!isset($this->settingsInstance)) {
      // We do not pass in configuration into this plugin as the settings will
      // be automatically included.
      $this->settingsInstance = $this->settingsManager->createInstance($this->getSettingsPluginId());
    }
    return $this->settingsInstance;
  }

}
