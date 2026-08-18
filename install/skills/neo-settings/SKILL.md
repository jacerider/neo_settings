---
name: neo-settings
description: Define and maintain a Neo Settings plugin — the per-module settings config that powers /admin/config/neo/*, its optional variations, and the repository service other code reads it through. Use when adding settings to a Neo module, adding a field to an existing settings form, enabling or debugging variations, wiring a `*.settings` repository service, binding a variation to a block/formatter via the `neo_settings` render element, or when a saved setting will not persist. NOT for authoring components (use neo-component) and NOT for the Vite/Tailwind asset pipeline (use neo-build).
allowed-tools: Read, Write, Edit, Glob, Grep, Bash
---

# Neo Settings

Module: [web/modules/contrib/neo_settings/](web/modules/contrib/neo_settings/)

Gives a module one settings form at `/admin/config/neo/<id>`, backed by an
ordinary config object, plus an optional **variation** system: named config
entities that inherit from that base config and from each other, each
selectable at render time.

Ten modules use it (`neo_alchemist`, `neo_font`, `neo_form`, `neo_image`,
`neo_loader`, `neo_modal`, `neo_search`, `neo_tooltip`, `neo_menu_link`, plus
the test fixture). Only `neo_modal` and `neo_search` enable variations.

## Architecture in one pass

- A **plugin** (`src/Settings/<Name>Settings.php`, `@Settings` annotation,
  extends `SettingsBase`) declares the form and the defaults.
- Values resolve as five layers, lowest precedence first:
  **default** (the module's `config/install` YAML, via the plugin definition)
  → **config** (the live base config object)
  → **extended** (ancestor variations' settings, and front/back scope)
  → **variation** (this variation's own stored settings)
  → **instance** (per-render overrides passed by the caller).
  `getValue()` reads the resolved stack; `getVariationValue()`,
  `getConfigValue()` and `getDefaultValue()` read single layers.
- A **variation** is a `neo_settings` config entity (`neo_settings.variation.*`)
  holding a `plugin`, an optional `parent`, and a sparse `settings` array — only
  the keys it overrides.
- A **repository service** is how everything else reads settings. It is declared
  per module and resolves the active variation from the route, visibility
  conditions, or an explicit id.

## Adding a settings plugin

Five steps. Step 3 is the one that is almost always missed — **8 of the 10
existing plugins skip it**, and it fails silently until variations are enabled.

**1. The plugin** — `src/Settings/FooSettings.php`:

```php
/**
 * @Settings(
 *   id = "neo_foo",
 *   label = @Translation("Foo"),
 *   config_name = "neo_foo.settings",
 *   menu_title = @Translation("Foo"),
 *   route = "/admin/config/neo/foo",
 *   admin_permission = "administer neo_foo",
 *   variation_allow = false,
 * )
 */
class FooSettings extends SettingsBase {
  protected function buildForm(array $form, FormStateInterface $form_state) {
    $form['thing'] = [
      '#type' => 'textfield',
      '#default_value' => $this->getValue('thing'),
    ];
    return $form;
  }
}
```

Override `buildForm()` for per-variation settings and `buildBaseForm()` for
settings that exist only on the base config. Do not override the `*SettingsForm`
wrappers — they drive the override toggles.

**2. Default config** — `config/install/neo_foo.settings.yml`, every key the
form can write. Store real booleans (`true`), not `1`.

**3. Config schema** — `config/schema/neo_foo.schema.yml`. You need **two**
types over one shape, because the same keys are stored both as the base config
object and as a variation's `settings` value:

```yaml
neo_foo.settings:
  type: neo_foo
  mapping:
    _core:
      requiredKey: false
      type: _core_config_info

neo_settings.settings.neo_foo:   # required — see neo_settings.schema.yml
  type: neo_foo

neo_foo:
  type: mapping
  mapping:
    thing:
      type: string
```

Omitting `neo_settings.settings.<id>` makes every variation of that plugin fail
strict config-schema validation the moment `variation_allow` is turned on.

**4. The repository service** — `neo_foo.services.yml`:

```yaml
services:
  neo_foo.settings:
    class: Drupal\neo_settings\SettingsRepository
    parent: neo_settings.repository
    arguments: ['neo_foo']
