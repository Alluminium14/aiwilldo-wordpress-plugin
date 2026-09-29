<?php
defined( 'ABSPATH' ) || exit;

function awd_lm_privacy_cookie_defaults() {
	return array(
		'consent_version' => 1,
		'retention_months' => 6,
		'categories'       => array(
			'necessary' => array( 'label' => 'Necessary', 'required' => 1, 'enabled' => 1 ),
			'analytics' => array( 'label' => 'Analytics', 'required' => 0, 'enabled' => 1 ),
			'marketing' => array( 'label' => 'Marketing', 'required' => 0, 'enabled' => 1 ),
		),
		'services'         => array(
			array( 'key'=>'awd-consent', 'name'=>'AWD Consent', 'category'=>'necessary', 'purpose'=>'Stores the visitor’s cookie preferences.', 'purpose_de'=>'Speichert die Cookie-Auswahl des Besuchers.', 'purpose_fr'=>'Enregistre les préférences de cookies du visiteur.', 'provider'=>'Website To Go', 'providers'=>array( 'gloriafood'=>'Website To Go', 'second-landing'=>'AI Will Do' ), 'storage'=>'6 months', 'enabled'=>1 ),
			array( 'key'=>'cloudflare-turnstile', 'name'=>'Cloudflare Turnstile', 'category'=>'necessary', 'purpose'=>'Protects forms from spam and automated abuse.', 'purpose_de'=>'Schützt Formulare vor Spam und automatisiertem Missbrauch.', 'purpose_fr'=>'Protège les formulaires contre le spam et les abus automatisés.', 'provider'=>'Cloudflare, Inc.', 'providers'=>array( 'gloriafood'=>'Cloudflare, Inc.', 'second-landing'=>'Cloudflare, Inc.' ), 'storage'=>'According to Cloudflare policy', 'enabled'=>1 ),
			array( 'key'=>'awd-analytics', 'name'=>'AWD Analytics', 'category'=>'analytics', 'purpose'=>'Measures sessions, viewed sections, interactions and campaign attribution.', 'purpose_de'=>'Misst Sitzungen, angesehene Bereiche, Interaktionen und Kampagnenzuordnung.', 'purpose_fr'=>'Mesure les sessions, les sections vues, les interactions et l’attribution des campagnes.', 'provider'=>'Website To Go', 'providers'=>array( 'gloriafood'=>'Website To Go', 'second-landing'=>'AI Will Do' ), 'storage'=>'12 months', 'enabled'=>1 ),
		),
		'policy'           => array(
			'de'=>array( 'title'=>'Cookie-Richtlinie', 'slug'=>'cookie-richtlinie', 'intro'=>'Diese Cookie-Richtlinie erklärt, welche Cookies und ähnlichen Technologien auf dieser Website verwendet werden und wie Sie Ihre Auswahl ändern können.', 'outro'=>'' ),
			'en'=>array( 'title'=>'Cookie Policy', 'slug'=>'cookie-policy', 'intro'=>'This Cookie Policy explains which cookies and similar technologies are used on this website and how you can change your choice.', 'outro'=>'' ),
			'fr'=>array( 'title'=>'Politique relative aux cookies', 'slug'=>'politique-de-cookies', 'intro'=>'Cette politique explique quels cookies et technologies similaires sont utilisés sur ce site et comment modifier votre choix.', 'outro'=>'' ),
		),
	);
}

function awd_lm_privacy_cookie_settings() {
	$defaults = awd_lm_privacy_cookie_defaults();
	$stored = get_option( 'awd_lm_privacy_cookies', array() );
	if ( ! is_array( $stored ) ) { $stored = array(); }
	$settings = array_replace_recursive( $defaults, $stored );
	$settings['consent_version'] = max( 1, absint( $settings['consent_version'] ) );
	$settings['retention_months'] = min( 24, max( 1, absint( $settings['retention_months'] ) ) );
	$settings['categories']['necessary']['required'] = 1;
	$settings['categories']['necessary']['enabled'] = 1;
	return $settings;
}

function awd_lm_cookie_settings() { return awd_lm_privacy_cookie_settings(); }

