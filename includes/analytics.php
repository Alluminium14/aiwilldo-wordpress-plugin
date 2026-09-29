<?php
defined( 'ABSPATH' ) || exit;

function awd_lm_analytics_tables() {
	global $wpdb;
	return array(
		'sessions' => $wpdb->prefix . 'awd_lm_analytics_sessions',
		'events'   => $wpdb->prefix . 'awd_lm_analytics_events',
	);
}

function awd_lm_install_analytics_tables() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$tables = awd_lm_analytics_tables();
	$charset = $wpdb->get_charset_collate();
	dbDelta( "CREATE TABLE {$tables['sessions']} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		session_key varchar(64) NOT NULL,
		started_at datetime NOT NULL,
		last_seen_at datetime NOT NULL,
		landing varchar(50) NOT NULL DEFAULT 'gloriafood',
		landing_id varchar(50) NOT NULL DEFAULT 'gloriafood',
		language varchar(5) NOT NULL DEFAULT 'de',
		landing_url text NULL,
		referrer text NULL,
		utm_source varchar(190) NOT NULL DEFAULT '',
		utm_medium varchar(190) NOT NULL DEFAULT '',
		utm_campaign varchar(190) NOT NULL DEFAULT '',
		utm_content varchar(190) NOT NULL DEFAULT '',
		utm_term varchar(190) NOT NULL DEFAULT '',
		utm_id varchar(190) NOT NULL DEFAULT '',
		click_id varchar(255) NOT NULL DEFAULT '',
		device_type varchar(20) NOT NULL DEFAULT '',
		engaged_seconds int(10) unsigned NOT NULL DEFAULT 0,
		lead_id bigint(20) unsigned NOT NULL DEFAULT 0,
		email_send_id bigint(20) unsigned NOT NULL DEFAULT 0,
		event_count int(10) unsigned NOT NULL DEFAULT 0,
		PRIMARY KEY  (id),
		UNIQUE KEY session_key (session_key),
		KEY started_at (started_at),
		KEY last_seen_at (last_seen_at),
		KEY source_campaign (utm_source,utm_campaign),
		KEY lead_id (lead_id),
		KEY email_send_id (email_send_id),
		KEY landing_started (landing_id,started_at)
	) $charset;" );
	dbDelta( "CREATE TABLE {$tables['events']} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		event_key varchar(64) NOT NULL,
		session_id bigint(20) unsigned NOT NULL,
		occurred_at datetime NOT NULL,
		event_name varchar(50) NOT NULL,
		landing_id varchar(50) NOT NULL DEFAULT 'gloriafood',
		page_url text NULL,
		section_id varchar(100) NOT NULL DEFAULT '',
		section_name varchar(190) NOT NULL DEFAULT '',
		element_id varchar(100) NOT NULL DEFAULT '',
		element_label varchar(255) NOT NULL DEFAULT '',
		metadata text NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY event_key (event_key),
		KEY session_time (session_id,occurred_at),
		KEY event_time (event_name,occurred_at),
		KEY landing_event_time (landing_id,event_name,occurred_at)
	) $charset;" );
	/* Old installations only knew the GloriaFood landing. Preserve those rows explicitly. */
	$wpdb->query( "UPDATE {$tables['sessions']} SET landing_id=IF(landing<>'',landing,'gloriafood') WHERE landing_id='' OR landing_id IS NULL" );
	$wpdb->query( "UPDATE {$tables['events']} e INNER JOIN {$tables['sessions']} s ON s.id=e.session_id SET e.landing_id=s.landing_id WHERE e.landing_id='' OR e.landing_id IS NULL" );
	update_option( 'awd_lm_analytics_db_version', '1.3', false );
}

function awd_lm_maybe_upgrade_analytics_tables() {
	if ( '1.3' !== get_option( 'awd_lm_analytics_db_version' ) ) { awd_lm_install_analytics_tables(); }
}

function awd_lm_analytics_landing_id( $value ) {
	$value = sanitize_key( (string) $value );
	if ( function_exists( 'awd_lm_definitions' ) ) {
		$definitions = awd_lm_definitions();
		if ( isset( $definitions[ $value ] ) ) { return $value; }
	}
	return 'gloriafood';
}

