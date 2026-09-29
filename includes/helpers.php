<?php
defined( 'ABSPATH' ) || exit;

function awd_lm_definition( $key ) {
	$all = awd_lm_definitions();
	return isset( $all[ $key ] ) ? $all[ $key ] : null;
}

function awd_lm_get_content( $key ) {
	$definition = awd_lm_definition( $key );
	return $definition ? get_option( $definition['option'], awd_lm_default_content_for( $key ) ) : array();
}

function awd_lm_array_get( $array, $path, $default = '' ) {
	foreach ( explode( '.', $path ) as $part ) {
		if ( ! is_array( $array ) || ! array_key_exists( $part, $array ) ) { return $default; }
		$array = $array[ $part ];
	}
	return $array;
}

function awd_field( $path, $lang = null, $content = null ) {
	$content = null === $content ? ( isset( $GLOBALS['awd_lm_render_content'] ) ? $GLOBALS['awd_lm_render_content'] : array() ) : $content;
	$value = awd_lm_array_get( $content, $path, '' );
	if ( ! is_array( $value ) ) { return $value; }
	$lang = awd_get_current_language( $lang );
	if ( isset( $value[ $lang ] ) && '' !== trim( (string) $value[ $lang ] ) ) { return $value[ $lang ]; }
	return isset( $value['de'] ) ? $value['de'] : '';
}

function awd_lm_public_url( $key, $lang ) {
	$definition = awd_lm_definition( $key );
	/*
	 * Landing pages are intercepted by our router, so their public URL must not
	 * depend on the site's WordPress permalink structure. Some installations
	 * return ?page_id=123 from get_permalink(); that URL is technical and makes
	 * the language switcher leave the intended /{lang}/{slug}/ route.
	 */
	$lang = awd_get_current_language( $lang );
	/*
	 * Build from the stored home URL, not filtered home_url(). TranslatePress
	 * localizes home_url() during a translated request and would otherwise turn
	 * an already localized route into /de/de/<slug>/.
	 */
	$home = untrailingslashit( (string) get_option( 'home' ) );
	return $home . '/' . user_trailingslashit( $lang . '/' . awd_lm_landing_slug( $key, $lang ) );
}

/**
 * Resolve a WordPress menu item linked to a managed landing in the language of
 * the current request. This keeps one shared site menu compatible with the
 * landing manager's separate /de/, /en/ and /fr/ page identities.
 */
function awd_lm_localize_menu_landing_link( $atts, $menu_item ) {
	$key = sanitize_key( get_post_meta( $menu_item->ID, '_awd_lm_menu_landing_key', true ) );
	if ( ! $key || ! awd_lm_definition( $key ) ) { return $atts; }
	$lang = '';
	$request_path = trim( rawurldecode( (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ) ), '/' );
	if ( preg_match( '~^(de|en|fr)(?:/|$)~', $request_path, $match ) ) { $lang = $match[1]; }
	$atts['href'] = awd_lm_public_url( $key, $lang ?: awd_get_current_language() );
	return $atts;
}

function awd_lm_content_hash( $content, $lang ) {
	$localized = array();
	foreach ( $content as $section => $values ) {
		$localized[ $section ] = array();
		foreach ( (array) $values as $field => $value ) {
			$localized[ $section ][ $field ] = is_array( $value ) ? ( isset( $value[ $lang ] ) ? $value[ $lang ] : '' ) : $value;
		}
	}
	return hash( 'sha256', wp_json_encode( $localized ) );
}

/*
 * A landing owns exactly one plugin-managed stylesheet. WordPress no longer
 * stores or combines CSS layers. Add-on landings can register their own file
 * without changing the GloriaFood bundle through this filter:
 *
 * add_filter( 'awd_lm_landing_stylesheets', function ( $files ) {
 *     $files['second-landing'] = 'landings/second-landing/style.css';
 *     return $files;
 * } );
 */
function awd_lm_landing_stylesheets() {
	$files = array( 'gloriafood' => 'assets/landing-v1.css' );
	if ( function_exists( 'awd_lm_landing_registry' ) ) {
		foreach ( awd_lm_landing_registry() as $key => $module ) {
			$relative = ltrim( str_replace( wp_normalize_path( AWD_LM_DIR ), '', wp_normalize_path( $module['style'] ) ), '/' );
			if ( '' !== $relative ) { $files[ $key ] = $relative; }
		}
	}
	return (array) apply_filters( 'awd_lm_landing_stylesheets', $files );
}

/* Deprecated compatibility shims. Profile bundles no longer exist. */
function awd_lm_website_to_go_style_css() { return ''; }
function awd_lm_plugin_profile_css( $profile_key ) { return ''; }

function awd_lm_default_css_profiles() {
	$created = current_time( 'mysql' );
	return array(
		'active'       => 'gloriafood',
		'architecture' => 2,
		'profiles'     => array(
			'gloriafood' => array( 'name' => 'GloriaFood landing', 'created' => $created ),
		),
	);
}

function awd_lm_css_profiles( $persist_migration = false ) {
	$stored   = get_option( 'awd_lm_css_profiles', null );
	$defaults = awd_lm_default_css_profiles();
	$current  = is_array( $stored ) && 2 === (int) ( $stored['architecture'] ?? 0 );

	if ( $current ) { return $defaults; }
	if ( $persist_migration ) {
		/*
		 * Never discard site CSS silently. Keep the complete pre-migration value,
		 * including inactive profiles and empty fields, for manual recovery/audit.
		 * Archived CSS is deliberately not executed by the public landing.
		 */
		if ( is_array( $stored ) && null === get_option( 'awd_lm_css_profiles_backup_before_single_stylesheet_2', null ) ) {
			update_option( 'awd_lm_css_profiles_backup_before_single_stylesheet_2', array(
				'migrated_at' => current_time( 'mysql' ),
				'previous'    => $stored,
			), false );
		}
		update_option( 'awd_lm_css_profiles', $defaults, false );
	}
	return $defaults;
}

