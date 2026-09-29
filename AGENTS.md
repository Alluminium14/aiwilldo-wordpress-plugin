# AGENTS.md — Working on AWD Landing Manager

This file is the maintenance guide for future Codex agents. **Read it in full before changing this plugin.** Treat it as the authoritative overview of its architecture, the local deployment workflow, and the guardrails created from real production/editor failures.

> **Multi-landing handoff:** before changing landing discovery, rendering, publishing, URLs, shared services, or the Restaurants Ordering System landing, read [`MULTI_LANDING_ARCHITECTURE.md`](MULTI_LANDING_ARCHITECTURE.md). It documents the current N-landing architecture and supersedes older one-landing assumptions that may still appear later in this historical guide.

> **Current-state correction (2026-08-25):** this plugin is no longer a one-landing GloriaFood plugin. The active design work is on `second-landing` (Restaurants Ordering System). For that landing, the source template/style/script/defaults are under `landings/second-landing/`, with additional legacy-compatible transformations in `templates/template-helpers.php`. Any later passage that says all content lives in `awd_landing_gloriafood`, all public CSS belongs in `assets/landing-v1.css`, or only `gloriafood` must be published is historical and must not be applied to `second-landing`.

> Non-negotiable: do not make a “quick visual fix” by adding a new per-element class, an inline style, a template `<style>` block, or a higher-specificity `!important` override. Find the existing semantic component and central shared rule first. The client explicitly wants a small, coherent system—not an accumulation of patches.

## Product rule

The plugin is a small static-first landing CMS, not a page builder.

- Developers own structure, layout, components, and stylesheet defaults.
- Marketing users own copy, links, images, section visibility, and menu entries through Landing Manager.
- WordPress is the authenticated editor and Media Library source.
- Published visitors receive generated static HTML wherever server configuration permits it.

Do not reintroduce Gutenberg blocks, arbitrary HTML entry, drag-and-drop layout controls, or a second renderer.

## Important files

| File | Responsibility |
| --- | --- |
| `awd-landing-manager.php` | Plugin bootstrap, hooks, activation. |
| `includes/landing-registry.php` | Discovers and validates N landing modules from `landings/*/manifest.php`. |
| `includes/schema.php` | Landing definitions, permitted structured fields, starter content. |
| `includes/helpers.php` | Content lookup, URLs, draft hashes, landing stylesheet registry. |
| `includes/renderer.php` | Single render entrypoint: `awd_render_landing()`. |
| `includes/publisher.php` | Temporary-file publication of static HTML. |
| `includes/routing.php` | Pretty URLs, static-file fallback and preview access. |
| `includes/pages.php` | Real WordPress Page identities for DE/EN/FR. |
| `admin/admin-page.php` | Save handlers, visual-editor route, CSS editor. |
| `admin/visual-editor.js` | Inline text editor, image slots, menu, anchors and section controls. |
| `admin/visual-editor.css` | Editor-only toolbar, controls, modal and pause behaviour. |
| `templates/landing-gloriafood.php` | Outer document, SEO tags, editor toolbar and asset links. |
| `templates/prototype-v7-body.php` | The complete developer-controlled V7 DOM source. |
| `templates/template-helpers.php` | Safely applies saved values to the V7 body at render time. |
| `assets/landing-v1.css` | Default public stylesheet extracted from V7. |
| `assets/landing-v1.js` | Public interactions: menu, slider, reveal, FAQ. |
| `landings/second-landing/manifest.php` | Restaurants Ordering System identity, option, routes and owned assets. |
| `landings/second-landing/template.php` | Module-owned second-landing template entrypoint. |
| `landings/second-landing/style.css` | The only public stylesheet for `second-landing`; scope it under `.awd-landing--second-landing`. |
| `landings/second-landing/script.js` | Public behavior specific to `second-landing`. |
| `landings/second-landing/default-content.json` | DE/EN/FR bootstrap/fallback copy; it never overwrites saved production content. |

## One renderer; one content source per landing

Never build a separate preview page or a separate static HTML template.

