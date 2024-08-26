<?php

namespace Drupal\neo_settings\EventSubscriber;

use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\neo_settings\SettingsManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Config subscriber.
 */
class ConfigSubscriber implements EventSubscriberInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The settings manager.
   *
   * @var \Drupal\neo_settings\SettingsManagerInterface
   */
  protected $settingsManager;

  /**
   * Constructs a ConfigSubscriber.
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
  public static function getSubscribedEvents() {
    $events[ConfigEvents::SAVE][] = ['onConfigSave', 0];
    $events[ConfigEvents::DELETE][] = ['onConfigDelete', 0];
    return $events;
  }

  /**
   * Acts on config save.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The configuration event.
   */
  public function onConfigSave(ConfigCrudEvent $event) {
    $config = $event->getConfig();
    if ($definition = $this->settingsManager->getDefinitionByConfigName($config->getName())) {
      $plugin = $this->settingsManager->createInstance($definition['id']);
      // Triggered when core config is saved.
      $plugin->save();
      // All variations use this configuration as their base. We want to resave
      // them in this instance.
      foreach ($this->entityTypeManager->getStorage('neo_settings')->loadByProperties([
        'plugin' => $definition['id'],
      ]) as $entity) {
        $entity->settingsPluginOperationSkip = TRUE;
        $entity->save();
      }
    }
  }

  /**
   * Acts on config delete.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The configuration event.
   */
  public function onConfigDelete(ConfigCrudEvent $event) {
    $config = $event->getConfig();
    if ($definition = $this->settingsManager->getDefinitionByConfigName($config->getName())) {
      $plugin = $this->settingsManager->createInstance($definition['id']);
      // Triggered when core config is deleted.
      $plugin->delete();
      // All variations use this configuration as their base. We want to delete
      // them in this instance.
      foreach ($this->entityTypeManager->getStorage('neo_settings')->loadByProperties([
        'plugin' => $definition['id'],
      ]) as $entity) {
        $entity->settingsPluginOperationSkip = TRUE;
        $entity->delete();
      }
    }
  }

}
