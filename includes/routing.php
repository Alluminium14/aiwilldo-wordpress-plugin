<?php
defined( 'ABSPATH' ) || exit;

function awd_lm_add_rewrite_rules() {
	foreach ( awd_lm_definitions() as $key => $definition ) {
		foreach ( awd_lm_languages() as $lang => $label ) {
			add_rewrite_rule( '^' . preg_quote( $lang, '/' ) . '/' . preg_quote( awd_lm_landing_slug( $key, $lang ), '/' ) . '/?$', 'index.php?awd_lm_landing=' . $key . '&awd_lm_lang=' . $lang, 'top' );
		}
	}
	add_rewrite_tag( '%awd_lm_landing%', '([a-z0-9_-]+)' );
	add_rewrite_tag( '%awd_lm_lang%', '([a-z]{2})' );
}

/*
 * Some multilingual plugins claim /en/ and /fr/ paths before WordPress applies
 * custom rewrite rules. Serve our exact landing URLs at parse time so the
 * landing never falls through to that plugin's Blog/archive page.
 */
function awd_lm_intercept_landing_path( $wp ) {
	if ( is_admin() ) { return; }
	$request_uri = wp_unslash( $_SERVER['REQUEST_URI'] ?? '' );
	$request_path = trim( rawurldecode( (string) wp_parse_url( $request_uri, PHP_URL_PATH ) ), '/' );
	if ( empty( $wp->request ) && '' === $request_path ) {
		awd_lm_redirect_tracked_root_to_landing();
		return;
	}
	/*
	 * Multilingual plugins may remove the language prefix from $wp->request.
	 * The browser path remains authoritative for our exact /<lang>/<slug>/
	 * routes and lets us intercept them before a translated 404 takes over.
	 */
	$request = '' !== $request_path ? $request_path : trim( rawurldecode( $wp->request ), '/' );
	awd_lm_redirect_for_legacy_path( $request );
	foreach ( awd_lm_definitions() as $key => $definition ) {
		foreach ( awd_lm_languages() as $lang => $label ) {
			if ( $request !== $lang . '/' . awd_lm_landing_slug( $key, $lang ) ) { continue; }
			awd_lm_serve_front_response( $key, $lang, false );
		}
	}
}

/**
 * Email clients may still contain an older CTA whose destination was the site
 * root. The root is not a managed landing on every installation, so preserve
 * its attribution query and move only tracked campaign visits to the default
 * landing. Ordinary homepage requests remain untouched.
 */
function awd_lm_redirect_tracked_root_to_landing() {
	$method = strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) );
	if ( ! in_array( $method, array( 'GET', 'HEAD' ), true ) ) { return; }
	// Some multilingual routers leave $wp->request empty even for a real path.
	// Verify the browser's actual URL so this fallback can only redirect `/`.
	$request_uri = wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' );
	$request_path = trim( rawurldecode( (string) wp_parse_url( $request_uri, PHP_URL_PATH ) ), '/' );
	if ( '' !== $request_path ) { return; }
	$has_click = ! empty( $_GET['awd_click'] );
	$has_utm = ! empty( $_GET['utm_source'] ) || ! empty( $_GET['utm_campaign'] ) || ! empty( $_GET['utm_id'] );
	if ( ! $has_click && ! $has_utm ) { return; }
	$query = (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_QUERY );
	$destination = awd_lm_public_url( 'gloriafood', 'de' );
	if ( '' !== $query ) { $destination .= '?' . $query; }
	wp_safe_redirect( $destination, 302, 'AWD Landing Manager' );
	exit;
}

function awd_lm_landing_from_request_uri() {
	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	$path = trim( rawurldecode( (string) wp_parse_url( $request_uri, PHP_URL_PATH ) ), '/' );
	if ( '' === $path ) { return false; }
	awd_lm_redirect_for_legacy_path( $path );
	foreach ( awd_lm_definitions() as $key => $definition ) {
		foreach ( awd_lm_languages() as $lang => $label ) {
			if ( $path === $lang . '/' . awd_lm_landing_slug( $key, $lang ) ) { return array( 'key' => $key, 'lang' => $lang ); }
		}
	}
	return false;
}

function awd_lm_serve_front_response( $key, $lang, $preview = false ) {
	if ( ! $preview ) {
		$path = awd_lm_static_path( $key, $lang );
		if ( is_file( $path ) && is_readable( $path ) ) {
			header( 'Content-Type: text/html; charset=UTF-8' );
			header( 'X-AWD-Landing: static-fallback' );
			readfile( $path );
			exit;
		}
	}
	$html = awd_render_landing( $key, $lang, null, $preview );
	if ( is_wp_error( $html ) ) { status_header( 500 ); exit( esc_html( $html->get_error_message() ) ); }
	header( 'Content-Type: text/html; charset=UTF-8' );
	if ( $preview ) { header( 'X-Robots-Tag: noindex, nofollow', true ); }
	echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit;
}

function awd_lm_handle_front_request() {
	$key = sanitize_key( get_query_var( 'awd_lm_landing' ) );
	$lang = awd_get_current_language( get_query_var( 'awd_lm_lang' ) );
	$preview = isset( $_GET['awd-preview'] ) && '1' === $_GET['awd-preview'];
	/* Final fallback for hosts/themes that discard custom rewrite query vars. */
	if ( ! $key && ! $preview ) {
		$requested_landing = awd_lm_landing_from_request_uri();
		if ( $requested_landing ) {
			$key = $requested_landing['key'];
			$lang = $requested_landing['lang'];
		}
	}
	/*
	 * A WordPress page can also be opened through its technical ?page_id= URL
	 * (for example from an editor or a multilingual plugin). That URL bypasses
	 * rewrite rules, so recognise our managed page by its metadata as well.
	 */
	if ( ! $key && ! $preview && is_page() ) {
		$page_id = get_queried_object_id();
		$page_key = sanitize_key( get_post_meta( $page_id, '_awd_lm_landing_key', true ) );
		if ( $page_key ) {
			$key = $page_key;
			$lang = awd_get_current_language( get_post_meta( $page_id, '_awd_lm_language', true ) );
		}
	}
	if ( $preview ) {
		if ( ! is_user_logged_in() || ! current_user_can( 'edit_pages' ) ) { status_header( 403 ); exit( 'Preview forbidden.' ); }
		$key = isset( $_GET['landing'] ) ? sanitize_key( wp_unslash( $_GET['landing'] ) ) : 'gloriafood';
		$lang = isset( $_GET['lang'] ) ? awd_get_current_language( wp_unslash( $_GET['lang'] ) ) : 'de';
	}
	if ( ! $key ) { return; }
	awd_lm_serve_front_response( $key, $lang, $preview );
}
