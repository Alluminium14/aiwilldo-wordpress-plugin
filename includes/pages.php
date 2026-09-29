<?php
defined( 'ABSPATH' ) || exit;

/**
 * WordPress pages provide stable identities for menus and SEO plugins.
 * The landing template remains the sole renderer; page post_content is never used.
 */
function awd_lm_landing_pages() {
	return get_option( 'awd_lm_landing_pages', array() );
}

function awd_lm_page_id( $key, $lang ) {
	$pages = awd_lm_landing_pages();
	return isset( $pages[ $key ][ $lang ] ) ? absint( $pages[ $key ][ $lang ] ) : 0;
}

function awd_lm_ensure_landing_pages() {
	$stored = awd_lm_landing_pages();
	foreach ( awd_lm_definitions() as $key => $definition ) {
		foreach ( awd_lm_languages() as $lang => $language_name ) {
			$page_id = isset( $stored[ $key ][ $lang ] ) ? absint( $stored[ $key ][ $lang ] ) : 0;
			if ( $page_id && 'page' === get_post_type( $page_id ) ) { continue; }

			$parent = get_page_by_path( $lang, OBJECT, 'page' );
			if ( ! $parent ) {
				$parent_id = wp_insert_post( array(
					'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $language_name,
					'post_name' => $lang, 'post_content' => '',
				), true );
				if ( is_wp_error( $parent_id ) ) { continue; }
				$parent = get_post( $parent_id );
			}

			$slug = awd_lm_landing_slug( $key, $lang );
			$existing = get_page_by_path( $lang . '/' . $slug, OBJECT, 'page' );
			if ( $existing ) {
				$page_id = $existing->ID;
			} else {
				$page_id = wp_insert_post( array(
					'post_type' => 'page', 'post_status' => 'publish', 'post_parent' => $parent->ID,
					'post_title' => $definition['name'] . ' — ' . $language_name,
					'post_name' => $slug, 'post_content' => '',
				), true );
				if ( is_wp_error( $page_id ) ) { continue; }
			}
			update_post_meta( $page_id, '_awd_lm_landing_key', $key );
			update_post_meta( $page_id, '_awd_lm_language', $lang );
			$stored[ $key ][ $lang ] = absint( $page_id );
		}
	}
	update_option( 'awd_lm_landing_pages', $stored, false );
	return $stored;
}

function awd_lm_maybe_ensure_landing_pages() {
	$pages = awd_lm_landing_pages();
	foreach ( awd_lm_definitions() as $key => $definition ) {
		if ( empty( $pages[ $key ] ) ) { awd_lm_ensure_landing_pages(); break; }
	}
}