`awd_render_landing( $landing_id, $language )` is used for preview, WordPress fallback and static publishing. The definition/manifest selects the correct module template and assets. `second-landing` still uses the mature legacy V7 key model, with bespoke transformations in `templates/template-helpers.php`.

Landing content is stored independently per landing. Current options include `awd_landing_gloriafood` and `awd_landing_second_landing`. Never read or write the GloriaFood option when the active definition is `second-landing`.

The generic V7 editor stores these additional buckets there:

- `prototype_text[language][tN]`: inline text nodes in V7 DOM order.
- `prototype_links[language][lN]`: regular link URLs in V7 DOM order.
- `prototype_images[hero|mockN|productN]`: WordPress attachment IDs.
- `prototype_sections[sectionN]`: `anchor` and `enabled` values.
- `prototype_menu[existingN|added]`: label, selected anchor and deletion state.

These keys are intentionally generated from stable V7 DOM order. If changing the source body, avoid casually moving or deleting text nodes before existing content: that would remap `tN` values. For a large redesign, migrate the saved option deliberately or create a new landing definition/version.

## Changing copy or functionality

### Add a text field or a small content feature

Prefer extending the generic visual editor first. If the target is an ordinary visible text node in `prototype-v7-body.php`, it is already inline-editable without adding an admin form.

For specialised content (repeaters, structured pricing, forms, rich media):

1. Add a developer-controlled element to `prototype-v7-body.php`.
2. Add only the needed data schema and starter values in `includes/schema.php`.
3. Save/sanitise it in `awd_lm_sanitize_content()`.
4. Apply it from `awd_lm_render_prototype_body()` or the outer template.
5. Add a focused editor control in `admin/visual-editor.js`.
6. Verify preview and static publishing use the new value.

### Add or replace image zones

Do not let users inject arbitrary images anywhere in the DOM. Add a named image slot.

1. Mark or identify the developer-controlled visual element in `prototype-v7-body.php`.
2. In `awd_lm_render_prototype_body()`, add a stable `data-awd-image-slot` and `data-awd-image-id` attribute and apply the selected attachment URL.
3. Permit the slot key in the `prototype_images` sanitisation regex.
4. `visual-editor.js` already attaches `Set image` to every element with `data-awd-image-slot`.
5. Add CSS in `visual-editor.css` to hide a CSS/HTML placeholder only when the slot has `awd-lm-slot-image`.

Image-slot inventory evolves with each module. Inspect the active module template, `templates/template-helpers.php`, schema/default data and existing `data-awd-image-slot` attributes instead of relying on a fixed historical list.

### Change the landing style or layout

For normal requested style/layout changes:

1. Change semantic structure in `templates/prototype-v7-body.php` only when required.
2. Change default visual rules in the active landing's manifest-owned stylesheet. For Restaurants Ordering System this is `landings/second-landing/style.css`; do not put its rules in `assets/landing-v1.css`.
3. Keep all public classes `wtg-` prefixed.
4. Keep all editor-only selectors scoped to `body.awd-lm-visual` in `admin/visual-editor.css`.
5. Verify desktop and mobile. The editor toolbar is fixed; the site nav is offset beneath it only in editor mode.

The single-stylesheet architecture is described in **CSS architecture and change policy** below. Each landing owns one plugin-managed CSS file; public pages never execute CSS stored in WordPress options. Editor controls remain isolated in `admin/visual-editor.css`.

### Change the plugin's core behaviour

Keep responsibilities separated:

- Routes belong in `includes/routing.php`.
- Safe static file writes belong in `includes/publisher.php`.
- Page creation/identity belongs in `includes/pages.php`.
- Admin requests require capability checks, nonces and sanitisation in `admin/admin-page.php`.

The publisher must render first, write a temporary file, protect the previous file, then replace it. Do not write directly over `index.html`.

## Editor behaviours to preserve

- Inline text gets focus safely; when editing starts, CSS motion and the hero slider pause.
- `Esc` / blur resumes motion.
- Section controls edit anchors and visibility. A disabled section is removed only from the next published render.
- Menu controls set a label and choose an existing anchor. Deleting a menu item does not delete its section.
- The design information screen opens in a separate tab; it has no CSS editor.
- Media selection uses `wp.media`; do not replace it with a raw URL field.

