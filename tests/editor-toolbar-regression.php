<?php
/** Regression checks for the scalable N-landing editor toolbar. */

$root     = dirname( __DIR__ );
$template = file_get_contents( $root . '/templates/landing-gloriafood.php' );
$module_template = file_get_contents( $root . '/templates/landing-module.php' );
$second_template = file_get_contents( $root . '/landings/second-landing/template.php' );
$css      = file_get_contents( $root . '/admin/visual-editor.css' );
$js       = file_get_contents( $root . '/admin/visual-editor.js' );
$errors   = array();

if ( false === strpos( $template, 'data-awd-landing-switcher' ) ) {
	$errors[] = 'The editor must expose the landing registry through one compact switcher.';
}
if ( false === strpos( $template, 'awd-lm-toolbar-brand' ) || false === strpos( $template, 'awd-lm-brand-name' ) ) {
	$errors[] = 'Landing and brand controls must share the normal toolbar flow.';
}
if ( preg_match( '/\.awd-lm-brand-name\s*\{[^}]*position\s*:\s*fixed/i', $css ) ) {
	$errors[] = 'The brand control must never use fixed coordinates.';
}
if ( false === strpos( $css, '.awd-lm-toolbar .awd-admin-nav-link' ) ) {
	$errors[] = 'Direct workspace navigation links must be styled inside the editor toolbar.';
}
if ( false === strpos( $js, "querySelector('[data-awd-landing-switcher]')" ) ) {
	$errors[] = 'The landing switcher must navigate when its value changes.';
}
if ( false === strpos( $js, "slot.querySelector('.awd-lm-slot-img')" ) || false === strpos( $js, 'image.src=item.url' ) ) {
	$errors[] = 'The media picker must refresh rendered image elements, not only background-image slots.';
}
if ( false === strpos( $module_template, 'class="awd-lm-toolbar"' ) || false === strpos( $module_template, 'data-awd-module-fields-toggle' ) ) {
	$errors[] = 'Every module landing must use the shared visual editor chrome and modal page fields.';
}
if ( false === strpos( $second_template, 'data-awd-content-path="hero.title"' ) || false === strpos( $second_template, 'contenteditable="true"' ) ) {
	/* The original scaffold remains a valid example for future module-owned layouts. */
}
if ( false === strpos( file_get_contents( $root . '/landings/second-landing/manifest.php' ), "'clone_of'     => 'gloriafood'" ) ) {
	$errors[] = 'The Restaurants Ordering System landing must remain an independent clone of the established visual editor.';
}

if ( $errors ) {
	fwrite( STDERR, implode( PHP_EOL, $errors ) . PHP_EOL );
	exit( 1 );
}

echo "Editor toolbar regression checks passed.\n";