function awd_lm_analytics_resolve_landing_id( $context ) {
	$explicit = sanitize_key( $context['landing_id'] ?? ( $context['landing'] ?? '' ) );
	if ( '' !== $explicit ) { return awd_lm_analytics_landing_id( $explicit ); }
	$path = trim( (string) wp_parse_url( $context['landing_url'] ?? '', PHP_URL_PATH ), '/' );
	if ( '' !== $path && function_exists( 'awd_lm_definitions' ) ) {
		foreach ( awd_lm_definitions() as $key => $definition ) {
			foreach ( array_keys( function_exists( 'awd_lm_languages' ) ? awd_lm_languages() : array( 'de' => 'Deutsch' ) ) as $language ) {
				$slug = function_exists( 'awd_lm_landing_slug' ) ? awd_lm_landing_slug( $key, $language ) : ( $definition['slug'] ?? '' );
				if ( '' !== $slug && preg_match( '#(?:^|/)' . preg_quote( $language . '/' . $slug, '#' ) . '/?$#', $path ) ) { return $key; }
			}
		}
	}
	return 'gloriafood';
}

function awd_lm_analytics_valid_session_key( $value ) {
	$value = strtolower( sanitize_text_field( (string) $value ) );
	return preg_match( '/^[a-f0-9-]{32,64}$/', $value ) ? $value : '';
}

function awd_lm_analytics_allowed_events() {
	return array( 'page_view', 'section_view', 'cta_click', 'menu_click', 'language_change', 'faq_open', 'interest_selected', 'form_start', 'form_validation_error', 'form_submit', 'lead_created', 'newsletter_signup', 'phone_click', 'email_click', 'external_link_click', 'engagement_ping' );
}

function awd_lm_analytics_url( $value ) {
	$value = esc_url_raw( (string) $value, array( 'http', 'https' ) );
	return substr( $value, 0, 2000 );
}

function awd_lm_analytics_metadata( $value ) {
	if ( ! is_array( $value ) ) { return ''; }
	$clean = array();
	foreach ( array_slice( $value, 0, 10, true ) as $key => $item ) {
		$key = sanitize_key( $key );
		if ( '' === $key || ! is_scalar( $item ) ) { continue; }
		$clean[ $key ] = substr( sanitize_text_field( (string) $item ), 0, 190 );
	}
	return $clean ? wp_json_encode( $clean ) : '';
}

