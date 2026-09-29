# AWD Landing Manager — Multi-Landing Architecture Handoff

Last updated: 2026-08-24  
Status: current implementation, including the GloriaFood Alternative and Restaurants Ordering System landings

This is the primary technical handoff for continuing development in another Codex chat. Read it together with `AGENTS.md`, but use this document when an older note assumes that the plugin contains only one landing.

## 1. Executive summary

AWD Landing Manager is a static-first, multilingual landing CMS inside WordPress. It is not a general page builder.

The plugin now supports **N independent landing modules**, discovered from `landings/*/manifest.php`. Two modules currently exist:

1. `gloriafood` — the original GloriaFood Alternative landing;
2. `second-landing` — displayed as **Restaurants Ordering System**, with slug `restaurants-ordering-system`.

The shared platform owns:

- module discovery and definitions;
- multilingual routes and WordPress Page identities;
- visual-editor entry points and save/publish actions;
- static HTML publication;
- forms, leads, Brevo and email tooling;
- analytics, sessions, UTM attribution and block-view events;
- cookie/privacy settings and content backups.

Each landing module owns:

- its manifest and identity;
- its developer-controlled HTML structure;
- exactly one public stylesheet;
- its public JavaScript;
- its translation/default data;
- its structured schema;
- its independent saved content option.

The second landing began as a one-time clone of GloriaFood, but it is **not live inheritance**. After initialization, both landings have separate WordPress options, styles, assets, URLs and published output.

## 2. Architectural invariants

These rules are intentional and must remain true.

1. **N modules, not a hardcoded pair.** New landings are direct children of `landings/` and are discovered automatically.
2. **One renderer.** Editor, dynamic public response and static publication all pass through `awd_render_landing()`.
3. **One content source per landing.** Saved content is stored in that landing's own WordPress option.
4. **One stylesheet per landing.** Do not bring back independent style profiles, runtime CSS variants or a second custom stylesheet for the same landing.
5. **Developer-controlled structure.** Users edit copy, links, images, menu and visibility; they do not build arbitrary DOM.
6. **Stable semantic selectors.** Component CSS and JavaScript must use durable component classes or stable image-slot keys, never a mutable anchor or generated section ID.
7. **Landing isolation.** Landing-specific CSS must be scoped beneath its manifest `root_class`.
8. **Shared services stay shared.** Do not copy analytics, forms, leads, Brevo, cookies or publishing code into a landing module.
9. **Static publication is part of completion.** A renderer/template/style change is not production-visible until the affected landing/languages are republished or the asset-version refresh republishes them.
10. **Defaults never overwrite edited production content.** Translation JSON and schema defaults are bootstrap/fallback data, not an update mechanism for saved copy.

## 3. Directory and responsibility map

```text
awd-landing-manager/
├─ awd-landing-manager.php          Plugin bootstrap, hooks, activation, migrations
├─ includes/
│  ├─ landing-registry.php          Discovers and validates landings/*/manifest.php
│  ├─ schema.php                    Builds definitions and schemas
│  ├─ helpers.php                   Content/default/URL/style helpers
│  ├─ renderer.php                  awd_render_landing()
│  ├─ publisher.php                 Atomic static publication
│  ├─ routing.php                   Pretty routes, previews, static/dynamic response
│  ├─ pages.php                     WP Page identities and landing meta
│  ├─ urls.php                      Per-landing slug/history settings
│  ├─ analytics.php                 Shared session/event/UTM tracking
│  ├─ forms.php                     Shared contact form and lead flow
│  ├─ email-campaigns.php           Shared outbound email system
│  ├─ cookies.php                   Shared consent/privacy system
│  └─ content-backup.php            Shared backup/export behavior
├─ admin/
│  ├─ admin-page.php                Admin routes/save handlers/editor surfaces
│  ├─ visual-editor.js              Inline copy/image/menu/section editing
│  └─ visual-editor.css             Editor-only UI
├─ templates/
│  ├─ landing-module.php            Generic module document/editor shell
│  ├─ landing-gloriafood.php        Legacy-compatible outer document
│  ├─ prototype-v7-body.php         Original V7 DOM source
│  └─ template-helpers.php          Applies saved legacy values and bespoke modules
├─ landings/
│  ├─ gloriafood/
│  │  ├─ manifest.php
│  │  ├─ template.php
│  │  ├─ style.css
│  │  ├─ script.js
│  │  ├─ translations.json
│  │  ├─ schema.php
│  │  └─ assets/
│  └─ second-landing/
│     ├─ manifest.php
│     ├─ template.php
│     ├─ style.css
│     ├─ script.js
│     ├─ translations.json
│     ├─ schema.php
│     └─ assets/
└─ tests/                           Regression scripts
```