## WordPress Pages, languages and URLs

Historically, `includes/pages.php` created real Pages for:

- `/de/gloriafood-alternative/`
- `/en/gloriafood-alternative/`
- `/fr/gloriafood-alternative/`

The current implementation creates language-specific Page identities for every registered landing definition, including Restaurants Ordering System routes such as `/en/restaurants-ordering-system/`. They are useful for menus and page-level SEO identity. Their visible body is still generated by Landing Manager. Do not copy landing content into WordPress page content.

Language switching is one landing definition with language-specific values, not three independent landings. Fallback order remains requested language → German → empty value.

## Verification checklist

After every material change:

1. Run `php -l` for every plugin PHP file using the site's PHP runtime.
2. Run `node --check` on `assets/landing-v1.js` and `admin/visual-editor.js`.
3. Render all languages with `awd_render_landing()` and ensure valid non-empty HTML.
4. Call `awd_publish_landing()` for DE, EN and FR on local/staging.
5. Check the visual editor after a hard refresh: text, anchors, menu and image selectors.
6. Confirm normal public HTML contains no editor toolbar, buttons, hidden inputs, or editor CSS.

## Local installation used in this workspace

The active local copy is normally:

`C:\Users\USER\Local Sites\ailwilldolu\app\public\wp-content\plugins\awd-landing-manager`

The source copy is this directory. `Build-AWD-Landing-Manager.ps1` automatically syncs this source into that local plugin directory after its syntax checks and before it creates each ZIP. **Do not run the builder for every edit.** It is a release/ZIP operation and must be run only when the user asks for a ZIP or a release build. For normal work, explicitly synchronise the changed source files to the local plugin and publish the three local language pages. Do not touch unrelated plugins or WordPress core.

---

## Current maintenance runbook — read before editing

### Live handoff — Restaurants Ordering System (2026-08-25)

The current visual/content iteration is on `second-landing`, not GloriaFood. Source of truth and the active LocalWP copy were kept in sync manually during the latest session. No release ZIP should be assumed to contain the latest changes unless a new build is explicitly requested.

Recently changed areas:

- `landings/second-landing/style.css`: AI Assistant chat/image layout, FAQ recovery, semantic watermarks, workflow cards/connector, the five `#loesungen` brick cards, web-design option cards and their responsive layout;
- `landings/second-landing/default-content.json`: current DE/EN/FR defaults, including workflow and building-block copy;
- `templates/template-helpers.php`: second-landing bespoke blocks and matching fallback translations;
- stable image metadata/default handling was extended; images themselves remain shared, language-independent slots.

Current unfinished request at handoff: in the Web Design section, the four SVG pictograms inside the round side-card icon containers still look optically off-centre. A previous CSS attempt added `display:block`, `margin:auto` and `line-height:0`, but the user reports that it did not solve the visual issue. Inspect the actual SVG paths/viewBoxes in `templates/prototype-v7-body.php` and the rendered browser geometry before changing CSS again. The problem may be optical/path geometry rather than container alignment. Do not add another blind specificity override.

For default-copy work, update both `landings/second-landing/default-content.json` and the matching fallback arrays in `templates/template-helpers.php`, with real DE/EN/FR translations. Remember: these files bootstrap/fallback content; saved WordPress option values may still override them until the user deliberately resets/imports defaults.

For browser verification, the visual editor requires an authenticated WordPress browser session. If the automation browser is not logged in, do not claim that the editor was visually checked. Use an already authenticated user tab when available, or clearly report the limitation.

### The two copies that must stay in sync

There are intentionally two copies during local development:

1. Source of truth: `C:\Users\USER\Documents\AWD Plugin\awd-landing-manager`
2. Active LocalWP plugin: `C:\Users\USER\Local Sites\ailwilldolu\app\public\wp-content\plugins\awd-landing-manager`

For every source-code, template, CSS or JavaScript change, copy the same changed files to the active local plugin before testing. A change only in the source folder does **not** affect the local website. A change only in the installed copy will be lost at the next build.

