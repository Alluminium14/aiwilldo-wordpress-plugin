<?php
defined( 'ABSPATH' ) || exit;

function awd_get_current_language( $requested = null ) {
	$languages = awd_lm_languages();
	$lang = $requested ? sanitize_key( $requested ) : '';
	if ( ! $lang ) { $lang = get_query_var( 'awd_lm_lang' ); }
	if ( ! $lang ) { $lang = apply_filters( 'awd_lm_current_language', '' ); }
	if ( ! $lang && function_exists( 'pll_current_language' ) ) { $lang = pll_current_language( 'slug' ); }
	if ( ! $lang ) { $lang = substr( determine_locale(), 0, 2 ); }
	return isset( $languages[ $lang ] ) ? $lang : 'de';
}