```

**5. The permission** named in `admin_permission`, in `neo_foo.permissions.yml`.

## Reading settings

```php
use Drupal\neo_settings\SettingsTrait;

class Foo {
  use SettingsTrait;
  // The SERVICE id, not the plugin id.
  protected $settingsId = 'neo_foo.settings';

  public function bar() {
    $this->getSettings()->getValue('thing');
    // Per-instance overrides, or a specific variation:
    $this->getSettings(['thing' => 'x'], 'header');
  }
}
```

Or directly: `\Drupal::service('neo_foo.settings')->getActive()`.

## Variations

`variation_allow = true` unlocks the whole variations UI — collection, add,
edit and delete routes, the local task, and the local action. Related keys:
`variation_label` / `variation_label_plural` (singular/plural nouns used in the
UI), `variation_conditions` (visibility conditions per variation),
`variation_ordering` (draggable weights), `variation_scope`.

A variation may `parent` another; ancestors' settings are overlaid lowest-first
at read time. The child stores only what it overrides.

Bind one to a block or formatter with the render elements:

```php
$form['preset'] = ['#type' => 'neo_settings_variation', '#settings_repository_id' => 'neo_foo.settings'];
$form['settings'] = [
  '#type' => 'neo_settings',
  '#settings_id' => 'neo_foo',            // plugin id
  '#settings_variation' => $preset,       // optional variation id
  '#default_value' => $saved,
];
```

## Strict parents

`NestedArray::mergeDeepStrict()` decides "is this a list?" with
`is_int(key($value))`, and `key([])` is NULL — so an **empty array is swallowed
by the merge** instead of overriding. Any key whose value is a list therefore
needs declaring, or unchecking every box will silently fall back to the config
value:

```php
protected $strictParents = [
  ['value_multiple'],
  ['view_modes'],
];
```

`checkboxes` and multi-`select` are also discovered at form-build time, but only
during a form submit — the render path sees only the declared list. **Declare
them.** `neo_form` and `neo_menu_link` currently rely on the runtime discovery.

## Scope (front/back)

`variation_scope = "<key>"` adds a per-variation checkbox that merges a
designated frontend or backend variation's values in. Only `neo_modal` uses it.

It resolves **only** through `extendInstanceValues()` — i.e. when a caller
passes instance overrides. A plain `getActive()` never applies scope.

## Gotchas

- **`SettingsManager::$defaults` is the real definition contract**, not
  `Annotation/Settings.php`, whose properties are all NULL-defaulted and are
  filtered out by core. That is why `menu_title` works while being undeclared —
  and why a typo like `variaton_allow` is accepted silently, taking that
  plugin's entire variations UI with it.
- **`$repository->get($id)` falls back to the active settings on a miss**, and a
  *disabled* variation counts as a miss. It cannot report "not found". If you
  need that, check `getAll()` yourself.
- **Do not gate the `.config` local task on `variation_allow`.** It looks
  redundant for the eight plugins without variations, but `neo_form` hangs three
  static tabs off `base_route: neo.settings.plugin.neo_form.config`.
- **Do not make the repository's id prefix segment-aware.** `get('neo_modal')`
  legitimately resolves to the core instance today; requiring a `<plugin>_`
  separator breaks it.
- A settings plugin's cacheability is currently **write-only** — nothing reads
  `$settings->getCacheTags()`. Consumers hardcode `config:<name>` tags. If you
  cache output derived from a variation, tag it yourself with
  `config:neo_settings.variation.<id>`.

## Testing

The fixture module `tests/modules/neo_settings_test` provides a plugin with
nested values, a strict parent, and variations enabled. Kernel tests need:

```php
protected static $modules = ['system', 'user', 'neo_settings', 'neo_settings_test'];
// Add 'path_alias' if the variation entity form is built (visibility conditions).
```

Run: `ddev phpunit --testsuite neo_settings`

## Files

- `src/Plugin/SettingsBase.php` — value layers, form lifecycle, override toggles
- `src/Entity/Settings.php` — the variation entity and its inheritance graph
- `src/SettingsRepository.php` — active/variation resolution
- `src/SettingsManager.php` — plugin manager, `$defaults` definition contract
- `src/Element/NeoSettings.php`, `src/Element/NeoSettingsVariation.php`
- `config/schema/neo_settings.schema.yml` — the variation entity's schema
- `tests/` — kernel tests and the fixture module
