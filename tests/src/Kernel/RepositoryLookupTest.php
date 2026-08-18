<?php

declare(strict_types=1);

namespace Drupal\Tests\neo_settings\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\neo_settings\Entity\Settings;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests variation lookup through the repository.
 *
 * ::get() used to fall back to the active settings whenever an id did not
 * resolve, so a caller could not tell a hit from a miss — and because
 * ::getVariationEntities() filters on status, merely disabling a variation
 * turned a hit into a silent fallback. neo_search worked around this by
 * reimplementing the lookup privately so it could throw.
 *
 * ::get() is now total: it returns the variation or NULL, and callers choose
 * their own fallback.
 */
#[Group('neo_settings')]
class RepositoryLookupTest extends KernelTestBase {

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
    Settings::create([
      'id' => 'neo_settings_test_alpha',
      'label' => 'Alpha',
      'plugin' => 'neo_settings_test',
      'status' => TRUE,
      'settings' => ['input' => 'Alpha'],
    ])->save();
  }

  /**
   * Returns the repository under test.
   */
  protected function repository() {
    return $this->container->get('neo_settings_test.repository');
  }

  /**
   * A full id resolves.
   */
  public function testFullIdResolves(): void {
    $found = $this->repository()->get('neo_settings_test_alpha', FALSE);
    $this->assertNotNull($found);
    $this->assertSame('neo_settings_test_alpha', $found->id());
  }

  /**
   * The short form, without the plugin prefix, resolves to the same instance.
   */
  public function testShortIdResolves(): void {
    $this->assertSame(
      $this->repository()->get('neo_settings_test_alpha', FALSE),
      $this->repository()->get('alpha', FALSE)
    );
  }

  /**
   * An unknown id reports a miss instead of silently returning the active one.
   */
  public function testUnknownIdReturnsNull(): void {
    $this->assertNull($this->repository()->get('no_such_variation', FALSE));
  }

  /**
   * A disabled variation is a miss, not a silent fallback.
   */
  public function testDisabledVariationReturnsNull(): void {
    $alpha = Settings::load('neo_settings_test_alpha');
    $alpha->setStatus(FALSE)->save();

    $this->assertNull($this->repository()->get('alpha', FALSE));
  }

  /**
   * Asking for the bare plugin id still resolves to the core instance.
   *
   * Guards against "fixing" the prefix test to require a `<plugin>_` separator,
   * which would send this through the prefixing branch and miss.
   */
  public function testBarePluginIdResolvesToCore(): void {
    $this->assertSame(
      $this->repository()->getCore(),
      $this->repository()->get('neo_settings_test', FALSE)
    );
  }

}
