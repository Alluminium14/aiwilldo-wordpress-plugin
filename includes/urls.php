<?php
defined( 'ABSPATH' ) || exit;

function awd_lm_url_settings( $key = 'gloriafood' ) {
	$key = sanitize_key( $key );
	$definition = awd_lm_definition( $key );
	$default_slug = sanitize_title( $definition['slug'] ?? $key );
	$option = 'gloriafood' === $key ? 'awd_lm_url_settings' : 'awd_lm_url_settings_' . $key;
	$stored = get_option( $option, array() );
	$slugs = is_array( $stored['slugs'] ?? null ) ? $stored['slugs'] : array();
	$history = is_array( $stored['history'] ?? null ) ? $stored['history'] : array();
	foreach ( awd_lm_languages() as $lang => $label ) {
		$slugs[ $lang ] = sanitize_title( $slugs[ $lang ] ?? $default_slug );
		if ( '' === $slugs[ $lang ] ) { $slugs[ $lang ] = $default_slug; }
		if ( ! isset( $history[ $lang ] ) || ! is_array( $history[ $lang ] ) ) { $history[ $lang ] = array(); }
		// Old installations may contain the active slug in redirect history.
		// Remove it at read time so a current landing URL can never redirect to itself.
		$history[ $lang ] = array_values( array_filter( $history[ $lang ], static function( $entry ) use ( $slugs, $lang ) {
			$historical_slug = sanitize_title( is_array( $entry ) ? ( $entry['slug'] ?? '' ) : $entry );
			return '' !== $historical_slug && $historical_slug !== $slugs[ $lang ];
		} ) );
	}
	return array( 'slugs' => $slugs, 'history' => $history );
}

function awd_lm_landing_slug( $key, $lang ) {
	$lang = awd_get_current_language( $lang );
	if ( isset( $GLOBALS['awd_lm_staged_slugs'][ $key ][ $lang ] ) ) { return $GLOBALS['awd_lm_staged_slugs'][ $key ][ $lang ]; }
	$settings = awd_lm_url_settings( $key );
	return $settings['slugs'][ $lang ];
}

function awd_lm_reserved_slugs() {
	return array( 'wp-admin', 'wp-content', 'wp-includes', 'admin', 'login', 'feed', 'preview', 'analytics', 'cookie-settings', 'api', 'rest', 'xmlrpc' );
}

function awd_lm_validate_landing_slug( $slug, $lang, $page_id = 0 ) {
	$raw = trim( (string) $slug );
	$slug = sanitize_title( $raw );
	if ( '' === $slug || $slug !== strtolower( $raw ) || ! preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug ) ) { return new WP_Error( 'invalid_slug', strtoupper( $lang ) . ': use lowercase letters, numbers and hyphens only.' ); }
	if ( in_array( $slug, awd_lm_reserved_slugs(), true ) ) { return new WP_Error( 'reserved_slug', strtoupper( $lang ) . ': this address is reserved by the system.' ); }
	if ( function_exists( 'awd_lm_cookie_settings' ) ) {
		$cookie_settings = awd_lm_cookie_settings();
		if ( $slug === sanitize_title( $cookie_settings['policy'][ $lang ]['slug'] ?? '' ) ) { return new WP_Error( 'cookie_slug_conflict', strtoupper( $lang ) . ': this address is used by the Cookie Policy.' ); }
	}
	$existing = get_page_by_path( $lang . '/' . $slug, OBJECT, 'page' );
	if ( $existing && absint( $existing->ID ) !== absint( $page_id ) ) { return new WP_Error( 'slug_conflict', strtoupper( $lang ) . ': another WordPress page already uses this address.' ); }
	return $slug;
}