After a public rendering/style/content-schema change, republish all three local pages. This is the local command used in this workspace:

```powershell
& 'C:\Users\USER\AppData\Roaming\Local\lightning-services\php-8.2.29+0\bin\win64\php.exe' -d 'extension_dir=C:\Users\USER\AppData\Roaming\Local\lightning-services\php-8.2.29+0\bin\win64\ext' -d 'extension=php_mysqli.dll' -r 'require_once "C:\Users\USER\Local Sites\ailwilldolu\app\public\wp-load.php"; foreach (awd_lm_definitions() as $landing_id => $definition) { foreach (($definition["languages"] ?? array("de","en","fr")) as $lang) { $result=awd_publish_landing($landing_id,$lang); if(is_wp_error($result)){ fwrite(STDERR,$landing_id."/".$lang.": ".$result->get_error_message()); exit(1); } } } echo "published";'
```

The generated public files are landing-specific. Current directories include:

```text
C:\Users\USER\Local Sites\ailwilldolu\app\public\wp-content\uploads\awd-landings\gloriafood\de\index.html
C:\Users\USER\Local Sites\ailwilldolu\app\public\wp-content\uploads\awd-landings\gloriafood\en\index.html
C:\Users\USER\Local Sites\ailwilldolu\app\public\wp-content\uploads\awd-landings\gloriafood\fr\index.html
C:\Users\USER\Local Sites\ailwilldolu\app\public\wp-content\uploads\awd-landings\second-landing\de\index.html
C:\Users\USER\Local Sites\ailwilldolu\app\public\wp-content\uploads\awd-landings\second-landing\en\index.html
C:\Users\USER\Local Sites\ailwilldolu\app\public\wp-content\uploads\awd-landings\second-landing\fr\index.html
```

Only make a ZIP when explicitly asked. The release command is:

```powershell
& 'C:\Users\USER\Documents\AWD Plugin\Build-AWD-Landing-Manager.ps1'
```