/* Stable public API for the consent UI and tracker gate. */
function awd_lm_cookie_categories() { return awd_lm_privacy_cookie_settings()['categories']; }
function awd_lm_cookie_services( $enabled_only = true ) {
	$services = awd_lm_privacy_cookie_settings()['services'];
	return $enabled_only ? array_values( array_filter( $services, static function( $service ) { return ! empty( $service['enabled'] ); } ) ) : $services;
}
function awd_lm_cookie_policy_url( $lang = 'de', $landing_key = 'gloriafood' ) {
	$lang = awd_get_current_language( $lang );
	$landing_key = sanitize_key( $landing_key );
	if ( ! awd_lm_definition( $landing_key ) ) { $landing_key = 'gloriafood'; }
	$settings = awd_lm_privacy_cookie_settings();
	if ( 'gloriafood' !== $landing_key ) {
		$landing_slug = awd_lm_landing_slug( $landing_key, $lang );
		return home_url( user_trailingslashit( $lang . '/' . $settings['policy'][ $lang ]['slug'] . '-' . $landing_slug ) );
	}
	return home_url( user_trailingslashit( $lang . '/' . $settings['policy'][ $lang ]['slug'] ) );
}
function awd_lm_cookie_policy_static_path( $lang, $landing_key = 'gloriafood' ) {
	$uploads = wp_upload_dir();
	return trailingslashit( $uploads['basedir'] ) . 'awd-landings/cookie-policy/' . sanitize_key( $landing_key ) . '/' . sanitize_key( $lang ) . '/index.html';
}

function awd_lm_cookie_frontend_config( $config, $lang, $content = array(), $landing_key = 'gloriafood' ) {
	$settings = awd_lm_privacy_cookie_settings();
	$config['cookieName'] = 'awd_consent';
	$config['version'] = (string) $settings['consent_version'];
	$config['lifetimeDays'] = max( 30, (int) $settings['retention_months'] * 30 );
	$config['policyUrl'] = awd_lm_cookie_policy_url( $lang, $landing_key );
	$config['showFooterControl'] = true;
	$config['categories'] = array(
		'analytics' => ! empty( $settings['categories']['analytics']['enabled'] ),
		'marketing' => ! empty( $settings['categories']['marketing']['enabled'] ),
	);
	return $config;
}
add_filter( 'awd_lm_consent_frontend_config', 'awd_lm_cookie_frontend_config', 10, 4 );

