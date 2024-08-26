<?php

namespace Drupal\neo_settings;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\neo\VisibilityEntityAccessControlTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Defines the access control handler for the Neo Settings entity type.
 */
class SettingsAccessControlHandler extends EntityAccessControlHandler implements EntityHandlerInterface {
  use VisibilityEntityAccessControlTrait {
    VisibilityEntityAccessControlTrait::checkAccess as checkVisibilityAccess;
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\neo_settings\SettingsInterface $entity */
    if ($operation === 'delete' && $entity->get('lock')) {
      return AccessResult::forbidden('A locked settings config cannot be removed.');
    }
    if ($operation !== 'view') {
      $definition = $entity->getPlugin()->getPluginDefinition();
      $admin_permission = $definition['admin_permission'] ?? $entity->getEntityType()->getAdminPermission();
      return AccessResult::allowedIfHasPermission($account, $admin_permission);
    }
    return $this->checkVisibilityAccess($entity, $operation, $account);
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultVisibilityAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    return AccessResult::allowed()->addCacheContexts(['user.permissions']);
  }

}