function awd_lm_handle_analytics_track() {
	if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { status_header( 405 ); exit; }
	if ( is_user_logged_in() ) { status_header( 204 ); exit; }
	$length = absint( $_SERVER['CONTENT_LENGTH'] ?? 0 );
	if ( $length > 32768 ) { status_header( 413 ); exit; }
	$site_host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	$origin_host = strtolower( (string) wp_parse_url( wp_unslash( $_SERVER['HTTP_ORIGIN'] ?? '' ), PHP_URL_HOST ) );
	$referer_host = strtolower( (string) wp_parse_url( wp_unslash( $_SERVER['HTTP_REFERER'] ?? '' ), PHP_URL_HOST ) );
	if ( ( $origin_host && $origin_host !== $site_host ) || ( ! $origin_host && $referer_host && $referer_host !== $site_host ) ) { status_header( 403 ); exit; }
	$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
	$rate_key = 'awd_lm_analytics_' . md5( $ip . gmdate( 'Y-m-d-H-i' ) );
	$requests = absint( get_transient( $rate_key ) );
	if ( $requests >= 120 ) { status_header( 429 ); exit; }
	set_transient( $rate_key, $requests + 1, 2 * MINUTE_IN_SECONDS );
	$payload = json_decode( (string) file_get_contents( 'php://input' ), true );
	if ( ! is_array( $payload ) ) { status_header( 400 ); exit; }
	$session_key = awd_lm_analytics_valid_session_key( $payload['session_id'] ?? '' );
	$events = isset( $payload['events'] ) && is_array( $payload['events'] ) ? array_slice( $payload['events'], 0, 20 ) : array();
	if ( '' === $session_key || empty( $events ) ) { status_header( 400 ); exit; }
	awd_lm_maybe_upgrade_analytics_tables();
	global $wpdb;
	$tables = awd_lm_analytics_tables();
	$now = current_time( 'mysql', true );
	$context = isset( $payload['context'] ) && is_array( $payload['context'] ) ? $payload['context'] : array();
	$landing_id = awd_lm_analytics_resolve_landing_id( $context );
	$language = awd_get_current_language( sanitize_key( $context['language'] ?? 'de' ) );
	$device = sanitize_key( $context['device_type'] ?? '' );
	if ( ! in_array( $device, array( 'mobile', 'tablet', 'desktop' ), true ) ) { $device = ''; }
	$campaign = isset( $context['campaign'] ) && is_array( $context['campaign'] ) ? $context['campaign'] : array();
	$email_send_id = function_exists( 'awd_lm_email_click_resolve' ) ? awd_lm_email_click_resolve( $context['email_click_token'] ?? '' ) : 0;
	$utm = function ( $key ) use ( $campaign ) { return substr( sanitize_text_field( $campaign[ $key ] ?? '' ), 0, 190 ); };
	$click_id = '';
	foreach ( array( 'gclid', 'fbclid', 'msclkid' ) as $click_key ) {
		if ( ! empty( $campaign[ $click_key ] ) ) { $click_id = $click_key . ':' . substr( sanitize_text_field( $campaign[ $click_key ] ), 0, 240 ); break; }
	}
	$event_times = array();
	foreach ( $events as $candidate ) {
		$milliseconds = is_array( $candidate ) ? absint( $candidate['timestamp'] ?? 0 ) : 0;
		$seconds = $milliseconds ? (int) floor( $milliseconds / 1000 ) : 0;
		if ( $seconds && abs( time() - $seconds ) < DAY_IN_SECONDS ) { $event_times[] = $seconds; }
	}
	$first_event_at = $event_times ? gmdate( 'Y-m-d H:i:s', min( $event_times ) ) : $now;
	$last_event_at = $event_times ? gmdate( 'Y-m-d H:i:s', max( $event_times ) ) : $now;
	$existing_session = $wpdb->get_row( $wpdb->prepare( "SELECT id,landing_id FROM {$tables['sessions']} WHERE session_key=%s", $session_key ) );
	$existing_id = $existing_session ? (int) $existing_session->id : 0;
	if ( ! $existing_id ) {
		$wpdb->insert( $tables['sessions'], array(
			'session_key' => $session_key, 'started_at' => $first_event_at, 'last_seen_at' => $last_event_at, 'landing' => $landing_id, 'landing_id' => $landing_id, 'language' => $language,
			'landing_url' => awd_lm_analytics_url( $context['landing_url'] ?? '' ), 'referrer' => awd_lm_analytics_url( $context['referrer'] ?? '' ),
			'utm_source' => $utm( 'utm_source' ), 'utm_medium' => $utm( 'utm_medium' ), 'utm_campaign' => $utm( 'utm_campaign' ),
			'utm_content' => $utm( 'utm_content' ), 'utm_term' => $utm( 'utm_term' ), 'utm_id' => $utm( 'utm_id' ),
			'click_id' => $click_id, 'device_type' => $device, 'email_send_id' => $email_send_id, 'event_count' => 0,
		) );
		$existing_id = (int) $wpdb->insert_id;
	} else {
		/* A session belongs to the landing where it started, even if a later request is malformed. */
		$landing_id = awd_lm_analytics_landing_id( $existing_session->landing_id );
	}
	if ( ! $existing_id ) { status_header( 500 ); exit; }
	if ( $email_send_id ) { $wpdb->query( $wpdb->prepare( "UPDATE {$tables['sessions']} SET email_send_id=IF(email_send_id=0,%d,email_send_id) WHERE id=%d", $email_send_id, $existing_id ) ); }
	$allowed = awd_lm_analytics_allowed_events();
	$inserted = 0;
	$engaged_seconds = 0;
	foreach ( $events as $event ) {
		if ( ! is_array( $event ) ) { continue; }
		$name = sanitize_key( $event['name'] ?? '' );
		$event_key = awd_lm_analytics_valid_session_key( $event['event_id'] ?? '' );
		if ( ! in_array( $name, $allowed, true ) || '' === $event_key ) { continue; }
		if ( 'engagement_ping' === $name ) {
			$seconds = isset( $event['metadata']['seconds'] ) ? absint( $event['metadata']['seconds'] ) : 0;
			$engaged_seconds += min( 30, $seconds );
			continue;
		}
		$milliseconds = absint( $event['timestamp'] ?? 0 );
		$occurred = $milliseconds > 0 && abs( time() - (int) floor( $milliseconds / 1000 ) ) < DAY_IN_SECONDS ? gmdate( 'Y-m-d H:i:s', (int) floor( $milliseconds / 1000 ) ) : $now;
		$result = $wpdb->insert( $tables['events'], array(
			'event_key' => $event_key, 'session_id' => $existing_id, 'occurred_at' => $occurred, 'event_name' => $name, 'landing_id' => $landing_id,
			'page_url' => awd_lm_analytics_url( $event['page_url'] ?? '' ), 'section_id' => substr( sanitize_key( $event['section_id'] ?? '' ), 0, 100 ),
			'section_name' => substr( sanitize_text_field( $event['section_name'] ?? '' ), 0, 190 ),
			'element_id' => substr( sanitize_key( $event['element_id'] ?? '' ), 0, 100 ), 'element_label' => substr( sanitize_text_field( $event['element_label'] ?? '' ), 0, 255 ),
			'metadata' => awd_lm_analytics_metadata( $event['metadata'] ?? array() ),
		) );
		if ( false !== $result ) { $inserted++; }
	}
	if ( $inserted || $engaged_seconds ) {
		$wpdb->query( $wpdb->prepare( "UPDATE {$tables['sessions']} SET last_seen_at=GREATEST(last_seen_at,%s), language=%s, device_type=IF(device_type='',%s,device_type), event_count=event_count+%d, engaged_seconds=engaged_seconds+%d WHERE id=%d", $last_event_at, $language, $device, $inserted, $engaged_seconds, $existing_id ) );
	}
	wp_send_json_success( array( 'accepted' => $inserted ), 202 );
}