function awd_lm_redirect_for_legacy_path( $request ) {
	foreach ( awd_lm_definitions() as $key => $definition ) {
		$settings = awd_lm_url_settings( $key );
		foreach ( awd_lm_languages() as $lang => $label ) {
			foreach ( $settings['history'][ $lang ] as $entry ) {
				$old = sanitize_title( is_array( $entry ) ? ( $entry['slug'] ?? '' ) : $entry );
				if ( $old === $settings['slugs'][ $lang ] || '' === $old || $request !== $lang . '/' . $old ) { continue; }
				$destination = awd_lm_public_url( $key, $lang );
				$query = (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_QUERY );
				if ( '' !== $query ) { $destination .= '?' . $query; }
				wp_safe_redirect( $destination, 301, 'AWD Landing Manager' ); exit;
			}
		}
	}
}

function awd_lm_urls_settings_page_v2() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', 403 ); }
	$definitions = awd_lm_definitions();
	$key = sanitize_key( wp_unslash( $_GET['landing'] ?? 'gloriafood' ) );
	if ( ! isset( $definitions[ $key ] ) ) { $key = array_key_first( $definitions ); }
	$settings = awd_lm_url_settings( $key );
	$notice = sanitize_key( wp_unslash( $_GET['awd_urls_notice'] ?? '' ) );
	$error = sanitize_text_field( wp_unslash( $_GET['awd_urls_error'] ?? '' ) );
	?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Settings</title><style>
	body{margin:30px;font:14px/1.5 system-ui;background:#f5f8f6;color:#182019}main{max-width:920px;margin:auto}.tabs{display:flex;gap:8px;flex-wrap:wrap}.tabs a{padding:9px 13px;border:1px solid #bcdac7;border-radius:9px;color:#176147;text-decoration:none;font-weight:750;background:#fff}.tabs a.on{background:#008b55;color:#fff}.card{background:#fff;border:1px solid #d6e8dc;padding:22px;border-radius:14px;margin:18px 0}.row{display:grid;grid-template-columns:110px 1fr;gap:14px;align-items:center;margin:13px 0}input{box-sizing:border-box;width:100%;padding:11px;border:1px solid #bcdac7;border-radius:8px;font:inherit}button{padding:11px 15px;border:0;border-radius:8px;background:#008b55;color:#fff;font-weight:800;cursor:pointer}.notice{padding:12px 14px;border-radius:8px;background:#e8f7ed;color:#195d3b;font-weight:700}.error{background:#fde8e5;color:#84251d}code{color:#315d49}@media(max-width:600px){body{margin:14px}.row{grid-template-columns:1fr;gap:3px}}
	</style></head><body><main><h1>Settings</h1><p>Every landing has one independent address and one plugin-managed stylesheet. Shared integrations stay global.</p><nav class="tabs"><?php foreach ( $definitions as $landing_key => $definition ) : ?><a class="<?php echo $landing_key === $key ? 'on' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'action'=>'awd_lm_urls_settings', 'landing'=>$landing_key ), admin_url( 'admin-post.php' ) ) ); ?>"><?php echo esc_html( $definition['name'] ?? $landing_key ); ?></a><?php endforeach; ?></nav>
	<?php if ( 'saved' === $notice ) : ?><p class="notice">URLs saved and all languages published.</p><?php elseif ( $error ) : ?><p class="notice error"><?php echo esc_html( $error ); ?></p><?php endif; ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="awd_lm_save_urls"><input type="hidden" name="landing" value="<?php echo esc_attr( $key ); ?>"><?php wp_nonce_field( 'awd_lm_save_urls_' . $key ); ?><section class="card"><h2><?php echo esc_html( $definitions[ $key ]['name'] ?? $key ); ?> URLs</h2><?php foreach ( awd_lm_languages() as $lang => $label ) : ?><label class="row"><strong><?php echo esc_html( $label ); ?></strong><span><input name="slugs[<?php echo esc_attr( $lang ); ?>]" value="<?php echo esc_attr( $settings['slugs'][ $lang ] ); ?>" required pattern="[a-z0-9]+(?:-[a-z0-9]+)*"><small><?php echo esc_html( home_url( '/' . $lang . '/' ) ); ?><code><?php echo esc_html( $settings['slugs'][ $lang ] ); ?></code>/</small></span></label><?php endforeach; ?></section><button type="submit">Save URLs &amp; publish this landing</button></form>
	<section class="card"><h2>Redirect history</h2><?php foreach ( awd_lm_languages() as $lang => $label ) : $old_slugs = array_filter( array_map( static function( $entry ) { return is_array( $entry ) ? ( $entry['slug'] ?? '' ) : $entry; }, $settings['history'][ $lang ] ) ); ?><p><strong><?php echo esc_html( $label ); ?>:</strong> <?php echo $old_slugs ? esc_html( implode( ', ', $old_slugs ) ) : 'No previous addresses.'; ?></p><?php endforeach; ?></section>
	<?php awd_lm_leads_tool_connection_panel(); awd_lm_content_backup_panel(); ?></main></body></html><?php
	exit;
}