Some original V7 implementation still lives under `templates/` and `assets/`. That is compatibility debt, not a reason to add new global landing code there indiscriminately.

## 4. Module discovery and manifest contract

`includes/landing-registry.php` scans direct children matching:

```text
landings/*/manifest.php
```

The registry is request-cached and available through:

- `awd_lm_landing_registry($refresh = false)`;
- `awd_lm_get_landing_module($id)`;
- `awd_lm_landing_module_data($id, 'schema'|'translations')`.

A manifest is normalized by `awd_lm_normalize_landing_manifest()`. The directory basename must equal the sanitized manifest ID. Required module files must exist.

Normalized fields:

| Field | Meaning |
| --- | --- |
| `id` | Stable machine identity; must equal module directory name. |
| `name` | Admin/editor display name. |
| `version` | Module version, default `1.0.0`. |
| `option` | WordPress option holding saved content. |
| `slug` | Default public slug. |
| `languages` | Supported subset of `de`, `en`, `fr`. |
| `root_class` | Body/root scope used by CSS and JS. |
| `template` | Module template file. |
| `style` | The module's single public stylesheet. |
| `script` | Module public behavior. |
| `translations` | Defaults/translation data. |
| `schema` | Structured field definitions. |
| `legacy_mode` | Enables the V7 compatibility content/editor model. |
| `clone_of` | Optional bootstrap source; not runtime inheritance. |

The registry has the filter `awd_lm_landing_registry`, but normal modules should be filesystem-backed and self-contained.

## 5. Current module definitions

| Property | GloriaFood Alternative | Restaurants Ordering System |
| --- | --- | --- |
| Directory / ID | `landings/gloriafood`, `gloriafood` | `landings/second-landing`, `second-landing` |
| Admin name | GloriaFood Alternative | Restaurants Ordering System |
| Content option | `awd_landing_gloriafood` | `awd_landing_second_landing` |
| Default slug | `gloriafood-alternative` | `restaurants-ordering-system` |
| Root class | `awd-landing--gloriafood` | `awd-landing--second-landing` |
| Legacy mode | yes | yes |
| Clone source | none | `gloriafood` (one-time bootstrap only) |
| Styles/scripts/assets | own module files | own module files |

### Why both are still `legacy_mode`

The original visual editor is based on V7 keys (`tN`, `lN`, stable image slots, section state and menu state). The second landing intentionally kept that mature editing contract while acquiring a separate template, design system and content option. This allowed the existing “cool editor” to work for both landings without creating a second editor.

Do not mistake `legacy_mode` for shared content. The stores are independent.

## 6. Content model and storage

The legacy-compatible content shape is broadly:

```php
[
    'prototype_text' => [
        'de' => ['t1' => '...', 't2' => '...'],
        'en' => ['t1' => '...', 't2' => '...'],
        'fr' => ['t1' => '...', 't2' => '...'],
    ],
    'prototype_links' => [
        'de' => ['l1' => ['label' => '...', 'url' => '...']],
        // en/fr
    ],
    'prototype_images' => [
        // stable slot IDs and media URLs/IDs
    ],
    'prototype_sections' => [
        'section1' => ['show' => true, 'anchor' => '...'],
    ],
    'prototype_menu' => [
        // multilingual labels and target anchors
    ],
]
```

Native structured module fields, where used, follow a section/field/language shape:

```php
$content[$section][$field][$language]
```

The editor and renderer must always read/write through the active landing definition, never directly assume `awd_landing_gloriafood`.

### One-time second-landing bootstrap

`awd-landing-manager.php` contains a one-time initialization migration which:

1. copies the current GloriaFood option into `awd_landing_second_landing` if the marker is absent;
2. sets the second landing's language slugs to `restaurants-ordering-system`;
3. records the old `second-landing` slug in history;
4. publishes the second landing in all supported languages;
5. sets a migration marker.

After this completes, later edits to one landing do not flow into the other.

## 7. Definition and render pipeline

The effective pipeline is:

