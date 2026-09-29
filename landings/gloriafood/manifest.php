<?php
/**
 * Compatibility manifest for the established GloriaFood landing.
 *
 * The module registry can expose it today while the current renderer keeps
 * using its proven V7 paths. A later integration may switch these paths to
 * module-local implementations after its saved tN content has been migrated.
 */

$plugin_dir = dirname( __DIR__, 2 );

return array(
	'id'           => 'gloriafood',
	'name'         => 'GloriaFood Alternative',
	'version'      => '1.0.0',
	'option'       => 'awd_landing_gloriafood',
	'slug'         => 'gloriafood-alternative',
	'languages'    => array( 'de', 'en', 'fr' ),
	'root_class'   => 'awd-landing--gloriafood',
	'legacy_mode'  => true,
	'template'     => __DIR__ . '/template.php',
	'style'        => $plugin_dir . '/assets/landing-v1.css',
	'script'       => $plugin_dir . '/assets/landing-v1.js',
	'translations' => $plugin_dir . '/includes/data/prototype-translations.json',
	'schema'       => __DIR__ . '/schema.php',
);