function awd_lm_link_analytics_session_to_lead( $session_key, $lead_id, $newsletter = false ) {
	global $wpdb;
	$session_key = awd_lm_analytics_valid_session_key( $session_key );
	$lead_id = absint( $lead_id );
	if ( '' === $session_key || ! $lead_id ) { return false; }
	awd_lm_maybe_upgrade_analytics_tables();
	$tables = awd_lm_analytics_tables();
	$session = $wpdb->get_row( $wpdb->prepare( "SELECT id,landing_id FROM {$tables['sessions']} WHERE session_key=%s", $session_key ) );
	$session_id = $session ? (int) $session->id : 0;
	if ( ! $session_id ) { return false; }
	$now = current_time( 'mysql', true );
	$wpdb->update( $tables['sessions'], array( 'lead_id' => $lead_id, 'last_seen_at' => $now ), array( 'id' => $session_id ) );
	$landing_id = awd_lm_analytics_landing_id( $session->landing_id ?? 'gloriafood' );
	$wpdb->insert( $tables['events'], array( 'event_key' => wp_generate_uuid4(), 'session_id' => $session_id, 'occurred_at' => $now, 'event_name' => 'lead_created', 'landing_id' => $landing_id, 'element_id' => 'wtg-demo-form', 'metadata' => wp_json_encode( array( 'lead_id' => $lead_id, 'landing_id' => $landing_id ) ) ) );
	if ( $newsletter ) { $wpdb->insert( $tables['events'], array( 'event_key' => wp_generate_uuid4(), 'session_id' => $session_id, 'occurred_at' => $now, 'event_name' => 'newsletter_signup', 'landing_id' => $landing_id, 'element_id' => 'wtg-demo-form' ) ); }
	$wpdb->query( $wpdb->prepare( "UPDATE {$tables['sessions']} SET event_count=event_count+%d WHERE id=%d", $newsletter ? 2 : 1, $session_id ) );
	return true;
}

