<?php

declare(strict_types = 1);

namespace Drupal\neo_settings\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\neo_settings\SettingsInterface;
use Drupal\neo_settings\SettingsManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A test controller for building code.
 */
class SettingsController extends ControllerBase {

  /**
   * The controller constructor.
   */
  public function __construct(
    private readonly SettingsManagerInterface $pluginManagerNeoSettings,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('plugin.manager.neo_settings'),
    );
  }

  /**
   * Generate the neo settings plugin form.
   *
   * @param string $plugin_id
   *   The plugin ID for the neo settings plugin.
   *
   * @return string
   *   The title.
   */
  public function settingsConfigForm($plugin_id) {
    return $this->formBuilder()->getForm('\Drupal\neo_settings\Form\SettingsConfigForm', $plugin_id);
  }

  /**
   * Generate the neo settings plugin form title.
   *
   * @param string $plugin_id
   *   The plugin ID for the neo settings plugin.
   *
   * @return string
   *   The title.
   */
  public function settingsConfigFormTitle($plugin_id) {
    return $this->generatePluginTitle('@plugin_label @entity-type', $plugin_id);
  }

  /**
   * Build the neo settings plugin add form.
   *
   * @param string $plugin_id
   *   The plugin ID for the neo settings plugin.
   *
   * @return array
   *   The neo settings plugin edit form.
   */
  public function settingsEntityAddForm($plugin_id) {
    // Create an neo settings entity.
    $entity = $this->entityTypeManager()->getStorage('neo_settings')->create(['plugin' => $plugin_id]);
    return $this->entityFormBuilder()->getForm($entity);
  }

  /**
   * Generate the neo settings plugin add form title.
   *
   * @param string $plugin_id
   *   The plugin ID for the neo settings plugin.
   *
   * @return string
   *   The title.
   */
  public function settingsEntityAddFormTitle($plugin_id) {
    return $this->generatePluginTitle('Add @plugin_label @entity-type', $plugin_id);
  }

  /**
   * Build the neo settings plugin add form.
   *
   * @param \Drupal\neo_settings\SettingsInterface $neo_settings
   *   The settings entity.
   * @param string $plugin_id
   *   The plugin ID for the neo settings plugin.
   *
   * @return array
   *   The neo settings plugin edit form.
   */
  public function settingsEntityEditForm(SettingsInterface $neo_settings, $plugin_id) {
    return $this->entityFormBuilder()->getForm($neo_settings);
  }

  /**
   * Generate the neo settings plugin add form title.
   *
   * @param \Drupal\neo_settings\SettingsInterface $neo_settings
   *   The settings entity.
   * @param string $plugin_id
   *   The plugin ID for the neo settings plugin.
   *
   * @return string
   *   The title.
   */
  public function settingsEntityEditFormTitle(SettingsInterface $neo_settings, $plugin_id) {
    return $this->generatePluginTitle('Edit @variation-label: @entity-label', $plugin_id, $neo_settings);
  }

  /**
   * Build the neo settings plugin add form.
   *
   * @param \Drupal\neo_settings\SettingsInterface $neo_settings
   *   The settings entity.
   * @param string $plugin_id
   *   The plugin ID for the neo settings plugin.
   *
   * @return array
   *   The neo settings plugin edit form.
   */
  public function settingsEntityDeleteForm(SettingsInterface $neo_settings, $plugin_id) {
    return $this->entityFormBuilder()->getForm($neo_settings, 'delete');
  }

  /**
   * Generate the neo settings plugin add form title.
   *
   * @param \Drupal\neo_settings\SettingsInterface $neo_settings
   *   The settings entity.
   * @param string $plugin_id
   *   The plugin ID for the neo settings plugin.
   *
   * @return string
   *   The title.
   */
  public function settingsEntityDeleteFormTitle(SettingsInterface $neo_settings, $plugin_id) {
    return $this->generatePluginTitle('Delete @variation-label: @entity-label', $plugin_id, $neo_settings);
  }

  /**
   * Generate the neo settings entity collection.
   *
   * @param string $plugin_id
   *   The plugin ID for the neo settings plugin.
   *
   * @return array
   *   The neo settings collection.
   */
  public function settingsEntityCollection($plugin_id) {
    $entity_type_manager = $this->entityTypeManager();
    $plugin_definition = $this->pluginManagerNeoSettings->getDefinition($plugin_id);
    if (!empty($plugin_definition['handlers']['list_builder'])) {
      $list_builder = $entity_type_manager->createHandlerInstance($plugin_definition['handlers']['list_builder'], $entity_type_manager->getDefinition('neo_settings'));
    }
    else {
      $list_builder = $entity_type_manager->getListBuilder('neo_settings');
    }
    /** @var \Drupal\neo_settings\SettingsListBuilder $list_builder */
    $list_builder->setPluginId($plugin_id);
    return $list_builder->render();
  }

  /**
   * Generate the neo settings entity collection title.
   *
   * @param string $plugin_id
   *   The plugin ID for the neo settings plugin.
   *
   * @return string
   *   The title.
   */
  public function settingsEntityCollectionTitle($plugin_id) {
    return $this->generatePluginTitle('@plugin_label @variation-label-plural', $plugin_id);
  }

  /**
   * Generate a plugin title.
   *
   * @param string $title
   *   The title.
   * @param string $plugin_id
   *   The plugin ID for the neo settings plugin.
   * @param \Drupal\neo_settings\SettingsInterface $neo_settings
   *   The settings entity.
   *
   * @return string
   *   The title.
   */
  protected function generatePluginTitle($title, $plugin_id, SettingsInterface $neo_settings = NULL) {
    $plugin_definition = $this->pluginManagerNeoSettings->getDefinition($plugin_id);
    $entity_type = $this->entityTypeManager()->getDefinition('neo_settings');
    return $this->t($title, [ // phpcs:ignore
      '@entity-type' => ucwords((string) $entity_type->getSingularLabel()),
      '@plugin_label' => ucwords((string) $plugin_definition['label']),
      '@entity-label' => $neo_settings ? $neo_settings->label() : NULL,
      '@variation-label' => ucwords($plugin_definition['variation_label']),
      '@variation-label-plural' => ucwords($plugin_definition['variation_label_plural']),
    ]);
  }

}
