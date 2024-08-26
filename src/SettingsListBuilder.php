<?php

namespace Drupal\neo_settings;

use Drupal\Core\Config\Entity\DraggableListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a listing of Settings entities.
 */
class SettingsListBuilder extends DraggableListBuilder {

  /**
   * The settings manager.
   *
   * @var \Drupal\neo_settings\SettingsManagerInterface
   */
  protected $settingsManager;

  /**
   * The settings plugin id.
   *
   * @var string
   */
  protected $pluginId;

  /**
   * The settings plugin definition.
   *
   * @var array
   */
  protected $pluginDefinition;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new self(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('plugin.manager.neo_settings')
    );
  }

  /**
   * Constructs a new EntityListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\neo_settings\SettingsManagerInterface $settings_manager
   *   The settings manager.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, SettingsManagerInterface $settings_manager) {
    parent::__construct($entity_type, $storage);
    $this->settingsManager = $settings_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'neo_settings_overview';
  }

  /**
   * Set the plugin id.
   */
  public function setPluginId($plugin_id) {
    $this->pluginId = $plugin_id;
    $this->pluginDefinition = $this->settingsManager->getDefinition($plugin_id);
    if (empty($this->pluginDefinition['variation_ordering'])) {
      unset($this->weightKey);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = $this->getHeader();
    return $header + parent::buildHeader();
  }

  /**
   * Get header.
   *
   * @return array
   *   The header.
   */
  protected function getHeader() {
    $header = [];
    $header['label'] = $this->t('Name');
    $header['id'] = $this->t('ID');
    $header['parents'] = $this->t('Extend Path');
    if (!empty($this->pluginDefinition['variation_conditions'])) {
      $header['conditions'] = $this->t('Has Conditions');
    }
    return $header;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\neo_settings\SettingsInterface $entity */
    $row = $this->getRow($entity);
    return $row + parent::buildRow($entity);
  }

  /**
   * Get row.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return array
   *   The row.
   */
  protected function getRow(EntityInterface $entity) {
    /** @var \Drupal\neo_settings\SettingsInterface $entity */
    $row = [];
    $row['label'] = $entity->label();
    $row['id']['data']['#markup'] = $entity->id();
    $row['parents']['data']['#markup'] = implode(' › ', $entity->getParentIds()) ?: '-';
    if (!empty($this->pluginDefinition['variation_conditions'])) {
      $row['conditions']['data']['#markup'] = !empty($entity->getVisibilityConditions()->getConfiguration()) ? $this->t('Yes') : $this->t('No');
    }
    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $entities = $this->load();
    if (count($entities) <= 1) {
      unset($this->weightKey);
    }
    $build = parent::render();
    $build['table']['#empty'] = t('No settings available.');
    return $build;
  }

  /**
   * Loads entity IDs using a pager sorted by the entity id.
   *
   * @return array
   *   An array of entity IDs.
   */
  protected function getEntityIds() {
    $query = $this->getStorage()->getQuery()
      ->sort($this->entityType->getKey('label'));

    if ($this->pluginId) {
      $query->condition('plugin', $this->pluginId);
    }

    // Only add the pager if a limit is specified.
    if ($this->limit) {
      $query->pager($this->limit);
    }
    return $query->execute();
  }

}