```text
manifest files
    ↓ landing registry
schema + defaults + legacy adapters
    ↓ awd_lm_definitions()
route/editor/publisher chooses landing + language
    ↓ awd_render_landing($key, $lang, $content, $preview)
single module/legacy document wrapper
    ↓ module template + template helpers
one final HTML document
```

`includes/schema.php` merges discovered module information into the definitions used throughout the plugin. Code outside the registry should normally consume definitions rather than rescan directories.

`includes/renderer.php` exposes the single entrypoint:

```php
awd_render_landing($key, $lang, $content = null, $preview = false)
```

For module landings it makes the render content available for the module wrapper, includes `templates/landing-module.php`, and cleans up its temporary global afterward.

`templates/landing-module.php` provides:

- SEO title/description, canonical, robots and hreflang;
- site icon support;
- the landing-specific stylesheet and script;
- root classes, including the manifest `root_class`;
- editor toolbar, language tabs and landing selector;
- structured Page fields when a schema exposes them;
- the module template itself;
- public analytics bootstrap with the landing key.

There must never be separate editor HTML, dynamic-public HTML and static-public HTML implementations.

## 8. Visual editor and save flow

The visual editor uses the same rendered page with editor controls added.

The main save action is `awd_lm_save`, handled through the shared admin save handler. The request includes at least landing ID, language and nonce, so the handler can save to the active module's option.

The editor supports:

- inline editable text values;
- multilingual links and button labels;
- Media Library image replacement via stable slots;
- section visibility and anchors;
- analytics name for sections;
- menu item creation/edit/removal;
- draft save and publish-all.

Image slots are intentionally language-independent unless a component explicitly requires localized artwork.

### Editor/public parity rule

When an image or text looks correct immediately after editing but changes after Publish, the usual cause is one of:

- the editor preview patched only the live DOM but did not save the correct slot/key;
- the public renderer uses a different fallback/default path;
- static HTML was not regenerated;
- CSS targets the editor DOM or a mutable ID rather than the stable component.

Fix the shared data/render contract. Do not mask the difference with editor-only CSS.

## 9. Publication and static output

`includes/publisher.php` renders each landing/language through the same renderer and writes static HTML under:

```text
/awd-landings/<landing-id>/<language>/index.html
```

Publication is atomic: temporary and backup paths are used before replacement. Status is stored per landing, e.g. `awd_lm_publish_status_<key>`.

The plugin also tracks an asset version. When plugin assets change, the refresh path can republish all definitions and supported languages.

Implications:

- publishing one landing must not overwrite another landing's static directory;
- changes to shared templates may require republishing all landings;
- changes to one module's CSS/template normally require republishing that module's languages;
- a browser test of the editor alone is not proof that the public static page is correct.

## 10. URLs, WordPress pages and routing

Each landing/language has a real WordPress Page identity, but the page body is not the source of the landing. `includes/pages.php` stores meta such as:

- `_awd_lm_landing_key`;
- landing language.

The canonical public shape is:

```text
/<language>/<landing-slug>/
```

Examples:

```text
/de/gloriafood-alternative/
/en/restaurants-ordering-system/
```

`includes/urls.php` stores URL settings separately:

- GloriaFood retains the historical `awd_lm_url_settings` option;
- other landings use `awd_lm_url_settings_<landing-id>`.

URL changes are transactional: publish the proposed URL first, then commit settings/pages/history; roll back on failure. Slug history is retained (bounded) for redirects.

`includes/routing.php` handles:

- pretty paths;
- WordPress Page identities;
- editor/preview queries;
- static file response when available;
- dynamic renderer fallback;
- protection from falling through to the theme's Blog/archive page.

Known debt: old GloriaFood-specific URL helpers still coexist with newer generic URL code. New work must use the generic per-definition path and avoid creating a third implementation.

## 11. CSS, JavaScript and asset isolation

### CSS

Every landing has one manifest stylesheet. The second landing is heavily scoped under:

```css
.awd-landing--second-landing { ... }
```

Rules for new CSS:

- use a semantic component class such as `.wtg-menu-performance` or `.wtg-orders-app`;
- keep second-landing visual changes beneath `.awd-landing--second-landing`;
- do not use arbitrary inline styles or template `<style>` blocks;
- do not solve specificity problems with escalating `!important` patches;
- do not create a second selectable style for a landing.

### JavaScript

Each module owns one public script. Shared editor behavior remains in `admin/visual-editor.js`. Public interactions must be safe when the component is absent and must honor reduced-motion preferences where animation is involved.