function awd_lm_ensure_css_profile_presets() { awd_lm_css_profiles( true ); }

function awd_lm_landing_css_url( $landing_key = 'gloriafood' ) {
	$landing_key = sanitize_key( $landing_key );
	$files       = awd_lm_landing_stylesheets();
	$relative    = isset( $files[ $landing_key ] ) ? ltrim( (string) $files[ $landing_key ], '/\\' ) : '';
	if ( '' === $relative || ! is_readable( AWD_LM_DIR . $relative ) ) {
		$relative = 'assets/landing-v1.css';
	}
	$modified = filemtime( AWD_LM_DIR . $relative );
	$version  = false === $modified ? AWD_LM_VERSION : (string) $modified;
	return AWD_LM_URL . str_replace( DIRECTORY_SEPARATOR, '/', $relative ) . '?ver=' . rawurlencode( $version );
}

/*
 * V1.1.9 removed 65 legacy text nodes from the hero mockup. Keep all later
 * editor keys stable so existing saved translations still target their source
 * content, rather than their new shorter DOM positions.
 */
function awd_lm_prototype_text_key( $render_index ) {
	$render_index = absint( $render_index );
	return 't' . ( $render_index > 35 ? $render_index + 65 : $render_index );
}

/* Repair a draft that was saved once with the short-lived V1.1.9 key shift. */
function awd_lm_repair_hero_text_keys_after_mock_removal() {
	if ( get_option( 'awd_lm_hero_text_key_repair_1_1_10', false ) ) { return; }
	$content = get_option( 'awd_landing_gloriafood', array() );
	if ( empty( $content['prototype_images']['hero'] ) || empty( $content['prototype_text'] ) || ! is_array( $content['prototype_text'] ) ) {
		update_option( 'awd_lm_hero_text_key_repair_1_1_10', 1, false );
		return;
	}
	foreach ( awd_lm_languages() as $lang => $label ) {
		if ( empty( $content['prototype_text'][ $lang ] ) || ! is_array( $content['prototype_text'][ $lang ] ) ) { continue; }
		$texts = $content['prototype_text'][ $lang ];
		for ( $index = 340; $index >= 36; $index-- ) {
			$from = 't' . $index;
			$to = awd_lm_prototype_text_key( $index );
			if ( array_key_exists( $from, $texts ) ) { $content['prototype_text'][ $lang ][ $to ] = $texts[ $from ]; }
		}
	}
	update_option( 'awd_landing_gloriafood', $content, false );
	update_option( 'awd_lm_hero_text_key_repair_1_1_10', 1, false );
}

/*
 * A V1.1.9 visual-editor save could write shifted hero keys back into the
 * option. Detect its unmistakable signature, keep a private backup, and fall
 * back to the source/seed copy for the affected portion of the landing.
 */
function awd_lm_restore_corrupted_text_layer_after_mock_removal() {
	if ( get_option( 'awd_lm_hero_text_restore_1_1_11', false ) ) { return; }
	$content = get_option( 'awd_landing_gloriafood', array() );
	$texts = $content['prototype_text'] ?? array();
	$corrupted = isset( $texts['de']['t101'] ) && 'Take Order' === trim( (string) $texts['de']['t101'] );
	if ( ! $corrupted ) { update_option( 'awd_lm_hero_text_restore_1_1_11', 1, false ); return; }
	update_option( 'awd_lm_hero_text_backup_before_restore_1_1_11', $content, false );
	foreach ( awd_lm_languages() as $lang => $label ) {
		$preserved = array();
		foreach ( (array) ( $content['prototype_text'][ $lang ] ?? array() ) as $key => $value ) {
			if ( preg_match( '/^t(?:[1-9]|[12][0-9]|3[0-5])$/', $key ) ) { $preserved[ $key ] = $value; }
		}
		$content['prototype_text'][ $lang ] = $preserved;
	}
	update_option( 'awd_landing_gloriafood', $content, false );
	update_option( 'awd_lm_hero_text_restore_1_1_11', 1, false );
}

/**
 * Keep saved editor anchors and visibility settings stable after splitting the
 * former Problem and Take Away mega-sections into their own page sections.
 *
 * The visual editor stores section settings by their DOM order. This migration
 * shifts only those saved keys; it never changes a site's content or custom CSS.
 */
function awd_lm_migrate_section_keys_after_section_split() {
	if ( get_option( 'awd_lm_section_key_split_migration_1_1_89', false ) ) { return; }
	$content = get_option( 'awd_landing_gloriafood', array() );
	$sections = $content['prototype_sections'] ?? array();
	if ( is_array( $sections ) && $sections ) {
		$migrated = array();
		foreach ( $sections as $key => $settings ) {
			if ( preg_match( '/^section(\d+)$/', (string) $key, $match ) ) {
				$number = (int) $match[1];
				// New sections were inserted after old sections 3 and 6.
				if ( $number >= 7 ) { $number += 2; }
				elseif ( $number >= 4 ) { $number += 1; }
				$key = 'section' . $number;
			}
			$migrated[ $key ] = $settings;
		}
		$content['prototype_sections'] = $migrated;
		update_option( 'awd_landing_gloriafood', $content, false );
	}
	update_option( 'awd_lm_section_key_split_migration_1_1_89', 1, false );
}
