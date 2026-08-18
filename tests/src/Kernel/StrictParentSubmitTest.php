<?php

declare(strict_types=1);

namespace Drupal\Tests\neo_settings\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\neo_settings\Form\SettingsConfigForm;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests strict parents across a real form submit.
 *
 * SettingsMergeAlgebraTest covers the resolution path. This covers the submit
 * path, which used to rely on buildStrictParents() discovering checkboxes and
 * multi-selects at form-build time and appending them to $strictParents. That
 * discovery ran only on submit, so the merge algebra differed between rendering
 * and saving. Strict parents are now declared on the plugin, and this pins the
 * behaviour that the discovery used to provide.
 */
#[Group('neo_settings')]
class StrictParentSubmitTest extends KernelTestBase {

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
   * Unchecking every box persists an empty list, not the previous selection.
   */
  public function testEmptyCheckboxesPersistAsEmpty(): void {
    $this->assertSame(
      ['two', 'three'],
      $this->config('neo_settings_test.settings')->get('value_multiple'),
      'Premise: the installed config has two values selected.'
    );

    // Nothing ticked: the normalised checkboxes value is an empty array.
    $this->submitWith(['value_multiple' => []]);

    $this->assertSame(
      [],
      $this->config('neo_settings_test.settings')->get('value_multiple'),
      'Unchecking everything must clear the value rather than merge back over it.'
    );
  }

  /**
   * A narrowed selection replaces the stored list rather than merging into it.
   */
  public function testNarrowedSelectionReplaces(): void {
    $this->submitWith(['value_multiple' => ['one' => 'one']]);

    $this->assertSame(
      ['one'],
      $this->config('neo_settings_test.settings')->get('value_multiple'),
      'The stored list must be replaced, not unioned with the previous one.'
    );
  }

  /**
   * Submits the core settings form with the given instance overrides.
   *
   * @param array $overrides
   *   Instance values to merge over the installed config.
   */
  protected function submitWith(array $overrides): void {
    $config = $this->config('neo_settings_test.settings')->getRawData();
    unset($config['_core']);

    $form_state = new FormState();
    $form_state->addBuildInfo('args', ['neo_settings_test']);
    $form_state->setValues([
      'base' => ['global' => $config['global']],
      'instance' => $overrides + $config,
    ]);

    $this->container->get('form_builder')
      ->submitForm(SettingsConfigForm::class, $form_state);

    $this->assertEmpty($form_state->getErrors(), 'Form errors: ' . print_r($form_state->getErrors(), TRUE));
  }

}