function awd_lm_sanitize_privacy_cookie_settings( $raw ) {
	$defaults = awd_lm_privacy_cookie_defaults();
	$out = $defaults;
	$out['consent_version'] = max( 1, absint( $raw['consent_version'] ?? 1 ) );
	$out['retention_months'] = min( 24, max( 1, absint( $raw['retention_months'] ?? 6 ) ) );
	foreach ( array( 'analytics', 'marketing' ) as $category ) {
		$out['categories'][ $category ]['enabled'] = empty( $raw['categories'][ $category ]['enabled'] ) ? 0 : 1;
	}
	$out['services'] = array();
	$rows = is_array( $raw['services'] ?? null ) ? $raw['services'] : array();
	$current_services = array();
	foreach ( awd_lm_privacy_cookie_settings()['services'] as $current_service ) {
		$current_services[ sanitize_key( $current_service['key'] ?? '' ) ] = $current_service;
	}
	foreach ( $rows as $row ) {
		if ( ! is_array( $row ) ) { continue; }
		$name = sanitize_text_field( $row['name'] ?? '' );
		if ( '' === $name ) { continue; }
		$category = sanitize_key( $row['category'] ?? 'necessary' );
		if ( ! isset( $defaults['categories'][ $category ] ) ) { $category = 'necessary'; }
		$service_key = sanitize_key( $row['key'] ?? $name );
		$current_providers = $current_services[ $service_key ]['providers'] ?? array();
		$out['services'][] = array(
			'key' => $service_key, 'name' => $name, 'category' => $category,
			'purpose' => sanitize_textarea_field( $row['purpose'] ?? '' ), 'purpose_de' => sanitize_textarea_field( $row['purpose_de'] ?? '' ), 'purpose_fr' => sanitize_textarea_field( $row['purpose_fr'] ?? '' ), 'provider' => sanitize_text_field( $row['provider'] ?? '' ),
			'providers' => array(
				'gloriafood' => sanitize_text_field( $row['providers']['gloriafood'] ?? $current_providers['gloriafood'] ?? $row['provider'] ?? '' ),
				'second-landing' => sanitize_text_field( $row['providers']['second-landing'] ?? $current_providers['second-landing'] ?? $row['provider'] ?? '' ),
			),
			'storage' => sanitize_text_field( $row['storage'] ?? '' ), 'enabled' => empty( $row['enabled'] ) ? 0 : 1,
		);
	}
	foreach ( awd_lm_languages() as $lang => $label ) {
		$out['policy'][ $lang ] = array(
			'title' => sanitize_text_field( $raw['policy'][ $lang ]['title'] ?? $defaults['policy'][ $lang ]['title'] ),
			'slug' => sanitize_title( $raw['policy'][ $lang ]['slug'] ?? $defaults['policy'][ $lang ]['slug'] ),
			'intro' => sanitize_textarea_field( $raw['policy'][ $lang ]['intro'] ?? $defaults['policy'][ $lang ]['intro'] ),
			'outro' => sanitize_textarea_field( $raw['policy'][ $lang ]['outro'] ?? $defaults['policy'][ $lang ]['outro'] ),
		);
		if ( '' === $out['policy'][ $lang ]['slug'] ) { $out['policy'][ $lang ]['slug'] = $defaults['policy'][ $lang ]['slug']; }
	}
	return $out;
}

function awd_lm_validate_cookie_policy_slug( $slug, $lang ) {
	$raw = trim( (string) $slug );
	$slug = sanitize_title( $raw );
	if ( '' === $slug || $slug !== strtolower( $raw ) || ! preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug ) ) { return new WP_Error( 'invalid_cookie_slug', strtoupper( $lang ) . ': cookie-page slug must use lowercase letters, numbers and hyphens only.' ); }
	if ( function_exists( 'awd_lm_reserved_slugs' ) && in_array( $slug, awd_lm_reserved_slugs(), true ) ) { return new WP_Error( 'reserved_cookie_slug', strtoupper( $lang ) . ': cookie-page slug is reserved.' ); }
	$url_settings = function_exists( 'awd_lm_url_settings' ) ? awd_lm_url_settings() : array( 'slugs'=>array(), 'history'=>array() );
	$landing_slugs = array( sanitize_title( $url_settings['slugs'][ $lang ] ?? '' ) );
	foreach ( (array) ( $url_settings['history'][ $lang ] ?? array() ) as $entry ) { $landing_slugs[] = sanitize_title( is_array( $entry ) ? ( $entry['slug'] ?? '' ) : $entry ); }
	if ( in_array( $slug, array_filter( $landing_slugs ), true ) ) { return new WP_Error( 'landing_slug_conflict', strtoupper( $lang ) . ': this address is used by the landing page or one of its redirects.' ); }
	$existing = get_page_by_path( $lang . '/' . $slug, OBJECT, 'page' );
	if ( $existing ) { return new WP_Error( 'cookie_page_conflict', strtoupper( $lang ) . ': another WordPress page already uses this address.' ); }
	return $slug;
}

