<?php
defined( 'ABSPATH' ) || exit;

require_once AWD_LM_DIR . 'templates/template-helpers.php';

function awd_render_landing( $key, $lang, $content = null, $preview = false ) {
	$key = sanitize_key( $key );
	$definition = awd_lm_definition( $key );
	$lang = awd_get_current_language( $lang );
	if ( ! $definition ) { return new WP_Error( 'invalid_landing', 'Unknown landing definition.' ); }
	$template = ! empty( $definition['module'] ) ? AWD_LM_DIR . 'templates/landing-module.php' : AWD_LM_DIR . 'templates/' . $definition['template'];
	if ( ! is_file( $template ) ) { return new WP_Error( 'missing_template', 'Landing template is missing.' ); }
	$content = null === $content ? awd_lm_get_content( $key ) : $content;
	$GLOBALS['awd_lm_render_content'] = $content;
	$canonical = awd_lm_public_url( $key, $lang );
	$asset_version = AWD_LM_VERSION;
	ob_start();
	include $template;
	$html = ob_get_clean();
	unset( $GLOBALS['awd_lm_render_content'] );
	return trim( $html );
}