### Images

Use stable `data-awd-image-slot` values and semantic image classes. The slot is the persistence identity; the surrounding section anchor is not.

## 12. Second-landing specialization

The Restaurants Ordering System landing is not merely recolored CSS. It has its own design, copy and bespoke sections while retaining the legacy editor contract.

Important custom component hooks include:

- `.wtg-menu-performance` — Menu Performance section;
- `.wtg-orders-app` — Order Management & Orders App section;
- image slot `mock99` for the Orders App visual;
- `#loesungen` and the building-block/puzzle presentation;
- `.awd-landing--second-landing` as the root style boundary.

Some bespoke section construction/transformation currently lives in `templates/template-helpers.php`, selected by the active landing key. This is an important coupling point: inspect it before assuming that all visible second-landing markup comes directly from `landings/second-landing/template.php`.

### Critical regression already encountered

Menu Performance was once styled through the mutable anchor `#menu-marketing`. Inserting the Orders App section changed section/anchor association. The following block inherited huge image/layout rules and “exploded”. The correct repair was to scope styles to stable `.wtg-menu-performance`.

**Non-negotiable:** an editable anchor is navigation/content data, never a component type selector.

## 13. Shared platform services

These are global capabilities and must remain centralized.

### Analytics

Analytics records anonymous sessions, UTM attribution, timed events, viewed blocks and lead conversion. Events must carry the landing identity so reports can separate landings while sharing one database/dashboard implementation.

Block tracking relies on stable block IDs plus editable analytics names. Do not use the analytics name as a CSS selector.

### Forms, leads and Brevo

The contact form, privacy consent, newsletter consent, Turnstile validation, local lead database, Brevo synchronization, recipient lists and transactional emails are shared services. Landing-specific copy or layout may differ, but submission/storage/sync logic should not be forked.

### Cookies and privacy

The consent panel and cookie-policy tooling are shared. A landing may style the panel through its root scope, but consent state and policy generation remain centralized.

Known debt: some cookie/privacy defaults or links may still mention the original GloriaFood landing. Audit these before onboarding a new production landing.

### Backups

Content backup/export should enumerate definitions and their option values. A backup must identify the landing ID and must not flatten all landing content into one ambiguous payload.

## 14. Adding a third or Nth landing

Use this sequence.

1. Copy `landings/second-landing/` to `landings/<new-id>/`.
2. Rename the manifest `id` and ensure it exactly matches the directory basename.
3. Give it a distinct `name`, `option`, `slug` and `root_class`.
4. Decide whether it temporarily needs `legacy_mode`:
   - use it when reusing the mature V7 visual-editor key model;
   - prefer a native structured schema for a genuinely new component/data model.
5. Remove `clone_of` unless a one-time bootstrap is explicitly required.
6. Replace template, stylesheet, script, translations, schema and assets.
7. Scope all landing-specific rules under the new root class.
8. Use stable semantic component classes and image slots.
9. Activate/refresh the registry and ensure option/page initialization runs.
10. Configure per-language slugs.
11. Publish all supported languages.
12. Test editor, dynamic preview and public static routes independently.
13. Verify shared analytics contains the new landing dimension and shared forms still submit correctly.
14. Add/update registry and architecture regression tests.

Do not add the third landing to a hardcoded `if ($key === '...')` chain unless it is a strictly local compatibility adapter. Prefer manifest data and module-owned templates.

## 15. Safe development workflow

### Source of truth

Primary repository:

```text
C:\Users\USER\Documents\AWD Plugin\awd-landing-manager
```

Active LocalWP installation used for browser testing:

```text
C:\Users\USER\Local Sites\ailwilldolu\app\public\wp-content\plugins\awd-landing-manager
```

Edit the source repository first. Then sync changed files into the active LocalWP plugin before browser verification. Do not accidentally treat the installed copy as the only source.

### Minimum validation

From the plugin root:

```powershell
$phpFiles = rg --files -g '*.php'
foreach ($phpFile in $phpFiles) { php -l $phpFile }

php tests/landing-registry-regression.php
php tests/css-architecture-regression.php
php tests/text-rendering-regression.php
php tests/editor-toolbar-regression.php
php tests/analytics-multilanding-regression.php
```

Then test in a real browser at desktop and mobile widths:

