# AWD Landing Manager

Content-only multilingual WordPress editor with safe static publishing. Landing
types are discovered from `landings/*/manifest.php`, so one installation can
host any number of independently designed DE/EN/FR landings while sharing the
same publishing, analytics, integrations, privacy settings and admin shell.

Each landing owns exactly one plugin-managed stylesheet. Runtime CSS profiles
and WordPress-stored public CSS are intentionally not used. See
`landings/README.md` for the module contract and copyable starter module.

## Install

Zip the `awd-landing-manager` directory, upload it in Plugins → Add New → Upload Plugin, then activate it. The plugin creates one normal WordPress Page for each language, nested below `/de/`, `/en/` and `/fr/`. Open **Landing Manager**, save the starter draft if desired, and click **Publish all languages**. The pages are suitable for menus and page-level SEO settings; their visible landing content is always rendered from Landing Manager and is not edited in the standard WordPress page editor.

Public URLs:

- `/de/gloriafood-alternative/`
- `/en/gloriafood-alternative/`
- `/fr/gloriafood-alternative/`

WordPress can serve the generated file as a safe fallback. To bypass WordPress/PHP for normal traffic, add a server-level rule. Test it on staging first.

### Nginx example

Inside the relevant server block, before the normal WordPress `location /`:

```nginx
location ~ ^/(de|en|fr)/gloriafood-alternative/?$ {
    try_files /wp-content/uploads/awd-landings/gloriafood/$1/index.html $uri $uri/ /index.php?$args;
}
```

### Apache example

Before the standard WordPress rules in `.htaccess`:

```apache
RewriteCond %{DOCUMENT_ROOT}/wp-content/uploads/awd-landings/gloriafood/$1/index.html -f
RewriteRule ^(de|en|fr)/gloriafood-alternative/?$ /wp-content/uploads/awd-landings/gloriafood/$1/index.html [L]
```

The plugin deliberately does not edit web-server configuration automatically.

## Adding a developer-controlled section

Add the section and its allowed content fields to `awd_lm_definitions()` in `includes/schema.php`, provide starter translations in `awd_lm_default_content()`, and render the section in the PHP template. The generic admin editor automatically adds the language-aware controls, validation, completeness counting, draft hash and publication state. Marketing users never receive layout or arbitrary HTML controls.

The `awd_landing_published` action fires after each successful language publication and can be used for CDN cache invalidation.
