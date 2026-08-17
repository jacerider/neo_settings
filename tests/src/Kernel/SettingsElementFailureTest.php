<?php

declare(strict_types=1);

namespace Drupal\Tests\neo_settings\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\neo_settings\Element\NeoSettings;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests how the neo_settings render element handles an unusable plugin id.
 *
 * A #process callback's return value REPLACES the element in the form tree
 * (see FormBuilder::doBuildForm()), so returning [] destroys #type, #parents,
 * #array_parents and #theme_wrappers. The element then renders as nothing at
 * all: no message, no log entry, and — because the element no longer round
 * trips its #default_value — a save can silently flatten previously stored
 * settings.
 *
 * A misconfigured element must therefore stay a valid element and become
 * inaccessible, rather than ceasing to exist.
 */
#[Group('neo_settings')]
class SettingsElementFailureTest extends KernelTestBase {

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
   * An unknown plugin id yields an inaccessible element, not an empty array.
   */
  public function testUnknownPluginIdKeepsElementIntact(): void {
    $result = $this->processElement('no_such_settings_plugin');

    $this->assertNotSame([], $result, 'The element must not be replaced by an empty array.');
    $this->assertSame('neo_settings', $result['#type'], '#type must survive so the element still renders as itself.');
    $this->assertSame(['x'], $result['#parents'], '#parents must survive so the element still round trips its value.');
    $this->assertFalse($result['#access'], 'A misconfigured element must be hidden via #access.');
  }

  /**
   * A missing plugin id yields an inaccessible element, not an empty array.
   */
  public function testEmptyPluginIdKeepsElementIntact(): void {
    $result = $this->processElement('');

    $this->assertNotSame([], $result);
    $this->assertSame('neo_settings', $result['#type']);
    $this->assertFalse($result['#access']);
  }

  /**
   * A valid plugin id still builds the settings form.
   */
  public function testValidPluginIdBuildsForm(): void {
    $result = $this->processElement('neo_settings_test');

    $this->assertNotEmpty($result);
    $this->assertArrayNotHasKey('#access', $result, 'A working element must not be marked inaccessible.');
    $this->assertArrayHasKey('input', $result, 'The plugin form should have been built into the element.');
  }

  /**
   * The declared properties match the ones the element actually reads.
   */
  public function testDeclaredPropertiesMatchUsage(): void {
    $info = $this->container->get('plugin.manager.element_info')
      ->getInfo('neo_settings');

    $this->assertSame([], $info['#settings_config'], '#settings_config is merged as an array, so its default must be an array.');
    $this->assertArrayHasKey('#settings_variation', $info, '#settings_variation is read by the element and must be declared.');
  }

  /**
   * Runs the element through its own #process callback.
   *
   * @param string $settings_id
   *   The plugin id to put on the element.
   *
   * @return array
   *   Whatever the process callback returned.
   */
  protected function processElement(string $settings_id): array {
    $element = [
      '#type' => 'neo_settings',
      '#settings_id' => $settings_id,
      '#default_value' => [],
      '#parents' => ['x'],
      '#array_parents' => ['x'],
    ];
    $form_state = new FormState();
    $complete_form = [];

    return NeoSettings::processSettings($element, $form_state, $complete_form);
  }

}