1. switch between both landing definitions in the editor;
2. switch DE/EN/FR;
3. edit text and save draft;
4. replace an image and publish;
5. reload editor and public page to prove persistence;
6. hide/show a section and republish;
7. add/edit/delete a menu item;
8. submit the form with privacy, Turnstile and optional newsletter;
9. follow a UTM link and confirm a landing-specific session/event timeline;
10. check canonical/hreflang and no redirect loop;
11. check that no following section inherits the preceding section's layout.

## 16. Non-regression checklist

Before shipping any multi-landing change, confirm all of these.

- [ ] Registry discovers every direct module and ignores invalid manifests safely.
- [ ] Manifest ID equals directory name.
- [ ] Every landing has a unique option, slug and root class.
- [ ] GloriaFood edits do not alter Restaurants Ordering System content, and vice versa.
- [ ] Editor, preview, dynamic page and static page use the same renderer.
- [ ] Public page was republished after renderer/template/style changes.
- [ ] CSS is rooted to the intended landing.
- [ ] No component CSS depends on editable anchors or analytics names.
- [ ] Image replacement survives save, publish and reload.
- [ ] A newly inserted section does not alter the next section's dimensions.
- [ ] Hidden sections remain visible-but-dimmed in editor and absent publicly.
- [ ] Menu changes persist and deletion works.
- [ ] Shared form/lead/Brevo/cookie/analytics code has not been duplicated.
- [ ] Analytics records the correct landing ID and block chronology.
- [ ] Reduced-motion behavior is respected.
- [ ] Mobile layout has no horizontal overflow.
- [ ] Defaults/migrations do not overwrite edited production content.
- [ ] No large base64-encoded PHP payloads are introduced; translations/assets stay as normal files.

## 17. Known debt and caution areas

1. Both current modules still depend on the legacy V7/tN editing contract.
2. `templates/template-helpers.php` contains substantial second-landing specialization and should eventually be decomposed into module-owned helpers/components.
3. Historical GloriaFood-only URL helpers remain beside generic multi-landing URL code.
4. Some old admin CSS-profile UI code may remain even though runtime architecture now enforces one stylesheet per landing. Treat it as dead/legacy surface, not a supported feature.
5. Cookie/privacy defaults and links need a multi-landing audit.
6. Older README/AGENTS passages can contain stale one-landing paths or responsibilities.
7. The one-time clone migration is safe only while guarded by its marker; never make it run on every update.
8. Translation JSON is default data. It is not a deployment-time overwrite of WordPress option content.

## 18. Troubleshooting map

| Symptom | First places to inspect |
| --- | --- |
| Landing absent from selector | manifest ID/directory match, required files, `awd_lm_landing_registry()` |
| Editor shows wrong landing | request `landing` key, definition lookup, landing option |
| Public page differs from editor | saved slot/key, renderer fallback, static republish, editor-only CSS |
| Publish changes disappear | save handler target option, defaults overwriting data, static output path |
| Image becomes blurry after publish | saved media URL/size, renderer normalization, CSS `background-size`/`object-fit` |
| New block breaks following block | unclosed markup, overly broad selectors, mutable anchor-ID scoping |
| Page opens theme Blog/404 | routing match, WP page meta, slug settings, static fallback |
| Redirect loop | slug history/canonical normalization, generic URL handler vs legacy helper |
| Analytics session missing | cookie consent, public analytics bootstrap, landing key, endpoint response |
| Changes affect both landings | duplicate option names, unscoped CSS, global template-helper branch |

## 19. Mental model for future work

Think of the plugin as a shared CMS kernel plus installable landing modules:

```text
                    ┌──────────────────────────┐
                    │ Shared CMS kernel        │
                    │ registry / editor / URLs │
                    │ publish / analytics      │
                    │ forms / email / cookies  │
                    └────────────┬─────────────┘
                                 │ definitions
             ┌───────────────────┼───────────────────┐
             │                   │                   │
     ┌───────▼────────┐  ┌───────▼────────┐  ┌──────▼─────────┐
     │ gloriafood     │  │ second-landing │  │ future module  │
     │ own content    │  │ own content    │  │ own content    │
     │ own design     │  │ own design     │  │ own design     │
     └────────────────┘  └────────────────┘  └────────────────┘
```

If a requested change is about identity, layout or visual behavior, it usually belongs in the module. If it is about saving, publishing, attribution, leads, consent or routing, it usually belongs in the shared kernel and must work for every definition.