function awd_lm_render_cookie_policy( $lang, $landing_key = 'gloriafood' ) {
	$lang = awd_get_current_language( $lang );
	$landing_key = sanitize_key( $landing_key );
	$definition = awd_lm_definition( $landing_key );
	if ( ! $definition ) { $landing_key = 'gloriafood'; $definition = awd_lm_definition( $landing_key ); }
	$settings = awd_lm_privacy_cookie_settings();
	$policy = $settings['policy'][ $lang ];
	$landing_content = awd_lm_get_content( $landing_key );
	$policy_brand = sanitize_text_field( $landing_content['brand_name'] ?? ( 'second-landing' === $landing_key ? 'AI Will Do' : 'Website To Go' ) );
	if ( '' === $policy_brand ) { $policy_brand = 'second-landing' === $landing_key ? 'AI Will Do' : 'Website To Go'; }
	$labels = array(
		'de'=>array( 'services'=>'Verwendete Dienste', 'service'=>'Dienst', 'category'=>'Kategorie', 'purpose'=>'Zweck', 'provider'=>'Anbieter', 'storage'=>'Speicherdauer', 'change'=>'Cookie-Einstellungen ändern', 'back'=>'Zur Website' ),
		'en'=>array( 'services'=>'Services used', 'service'=>'Service', 'category'=>'Category', 'purpose'=>'Purpose', 'provider'=>'Provider', 'storage'=>'Storage period', 'change'=>'Change cookie settings', 'back'=>'Back to website' ),
		'fr'=>array( 'services'=>'Services utilisés', 'service'=>'Service', 'category'=>'Catégorie', 'purpose'=>'Finalité', 'provider'=>'Fournisseur', 'storage'=>'Durée de conservation', 'change'=>'Modifier les paramètres des cookies', 'back'=>'Retour au site' ),
	);
	$l = $labels[ $lang ];
	$category_labels = array( 'de'=>array('necessary'=>'Notwendig','analytics'=>'Analyse','marketing'=>'Marketing'), 'en'=>array('necessary'=>'Necessary','analytics'=>'Analytics','marketing'=>'Marketing'), 'fr'=>array('necessary'=>'Nécessaire','analytics'=>'Analyse','marketing'=>'Marketing') );
	$rows = '';
	foreach ( awd_lm_cookie_services() as $service ) {
		$purpose = 'de' === $lang ? ( $service['purpose_de'] ?? $service['purpose'] ) : ( 'fr' === $lang ? ( $service['purpose_fr'] ?? $service['purpose'] ) : $service['purpose'] );
		$provider = $service['providers'][ $landing_key ] ?? $service['provider'];
		$rows .= '<tr><th scope="row">' . esc_html( $service['name'] ) . '</th><td>' . esc_html( $category_labels[ $lang ][ $service['category'] ] ?? ucfirst( $service['category'] ) ) . '</td><td>' . esc_html( $purpose ) . '</td><td>' . esc_html( $provider ) . '</td><td>' . esc_html( $service['storage'] ) . '</td></tr>';
	}
	$language_links = '';
	foreach ( awd_lm_languages() as $code => $name ) { $language_links .= '<a href="' . esc_url( awd_lm_cookie_policy_url( $code, $landing_key ) ) . '"' . ( $code === $lang ? ' aria-current="page"' : '' ) . '>' . esc_html( strtoupper( $code ) ) . '</a>'; }
	$cookie_css_path = AWD_LM_DIR . 'assets/cookie-policy.css';
	$css = AWD_LM_URL . 'assets/cookie-policy.css?ver=' . ( is_file( $cookie_css_path ) ? filemtime( $cookie_css_path ) : AWD_LM_VERSION );
	$base_css = awd_lm_landing_css_url( $landing_key );
	$consent_script = AWD_LM_URL . 'assets/consent.js?ver=' . AWD_LM_VERSION;
	$consent_config = awd_lm_cookie_frontend_config( array(), $lang, array(), $landing_key );
	$landing_url = awd_lm_public_url( $landing_key, $lang );
	$root_class = sanitize_html_class( $definition['root_class'] ?? ( 'awd-landing--' . $landing_key ) );
	$header_markup = '<header class="wtg-legal-header"><a href="' . esc_url( $landing_url ) . '">Website To Go</a><nav>' . $language_links . '</nav></header>';
	$page_open = '';
	$page_close = '';
	$navigation_script = '';
	if ( 'second-landing' === $landing_key && function_exists( 'awd_lm_render_prototype_body' ) ) {
		$landing_body = awd_lm_render_prototype_body( $landing_content, $lang, $landing_key );
		if ( preg_match( '/<header class="wtg-nav-wrap".*?<\/header>/su', $landing_body, $header_match ) ) {
			$header_markup = str_replace( 'class="wtg-nav-wrap"', 'class="wtg-nav-wrap wtg-cookie-nav"', $header_match[0] );
			$header_markup = preg_replace( '/href="#([^"]*)"/', 'href="' . esc_url( $landing_url ) . '#$1"', $header_markup );
			$policy_language_links = '<div class="wtg-language-switch" aria-label="Language">';
			foreach ( awd_lm_languages() as $code => $name ) {
				$policy_language_links .= '<a href="' . esc_url( awd_lm_cookie_policy_url( $code, $landing_key ) ) . '" hreflang="' . esc_attr( $code ) . '" lang="' . esc_attr( $code ) . '"' . ( $code === $lang ? ' class="is-current" aria-current="page"' : '' ) . '>' . esc_html( strtoupper( $code ) ) . '</a>';
			}
			$policy_language_links .= '</div>';
			$header_markup = preg_replace( '/<div class="wtg-language-switch".*?<\/div>/su', $policy_language_links, $header_markup, 1 );
			$page_open = '<div class="wtg-page wtg-cookie-page">';
			$page_close = '</div>';
			$navigation_script = '<script src="' . esc_url( AWD_LM_URL . 'assets/landing-v1.js?ver=' . AWD_LM_VERSION ) . '" defer></script>';
		}
	}
	$after_services = '' !== trim( (string) ( $policy['outro'] ?? '' ) ) ? '<p class="wtg-legal-intro wtg-legal-after-services">' . nl2br( esc_html( $policy['outro'] ) ) . '</p>' : '';
	return '<!doctype html><html lang="' . esc_attr( $lang ) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . esc_html( $policy['title'] ) . '</title><link rel="stylesheet" href="' . esc_url( $base_css ) . '"><link rel="stylesheet" href="' . esc_url( $css ) . '"></head><body class="' . esc_attr( $root_class ) . '">' . $page_open . $header_markup . '<main class="wtg-legal-main"><p class="wtg-legal-kicker">' . esc_html( $policy_brand ) . '</p><h1>' . esc_html( $policy['title'] ) . '</h1><p class="wtg-legal-intro">' . nl2br( esc_html( $policy['intro'] ) ) . '</p><h2>' . esc_html( $l['services'] ) . '</h2><div class="wtg-legal-table-wrap"><table><thead><tr><th>' . esc_html( $l['service'] ) . '</th><th>' . esc_html( $l['category'] ) . '</th><th>' . esc_html( $l['purpose'] ) . '</th><th>' . esc_html( $l['provider'] ) . '</th><th>' . esc_html( $l['storage'] ) . '</th></tr></thead><tbody>' . $rows . '</tbody></table></div>' . $after_services . '<p class="wtg-legal-actions"><button type="button" data-awd-cookie-settings>' . esc_html( $l['change'] ) . '</button><a href="' . esc_url( $landing_url ) . '">' . esc_html( $l['back'] ) . '</a></p></main>' . $page_close . $navigation_script . '<script src="' . esc_url( $consent_script ) . '" data-language="' . esc_attr( $lang ) . '" data-config="' . esc_attr( wp_json_encode( $consent_config ) ) . '" defer></script></body></html>';
}

