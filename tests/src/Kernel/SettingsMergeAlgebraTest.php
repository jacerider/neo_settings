<?php

declare(strict_types=1);

namespace Drupal\Tests\neo_settings\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\neo_settings\Entity\Settings;
use PHPUnit\Framework\Attributes\Group;

/**
 * Pins the value-merge algebra that the whole module exists to provide.
 *
 * Two rules are under test:
 *  - Ordinary keys DEEP MERGE, so a variation overriding one leaf keeps its
 *    siblings from the base config.
 *  - Keys declared in $strictParents are REPLACED wholesale, so a variation
 *    storing an empty array actually yields an empty array rather than having
 *    the base config merged back underneath it.
 *
 * The second rule is the subtle one: neo's NestedArray tests `is_int(key($v))`
 * to decide whether an array is a list, and `key([])` is NULL — so an empty
 * array falls through to the recurse branch and would otherwise be swallowed.
 */
#[Group('neo_settings')]
class SettingsMergeAlgebraTest extends KernelTestBase {

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
   * The base config is readable through the plugin with no variation.
   */
  public function testCoreSettingsExposeInstalledConfig(): void {
    $plugin = $this->container->get('plugin.manager.neo_settings')
      ->createInstance('neo_settings_test');

    $this->assertSame('three', $plugin->getValue('global'));
    $this->assertSame('Hello', $plugin->getValue('input'));
    $this->assertSame(['two', 'three'], $plugin->getValue('value_multiple'));
    $this->assertSame('Hello Nested', $plugin->getValue(['nested', 'input']));
    $this->assertSame('Hello Deep Nested', $plugin->getValue(['nested', 'deep', 'input']));
  }

  /**
   * A variation overriding one deep leaf keeps its siblings from base config.
   */
  public function testOrdinaryKeysDeepMerge(): void {
    $plugin = $this->createVariation('deep_override', [
      'nested' => ['deep' => ['input' => 'Overridden Deep']],
    ])->getPlugin();

    // The overridden leaf wins.
    $this->assertSame('Overridden Deep', $plugin->getValue(['nested', 'deep', 'input']));
    // Its sibling survives from the base config — this is the deep merge.
    $this->assertSame('Hello Nested', $plugin->getValue(['nested', 'input']));
    // Unrelated top-level keys survive too.
    $this->assertSame('Hello', $plugin->getValue('input'));
  }

  /**
   * A strict parent is replaced wholesale rather than merged.
   */
  public function testStrictParentReplacesRatherThanMerges(): void {
    $plugin = $this->createVariation('strict_subset', [
      'value_multiple' => ['one'],
    ])->getPlugin();

    // Not ['one', 'three'] and not ['two', 'three', 'one'] — exactly ['one'].
    $this->assertSame(['one'], $plugin->getValue('value_multiple'));
  }

  /**
   * An empty strict parent survives as empty instead of falling back.
   *
   * This is the invariant the $strictParents mechanism exists for, and it is
   * the one that silently regresses when strict handling does not apply.
   */
  public function testEmptyStrictParentIsNotSwallowed(): void {
    $plugin = $this->createVariation('strict_empty', [
      'value_multiple' => [],
    ])->getPlugin();

    $this->assertSame([], $plugin->getValue('value_multiple'));
  }

  /**
   * Creates and saves an enabled variation of the fixture plugin.
   *
   * @param string $id
   *   The id suffix, without the plugin prefix.
   * @param array $settings
   *   The variation's stored settings.
   *
   * @return \Drupal\neo_settings\SettingsInterface
   *   The saved variation entity.
   */
  protected function createVariation(string $id, array $settings): Settings {
    $variation = Settings::create([
      'id' => 'neo_settings_test_' . $id,
      'label' => ucfirst($id),
      'plugin' => 'neo_settings_test',
      'status' => TRUE,
      'settings' => $settings,
    ]);
    $variation->save();
    return $variation;
  }

}
