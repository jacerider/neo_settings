<?php

namespace Drupal\neo_settings\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\neo\VisibilityFormTrait;
use Drupal\neo_settings\Entity\Settings;
use Drupal\neo_settings\SettingsManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class default neo settings form.
 */
class SettingsForm extends EntityForm implements SettingsFormInterface {
  use VisibilityFormTrait;

  /**
   * The settings entity.
   *
   * @var \Drupal\neo_settings\SettingsInterface
   */
  protected $entity;

  /**
   * The settings manager.
   *
   * @var \Drupal\neo_settings\SettingsManagerInterface
   */
  protected $settingsManager;

  /**
   * All neo settings.
   *
   * @var \Drupal\neo_settings\SettingsInterface[]
   */
  protected $neoSettingsVariations;

  /**
   * Constructs a ContentEntityForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\neo_settings\SettingsManagerInterface $settings_manager
   *   The settings manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, SettingsManagerInterface $settings_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->settingsManager = $settings_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new self(
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.neo_settings')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getSettings($settings_id) {
    $variations = $this->getSettingsVariations();
    return $variations[$settings_id] ?? NULL;
  }

  /**
   * Load settings instances.
   */
  protected function getSettingsVariations() {
    if (!isset($this->neoSettingsVariations)) {
      $this->neoSettingsVariations = $this->entityTypeManager->getStorage('neo_settings')->loadByProperties([
        'status' => 1,
        'plugin' => $this->entity->getPluginId(),
      ]);
    }
    return $this->neoSettingsVariations;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    $neoSettings = $this->entity;

    $form['messages'] = [
      '#markup' => '<div id="neo-settings-messages"></div>',
      '#weight' => -100,
    ];

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#maxlength' => 255,
      '#default_value' => $neoSettings->label(),
      '#required' => TRUE,
    ];

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => t('Enabled'),
      '#default_value' => $neoSettings->status(),
    ];

    $options = [
      '' => $this->t('- None -'),
    ];
    foreach ($this->getSettingsVariations() as $id => $entity) {
      if ($neoSettings->id() !== $entity->id()) {
        $options[$id] = $entity->label();
      }
    }
    if (count($options) > 1) {
      $parent_id = $neoSettings->getParentId();
      $form['parent'] = [
        '#type' => 'select',
        '#title' => $this->t('Extend'),
        '#description' => $this->t('Inherit from another variation.'),
        '#options' => $options,
        '#default_value' => $parent_id,
        '#ajax' => [
          'wrapper' => 'neo-settings-settings',
          'callback' => [get_class($this), 'ajaxExtend'],
        ],
      ];
      if ($parent_id) {
        $extendedNeoSettings = $this->getSettings($form_state->getValue('parent'));
        if ($extendedNeoSettings) {
          $user_input = $form_state->getUserInput();
          $user_input['settings'] = $this->prepareDefaultUserInput($user_input['settings'], $user_input['_override']);
          $form_state->setUserInput($user_input);
          $neoSettings->getPlugin()->extendConfigValues($extendedNeoSettings->getPlugin()->getValues());
        }
      }
      else {
        if ($neoSettings->getPlugin()->isExtended()) {
          $user_input = $form_state->getUserInput();
          $user_input['settings'] = $this->prepareDefaultUserInput($user_input['settings'], $user_input['_override']);
          $form_state->setUserInput($user_input);
          $neoSettings->getPlugin()->unextendConfigValues();
        }
      }
    }

    $form['settings'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#parents' => ['settings'],
      '#attributes' => [
        'id' => 'neo-settings-settings',
      ],
    ];
    $subformState = SubformState::createForSubform($form['settings'], $form, $form_state);
    $form['settings'] = $neoSettings->getPlugin()->buildSettingsForm($form['settings'], $subformState);

    if ($neoSettings->getPlugin()->allowVariationConditions()) {
      $form['visibility'] = [];
      $form['visibility'] = $this->buildVisibility($form['visibility'], $form_state);
    }

    return $form;
  }

  /**
   * Remove values set as _default.
   *
   * @param array $values
   *   The values.
   *
   * @return array
   *   The remaining values.
   */
  protected function prepareDefaultUserInput(array $values, array $overrides, $path = ['settings']) {
    foreach ($values as $key => $value) {
      $valuePath = array_merge($path, [$key]);
      $valueKey = implode('_', $valuePath);
      if (isset($overrides[$valueKey]) && !empty($overrides[$valueKey])) {
        unset($values[$key]);
      }
      elseif (is_array($value)) {
        $values[$key] = $this->prepareDefaultUserInput($value, $overrides, $valuePath);
        if (empty($values[$key])) {
          unset($values[$key]);
        }
      }
    }
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public static function ajaxExtend(array $form, FormStateInterface $form_state, Request $request) {
    return [
      '_messages' => [
        '#type' => 'status_messages',
        '#weight' => -1000,
      ],
    ] + $form['settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $neoSettings = $this->entity;
    if ($this->entity->isNew() && Settings::load($this->entity->generateId())) {
      $form_state->setError($form, $this->t('This name has already been used. Please try a different name.'));
    }
    if ($neoSettings->getParentId()) {
      if (isset($neoSettings->getParentIds()[$neoSettings->id()])) {
        $form_state->setError($form, $this->t('Circular reference detected. This variation exists in the inheritance of the parents.'));
      }
    }
    if ($neoSettings->getPlugin()->allowVariationConditions()) {
      $this->validateVisibility($form, $form_state);
    }
    $subformState = SubformState::createForSubform($form['settings'], $form, $form_state);
    $neoSettings->getPlugin()->validateSettingsForm($form['settings'], $subformState);
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $neoSettings = $this->entity;
    if ($neoSettings->getPlugin()->allowVariationConditions()) {
      $this->submitVisibility($form, $form_state);
    }
    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $neoSettings = $this->entity;

    $subformState = SubformState::createForSubform($form['settings'], $form, $form_state);
    $values = $neoSettings->getPlugin()->extractSettingsFormValues($form['settings'], $subformState);
    $neoSettings->setSettings($values);

    $status = $neoSettings->save();

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addMessage($this->t('Created the %label Settings.', [
          '%label' => $neoSettings->label(),
        ]));
        break;

      default:
        $this->messenger()->addMessage($this->t('Saved the %label Settings.', [
          '%label' => $neoSettings->label(),
        ]));
    }
    $plugin_id = $neoSettings->getPluginId();
    $form_state->setRedirect("neo.settings.plugin.{$plugin_id}.variations");
    return $status;
  }

}