function awd_lm_publish_cookie_policy( $lang, $landing_key = 'gloriafood' ) {
	$html = awd_lm_render_cookie_policy( $lang, $landing_key );
	$target = awd_lm_cookie_policy_static_path( $lang, $landing_key );
	$dir = dirname( $target );
	if ( ! wp_mkdir_p( $dir ) || ! is_writable( $dir ) ) { return new WP_Error( 'not_writable', 'Cookie policy output directory is not writable.' ); }
	$temp = $target . '.tmp-' . wp_generate_password( 8, false, false );
	if ( false === file_put_contents( $temp, $html, LOCK_EX ) ) { return new WP_Error( 'write_failed', 'Could not write cookie policy.' ); }
	if ( is_file( $target ) && ! @rename( $target, $target . '.previous' ) ) { @unlink( $temp ); return new WP_Error( 'backup_failed', 'Could not protect current cookie policy.' ); }
	if ( ! @rename( $temp, $target ) ) { if ( is_file( $target . '.previous' ) ) { @rename( $target . '.previous', $target ); } return new WP_Error( 'replace_failed', 'Could not publish cookie policy.' ); }
	@unlink( $target . '.previous' );
	return array( 'path'=>$target, 'url'=>awd_lm_cookie_policy_url( $lang, $landing_key ) );
}

