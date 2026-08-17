<?php

declare(strict_types=1);

namespace Drupal\Tests\neo_settings\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\neo_settings\Entity\Settings;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests rebuilding a variation form when the "Extend" parent is in play.
 *
 * The Extend select is only rendered when more than one variation of the plugin
 * exists, and its AJAX rebuild re-reads $form_state's user input.
 *
 * Unchecked checkboxes are not submitted by browsers, so a variation that
 * overrides every field posts no `_override` key at all. Any code reading
 * $user_input['_override'] unguarded therefore has to cope with it being
 * absent — this test pins that.
 */
#[Group('neo_settings')]
class VariationExtendRebuildTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'path_alias',
    'neo_settings',
    'neo_settings_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['neo_settings_test']);
    $this->installEntitySchema('user');

    // A second variation, so the Extend select renders at all.
    Settings::create([
      'id' => 'neo_settings_test_base',
      'label' => 'Base',
      'plugin' => 'neo_settings_test',
      'status' => TRUE,
      'settings' => ['input' => 'From Base'],
    ])->save();
  }

  /**
   * Rebuilding with a parent selected and no `_override` posted must not fatal.
   */
  public function testRebuildWithoutOverrideKeyDoesNotFatal(): void {
    $form = $this->rebuildChildForm([
      'settings' => ['input' => 'Typed'],
      'parent' => 'neo_settings_test_base',
      // Note: no '_override' key, exactly as a browser posts when every
      // "Use Default" checkbox is unchecked.
    ]);

    $this->assertArrayHasKey('settings', $form);
    $this->assertArrayHasKey('parent', $form);
  }

  /**
   * Rebuilding with no parent selected and no `_override` posted is also safe.
   */
  public function testRebuildWithoutParentDoesNotFatal(): void {
    $form = $this->rebuildChildForm([
      'settings' => ['input' => 'Typed'],
      'parent' => '',
    ]);

    $this->assertArrayHasKey('settings', $form);
  }

  /**
   * Builds the child variation's form with the supplied user input.
   *
   * @param array $user_input
   *   The simulated POST values.
   *
   * @return array
   *   The built form.
   */
  protected function rebuildChildForm(array $user_input): array {
    $child = Settings::create([
      'id' => 'neo_settings_test_child',
      'label' => 'Child',
      'plugin' => 'neo_settings_test',
      'status' => TRUE,
      'settings' => ['input' => 'From Child'],
      'parent' => $user_input['parent'] ?: NULL,
    ]);
    $child->save();

    $form_object = $this->container->get('entity_type.manager')
      ->getFormObject('neo_settings', 'default');
    $form_object->setEntity($child);

    $form_state = new FormState();
    $form_state->setUserInput($user_input);

    return $this->container->get('form_builder')
      ->buildForm($form_object, $form_state);
  }

}