function awd_lm_save_urls_v2() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', 403 ); }
	$key = sanitize_key( wp_unslash( $_POST['landing'] ?? '' ) );
	$definition = awd_lm_definition( $key );
	if ( ! $definition ) { wp_die( 'Unknown landing.', 400 ); }
	check_admin_referer( 'awd_lm_save_urls_' . $key );
	$old = awd_lm_url_settings( $key );
	$pages = awd_lm_ensure_landing_pages();
	$new = array();
	foreach ( awd_lm_languages() as $lang => $label ) {
		$result = awd_lm_validate_landing_slug( wp_unslash( $_POST['slugs'][ $lang ] ?? '' ), $lang, $pages[ $key ][ $lang ] ?? 0 );
		if ( is_wp_error( $result ) ) { awd_lm_urls_error_redirect( $result->get_error_message(), $key ); }
		$new[ $lang ] = $result;
	}
	$GLOBALS['awd_lm_staged_slugs'][ $key ] = $new;
	foreach ( awd_lm_languages() as $lang => $label ) {
		$result = awd_publish_landing( $key, $lang );
		if ( is_wp_error( $result ) ) { unset( $GLOBALS['awd_lm_staged_slugs'][ $key ] ); awd_lm_urls_error_redirect( 'Publication failed; existing URLs were kept. ' . $result->get_error_message(), $key ); }
	}
	$renamed = array();
	foreach ( awd_lm_languages() as $lang => $label ) {
		$page_id = absint( $pages[ $key ][ $lang ] ?? 0 );
		if ( ! $page_id || $new[ $lang ] === $old['slugs'][ $lang ] ) { continue; }
		$result = wp_update_post( array( 'ID'=>$page_id, 'post_name'=>$new[ $lang ] ), true );
		$updated_page = is_wp_error( $result ) ? null : get_post( $page_id );
		if ( is_wp_error( $result ) || ! $updated_page || $updated_page->post_name !== $new[ $lang ] ) {
			foreach ( $renamed as $restore_lang => $restore_id ) { wp_update_post( array( 'ID'=>$restore_id, 'post_name'=>$old['slugs'][ $restore_lang ] ) ); }
			unset( $GLOBALS['awd_lm_staged_slugs'][ $key ] );
			awd_lm_urls_error_redirect( 'WordPress page update failed; existing URLs were kept.', $key );
		}
		$renamed[ $lang ] = $page_id;
	}
	$updated = $old;
	$updated['slugs'] = $new;
	foreach ( awd_lm_languages() as $lang => $label ) {
		if ( $new[ $lang ] === $old['slugs'][ $lang ] ) { continue; }
		array_unshift( $updated['history'][ $lang ], array( 'slug'=>$old['slugs'][ $lang ], 'changed_at'=>current_time( 'mysql' ) ) );
		$updated['history'][ $lang ] = array_values( array_slice( array_filter( $updated['history'][ $lang ], static function( $entry ) use ( $new, $lang ) { return sanitize_title( is_array( $entry ) ? ( $entry['slug'] ?? '' ) : $entry ) !== $new[ $lang ]; } ), 0, 20 ) );
	}
	$option = 'gloriafood' === $key ? 'awd_lm_url_settings' : 'awd_lm_url_settings_' . $key;
	update_option( $option, $updated, false );
	unset( $GLOBALS['awd_lm_staged_slugs'][ $key ] );
	flush_rewrite_rules( false );
	wp_safe_redirect( add_query_arg( array( 'action'=>'awd_lm_urls_settings', 'landing'=>$key, 'awd_urls_notice'=>'saved' ), admin_url( 'admin-post.php' ) ) );
	exit;
}

