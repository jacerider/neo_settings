<?php

declare(strict_types=1);

namespace Drupal\Tests\neo_settings\Kernel;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\KernelTests\KernelTestBase;
use Drupal\neo_settings\Entity\Settings;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests how saving propagates through the variation inheritance chain.
 *
 * A child variation's effective values are assembled at READ time by overlaying
 * each ancestor's settings, so a parent's change alters the child's output
 * without touching the child's stored data. Something must therefore invalidate
 * the child's cache tag when the parent is saved.
 *
 * The module used to achieve that by re-saving every descendant entity — a full
 * config write per descendant, writing byte-identical data, purely as a way of
 * triggering two cache-tag invalidations. Invalidating the tags directly is the
 * same guarantee without the write amplification.
 *
 * The invalidation itself is NOT optional: neo_search caches search payloads
 * permanently against `config:neo_settings.variation.<id>`, with no ancestor
 * tags, so dropping it would strand those entries stale indefinitely.
 */
#[Group('neo_settings')]
class ConfigCascadeTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'neo_settings',
    'neo_settings_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['neo_settings_test']);
  }

  /**
   * Saving a parent invalidates every descendant's cache tag.
   */
  public function testParentSaveInvalidatesDescendantTags(): void {
    [$parent] = $this->createChain();

    $cache = $this->container->get('cache.default');
    $cache->set('probe:child', 'x', Cache::PERMANENT, ['config:neo_settings.variation.neo_settings_test_child']);
    $cache->set('probe:grandchild', 'x', Cache::PERMANENT, ['config:neo_settings.variation.neo_settings_test_grandchild']);

    $parent->set('settings', ['input' => 'Changed'])->save();

    $this->assertFalse($cache->get('probe:child'), 'A direct child of the saved variation must be invalidated.');
    $this->assertFalse($cache->get('probe:grandchild'), 'A grandchild must be invalidated too — the walk is over the whole subtree.');
  }

  /**
   * Saving a parent does not rewrite its descendants' stored config.
   */
  public function testParentSaveDoesNotRewriteDescendants(): void {
    [$parent] = $this->createChain();

    $written = $this->recordConfigWrites(function () use ($parent) {
      $parent->set('settings', ['input' => 'Changed'])->save();
    });

    $this->assertContains('neo_settings.variation.neo_settings_test_parent', $written, 'The saved variation is written.');
    $this->assertNotContains('neo_settings.variation.neo_settings_test_child', $written, 'A descendant must not be rewritten just to invalidate it.');
    $this->assertNotContains('neo_settings.variation.neo_settings_test_grandchild', $written, 'A grandchild must not be rewritten either.');
  }

  /**
   * Saving the plugin's core config does not rewrite every variation.
   *
   * Every plugin instance already carries the core config's cache tag, and
   * Config::save() invalidates it, so re-saving each variation achieved
   * nothing.
   */
  public function testCoreConfigSaveDoesNotRewriteVariations(): void {
    $this->createChain();

    $written = $this->recordConfigWrites(function () {
      $this->config('neo_settings_test.settings')->set('input', 'Changed')->save();
    });

    $this->assertContains('neo_settings_test.settings', $written);
    $this->assertNotContains('neo_settings.variation.neo_settings_test_parent', $written, 'Saving core config must not rewrite variations.');
    $this->assertNotContains('neo_settings.variation.neo_settings_test_child', $written);
  }

  /**
   * Deleting a mid-chain parent still reparents its children.
   *
   * The delete path performs a real data change, unlike the save path.
   */
  public function testDeletingMidChainParentReparentsChildren(): void {
    [$parent, $child] = $this->createChain();
    $child->setParentId($parent->id())->save();

    $parent->delete();

    $reloaded = Settings::load('neo_settings_test_child');
    $this->assertNotNull($reloaded, 'The child survives its parent being deleted.');
    $this->assertNull($reloaded->getParentId(), 'The child is reparented to its deleted parent\'s parent.');
  }

  /**
   * Builds a parent → child → grandchild chain.
   *
   * @return \Drupal\neo_settings\Entity\Settings[]
   *   The parent, child and grandchild.
   */
  protected function createChain(): array {
    $parent = Settings::create([
      'id' => 'neo_settings_test_parent',
      'label' => 'Parent',
      'plugin' => 'neo_settings_test',
      'status' => TRUE,
      'settings' => ['input' => 'From Parent'],
    ]);
    $parent->save();

    $child = Settings::create([
      'id' => 'neo_settings_test_child',
      'label' => 'Child',
      'plugin' => 'neo_settings_test',
      'status' => TRUE,
      'parent' => 'neo_settings_test_parent',
      'settings' => [],
    ]);
    $child->save();

    $grandchild = Settings::create([
      'id' => 'neo_settings_test_grandchild',
      'label' => 'Grandchild',
      'plugin' => 'neo_settings_test',
      'status' => TRUE,
      'parent' => 'neo_settings_test_child',
      'settings' => [],
    ]);
    $grandchild->save();

    return [$parent, $child, $grandchild];
  }

  /**
   * Records the names of config objects written during a callback.
   *
   * @param callable $operation
   *   The operation to run.
   *
   * @return string[]
   *   The config names written.
   */
  protected function recordConfigWrites(callable $operation): array {
    $written = [];
    $listener = function (ConfigCrudEvent $event) use (&$written) {
      $written[] = $event->getConfig()->getName();
    };
    $dispatcher = $this->container->get('event_dispatcher');
    $dispatcher->addListener(ConfigEvents::SAVE, $listener);
    try {
      $operation();
    }
    finally {
      $dispatcher->removeListener(ConfigEvents::SAVE, $listener);
    }
    return $written;
  }

}
