<?php
/**
 * Regression checks for editable landing copy.
 *
 * Usage: php tests/text-rendering-regression.php "C:\path\to\wp-load.php"
 */

$wp_load = $argv[1] ?? '';
if ( ! $wp_load || ! is_file( $wp_load ) ) {
	fwrite( STDERR, "Pass the absolute path to wp-load.php.\n" );
	exit( 2 );
}

require_once $wp_load;
require_once AWD_LM_DIR . 'admin/admin-page.php';

function awd_lm_text_test_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

$source = file_get_contents( AWD_LM_DIR . 'templates/prototype-v7-body.php' );
$helper = file_get_contents( AWD_LM_DIR . 'templates/template-helpers.php' );
$translation_loader = file_get_contents( AWD_LM_DIR . 'includes/prototype-translations.php' );
$translation_json = json_decode( file_get_contents( AWD_LM_DIR . 'includes/data/prototype-translations.json' ), true );
awd_lm_text_test_assert( false !== strpos( $source, 'data-awd-stable-text-key="t9001"' ), 'Partnership copy lost its permanent structural key.' );
awd_lm_text_test_assert( false === strpos( $helper, '$partnership_copy' ), 'A hard-coded partnership post-render override was reintroduced.' );
awd_lm_text_test_assert( false === strpos( $helper, "wp_get_attachment_image_url( \$image_id, 'large' )" ), 'Published visual slots must not replace the editor original with a resized WordPress image.' );
awd_lm_text_test_assert( false === strpos( $translation_loader, 'base64_' . 'decode' ), 'Translation loader must not contain an encoded payload.' );
awd_lm_text_test_assert( is_array( $translation_json ), 'Translation JSON is invalid.' );
awd_lm_text_test_assert( ! empty( $translation_json['en']['t1'] ) && ! empty( $translation_json['fr']['t1'] ), 'Translation JSON lost EN/FR seed copy.' );
awd_lm_text_test_assert( ! empty( $translation_json['de']['t9001'] ) && ! empty( $translation_json['en']['t9001'] ) && ! empty( $translation_json['fr']['t9001'] ), 'Translation JSON lost permanent-key copy.' );

foreach ( array( 'de', 'en', 'fr' ) as $lang ) {
	$sentinel = 'Saved partnership text ' . strtoupper( $lang ) . ' 84f32b';
	$raw = array( 'prototype_text' => array( $lang => array( 't9001' => $sentinel ) ) );
	$content = awd_lm_sanitize_content( 'gloriafood', $raw );
	awd_lm_text_test_assert( $sentinel === ( $content['prototype_text'][ $lang ]['t9001'] ?? '' ), "Sanitizer lost t9001 for {$lang}." );

	$public_html = awd_render_landing( 'gloriafood', $lang, $content );
	awd_lm_text_test_assert( false !== strpos( $public_html, $sentinel ), "Public renderer lost saved t9001 for {$lang}." );
	awd_lm_text_test_assert( 1 === substr_count( $public_html, $sentinel ), "Public renderer duplicated t9001 for {$lang}." );

	$GLOBALS['awd_lm_visual_editor'] = true;
	$editor_html = awd_render_landing( 'gloriafood', $lang, $content );
	unset( $GLOBALS['awd_lm_visual_editor'] );
	awd_lm_text_test_assert( false !== strpos( $editor_html, 'data-awd-text-key="t9001"' ), "Editor lost t9001 for {$lang}." );
	awd_lm_text_test_assert( false !== strpos( $editor_html, $sentinel ), "Editor lost saved t9001 for {$lang}." );
	awd_lm_text_test_assert( false !== strpos( $editor_html, 'data-awd-text-key="t142"' ), "A text key after the partnership block shifted for {$lang}." );
	awd_lm_text_test_assert( false !== strpos( $editor_html, 'data-awd-text-key="t9002"' ), "Luxembourg phone label is not editable for {$lang}." );
	awd_lm_text_test_assert( false !== strpos( $editor_html, 'data-awd-text-key="t9005"' ), "Swiss phone number is not editable for {$lang}." );
	awd_lm_text_test_assert( false !== strpos( $public_html, 'href="tel:+352661554736"' ), "Luxembourg telephone link is missing for {$lang}." );
	awd_lm_text_test_assert( false !== strpos( $public_html, 'href="tel:+41416301487"' ), "Swiss telephone link is missing for {$lang}." );
}

echo "Text rendering regression checks passed.\n";
