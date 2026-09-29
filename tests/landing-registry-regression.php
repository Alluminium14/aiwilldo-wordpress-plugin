<?php
/** Standalone regression test for module discovery. */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . DIRECTORY_SEPARATOR );
}
if ( ! defined( 'AWD_LM_DIR' ) ) {
	define( 'AWD_LM_DIR', dirname( __DIR__ ) . DIRECTORY_SEPARATOR );
}

function trailingslashit( $value ) { return rtrim( $value, '/\\' ) . '/'; }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_title( $value ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $value ) ), '-' ); }
function sanitize_html_class( $value ) { return preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $value ); }
function wp_normalize_path( $value ) { return str_replace( '\\', '/', (string) $value ); }

require_once AWD_LM_DIR . 'includes/landing-registry.php';

function awd_lm_registry_test_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

$registry = awd_lm_landing_registry( true );
awd_lm_registry_test_assert( isset( $registry['gloriafood'], $registry['second-landing'] ), 'Expected modules were not discovered.' );
awd_lm_registry_test_assert( true === $registry['gloriafood']['legacy_mode'], 'GloriaFood compatibility mode was lost.' );
awd_lm_registry_test_assert( true === $registry['second-landing']['legacy_mode'], 'Restaurants Ordering System must reuse the proven visual-editor renderer.' );
awd_lm_registry_test_assert( 'gloriafood' === $registry['second-landing']['clone_of'], 'Clone source was lost.' );
awd_lm_registry_test_assert( 'Restaurants Ordering System' === $registry['second-landing']['name'], 'Landing name changed unexpectedly.' );
awd_lm_registry_test_assert( 'restaurants-ordering-system' === $registry['second-landing']['slug'], 'Landing slug changed unexpectedly.' );
awd_lm_registry_test_assert( $registry['gloriafood']['style'] !== $registry['second-landing']['style'], 'Landing styles are not isolated.' );
awd_lm_registry_test_assert( 'awd_landing_gloriafood' === $registry['gloriafood']['option'], 'Existing GloriaFood option compatibility was lost.' );
awd_lm_registry_test_assert( array( 'de', 'en', 'fr' ) === $registry['second-landing']['languages'], 'Module language contract changed.' );

$translations = awd_lm_landing_module_data( 'second-landing', 'translations' );
$schema = awd_lm_landing_module_data( 'second-landing', 'schema' );
awd_lm_registry_test_assert( ! empty( $translations['de']['hero']['title'] ) && ! empty( $translations['en']['hero']['title'] ) && ! empty( $translations['fr']['hero']['title'] ), 'Second landing translations are incomplete.' );
awd_lm_registry_test_assert( ! empty( $schema['sections']['hero']['fields'] ), 'Second landing schema is incomplete.' );
awd_lm_registry_test_assert( array() === awd_lm_landing_module_data( 'missing', 'schema' ), 'Unknown modules must fail closed.' );

echo "Landing registry regression checks passed.\n";
