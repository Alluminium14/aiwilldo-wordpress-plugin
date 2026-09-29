# Landing modules

Every directory directly below `landings/` is one independent landing type.
The registry discovers `*/manifest.php` automatically, so the plugin supports
any number of landing modules without adding their IDs to core PHP files.

A native module owns:

- `manifest.php` — ID, label, default slug and resource paths;
- `template.php` — body markup rendered by the shared document shell;
- `style.css` — the module's only public stylesheet;
- `script.js` — module-specific interactions;
- `translations.json` — DE/EN/FR seed content grouped by section and field;
- `schema.php` — editable sections and field types.

To add a landing, copy `second-landing/`, rename its directory and manifest ID,
then replace its design and content. Routes, WordPress pages, publishing,
backups, URL settings and analytics are picked up through the registry.

`gloriafood/` is a compatibility adapter. It keeps the established V7 renderer,
saved `tN` text keys and existing `awd_landing_gloriafood` option intact.
