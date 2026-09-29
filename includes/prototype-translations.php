<?php
defined( 'ABSPATH' ) || exit;

/** Default V7 copy for fresh installations. Client edits override these values. */
function awd_lm_prototype_seed_translations() {
	static $translations = null;
	if ( null === $translations ) {
		$file = __DIR__ . '/data/prototype-translations.json';
		$json = is_readable( $file ) ? file_get_contents( $file ) : '';
		$translations = is_string( $json ) && '' !== $json ? json_decode( $json, true ) : array();
		if ( ! is_array( $translations ) ) {
			$translations = array();
		}
	}
	return $translations;
}