function awd_lm_analytics_page_legacy() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', 403 ); }
	awd_lm_maybe_upgrade_analytics_tables();
	global $wpdb;
	$tables = awd_lm_analytics_tables();
	$days = isset( $_GET['days'] ) ? absint( $_GET['days'] ) : 30;
	if ( ! in_array( $days, array( 7, 30, 90, 365 ), true ) ) { $days = 30; }
	$since = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
	$sessions = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tables['sessions']} WHERE started_at >= %s", $since ) );
	$leads = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tables['sessions']} WHERE started_at >= %s AND lead_id > 0", $since ) );
	$events = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tables['events']} WHERE occurred_at >= %s", $since ) );
	$conversion = $sessions ? round( 100 * $leads / $sessions, 1 ) : 0;
	$sources = $wpdb->get_results( $wpdb->prepare( "SELECT IF(utm_source='',IF(referrer='','direct','referral'),utm_source) source, utm_medium medium, COUNT(*) sessions, SUM(lead_id>0) leads FROM {$tables['sessions']} WHERE started_at >= %s GROUP BY source,utm_medium ORDER BY sessions DESC LIMIT 15", $since ) );
	$campaigns = $wpdb->get_results( $wpdb->prepare( "SELECT utm_campaign campaign,utm_source source,COUNT(*) sessions,SUM(lead_id>0) leads FROM {$tables['sessions']} WHERE started_at >= %s AND utm_campaign<>'' GROUP BY utm_campaign,utm_source ORDER BY sessions DESC LIMIT 15", $since ) );
	$event_rows = $wpdb->get_results( $wpdb->prepare( "SELECT event_name,COUNT(*) total FROM {$tables['events']} WHERE occurred_at >= %s GROUP BY event_name ORDER BY total DESC", $since ) );
	$event_map = array(); foreach ( $event_rows as $row ) { $event_map[ $row->event_name ] = (int) $row->total; }
	$recent = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$tables['sessions']} WHERE started_at >= %s ORDER BY started_at DESC LIMIT 100", $since ) );
	$detail_id = isset( $_GET['session'] ) ? absint( $_GET['session'] ) : 0;
	$detail_session = $detail_id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tables['sessions']} WHERE id=%d", $detail_id ) ) : null;
	$detail_events = $detail_session ? $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$tables['events']} WHERE session_id=%d ORDER BY occurred_at ASC,id ASC LIMIT 500", $detail_id ) ) : array();
	$max_source = 1; foreach ( $sources as $row ) { $max_source = max( $max_source, (int) $row->sessions ); }
	?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Analytics</title><style>body{margin:28px;font:14px/1.45 system-ui;background:#f5f8f6;color:#182019}main{max-width:1440px;margin:auto}a{color:#087a4c;font-weight:750;text-decoration:none}.bar,.filters,.cards{display:flex;gap:12px;align-items:center;flex-wrap:wrap}.bar{justify-content:space-between}.filters a{padding:7px 10px;border-radius:7px;background:#e5eee8}.filters a.on{background:#087a4c;color:#fff}.cards{margin:22px 0}.metric{min-width:180px;flex:1;background:#fff;border:1px solid #d6e8dc;border-radius:14px;padding:18px}.metric strong{display:block;font-size:30px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}.panel{background:#fff;border:1px solid #d6e8dc;border-radius:14px;padding:20px;overflow:auto}.panel h2{margin-top:0}table{width:100%;border-collapse:collapse;font-size:13px}th,td{text-align:left;vertical-align:top;padding:9px;border-bottom:1px solid #e1ebe4}th{color:#52635a}.track{height:8px;background:#edf2ee;border-radius:9px;overflow:hidden;min-width:90px}.fill{height:100%;background:#cda574}.funnel{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}.funnel div{background:#edf6f0;padding:14px;border-radius:10px;text-align:center}.funnel strong{display:block;font-size:22px}.lead{color:#087a4c;font-weight:800}.muted{color:#65756c}.timeline td:first-child{white-space:nowrap}.badge{display:inline-block;background:#edf6f0;border-radius:999px;padding:4px 8px;font-weight:750}@media(max-width:800px){.grid{grid-template-columns:1fr}.funnel{grid-template-columns:1fr 1fr}}</style></head><body><main><div class="bar"><div><a href="<?php echo esc_url( admin_url( 'admin-post.php?action=awd_lm_visual_editor' ) ); ?>">← Editor</a><h1>Analytics</h1><p class="muted">Anonymous first-party sessions. No IP addresses or form field values are stored.</p></div><div class="filters"><?php foreach ( array( 7,30,90,365 ) as $range ) : ?><a class="<?php echo $days === $range ? 'on' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'action'=>'awd_lm_analytics','days'=>$range ), admin_url( 'admin-post.php' ) ) ); ?>"><?php echo esc_html( $range ); ?> days</a><?php endforeach; ?></div></div><div class="cards"><div class="metric"><span>Sessions</span><strong><?php echo esc_html( $sessions ); ?></strong></div><div class="metric"><span>Events</span><strong><?php echo esc_html( $events ); ?></strong></div><div class="metric"><span>Leads</span><strong><?php echo esc_html( $leads ); ?></strong></div><div class="metric"><span>Conversion</span><strong><?php echo esc_html( $conversion ); ?>%</strong></div></div><?php if ( $detail_session ) : ?><section class="panel" style="margin-bottom:20px"><div class="bar"><div><h2>Session timeline</h2><p class="muted"><?php echo esc_html( $detail_session->utm_source ?: ( $detail_session->referrer ? 'referral' : 'direct' ) ); ?> · <?php echo esc_html( $detail_session->utm_campaign ?: 'no campaign' ); ?> · <?php echo esc_html( strtoupper( $detail_session->language ) ); ?></p></div><a href="<?php echo esc_url( add_query_arg( array( 'action'=>'awd_lm_analytics','days'=>$days ), admin_url( 'admin-post.php' ) ) ); ?>">Close ×</a></div><table class="timeline"><thead><tr><th>Time</th><th>Event</th><th>Section / element</th><th>Label</th></tr></thead><tbody><?php foreach ( $detail_events as $event ) : ?><tr><td><?php echo esc_html( get_date_from_gmt( $event->occurred_at, 'Y-m-d H:i:s' ) ); ?></td><td><span class="badge"><?php echo esc_html( $event->event_name ); ?></span></td><td><?php echo esc_html( trim( $event->section_id . ' / ' . $event->element_id, ' /' ) ?: '—' ); ?></td><td><?php echo esc_html( $event->element_label ?: '—' ); ?></td></tr><?php endforeach; ?></tbody></table></section><?php endif; ?><div class="panel"><h2>Funnel</h2><div class="funnel"><?php foreach ( array( 'page_view'=>'Page views','cta_click'=>'CTA clicks','form_start'=>'Form starts','lead_created'=>'Leads' ) as $key=>$label ) : ?><div><span><?php echo esc_html( $label ); ?></span><strong><?php echo esc_html( $event_map[ $key ] ?? 0 ); ?></strong></div><?php endforeach; ?></div></div><div class="grid" style="margin-top:20px"><section class="panel"><h2>Sources</h2><table><thead><tr><th>Source / medium</th><th>Sessions</th><th>Leads</th><th></th></tr></thead><tbody><?php foreach ( $sources as $row ) : ?><tr><td><strong><?php echo esc_html( $row->source ); ?></strong><br><span class="muted"><?php echo esc_html( $row->medium ?: '—' ); ?></span></td><td><?php echo esc_html( $row->sessions ); ?></td><td><?php echo esc_html( $row->leads ); ?></td><td><div class="track"><div class="fill" style="width:<?php echo esc_attr( round( 100 * $row->sessions / $max_source ) ); ?>%"></div></div></td></tr><?php endforeach; ?><?php if ( ! $sources ) : ?><tr><td colspan="4">No sessions yet.</td></tr><?php endif; ?></tbody></table></section><section class="panel"><h2>Campaigns</h2><table><thead><tr><th>Campaign</th><th>Source</th><th>Sessions</th><th>Leads</th></tr></thead><tbody><?php foreach ( $campaigns as $row ) : ?><tr><td><strong><?php echo esc_html( $row->campaign ); ?></strong></td><td><?php echo esc_html( $row->source ); ?></td><td><?php echo esc_html( $row->sessions ); ?></td><td><?php echo esc_html( $row->leads ); ?></td></tr><?php endforeach; ?><?php if ( ! $campaigns ) : ?><tr><td colspan="4">No tagged campaigns yet.</td></tr><?php endif; ?></tbody></table></section></div><section class="panel" style="margin-top:20px"><h2>Recent sessions</h2><table><thead><tr><th>Started</th><th>Source</th><th>Campaign</th><th>Language / device</th><th>Events</th><th>Lead</th></tr></thead><tbody><?php foreach ( $recent as $row ) : ?><tr><td><a href="<?php echo esc_url( add_query_arg( array( 'action'=>'awd_lm_analytics','days'=>$days,'session'=>$row->id ), admin_url( 'admin-post.php' ) ) ); ?>"><?php echo esc_html( get_date_from_gmt( $row->started_at, 'Y-m-d H:i' ) ); ?></a></td><td><?php echo esc_html( $row->utm_source ?: ( $row->referrer ? 'referral' : 'direct' ) ); ?><br><span class="muted"><?php echo esc_html( $row->utm_medium ); ?></span></td><td><?php echo esc_html( $row->utm_campaign ?: '—' ); ?></td><td><?php echo esc_html( strtoupper( $row->language ) . ' · ' . ( $row->device_type ?: 'unknown' ) ); ?></td><td><?php echo esc_html( $row->event_count ); ?></td><td><?php if ( $row->lead_id ) : ?><span class="lead">#<?php echo esc_html( $row->lead_id ); ?></span><?php else : ?>—<?php endif; ?></td></tr><?php endforeach; ?><?php if ( ! $recent ) : ?><tr><td colspan="6">No sessions yet.</td></tr><?php endif; ?></tbody></table></section></main></body></html><?php exit;
}