It writes the release ZIP to `C:\Users\USER\Desktop\AWD Landing Manager Builds\` and synchronises the local plugin as part of its release flow.

### Mandatory sequence for any change

1. Read the affected template and the applicable shared CSS rules first. Search with `rg` before editing.
2. Decide whether the request is content, layout, a shared component visual rule, a mobile rule, editor behaviour, or publisher behaviour.
3. Make the smallest semantic change in the source copy. Do not rewrite unrelated content or markup.
4. Mirror the modified file(s) to the active local plugin.
5. Run proportionate syntax checks (`php -l`, `node --check` for JS when applicable).
6. Republish DE, EN and FR if output can change.
7. For visual/mobile work, inspect the actual local page in a browser at desktop and mobile widths before saying it is fixed. Hard-refresh first; generated CSS files are content-hashed but a browser may still show a cached page or stale static HTML.
8. Do not create a ZIP unless the user asks for it.

If the user asks for a purely visual adjustment, **do not silently change copy, translations, card order, section order, anchors, form handling, or generated assets.** Several past regressions came from changing content while attempting a CSS fix.

## CSS architecture and change policy

### One stylesheet per landing

Each landing has exactly one plugin-managed public stylesheet. GloriaFood uses
`assets/landing-v1.css`. Native modules declare their stylesheet in
`landings/<id>/manifest.php` and are discovered automatically. The
`awd_lm_landing_stylesheets` filter remains an extension point.
`awd_lm_landing_css_url( $landing_key )`
returns that file directly; it does not generate or combine CSS in uploads.

The former profile/custom-layer system was retired in architecture 2. During
migration, the complete old `awd_lm_css_profiles` value is preserved once in
`awd_lm_css_profiles_backup_before_single_stylesheet_2`. Archived CSS is never
executed. Do not remove this backup automatically.

### Hard rules for landing styles

- Put all public GloriaFood rules in `assets/landing-v1.css`.
- A second landing must have a different CSS file and a root namespace; never
  make it share or override GloriaFood selectors accidentally.
- Do not reintroduce selectable profiles, generated CSS in uploads, Custom CSS
  textareas, per-language styles, template `<style>` blocks, or option-based CSS.
- The WordPress design screen is informational only. Developers change the
  module stylesheet and release a tested plugin update.
- If languages look different, diagnose static publication/cache/content first;
  CSS is landing-specific, not language-specific.

### Typography: canonical system only

The client explicitly rejected a proliferation of font sizes, families and weights. Reuse the existing canonical tokens/classes:

- `--wtg-body-copy-size` / `--wtg-body-copy-line` for ordinary copy.
- `--wtg-meta-copy-size` / `--wtg-meta-copy-line` for compact metadata.
- `.wtg-h1`, `.wtg-h2`, `.wtg-h3` for headings.
- `.wtg-lead` for introductory/subheading copy.
- `.wtg-copy` for normal body copy.
- `.wtg-kicker` for the small bordered eyebrow/label.

`.wtg-h2` currently has an explicit `line-height: 1.1`; change it centrally if all H2s need more/less line spacing. Do not add a one-off line-height to a particular heading. Do not introduce a new font-size utility to fix one component unless the user explicitly asks to expand the canonical scale.

Different colour is the only normal reason for a narrowly scoped modifier. It is **not** a justification for a new font family, font weight, letter spacing, font size, or nested typographic style. Avoid inline `style=` attributes for public typography.

### Colour and component rules currently expected

- Page base: white (`--wtg-bg` / `.wtg-page`), not cream or an obsolete archived background.
- The global dark panel colour is blue `#142A42`, generally through `--wtg-blue-gradient` for the subtle radial treatment.
- Gold is the main accent. Use the established gold token/rules rather than inventing green/orange text variants for card labels.
- Blue/dark panels use light text; their gold titles/accents must retain contrast. Never leave a dark text class on a dark panel.
- Primary CTA is gold background with white text: reuse `.wtg-btn--primary`. Do not create a special “gold button” variant for a single button.
- Public cards use the canonical shared card class (`.wtg-card`) or the existing named component built from it. Do not recreate card typography separately for FAQ, flow, advantage or module cards.
- Image cards use the existing product/module card component. Preserve a usable visual aspect ratio and `object-fit: cover`; do not reduce images to a hard, shallow height that squashes them.
- Hover is shared: a small raise/scale and soft shadow where the shared card component supports it. Do not add a gold hover border for an isolated group.
- Requested alternating blue “chessboard” cards are a component/group choice, not a new typography system. Keep the shared card content classes and change only background/text colour modifiers.
- Blue compact comparison blocks share one component style. If making a new blue block, compose it from that component instead of copying its CSS into a section selector.

### What must not be reintroduced

The project previously accumulated “safety fixes” in template CSS with `!important`. Those caused conflicting rules and broken mobile layouts. Do not add them back.

Specifically prohibited without an explicit, documented migration:

- template-local `<style>` blocks for public layout;
- `!important` as a substitute for understanding specificity;
- broad selectors such as `section *`, `a *`, `h2 *` that restyle unrelated content;
- duplicate card classes with near-identical typography;
- per-section desktop/mobile padding exceptions where the shared spacing token/component can be changed;
- generated or user-entered CSS stored in WordPress options;
- individual font-size/weight classes for text that can use `.wtg-copy`, `.wtg-lead`, `.wtg-h1`, `.wtg-h2`, `.wtg-h3`, or `.wtg-kicker`.

When a historical override conflicts, remove or migrate the obsolete rule—do not add a stronger rule above it. Search the landing's single stylesheet before adding any override.

## Responsive layout rules

The primary mobile breakpoint is `760px`.

- All ordinary non-card content must be inside `.wtg-container`. On mobile it intentionally uses `calc(100% - 40px)`, providing a consistent 20px left/right edge matching cards.
- Do not add random section padding to “align” a particular text block. Fix the container/component instead.
- Use `grid-template-columns: repeat(..., minmax(0, 1fr))` and `min-width: 0` on grid children when needed. This prevents long text or comparison rows from forcing horizontal overflow.
- Verify at around 390px / 844px as well as desktop. Treat real horizontal overflow as a bug; the editor’s hidden honeypot is the only known intentional offscreen exception.
- On mobile, content and images should flow naturally. Do not retain desktop reserved vertical space, absolute positioning or large fixed hero min-heights when copy is shorter.
- The mobile header must always retain a working logo, hamburger toggle and language switcher. The desktop navigation must not be squeezed merely to make room for language controls.
- Do not claim a mobile fix without viewing the rendered local page. Check opening/closing hamburger navigation, language links, image aspect ratio, FAQ rows, card grids and no side overflow.

