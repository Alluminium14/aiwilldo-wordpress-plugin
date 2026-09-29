<?php
/**
 * Regression checks for the one-stylesheet-per-landing architecture.
 *
 * Usage (standalone): php tests/css-architecture-regression.php
 * Optional integration mode: php tests/css-architecture-regression.php C:/path/to/wp-load.php
 */

$wp_load = isset( $argv[1] ) ? $argv[1] : '';
if ( '' !== $wp_load && is_readable( $wp_load ) ) {
	require_once $wp_load;
} else {
	define( 'ABSPATH', __DIR__ );
	define( 'AWD_LM_DIR', dirname( __DIR__ ) . DIRECTORY_SEPARATOR );
	define( 'AWD_LM_URL', 'https://example.test/wp-content/plugins/awd-landing-manager/' );
	define( 'AWD_LM_VERSION', 'test' );
	$GLOBALS['awd_lm_test_options'] = array(
		'awd_lm_css_profiles' => array(
			'active'            => 'website-to-go-ch-style',
			'architecture'      => 1,
			'shared_custom_css' => '.client-shared{color:red}',
			'profiles'          => array(
				'website-to-go-ch-style' => array( 'name' => 'Warm', 'custom_css' => '.client-profile{display:block}' ),
			),
		),
	);
	function get_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['awd_lm_test_options'] ) ? $GLOBALS['awd_lm_test_options'][ $key ] : $default; }
	function update_option( $key, $value ) { $GLOBALS['awd_lm_test_options'][ $key ] = $value; return true; }
	function current_time() { return '2026-08-22 12:00:00'; }
	function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ); }
	function apply_filters( $tag, $value ) { return $value; }
	require_once AWD_LM_DIR . 'includes/helpers.php';
}

function awd_lm_css_test_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

awd_lm_css_profiles( true );
$profiles = get_option( 'awd_lm_css_profiles', array() );
$url      = awd_lm_landing_css_url( 'gloriafood' );
$backup   = get_option( 'awd_lm_css_profiles_backup_before_single_stylesheet_2', array() );

awd_lm_css_test_assert( 2 === (int) ( $profiles['architecture'] ?? 0 ), 'CSS option must be migrated to architecture 2.' );
awd_lm_css_test_assert( array( 'gloriafood' ) === array_keys( (array) ( $profiles['profiles'] ?? array() ) ), 'Only the GloriaFood compatibility entry may remain.' );
awd_lm_css_test_assert( false !== strpos( $url, '/assets/landing-v1.css?' ), 'GloriaFood must use its plugin-managed stylesheet directly.' );
awd_lm_css_test_assert( false === strpos( $url, '/uploads/awd-landings/assets/' ), 'CSS must not be generated in uploads.' );
awd_lm_css_test_assert( ! is_readable( AWD_LM_DIR . 'assets/website-to-go-style.css' ), 'The retired second GloriaFood stylesheet must not return.' );

if ( '' === $wp_load ) {
	awd_lm_css_test_assert( '.client-shared{color:red}' === ( $backup['previous']['shared_custom_css'] ?? '' ), 'Shared client CSS must be archived verbatim.' );
	awd_lm_css_test_assert( '.client-profile{display:block}' === ( $backup['previous']['profiles']['website-to-go-ch-style']['custom_css'] ?? '' ), 'Profile client CSS must be archived verbatim.' );
}

$css = file_get_contents( AWD_LM_DIR . 'assets/landing-v1.css' );
awd_lm_css_test_assert( false !== strpos( $css, 'GloriaFood landing theme' ), 'The former theme bundle must be part of the single stylesheet.' );

echo "PASS: one stylesheet per landing; legacy CSS is archived and not executed.\n";
