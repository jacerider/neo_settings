<?php

declare(strict_types=1);

namespace Drupal\Tests\neo_settings\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\neo_settings\Form\SettingsConfigForm;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that scope keys are only written when the scope UI was built.
 *
 * The front/back scope selects are only built for a plugin that both allows
 * variations AND declares a `variation_scope` key. The submit handler used to
 * write `neo_settings_scope_front` / `neo_settings_scope_back` under the looser
 * condition of "allows variations", so a plugin like this fixture — variations
 * on, no scope key — had two keys written into its config on every save that
 * its schema does not declare.
 *
 * @see \Drupal\neo_settings\Form\SettingsConfigForm
 */
#[Group('neo_settings')]
class ScopeKeyWriteTest extends KernelTestBase {

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
   * A plugin without a variation_scope key gets no scope keys written.
   */
  public function testScopelessPluginGetsNoScopeKeys(): void {
    $before = $this->config('neo_settings_test.settings')->getRawData();
    $this->assertArrayNotHasKey('neo_settings_scope_front', $before, 'Premise: the installed config has no scope keys.');

    $this->submitConfigForm();

    $after = $this->config('neo_settings_test.settings')->getRawData();
    $this->assertArrayNotHasKey('neo_settings_scope_front', $after, 'Saving must not introduce an undeclared front scope key.');
    $this->assertArrayNotHasKey('neo_settings_scope_back', $after, 'Saving must not introduce an undeclared back scope key.');
  }

  /**
   * Saving the config form still persists the plugin's own values.
   *
   * Guards against the predicate fix accidentally short-circuiting the save.
   */
  public function testOrdinaryValuesStillSave(): void {
    $this->submitConfigForm(['input' => 'Changed By Test']);

    $this->assertSame(
      'Changed By Test',
      $this->config('neo_settings_test.settings')->get('input')
    );
  }

  /**
   * Submits the plugin's core settings form.
   *
   * @param array $overrides
   *   Instance values to merge over the installed config.
   */
  protected function submitConfigForm(array $overrides = []): void {
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

    $this->assertEmpty($form_state->getErrors(), 'The settings form submitted without validation errors.');
  }

}
