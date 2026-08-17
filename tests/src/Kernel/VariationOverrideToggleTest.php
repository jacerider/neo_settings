<?php

declare(strict_types=1);

namespace Drupal\Tests\neo_settings\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\neo_settings\Entity\Settings;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the "Use Default" override toggles on a variation form.
 *
 * Each overridable element gets a checkbox whose #default_value is
 * `!hasVariationValue($path)` — checked means "this variation does not store a
 * value here, inherit it".
 *
 * The path used to probe must be the element's path RELATIVE TO THE SETTINGS
 * ROOT, because that is how a variation stores its values. Probing with only
 * the element's leaf name makes every nested value look unset, which pre-checks
 * the box; the subsequent save then decodes the correct full path from
 * #return_value and unsets the value. The result is a nested setting that
 * cannot be persisted through the UI at all.
 *
 * The fixture reproduces this shape: it has both a top-level `input` and a
 * nested `nested.input`, so a leaf-only probe confuses the two.
 */
#[Group('neo_settings')]
class VariationOverrideToggleTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    // The fixture allows variation conditions, and the entity form builds the
    // visibility UI, which instantiates core's request_path condition.
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
  }

  /**
   * A nested value stored by the variation is not offered as "Use Default".
   */
  public function testNestedOverrideIsDetected(): void {
    // The variation overrides ONLY the nested input, not the top-level one.
    $form = $this->buildVariationForm([
      'nested' => ['input' => 'Variation Nested'],
    ]);

    // The nested element stores a value, so "Use Default" must be unchecked.
    $this->assertFalse(
      $this->overrideDefault($form, ['nested', 'input']),
      'A nested value stored by the variation must not be flagged as inherited.'
    );
  }

  /**
   * A nested value the variation does NOT store is offered as "Use Default".
   */
  public function testUnsetNestedValueIsOfferedAsDefault(): void {
    $form = $this->buildVariationForm([
      'nested' => ['input' => 'Variation Nested'],
    ]);

    // The deep input is not stored by this variation, so it inherits.
    $this->assertTrue(
      $this->overrideDefault($form, ['nested', 'deep', 'input']),
      'A nested value absent from the variation must be flagged as inherited.'
    );
  }

  /**
   * A top-level override is not confused with a same-named nested one.
   *
   * This is the direct regression guard: with a leaf-only probe both elements
   * resolve to the key `input` and answer identically.
   */
  public function testTopLevelAndNestedSameNameAreDistinguished(): void {
    // Store ONLY the top-level `input`.
    $form = $this->buildVariationForm(['input' => 'Variation Top']);

    $this->assertFalse(
      $this->overrideDefault($form, ['input']),
      'The stored top-level value must not be flagged as inherited.'
    );
    $this->assertTrue(
      $this->overrideDefault($form, ['nested', 'input']),
      'The unstored nested value must still be flagged as inherited, even though it shares a leaf name with a stored top-level key.'
    );
  }

  /**
   * Builds the variation entity form and returns the built form array.
   *
   * @param array $settings
   *   The settings the variation stores.
   *
   * @return array
   *   The built form.
   */
  protected function buildVariationForm(array $settings): array {
    $variation = Settings::create([
      'id' => 'neo_settings_test_probe',
      'label' => 'Probe',
      'plugin' => 'neo_settings_test',
      'status' => TRUE,
      'settings' => $settings,
    ]);
    $variation->save();

    $form_object = $this->container->get('entity_type.manager')
      ->getFormObject('neo_settings', 'default');
    $form_object->setEntity($variation);

    // FormBuilder::buildForm() takes $form_state by reference.
    $form_state = new FormState();
    return $this->container->get('form_builder')
      ->buildForm($form_object, $form_state);
  }

  /**
   * Reads the "Use Default" checkbox state for an element under `settings`.
   *
   * @param array $form
   *   The built form.
   * @param array $path
   *   The element's path relative to the settings root.
   *
   * @return bool
   *   TRUE when the element is flagged as inheriting its value.
   */
  protected function overrideDefault(array $form, array $path): bool {
    $element = $form['settings'];
    foreach ($path as $key) {
      $this->assertArrayHasKey($key, $element, 'Form element exists: ' . implode('.', $path));
      $element = $element[$key];
    }
    $this->assertArrayHasKey('#neo_settings_override', $element, 'Element has an override toggle: ' . implode('.', $path));
    return (bool) $element['#neo_settings_override']['#default_value'];
  }

}
