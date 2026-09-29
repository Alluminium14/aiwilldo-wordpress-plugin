<?php
defined( 'ABSPATH' ) || exit;

function awd_lm_static_path( $key, $lang ) {
	$uploads = wp_upload_dir();
	return trailingslashit( $uploads['basedir'] ) . 'awd-landings/' . sanitize_key( $key ) . '/' . sanitize_key( $lang ) . '/index.html';
}

function awd_publish_landing( $key, $lang ) {
	$content = awd_lm_get_content( $key );
	$html = awd_render_landing( $key, $lang, $content, false );
	if ( is_wp_error( $html ) ) { return $html; }
	if ( strlen( $html ) < 500 || false === stripos( $html, '<!doctype html>' ) ) { return new WP_Error( 'invalid_output', 'Rendered HTML failed validation.' ); }
	$target = awd_lm_static_path( $key, $lang );
	$dir = dirname( $target );
	if ( ! wp_mkdir_p( $dir ) || ! is_writable( $dir ) ) { return new WP_Error( 'not_writable', 'The static output directory is not writable.' ); }
	$temp = $target . '.tmp-' . wp_generate_password( 8, false, false );
	if ( false === file_put_contents( $temp, $html, LOCK_EX ) ) { return new WP_Error( 'write_failed', 'Could not write the temporary static file.' ); }
	$backup = $target . '.previous';
	if ( is_file( $target ) && ! @rename( $target, $backup ) ) {
		@unlink( $temp );
		return new WP_Error( 'backup_failed', 'Could not protect the current published file; publication was cancelled.' );
	}
	if ( ! @rename( $temp, $target ) ) {
		if ( is_file( $backup ) ) { @rename( $backup, $target ); }
		@unlink( $temp );
		return new WP_Error( 'replace_failed', 'Could not replace the published file; the previous file was restored.' );
	}
	if ( is_file( $backup ) ) { @unlink( $backup ); }
	$status_option = 'awd_lm_publish_status_' . sanitize_key( $key );
	$status = get_option( $status_option, array() );
	$status[ $lang ] = array( 'hash' => awd_lm_content_hash( $content, $lang ), 'time' => current_time( 'mysql' ) );
	update_option( $status_option, $status, false );
	$url = awd_lm_public_url( $key, $lang );
	do_action( 'awd_landing_published', $key, $lang, $url );
	return array( 'path' => $target, 'url' => $url );
}

/**
 * Keep static HTML compatible with the PHP/JS assets shipped by the active
 * plugin version. WordPress updates a plugin in place and does not run its
 * activation hook, so without this refresh an old page can load new scripts
 * with a different bootstrap contract.
 */
function awd_lm_refresh_published_assets() {
	$errors = array();
	foreach ( awd_lm_definitions() as $key => $definition ) {
		foreach ( awd_lm_languages() as $lang => $label ) {
			$result = awd_publish_landing( $key, $lang );
			if ( is_wp_error( $result ) ) { $errors[] = $key . '/' . $lang . ': ' . $result->get_error_message(); }
		}
	}
	if ( function_exists( 'awd_lm_publish_all_cookie_policies' ) ) { awd_lm_publish_all_cookie_policies(); }
	if ( $errors ) { return new WP_Error( 'asset_refresh_failed', implode( ' | ', $errors ) ); }
	update_option( 'awd_lm_published_asset_version', AWD_LM_VERSION, false );
	return true;
}

function awd_lm_maybe_refresh_published_assets() {
	if ( ! current_user_can( 'edit_pages' ) ) { return; }
	if ( AWD_LM_VERSION === (string) get_option( 'awd_lm_published_asset_version', '' ) ) { return; }
	if ( get_transient( 'awd_lm_asset_refresh_lock' ) ) { return; }
	set_transient( 'awd_lm_asset_refresh_lock', 1, 5 * MINUTE_IN_SECONDS );
	$result = awd_lm_refresh_published_assets();
	delete_transient( 'awd_lm_asset_refresh_lock' );
	if ( is_wp_error( $result ) ) { set_transient( 'awd_lm_asset_refresh_error', $result->get_error_message(), 10 * MINUTE_IN_SECONDS ); }
}
