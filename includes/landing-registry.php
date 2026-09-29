<?php
/**
 * Landing module discovery and metadata access.
 *
 * This file is deliberately independent from the renderer. Loading it does not
 * change any existing route or landing output; callers opt in by asking for a
 * module definition.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Return the absolute directory containing landing modules.
 *
 * @return string
 */
function awd_lm_landing_modules_dir() {
	return trailingslashit( AWD_LM_DIR ) . 'landings';
}

/**
 * Validate and normalise one module manifest.
 *
 * @param mixed  $manifest   Manifest returned by manifest.php.
 * @param string $module_dir Absolute module directory.
 * @return array|null
 */
function awd_lm_normalize_landing_manifest( $manifest, $module_dir ) {
	if ( ! is_array( $manifest ) || empty( $manifest['id'] ) || empty( $manifest['name'] ) ) {
		return null;
	}

	$id = sanitize_key( $manifest['id'] );
	if ( '' === $id || basename( $module_dir ) !== $id ) {
		return null;
	}

	$required_files = array( 'template', 'style', 'script', 'translations', 'schema' );
	foreach ( $required_files as $key ) {
		if ( empty( $manifest[ $key ] ) ) {
			return null;
		}

		$path = wp_normalize_path( $manifest[ $key ] );
		if ( ! is_file( $path ) ) {
			return null;
		}
		$manifest[ $key ] = $path;
	}

	$languages = isset( $manifest['languages'] ) && is_array( $manifest['languages'] )
		? array_values( array_unique( array_map( 'sanitize_key', $manifest['languages'] ) ) )
		: array( 'de', 'en', 'fr' );
	$languages = array_values( array_intersect( array( 'de', 'en', 'fr' ), $languages ) );
	if ( empty( $languages ) ) {
		return null;
	}

	$manifest['id']          = $id;
	$manifest['name']        = sanitize_text_field( $manifest['name'] );
	$manifest['version']     = sanitize_text_field( $manifest['version'] ?? '1.0.0' );
	$manifest['option']      = sanitize_key( $manifest['option'] ?? 'awd_landing_' . $id );
	$manifest['slug']        = sanitize_title( $manifest['slug'] ?? $id );
	$manifest['languages']   = $languages;
	$manifest['root_class']  = sanitize_html_class( $manifest['root_class'] ?? 'awd-landing--' . $id );
	$manifest['module_dir']  = wp_normalize_path( $module_dir );
	$manifest['legacy_mode'] = ! empty( $manifest['legacy_mode'] );
	$manifest['clone_of']     = sanitize_key( $manifest['clone_of'] ?? '' );

	return $manifest;
}

/**
 * Discover all valid landing modules.
 *
 * @param bool $refresh Ignore the request-local cache.
 * @return array<string,array>
 */
function awd_lm_landing_registry( $refresh = false ) {
	static $registry = null;
	if ( null !== $registry && ! $refresh ) {
		return $registry;
	}

	$registry = array();
	$manifests = glob( awd_lm_landing_modules_dir() . '/*/manifest.php' );
	if ( false === $manifests ) {
		$manifests = array();
	}
	sort( $manifests, SORT_STRING );

	foreach ( $manifests as $manifest_file ) {
		$manifest = include $manifest_file;
		$manifest = awd_lm_normalize_landing_manifest( $manifest, dirname( $manifest_file ) );
		if ( null !== $manifest ) {
			$registry[ $manifest['id'] ] = $manifest;
		}
	}

	if ( function_exists( 'apply_filters' ) ) {
		$registry = apply_filters( 'awd_lm_landing_registry', $registry );
	}

	return is_array( $registry ) ? $registry : array();
}

/**
 * Fetch a registered module without changing legacy renderer behaviour.
 *
 * @param string $landing_id Landing identifier.
 * @return array|null
 */
function awd_lm_get_landing_module( $landing_id ) {
	$registry = awd_lm_landing_registry();
	$key = sanitize_key( $landing_id );
	return $registry[ $key ] ?? null;
}

/**
 * Load structured module data from a PHP or JSON module resource.
 *
 * @param string $landing_id Landing identifier.
 * @param string $resource   Either schema or translations.
 * @return array
 */
function awd_lm_landing_module_data( $landing_id, $resource ) {
	if ( ! in_array( $resource, array( 'schema', 'translations' ), true ) ) {
		return array();
	}
	$module = awd_lm_get_landing_module( $landing_id );
	if ( ! $module ) {
		return array();
	}

	if ( 'translations' === $resource ) {
		$data = json_decode( (string) file_get_contents( $module[ $resource ] ), true );
	} else {
		$data = include $module[ $resource ];
	}

	return is_array( $data ) ? $data : array();
}