function awd_lm_publish_all_cookie_policies() {
	foreach ( awd_lm_definitions() as $landing_key => $definition ) {
		foreach ( (array) ( $definition['languages'] ?? array_keys( awd_lm_languages() ) ) as $lang ) { awd_lm_publish_cookie_policy( $lang, $landing_key ); }
	}
}

function awd_lm_cookie_policy_context_from_path( $path ) {
	$path = trim( rawurldecode( (string) $path ), '/' );
	foreach ( awd_lm_definitions() as $landing_key => $definition ) {
		foreach ( (array) ( $definition['languages'] ?? array_keys( awd_lm_languages() ) ) as $lang ) {
			$policy_path = trim( (string) wp_parse_url( awd_lm_cookie_policy_url( $lang, $landing_key ), PHP_URL_PATH ), '/' );
			$translated_default_path = 'de' === $lang ? preg_replace( '#^de/#', '', $policy_path ) : $policy_path;
			if ( $path === $policy_path || $path === $translated_default_path ) { return array( 'landing_key' => $landing_key, 'lang' => $lang ); }
		}
	}
	return false;
}

function awd_lm_intercept_cookie_policy_path( $wp ) {
	if ( is_admin() || empty( $wp->request ) ) { return; }
	$request = trim( rawurldecode( $wp->request ), '/' );
	$context = awd_lm_cookie_policy_context_from_path( $request );
	if ( ! $context ) { return; }
	$path = awd_lm_cookie_policy_static_path( $context['lang'], $context['landing_key'] );
	if ( ! is_file( $path ) ) { awd_lm_publish_cookie_policy( $context['lang'], $context['landing_key'] ); }
	header( 'Content-Type: text/html; charset=UTF-8' );
	if ( is_readable( $path ) ) { readfile( $path ); } else { echo awd_lm_render_cookie_policy( $context['lang'], $context['landing_key'] ); } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit;
}

function awd_lm_cookie_policy_language_from_path( $path ) {
	$context = awd_lm_cookie_policy_context_from_path( $path );
	return $context ? $context['lang'] : false;
}

function awd_lm_serve_cookie_policy( $lang, $landing_key = 'gloriafood' ) {
	$path = awd_lm_cookie_policy_static_path( $lang, $landing_key );
	if ( ! is_file( $path ) ) { awd_lm_publish_cookie_policy( $lang, $landing_key ); }
	header( 'Content-Type: text/html; charset=UTF-8' );
	header( 'X-AWD-Cookie-Policy: static-fallback' );
	if ( is_readable( $path ) ) { readfile( $path ); } else { echo awd_lm_render_cookie_policy( $lang, $landing_key ); } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit;
}

function awd_lm_handle_cookie_policy_front_request() {
	if ( is_admin() ) { return; }
	$request_uri = wp_unslash( $_SERVER['REQUEST_URI'] ?? '' );
	$context = awd_lm_cookie_policy_context_from_path( wp_parse_url( $request_uri, PHP_URL_PATH ) );
	if ( $context ) { awd_lm_serve_cookie_policy( $context['lang'], $context['landing_key'] ); }
}