function awd_lm_urls_settings_page() {
	awd_lm_urls_settings_page_v2();
	return;
	/* Legacy screen kept below for rollback reference. */
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', 403 ); }
	$key = sanitize_key( wp_unslash( $_GET['landing'] ?? 'gloriafood' ) );
	if ( ! awd_lm_definition( $key ) ) { $key = 'gloriafood'; }
	$settings = awd_lm_url_settings( $key ); $notice = sanitize_key( wp_unslash( $_GET['awd_urls_notice'] ?? '' ) ); $error = sanitize_text_field( wp_unslash( $_GET['awd_urls_error'] ?? '' ) );
	?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Settings</title><style>body{margin:30px;font:14px/1.5 system-ui;background:#f5f8f6;color:#182019}main{max-width:920px;margin:auto}.card{background:#fff;border:1px solid #d6e8dc;padding:22px;border-radius:14px;margin:18px 0}.row{display:grid;grid-template-columns:110px 1fr;gap:14px;align-items:center;margin:13px 0}.lead-tool-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.lead-tool-grid label{display:block;font-weight:750;margin:10px 0 6px}input{box-sizing:border-box;width:100%;padding:11px;border:1px solid #bcdac7;border-radius:8px;font:inherit}button{padding:11px 15px;border:0;border-radius:8px;background:#008b55;color:#fff;font-weight:800;cursor:pointer}.notice{padding:12px 14px;border-radius:8px;background:#e8f7ed;color:#195d3b;font-weight:700}.error{background:#fde8e5;color:#84251d}.connection-tag{display:inline-block;margin-left:8px;padding:7px 10px;border-radius:999px;background:#e8f7ed;color:#087a4c;font-weight:750}code{color:#315d49}@media(max-width:600px){body{margin:14px}.row,.lead-tool-grid{grid-template-columns:1fr;gap:3px}.connection-tag{display:block;margin:10px 0 0}}</style></head><body><main><h1>Settings</h1><p>Website addresses and external service connections.</p><?php if ( 'saved' === $notice ) : ?><p class="notice">URLs saved and all languages published.</p><?php elseif ( $error ) : ?><p class="notice error"><?php echo esc_html( $error ); ?></p><?php endif; ?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="awd_lm_save_urls"><?php wp_nonce_field( 'awd_lm_save_urls' ); ?><section class="card"><h2>Landing slugs</h2><?php foreach ( awd_lm_languages() as $lang => $label ) : ?><label class="row"><strong><?php echo esc_html( $label ); ?></strong><span><input name="slugs[<?php echo esc_attr( $lang ); ?>]" value="<?php echo esc_attr( $settings['slugs'][ $lang ] ); ?>" required pattern="[a-z0-9]+(?:-[a-z0-9]+)*"><small><?php echo esc_html( home_url( '/' . $lang . '/' ) ); ?><code><?php echo esc_html( $settings['slugs'][ $lang ] ); ?></code>/</small></span></label><?php endforeach; ?></section><button type="submit">Save URLs &amp; publish all languages</button></form><section class="card"><h2>Redirect history</h2><?php foreach ( awd_lm_languages() as $lang => $label ) : ?><p><strong><?php echo esc_html( $label ); ?>:</strong> <?php $old_slugs = array_filter( array_map( static function( $entry ) { return is_array( $entry ) ? ( $entry['slug'] ?? '' ) : $entry; }, $settings['history'][ $lang ] ) ); echo $old_slugs ? esc_html( implode( ', ', $old_slugs ) ) : 'No previous addresses.'; ?></p><?php endforeach; ?></section><?php awd_lm_leads_tool_connection_panel(); awd_lm_content_backup_panel(); ?></main></body></html><?php exit;
}

function awd_lm_urls_error_redirect( $message, $key = 'gloriafood' ) { wp_safe_redirect( add_query_arg( array( 'action'=>'awd_lm_urls_settings', 'landing'=>sanitize_key( $key ), 'awd_urls_error'=>$message ), admin_url( 'admin-post.php' ) ) ); exit; }

function awd_lm_save_urls() {
	awd_lm_save_urls_v2();
	return;
	/* Legacy handler kept below for rollback reference. */
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', 403 ); }
	check_admin_referer( 'awd_lm_save_urls' );
	$old = awd_lm_url_settings(); $pages = awd_lm_ensure_landing_pages(); $new = array();
	foreach ( awd_lm_languages() as $lang => $label ) { $result = awd_lm_validate_landing_slug( wp_unslash( $_POST['slugs'][ $lang ] ?? '' ), $lang, $pages['gloriafood'][ $lang ] ?? 0 ); if ( is_wp_error( $result ) ) { awd_lm_urls_error_redirect( $result->get_error_message() ); } $new[ $lang ] = $result; }
	$GLOBALS['awd_lm_staged_slugs']['gloriafood'] = $new;
	foreach ( awd_lm_languages() as $lang => $label ) { $result = awd_publish_landing( 'gloriafood', $lang ); if ( is_wp_error( $result ) ) { unset( $GLOBALS['awd_lm_staged_slugs'] ); foreach ( awd_lm_languages() as $restore_lang => $unused ) { awd_publish_landing( 'gloriafood', $restore_lang ); } awd_lm_urls_error_redirect( 'Publication failed; existing URLs were kept. ' . $result->get_error_message() ); } }
	$renamed = array();
	foreach ( awd_lm_languages() as $lang => $label ) {
		$page_id = absint( $pages['gloriafood'][ $lang ] ?? 0 ); if ( ! $page_id || $new[ $lang ] === $old['slugs'][ $lang ] ) { continue; }
		$result = wp_update_post( array( 'ID'=>$page_id, 'post_name'=>$new[ $lang ] ), true );
		$updated_page = is_wp_error( $result ) ? null : get_post( $page_id );
		if ( is_wp_error( $result ) || ! $updated_page || $updated_page->post_name !== $new[ $lang ] ) { foreach ( $renamed as $restore_lang => $restore_id ) { wp_update_post( array( 'ID'=>$restore_id, 'post_name'=>$old['slugs'][ $restore_lang ] ) ); } unset( $GLOBALS['awd_lm_staged_slugs'] ); foreach ( awd_lm_languages() as $restore_lang => $unused ) { awd_publish_landing( 'gloriafood', $restore_lang ); } awd_lm_urls_error_redirect( 'WordPress page update failed or the requested slug was adjusted; existing URLs were kept.' ); }
		$renamed[ $lang ] = $page_id;
	}
	$updated = $old; $updated['slugs'] = $new;
	foreach ( awd_lm_languages() as $lang => $label ) { if ( $new[ $lang ] === $old['slugs'][ $lang ] ) { continue; } array_unshift( $updated['history'][ $lang ], array( 'slug'=>$old['slugs'][ $lang ], 'changed_at'=>current_time( 'mysql' ) ) ); $updated['history'][ $lang ] = array_values( array_slice( array_filter( $updated['history'][ $lang ], static function( $entry ) use ( $new, $lang ) { return sanitize_title( is_array( $entry ) ? ( $entry['slug'] ?? '' ) : $entry ) !== $new[ $lang ]; } ), 0, 20 ) ); }
	update_option( 'awd_lm_url_settings', $updated, false ); unset( $GLOBALS['awd_lm_staged_slugs'] ); flush_rewrite_rules( false );
	wp_safe_redirect( add_query_arg( array( 'action'=>'awd_lm_urls_settings', 'awd_urls_notice'=>'saved' ), admin_url( 'admin-post.php' ) ) ); exit;
}
