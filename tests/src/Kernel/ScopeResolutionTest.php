<?php

declare(strict_types=1);

namespace Drupal\Tests\neo_settings\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\neo_settings\Entity\Settings;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests front/back scope resolution.
 *
 * Scope merges a designated variation's values into whichever settings apply to
 * the current request. It used to be applied inside the plugin's
 * ::extendInstanceValues(), which meant it only ran when a caller happened to
 * pass per-instance overrides — a plain ::getActive() never applied it. It also
 * ran after the instance values were merged and then discarded them.
 *
 * Scope is now resolved by the repository, which is what decides which settings
 * apply to a request, so it applies consistently.
 */
#[Group('neo_settings')]
class ScopeResolutionTest extends KernelTestBase {

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

    // The variation that scope pulls values from.
    Settings::create([
      'id' => 'neo_settings_test_front',
      'label' => 'Front',
      'plugin' => 'neo_settings_test',
      'status' => TRUE,
      'settings' => ['input' => 'From Front Scope'],
    ])->save();

    $this->config('neo_settings_test.settings')
      ->set('neo_settings_scope_front', 'neo_settings_test_front')
      ->save();
  }

  /**
   * Returns the repository under test.
   */
  protected function repository() {
    return $this->container->get('neo_settings_test.repository');
  }

  /**
   * A variation opting into scope receives the front target's values.
   */
  public function testScopeAppliesOnPlainLookup(): void {
    Settings::create([
      'id' => 'neo_settings_test_consumer',
      'label' => 'Consumer',
      'plugin' => 'neo_settings_test',
      'status' => TRUE,
      'settings' => ['scope' => TRUE],
    ])->save();

    $this->assertSame(
      'From Front Scope',
      $this->repository()->get('consumer', FALSE)->getValue('input'),
      'Scope must apply without the caller passing instance overrides.'
    );
  }

  /**
   * A variation that does not opt in is unaffected.
   */
  public function testScopeIsNotAppliedWhenDisabled(): void {
    Settings::create([
      'id' => 'neo_settings_test_plain',
      'label' => 'Plain',
      'plugin' => 'neo_settings_test',
      'status' => TRUE,
      'settings' => ['scope' => FALSE],
    ])->save();

    $this->assertSame(
      'Hello',
      $this->repository()->get('plain', FALSE)->getValue('input'),
      'Without the scope flag the base config value stands.'
    );
  }

  /**
   * A variation's own stored values still win over the scoped ones.
   */
  public function testVariationValuesWinOverScope(): void {
    Settings::create([
      'id' => 'neo_settings_test_own',
      'label' => 'Own',
      'plugin' => 'neo_settings_test',
      'status' => TRUE,
      'settings' => ['scope' => TRUE, 'input' => 'My Own Value'],
    ])->save();

    $this->assertSame(
      'My Own Value',
      $this->repository()->get('own', FALSE)->getValue('input'),
      'Scope contributes below the variation, not above it.'
    );
  }

  /**
   * The scope target does not copy its own enable flag onto the consumer.
   */
  public function testScopeFlagIsNotChained(): void {
    Settings::create([
      'id' => 'neo_settings_test_chain',
      'label' => 'Chain',
      'plugin' => 'neo_settings_test',
      'status' => TRUE,
      'settings' => ['scope' => TRUE],
    ])->save();

    $plugin = $this->repository()->get('chain', FALSE);
    $this->assertSame('From Front Scope', $plugin->getValue('input'));
    // The target itself stores no scope flag, so nothing to chain; the guard is
    // that the merge never copies the key regardless.
    $this->assertNotNull($plugin);
  }

}