## Structure, content and translations: preservation rules

`templates/prototype-v7-body.php` is a structured source document, not a disposable mockup.

- Preserve semantic section IDs and `data-awd-section-key` values. Existing common section anchors include `problem`, `platform`, `loesungen`, `take-away`, `menu-marketing`, `reservation`, `webdesign`, `ki`, `vorteile`, `gloriafood`, `cases`, `faq` and `demo`.
- Do not move/delete/reorder visible text nodes casually. The editor’s `tN` mapping is derived from DOM order. A shifted node can make saved DE/EN/FR text appear in the wrong place.
- If a structural refactor genuinely requires node reordering, implement a deliberate migration of `prototype_text` / `prototype_links` keys and document it in code and this file.
- Menu labels are language-specific (`prototype_menu`), while menu anchors point to shared sections. A menu translation request must update the menu model for all three languages, not only the currently open language.
- Images are deliberately language-independent shared slots (`prototype_images`). Do not make separate image copies per language unless the user explicitly changes that product decision.
- All copy replacement requests require correct German, English and French values. Never put the German source sentence unchanged into all three language stores. Preserve legal links and form functionality unless told otherwise.
- When asked to restyle cards, retain their titles, copy and ordering. Do not turn an existing title into an icon label or move descriptions into different cards unless that content redesign was explicitly requested.

## Editor and publishing safety

- The public landing and editor must render through the same renderer. Never make a separate editor-only or static-only body template.
- Public client CSS must never style the toolbar, edit buttons, anchor controls or media dialog. Editor-only CSS stays under `body.awd-lm-visual` in `admin/visual-editor.css`.
- Keep `wp.media` image selection; do not replace it with an unmanaged URL input.
- Saving a draft must save state without redirecting the user to a bogus URL or automatically scrolling to the draft. Publishing saves the draft and republishes all languages.
- The publisher uses a temporary file and replacement strategy. Keep this safe-write behaviour.
- Contact-form success is only success after the request route returns a valid response; visual success text must be visible and not confused with a browser/network failure. Email delivery is delegated to WordPress/WP Mail SMTP, so diagnose SMTP separately from frontend form handling.

## Practical verification matrix

For a change touching a public component, verify at least:

| Area | What to check |
| --- | --- |
| DE / EN / FR | New rendering is published in all three static directories; translations remain in the correct language. |
| Desktop | Header fits, section spacing is consistent, cards line up, image aspect ratios look intentional. |
| Mobile (~390px) | 20px content edges, no horizontal overflow, header/menu/language switch work, images do not have unexplained vertical gaps. |
| Editor | Text remains editable, link/image controls remain available, no public CSS damages the editor chrome. |
| Landing CSS | The landing's one stylesheet loads for all languages; no generated/custom CSS asset is emitted. |

Use the browser for actual visual verification when the user asks to check/inspect. Do not state that something was “checked in browser” based only on reading CSS.

## Safe decision tree

Before writing code, use this decision order:

1. **Text/link/image/menu value?** Use the saved content model/editor; preserve the V7 structure.
2. **Existing component looks wrong everywhere?** Fix its shared rule in `assets/landing-v1.css`.
3. **Only one landing differs?** Fix that landing module's plugin-managed stylesheet.
4. **Client-specific one-off brand experiment?** Implement it deliberately in the landing stylesheet and release it as tested code; do not create runtime Custom CSS.
5. **A new component is genuinely required?** Add semantic V7 markup plus one reusable `wtg-` component class, then document it here.
6. **A new behaviour is required?** Modify the renderer/admin/public JS in their existing responsibility files and test static output plus editor output.

When in doubt, stop and inspect the current markup and the landing's CSS cascade. Do not solve uncertainty by adding another override.