function awd_lm_privacy_cookies_page() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', 403 ); }
	$settings = awd_lm_privacy_cookie_settings();
	$notice = sanitize_key( wp_unslash( $_GET['notice'] ?? '' ) );
	?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Privacy & Cookies</title><style>body{margin:0;padding:30px;background:#f3f7f4;color:#123b2c;font:14px/1.45 system-ui}main{max-width:1180px;margin:auto}.bar,.row{display:flex;gap:12px;align-items:center;justify-content:space-between;flex-wrap:wrap}.card{margin:18px 0;padding:22px;background:#fff;border:1px solid #cfe5d7;border-radius:15px}h1,h2{margin-top:0}label{display:block;font-weight:800;margin:12px 0 5px}input,textarea,select{box-sizing:border-box;width:100%;padding:10px;border:1px solid #bcdac7;border-radius:8px}textarea{min-height:110px}.grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.service{display:grid;grid-template-columns:1fr 1fr 1fr 2fr 1fr auto;gap:8px;align-items:end;margin:10px 0;padding:12px;background:#f7faf8;border-radius:10px}.service label{font-size:12px}.button,button{display:inline-block;padding:10px 14px;border:0;border-radius:8px;background:#087a4c;color:#fff;text-decoration:none;font-weight:800;cursor:pointer}.secondary{background:#60756c}.notice{padding:12px;background:#e5f7ea;border-radius:9px}@media(max-width:800px){body{padding:16px}.grid,.service{grid-template-columns:1fr}}</style></head><body><main><div class="bar"><div><h1>Privacy &amp; Cookies</h1><p>Consent categories, services and multilingual cookie-policy pages.</p></div><a class="button secondary" href="<?php echo esc_url( admin_url( 'admin-post.php?action=awd_lm_visual_editor' ) ); ?>">← Editor</a></div><?php if ( $notice ) : ?><p class="notice">Settings saved and cookie-policy pages published.</p><?php endif; ?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="awd_lm_save_privacy_cookies"><?php wp_nonce_field( 'awd_lm_save_privacy_cookies' ); ?><section class="card"><h2>Consent</h2><div class="grid"><div><label>Consent version</label><input type="number" min="1" name="settings[consent_version]" value="<?php echo esc_attr( $settings['consent_version'] ); ?>"><p>Increase after a material policy change to ask visitors again.</p></div><div><label>Remember choice (months)</label><input type="number" min="1" max="24" name="settings[retention_months]" value="<?php echo esc_attr( $settings['retention_months'] ); ?>"></div><div><label><input type="checkbox" checked disabled style="width:auto"> Necessary (always active)</label><label><input type="checkbox" name="settings[categories][analytics][enabled]" value="1" <?php checked( $settings['categories']['analytics']['enabled'] ); ?> style="width:auto"> Analytics available</label><label><input type="checkbox" name="settings[categories][marketing][enabled]" value="1" <?php checked( $settings['categories']['marketing']['enabled'] ); ?> style="width:auto"> Marketing available</label></div></div></section><section class="card"><div class="bar"><h2>Service registry</h2><button type="button" class="secondary" id="addService">+ Add service</button></div><div id="services"><?php foreach ( $settings['services'] as $index=>$service ) : ?><div class="service"><label>Name<input name="settings[services][<?php echo esc_attr( $index ); ?>][name]" value="<?php echo esc_attr( $service['name'] ); ?>"></label><label>Key<input name="settings[services][<?php echo esc_attr( $index ); ?>][key]" value="<?php echo esc_attr( $service['key'] ); ?>"></label><label>Category<select name="settings[services][<?php echo esc_attr( $index ); ?>][category]"><?php foreach ( array('necessary','analytics','marketing') as $category ) : ?><option value="<?php echo esc_attr( $category ); ?>" <?php selected( $service['category'], $category ); ?>><?php echo esc_html( ucfirst( $category ) ); ?></option><?php endforeach; ?></select></label><label>Purpose EN<input name="settings[services][<?php echo esc_attr( $index ); ?>][purpose]" value="<?php echo esc_attr( $service['purpose'] ); ?>"><input name="settings[services][<?php echo esc_attr( $index ); ?>][purpose_de]" value="<?php echo esc_attr( $service['purpose_de'] ?? '' ); ?>" placeholder="Purpose DE"><input name="settings[services][<?php echo esc_attr( $index ); ?>][purpose_fr]" value="<?php echo esc_attr( $service['purpose_fr'] ?? '' ); ?>" placeholder="Purpose FR"></label><label>Provider / storage<input name="settings[services][<?php echo esc_attr( $index ); ?>][provider]" value="<?php echo esc_attr( $service['provider'] ); ?>" placeholder="Provider"><input name="settings[services][<?php echo esc_attr( $index ); ?>][storage]" value="<?php echo esc_attr( $service['storage'] ); ?>" placeholder="Storage period"></label><label><input type="checkbox" name="settings[services][<?php echo esc_attr( $index ); ?>][enabled]" value="1" <?php checked( $service['enabled'] ); ?> style="width:auto"> Active<br><button type="button" class="remove secondary">Remove</button></label></div><?php endforeach; ?></div></section><section class="card"><h2>Cookie Policy pages</h2><div class="grid"><?php foreach ( awd_lm_languages() as $lang=>$language ) : $policy=$settings['policy'][$lang]; ?><div><h3><?php echo esc_html( $language ); ?></h3><label>Title</label><input name="settings[policy][<?php echo esc_attr( $lang ); ?>][title]" value="<?php echo esc_attr( $policy['title'] ); ?>"><label>Slug</label><input name="settings[policy][<?php echo esc_attr( $lang ); ?>][slug]" value="<?php echo esc_attr( $policy['slug'] ); ?>"><label>Text before services</label><textarea name="settings[policy][<?php echo esc_attr( $lang ); ?>][intro]"><?php echo esc_textarea( $policy['intro'] ); ?></textarea><label>Text after services</label><textarea name="settings[policy][<?php echo esc_attr( $lang ); ?>][outro]"><?php echo esc_textarea( $policy['outro'] ?? '' ); ?></textarea><p><a href="<?php echo esc_url( awd_lm_cookie_policy_url( $lang ) ); ?>" target="_blank">View page ↗</a></p></div><?php endforeach; ?></div></section><button type="submit">Save &amp; publish policies</button></form></main><template id="serviceTemplate"><div class="service"><label>Name<input data-field="name"></label><label>Key<input data-field="key"></label><label>Category<select data-field="category"><option>necessary</option><option>analytics</option><option>marketing</option></select></label><label>Purpose<input data-field="purpose" placeholder="Purpose EN"><input data-field="purpose_de" placeholder="Purpose DE"><input data-field="purpose_fr" placeholder="Purpose FR"></label><label>Provider / storage<input data-field="provider" placeholder="Provider"><input data-field="storage" placeholder="Storage period"></label><label><input type="checkbox" data-field="enabled" value="1" checked style="width:auto"> Active<br><button type="button" class="remove secondary">Remove</button></label></div></template><script>const box=document.getElementById('services');document.getElementById('addService').onclick=()=>{const row=document.getElementById('serviceTemplate').content.firstElementChild.cloneNode(true),i=box.children.length;row.querySelectorAll('[data-field]').forEach(el=>{el.name='settings[services]['+i+']['+el.dataset.field+']';el.removeAttribute('data-field')});box.appendChild(row)};document.addEventListener('click',e=>{if(e.target.matches('.remove'))e.target.closest('.service').remove()});</script></body></html><?php exit;
}

function awd_lm_save_privacy_cookies() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', 403 ); }
	check_admin_referer( 'awd_lm_save_privacy_cookies' );
	$raw = isset( $_POST['settings'] ) && is_array( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array();
	foreach ( awd_lm_languages() as $lang => $label ) {
		$validated = awd_lm_validate_cookie_policy_slug( $raw['policy'][ $lang ]['slug'] ?? '', $lang );
		if ( is_wp_error( $validated ) ) {
			wp_die( esc_html( $validated->get_error_message() ), 'Invalid cookie settings', array( 'response'=>400, 'back_link'=>true ) );
		}
		$raw['policy'][ $lang ]['slug'] = $validated;
	}
	update_option( 'awd_lm_privacy_cookies', awd_lm_sanitize_privacy_cookie_settings( $raw ), false );
	awd_lm_publish_all_cookie_policies();
	wp_safe_redirect( add_query_arg( array( 'action'=>'awd_lm_privacy_cookies', 'notice'=>'saved' ), admin_url( 'admin-post.php' ) ) ); exit;
}
