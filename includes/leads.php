<?php
defined( 'ABSPATH' ) || exit;

function awd_lm_lead_tables() {
	global $wpdb;
	return array(
		'leads' => $wpdb->prefix . 'awd_lm_leads',
		'consents' => $wpdb->prefix . 'awd_lm_consent_events',
		'queue' => $wpdb->prefix . 'awd_lm_sync_queue',
	);
}

function awd_lm_install_lead_tables() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$tables = awd_lm_lead_tables();
	$charset = $wpdb->get_charset_collate();
	dbDelta( "CREATE TABLE {$tables['leads']} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		name varchar(190) NOT NULL,
		first_name varchar(100) NOT NULL DEFAULT '',
		last_name varchar(100) NOT NULL DEFAULT '',
		business varchar(190) NOT NULL,
		email varchar(190) NOT NULL,
		phone varchar(80) NOT NULL DEFAULT '',
		system_code varchar(50) NOT NULL DEFAULT '',
		system_other varchar(190) NOT NULL DEFAULT '',
		interests longtext NULL,
		message text NULL,
		language varchar(5) NOT NULL DEFAULT 'de',
		source_url text NULL,
		newsletter_status varchar(30) NOT NULL DEFAULT 'not_requested',
		brevo_status varchar(30) NOT NULL DEFAULT 'not_requested',
		brevo_error text NULL,
		notification_status varchar(30) NOT NULL DEFAULT 'pending',
		notification_error text NULL,
		PRIMARY KEY  (id),
		KEY email (email),
		KEY created_at (created_at),
		KEY newsletter_status (newsletter_status)
	) $charset;" );
	dbDelta( "CREATE TABLE {$tables['consents']} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		lead_id bigint(20) unsigned NOT NULL,
		created_at datetime NOT NULL,
		event_type varchar(40) NOT NULL,
		consent_text text NULL,
		consent_version varchar(64) NOT NULL DEFAULT '',
		language varchar(5) NOT NULL DEFAULT 'de',
		source_url text NULL,
		PRIMARY KEY  (id),
		KEY lead_id (lead_id),
		KEY event_type (event_type)
	) $charset;" );
	dbDelta( "CREATE TABLE {$tables['queue']} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		lead_id bigint(20) unsigned NOT NULL,
		integration varchar(30) NOT NULL DEFAULT 'brevo',
		status varchar(20) NOT NULL DEFAULT 'pending',
		attempts smallint unsigned NOT NULL DEFAULT 0,
		next_attempt_at datetime NOT NULL,
		last_error text NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY lead_integration (lead_id,integration),
		KEY ready (integration,status,next_attempt_at)
	) $charset;" );
	update_option( 'awd_lm_lead_db_version', '1.2', false );
}

function awd_lm_maybe_upgrade_lead_tables() {
	if ( '1.2' !== get_option( 'awd_lm_lead_db_version' ) ) { awd_lm_install_lead_tables(); }
}

function awd_lm_brevo_settings() {
	$stored = get_option( 'awd_lm_brevo_settings', array() );
	return wp_parse_args( is_array( $stored ) ? $stored : array(), array(
		'enabled' => false, 'api_key' => '', 'list_id' => 0, 'sender_name' => '', 'sender_email' => '', 'tracking_consent' => false,
	) );
}

function awd_lm_brevo_ready() {
	$s = awd_lm_brevo_settings();
	return ! empty( $s['enabled'] ) && '' !== trim( (string) $s['api_key'] ) && absint( $s['list_id'] );
}

function awd_lm_newsletter_copy( $lang ) {
	$copy = array(
		'de' => 'Ich möchte den Newsletter erhalten. Diese Einwilligung kann ich jederzeit widerrufen.',
		'en' => 'I would like to receive the newsletter. I can withdraw this consent at any time.',
		'fr' => 'Je souhaite recevoir la newsletter. Je peux retirer ce consentement à tout moment.',
	);
	return $copy[ awd_get_current_language( $lang ) ];
}

function awd_lm_privacy_consent_copy( $lang ) {
	$copy = array(
		'de' => 'Ich stimme der Verarbeitung meiner Angaben zur Bearbeitung meiner Anfrage gemäß der Datenschutzerklärung zu.',
		'en' => 'I consent to the processing of my details to handle my request in accordance with the privacy policy.',
		'fr' => 'J’accepte le traitement de mes informations afin de répondre à ma demande conformément à la politique de confidentialité.',
	);
	return $copy[ awd_get_current_language( $lang ) ];
}

function awd_lm_newsletter_privacy_label( $lang ) {
	$labels = array( 'de' => 'Datenschutzerklärung', 'en' => 'Privacy policy', 'fr' => 'Politique de confidentialité' );
	return $labels[ awd_get_current_language( $lang ) ];
}

function awd_lm_store_lead( $data ) {
	global $wpdb;
	awd_lm_maybe_upgrade_lead_tables();
	$tables = awd_lm_lead_tables();
	$now = current_time( 'mysql', true );
	$newsletter = ! empty( $data['newsletter'] );
	$wpdb->query( 'START TRANSACTION' );
	$inserted = $wpdb->insert( $tables['leads'], array(
		'created_at' => $now, 'updated_at' => $now, 'name' => $data['name'], 'first_name' => $data['first_name'], 'last_name' => $data['last_name'], 'business' => $data['business'],
		'email' => $data['email'], 'phone' => $data['phone'], 'system_code' => $data['system'], 'system_other' => $data['system_other'],
		'interests' => wp_json_encode( array_keys( $data['interest'] ) ), 'message' => $data['message'], 'language' => $data['lang'],
		'source_url' => esc_url_raw( $data['source_url'] ), 'newsletter_status' => $newsletter ? 'requested' : 'not_requested',
		'brevo_status' => $newsletter ? 'pending' : 'not_requested', 'notification_status' => 'pending', 'notification_error' => '',
	) );
	if ( false === $inserted ) { $wpdb->query( 'ROLLBACK' ); return new WP_Error( 'lead_storage_failed', 'The request could not be stored.' ); }
	$lead_id = (int) $wpdb->insert_id;
	$privacy_text = awd_lm_privacy_consent_copy( $data['lang'] ) . ' URL: ' . esc_url_raw( $data['privacy_url'] ?? '' );
	$privacy_ok = $wpdb->insert( $tables['consents'], array( 'lead_id' => $lead_id, 'created_at' => $now, 'event_type' => 'privacy_accepted', 'consent_text' => $privacy_text, 'consent_version' => hash( 'sha256', $privacy_text ), 'language' => $data['lang'], 'source_url' => esc_url_raw( $data['source_url'] ) ) );
	if ( false === $privacy_ok ) { $wpdb->query( 'ROLLBACK' ); return new WP_Error( 'privacy_storage_failed', 'The privacy consent could not be stored.' ); }
	if ( $newsletter ) {
		$text = awd_lm_newsletter_copy( $data['lang'] );
		$consent_ok = $wpdb->insert( $tables['consents'], array( 'lead_id' => $lead_id, 'created_at' => $now, 'event_type' => 'newsletter_requested', 'consent_text' => $text, 'consent_version' => hash( 'sha256', $text ), 'language' => $data['lang'], 'source_url' => esc_url_raw( $data['source_url'] ) ) );
		$queue_ok = $wpdb->replace( $tables['queue'], array( 'lead_id' => $lead_id, 'integration' => 'brevo', 'status' => 'pending', 'attempts' => 0, 'next_attempt_at' => $now, 'last_error' => '', 'created_at' => $now, 'updated_at' => $now ) );
		if ( false === $consent_ok || false === $queue_ok ) { $wpdb->query( 'ROLLBACK' ); return new WP_Error( 'consent_storage_failed', 'The newsletter consent could not be stored.' ); }
		if ( ! wp_next_scheduled( 'awd_lm_process_brevo_queue_once' ) ) { wp_schedule_single_event( time() + 5, 'awd_lm_process_brevo_queue_once' ); }
	}
	$wpdb->query( 'COMMIT' );
	return $lead_id;
}

function awd_lm_set_notification_status( $lead_id, $status, $error = '' ) {
	global $wpdb;
	$tables = awd_lm_lead_tables();
	return $wpdb->update( $tables['leads'], array(
		'notification_status' => sanitize_key( $status ),
		'notification_error' => sanitize_text_field( $error ),
		'updated_at' => current_time( 'mysql', true ),
	), array( 'id' => absint( $lead_id ) ) );
}

function awd_lm_schedule_brevo_queue() {
	if ( ! wp_next_scheduled( 'awd_lm_process_brevo_queue' ) ) { wp_schedule_event( time() + 300, 'hourly', 'awd_lm_process_brevo_queue' ); }
}

function awd_lm_reset_brevo_queue_for_single_optin() {
	global $wpdb;
	$tables = awd_lm_lead_tables();
	$now = current_time( 'mysql', true );
	$wpdb->query( $wpdb->prepare( "UPDATE {$tables['queue']} SET status='pending', attempts=0, next_attempt_at=%s, last_error='', updated_at=%s WHERE integration='brevo'", $now, $now ) );
	$wpdb->query( "UPDATE {$tables['leads']} SET brevo_status='pending', brevo_error='' WHERE newsletter_status IN ('requested','subscribed') AND brevo_status IN ('pending','failed','doi_sent','synced')" );
	if ( ! wp_next_scheduled( 'awd_lm_process_brevo_queue_once' ) ) { wp_schedule_single_event( time() + 5, 'awd_lm_process_brevo_queue_once' ); }
}

function awd_lm_maybe_upgrade_brevo_contact_payload() {
	if ( '1.2' === get_option( 'awd_lm_brevo_contact_payload_version' ) ) { return; }
	update_option( 'awd_lm_brevo_contact_payload_version', '1.2', false );
	if ( awd_lm_brevo_ready() ) { awd_lm_reset_brevo_queue_for_single_optin(); }
}

function awd_lm_brevo_contact_attributes( $first_name, $last_name, $phone, $legacy_full_name = '' ) {
	$attributes = array();
	$first_name = trim( (string) $first_name );
	$last_name = trim( (string) $last_name );
	/* Existing queued leads predate separate columns. Preserve their retry path
	 * while all new submissions use the explicit, unambiguous values. */
	if ( '' === $first_name && '' !== trim( (string) $legacy_full_name ) ) {
		$name_parts = preg_split( '/\s+/u', trim( (string) $legacy_full_name ), -1, PREG_SPLIT_NO_EMPTY );
		$first_name = (string) array_shift( $name_parts );
		if ( '' === $last_name && ! empty( $name_parts ) ) { $last_name = implode( ' ', $name_parts ); }
	}
	if ( '' !== $first_name ) { $attributes['FIRSTNAME'] = $first_name; }
	if ( '' !== $last_name ) { $attributes['LASTNAME'] = $last_name; }
	$phone = preg_replace( '/[^0-9+]/', '', (string) $phone );
	if ( 0 === strpos( $phone, '00' ) ) { $phone = '+' . substr( $phone, 2 ); }
	if ( preg_match( '/^\+[1-9][0-9]{6,14}$/', $phone ) ) { $attributes['SMS'] = $phone; }
	return $attributes;
}

function awd_lm_process_brevo_queue( $only_lead_id = 0 ) {
	global $wpdb;
	if ( ! awd_lm_brevo_ready() ) { return; }
	$tables = awd_lm_lead_tables();
	$now = current_time( 'mysql', true );
	$stale = gmdate( 'Y-m-d H:i:s', time() - 30 * MINUTE_IN_SECONDS );
	$wpdb->query( $wpdb->prepare( "UPDATE {$tables['queue']} SET status='failed', last_error='Interrupted sync; retrying', next_attempt_at=%s WHERE status='processing' AND updated_at < %s", $now, $stale ) );
	$lead_filter = $only_lead_id ? $wpdb->prepare( ' AND q.lead_id=%d', absint( $only_lead_id ) ) : '';
	$jobs = $wpdb->get_results( $wpdb->prepare( "SELECT q.*, l.email, l.name, l.first_name, l.last_name, l.phone, l.business, l.language FROM {$tables['queue']} q INNER JOIN {$tables['leads']} l ON l.id=q.lead_id WHERE q.integration='brevo' AND q.status IN ('pending','failed') AND q.next_attempt_at <= %s AND q.attempts < 8{$lead_filter} ORDER BY q.id ASC LIMIT 10", $now ) );
	$s = awd_lm_brevo_settings();
	$request_contact = function ( $payload ) use ( $s ) {
		return wp_remote_post( 'https://api.brevo.com/v3/contacts', array( 'timeout' => 10, 'headers' => array( 'accept' => 'application/json', 'api-key' => $s['api_key'], 'content-type' => 'application/json' ), 'body' => wp_json_encode( $payload ) ) );
	};
	foreach ( $jobs as $job ) {
		$claimed = $wpdb->query( $wpdb->prepare( "UPDATE {$tables['queue']} SET status='processing', updated_at=%s WHERE id=%d AND status IN ('pending','failed')", $now, $job->id ) );
		if ( 1 !== $claimed ) { continue; }
		$payload = array( 'email' => $job->email, 'listIds' => array( absint( $s['list_id'] ) ), 'updateEnabled' => true );
		$attributes = awd_lm_brevo_contact_attributes( $job->first_name, $job->last_name, $job->phone, $job->name );
		$phone = $attributes['SMS'] ?? '';
		unset( $attributes['SMS'] );
		if ( ! empty( $attributes ) ) { $payload['attributes'] = $attributes; }
		$response = $request_contact( $payload );
		$code = is_wp_error( $response ) ? 0 : wp_remote_retrieve_response_code( $response );
		$phone_warning = '';
		if ( in_array( $code, array( 200, 201, 204 ), true ) && $phone ) {
			$phone_response = wp_remote_request( 'https://api.brevo.com/v3/contacts/' . rawurlencode( $job->email ), array( 'method' => 'PUT', 'timeout' => 10, 'headers' => array( 'accept' => 'application/json', 'api-key' => $s['api_key'], 'content-type' => 'application/json' ), 'body' => wp_json_encode( array( 'attributes' => array( 'SMS' => $phone ) ) ) ) );
			$phone_code = is_wp_error( $phone_response ) ? 0 : wp_remote_retrieve_response_code( $phone_response );
			if ( ! in_array( $phone_code, array( 200, 201, 204 ), true ) ) {
				$phone_error = is_wp_error( $phone_response ) ? $phone_response->get_error_message() : wp_strip_all_tags( wp_remote_retrieve_body( $phone_response ) );
				$phone_warning = 'Contact synced without phone: ' . $phone_error;
			}
		}
		if ( in_array( $code, array( 200, 201, 204 ), true ) ) {
			$sync_status = $phone_warning ? 'synced_phone_skipped' : 'synced';
			$wpdb->update( $tables['queue'], array( 'status' => 'synced', 'attempts' => (int) $job->attempts + 1, 'last_error' => substr( $phone_warning, 0, 2000 ), 'updated_at' => $now ), array( 'id' => $job->id ) );
			$wpdb->update( $tables['leads'], array( 'newsletter_status' => 'subscribed', 'brevo_status' => $sync_status, 'brevo_error' => substr( $phone_warning, 0, 2000 ), 'updated_at' => $now ), array( 'id' => $job->lead_id ) );
		} else {
			$error = is_wp_error( $response ) ? $response->get_error_message() : wp_strip_all_tags( wp_remote_retrieve_body( $response ) );
			$attempts = (int) $job->attempts + 1;
			if ( in_array( $code, array( 400, 401, 403, 404 ), true ) ) { $attempts = 8; }
			$next = gmdate( 'Y-m-d H:i:s', time() + min( DAY_IN_SECONDS, HOUR_IN_SECONDS * max( 1, $attempts ) ) );
			$wpdb->update( $tables['queue'], array( 'status' => 'failed', 'attempts' => $attempts, 'next_attempt_at' => $next, 'last_error' => substr( $error, 0, 2000 ), 'updated_at' => $now ), array( 'id' => $job->id ) );
			$wpdb->update( $tables['leads'], array( 'brevo_status' => 'failed', 'brevo_error' => substr( $error, 0, 2000 ), 'updated_at' => $now ), array( 'id' => $job->lead_id ) );
		}
	}
}

function awd_lm_leads_page() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', 403 ); }
	global $wpdb;
	$tables = awd_lm_lead_tables();
	$analytics = awd_lm_analytics_tables();
	$rows = $wpdb->get_results( "SELECT l.*,a.id analytics_session_id,a.utm_source analytics_source,a.utm_medium analytics_medium,a.utm_campaign analytics_campaign FROM {$tables['leads']} l LEFT JOIN {$analytics['sessions']} a ON a.lead_id=l.id ORDER BY l.created_at DESC LIMIT 500" );
	$export = wp_nonce_url( admin_url( 'admin-post.php?action=awd_lm_export_leads' ), 'awd_lm_export_leads' );
	?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Leads</title><style>body{margin:28px;font:14px/1.45 system-ui;background:#f5f8f6;color:#182019}main{max-width:1500px;margin:auto}.bar,.actions{display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap}.actions{justify-content:flex-start}a{color:#087a4c;font-weight:700;text-decoration:none}.button,button{display:inline-block;padding:9px 12px;border:0;background:#008b55;color:#fff;border-radius:7px;font-weight:700;cursor:pointer}.secondary{background:#506159}.icon{font-size:18px;line-height:1;padding:8px 10px}.danger{background:#8b3028}table{width:100%;margin-top:20px;border-collapse:collapse;background:#fff;font-size:13px}th,td{text-align:left;vertical-align:top;padding:10px;border:1px solid #d6e8dc}th{background:#e8f7ed}small{color:#63746a}.status{font-weight:750}form{margin:0}dialog{width:min(680px,calc(100% - 32px));border:1px solid #bcdac7;border-radius:14px;padding:0;box-shadow:0 24px 70px #10251f55}dialog::backdrop{background:#10251f88}.modal{padding:22px}.modal-head{display:flex;justify-content:space-between;align-items:center}.grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.grid .wide{grid-column:1/-1}label{display:block;font-weight:750;margin:10px 0 4px}input{box-sizing:border-box;width:100%;padding:10px;border:1px solid #bcdac7;border-radius:7px;font:inherit}@media(max-width:760px){body{margin:12px}.grid{grid-template-columns:1fr}.grid .wide{grid-column:auto}.table-wrap{overflow:auto}}</style></head><body><main><div class="bar"><div><a href="<?php echo esc_url( admin_url( 'admin-post.php?action=awd_lm_visual_editor' ) ); ?>">← Editor</a> · <a href="<?php echo esc_url( admin_url( 'admin-post.php?action=awd_lm_analytics' ) ); ?>">Analytics</a><h1>Leads / Anfragen</h1><small>Newest 500 requests. CSV export contains all requests.</small></div><a class="button" href="<?php echo esc_url( $export ); ?>">Export CSV</a></div><div class="table-wrap"><table><thead><tr><th>Date</th><th>Source</th><th>Contact</th><th>Business</th><th>Request</th><th>Newsletter</th><th>Brevo</th><th>Email notification</th><th>Actions</th></tr></thead><tbody><?php foreach ( $rows as $row ) : $interests = json_decode( $row->interests, true ); ?><tr><td><?php echo esc_html( get_date_from_gmt( $row->created_at, 'Y-m-d H:i' ) ); ?><br><small><?php echo esc_html( strtoupper( $row->language ) ); ?></small></td><td><?php if ( $row->analytics_session_id ) : ?><a href="<?php echo esc_url( add_query_arg( array( 'action'=>'awd_lm_analytics','session'=>$row->analytics_session_id ), admin_url( 'admin-post.php' ) ) ); ?>"><?php echo esc_html( $row->analytics_source ?: 'direct' ); ?></a><br><small><?php echo esc_html( trim( $row->analytics_medium . ' · ' . $row->analytics_campaign, ' ·' ) ?: 'untagged' ); ?></small><?php else : ?>—<?php endif; ?></td><td><strong><?php echo esc_html( $row->name ); ?></strong><br><a href="mailto:<?php echo esc_attr( $row->email ); ?>"><?php echo esc_html( $row->email ); ?></a><br><?php echo esc_html( $row->phone ); ?></td><td><?php echo esc_html( $row->business ); ?><br><small><?php echo esc_html( $row->system_code . ( $row->system_other ? ': ' . $row->system_other : '' ) ); ?></small></td><td><?php echo esc_html( implode( ', ', is_array( $interests ) ? $interests : array() ) ); ?><br><small><?php echo esc_html( $row->message ); ?></small></td><td class="status"><?php echo esc_html( $row->newsletter_status ); ?></td><td class="status"><?php echo esc_html( $row->brevo_status ); ?><?php if ( $row->brevo_error ) : ?><br><small><?php echo esc_html( $row->brevo_error ); ?></small><?php endif; ?></td><td class="status"><?php echo esc_html( $row->notification_status ); ?><?php if ( $row->notification_error ) : ?><br><small><?php echo esc_html( $row->notification_error ); ?></small><?php endif; ?></td><td><?php if ( 'anonymized' !== $row->newsletter_status ) : ?><div class="actions"><button type="button" class="secondary icon edit-lead" title="Edit contact" aria-label="Edit contact" data-id="<?php echo esc_attr( $row->id ); ?>" data-first="<?php echo esc_attr( $row->first_name ); ?>" data-last="<?php echo esc_attr( $row->last_name ); ?>" data-business="<?php echo esc_attr( $row->business ); ?>" data-email="<?php echo esc_attr( $row->email ); ?>" data-phone="<?php echo esc_attr( $row->phone ); ?>">✎</button><?php if ( in_array( $row->newsletter_status, array( 'requested', 'subscribed' ), true ) ) : ?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="awd_lm_sync_lead_brevo"><input type="hidden" name="lead_id" value="<?php echo esc_attr( $row->id ); ?>"><?php wp_nonce_field( 'awd_lm_sync_lead_brevo_' . $row->id ); ?><button type="submit">Sync</button></form><?php endif; ?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Anonymize this lead? This cannot be undone.')"><input type="hidden" name="action" value="awd_lm_anonymize_lead"><input type="hidden" name="lead_id" value="<?php echo esc_attr( $row->id ); ?>"><?php wp_nonce_field( 'awd_lm_anonymize_lead_' . $row->id ); ?><button class="danger" type="submit">Anonymize</button></form></div><?php endif; ?></td></tr><?php endforeach; ?><?php if ( empty( $rows ) ) : ?><tr><td colspan="9">No requests yet.</td></tr><?php endif; ?></tbody></table></div><dialog id="editLead"><form class="modal" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="awd_lm_update_lead"><input type="hidden" name="lead_id" id="editLeadId"><?php wp_nonce_field( 'awd_lm_update_lead', 'awd_lm_update_lead_nonce' ); ?><div class="modal-head"><h2>Edit contact</h2><button type="button" class="secondary" id="closeEdit">×</button></div><div class="grid"><div><label for="editFirst">First name</label><input id="editFirst" name="first_name" maxlength="100"></div><div><label for="editLast">Last name</label><input id="editLast" name="last_name" maxlength="100"></div><div class="wide"><label for="editBusiness">Company / business</label><input id="editBusiness" name="business" maxlength="190"></div><div><label for="editEmail">Email</label><input id="editEmail" name="email" type="email" required maxlength="190"></div><div><label for="editPhone">Phone (international format)</label><input id="editPhone" name="phone" maxlength="40" placeholder="+41 79 123 45 67"></div></div><p><small>Saving updates the local lead. Use Sync afterwards to send the corrected data to Brevo.</small></p><button type="submit">Save changes</button></form></dialog></main><script>const d=document.getElementById('editLead');document.querySelectorAll('.edit-lead').forEach(b=>b.addEventListener('click',()=>{document.getElementById('editLeadId').value=b.dataset.id;document.getElementById('editFirst').value=b.dataset.first;document.getElementById('editLast').value=b.dataset.last;document.getElementById('editBusiness').value=b.dataset.business;document.getElementById('editEmail').value=b.dataset.email;document.getElementById('editPhone').value=b.dataset.phone;d.showModal()}));document.getElementById('closeEdit').onclick=()=>d.close();d.addEventListener('click',e=>{if(e.target===d)d.close()});</script></body></html><?php exit;
}

function awd_lm_export_leads() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', 403 ); }
	check_admin_referer( 'awd_lm_export_leads' );
	global $wpdb;
	$tables = awd_lm_lead_tables();
	$rows = $wpdb->get_results( "SELECT * FROM {$tables['leads']} ORDER BY created_at DESC", ARRAY_A );
	nocache_headers(); header( 'Content-Type: text/csv; charset=UTF-8' ); header( 'Content-Disposition: attachment; filename="awd-leads-' . gmdate( 'Y-m-d' ) . '.csv"' );
	$out = fopen( 'php://output', 'w' ); fwrite( $out, "\xEF\xBB\xBF" );
	$columns = array( 'id','created_at','name','first_name','last_name','business','email','phone','system_code','system_other','interests','message','language','source_url','newsletter_status','brevo_status','brevo_error','notification_status','notification_error' );
	$csv_safe = function ( $value ) { $value = (string) $value; return preg_match( '/^[\x00-\x20]*[=+\-@]/', $value ) ? "'" . $value : $value; };
	fputcsv( $out, $columns, ';' ); foreach ( $rows as $row ) { fputcsv( $out, array_map( function ( $key ) use ( $row, $csv_safe ) { return $csv_safe( $row[ $key ] ?? '' ); }, $columns ), ';' ); } fclose( $out ); exit;
}

function awd_lm_retry_brevo() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', 403 ); }
	check_admin_referer( 'awd_lm_retry_brevo' ); awd_lm_process_brevo_queue();
	wp_safe_redirect( admin_url( 'admin-post.php?action=awd_lm_leads' ) ); exit;
}

function awd_lm_update_lead() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', 403 ); }
	check_admin_referer( 'awd_lm_update_lead', 'awd_lm_update_lead_nonce' );
	$lead_id = absint( $_POST['lead_id'] ?? 0 );
	$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
	if ( ! $lead_id || ! is_email( $email ) ) { wp_die( 'A valid lead and email are required.', 400 ); }
	global $wpdb;
	$tables = awd_lm_lead_tables();
	$lead = $wpdb->get_row( $wpdb->prepare( "SELECT id,newsletter_status FROM {$tables['leads']} WHERE id=%d", $lead_id ) );
	if ( ! $lead || 'anonymized' === $lead->newsletter_status ) { wp_die( 'Lead not found.', 404 ); }
	$first = sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) );
	$last = sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) );
	$name = trim( $first . ' ' . $last );
	$wpdb->update( $tables['leads'], array(
		'first_name' => $first, 'last_name' => $last, 'name' => $name,
		'business' => sanitize_text_field( wp_unslash( $_POST['business'] ?? '' ) ),
		'email' => $email, 'phone' => sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) ),
		'brevo_status' => in_array( $lead->newsletter_status, array( 'requested', 'subscribed' ), true ) ? 'pending' : 'not_requested',
		'brevo_error' => '', 'updated_at' => current_time( 'mysql', true ),
	), array( 'id' => $lead_id ) );
	wp_safe_redirect( admin_url( 'admin-post.php?action=awd_lm_leads' ) ); exit;
}

function awd_lm_sync_lead_brevo() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', 403 ); }
	$lead_id = absint( $_POST['lead_id'] ?? 0 );
	check_admin_referer( 'awd_lm_sync_lead_brevo_' . $lead_id );
	if ( ! $lead_id || ! awd_lm_brevo_ready() ) { wp_die( 'Lead or Brevo configuration is missing.', 400 ); }
	global $wpdb;
	$tables = awd_lm_lead_tables();
	$lead = $wpdb->get_row( $wpdb->prepare( "SELECT newsletter_status FROM {$tables['leads']} WHERE id=%d", $lead_id ) );
	if ( ! $lead || ! in_array( $lead->newsletter_status, array( 'requested', 'subscribed' ), true ) ) { wp_die( 'This contact has not requested the newsletter.', 400 ); }
	$now = current_time( 'mysql', true );
	$queue_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$tables['queue']} WHERE lead_id=%d AND integration='brevo'", $lead_id ) );
	if ( $queue_id ) {
		$wpdb->update( $tables['queue'], array( 'status'=>'pending', 'attempts'=>0, 'next_attempt_at'=>$now, 'last_error'=>'', 'updated_at'=>$now ), array( 'id'=>$queue_id ) );
	} else {
		$wpdb->insert( $tables['queue'], array( 'lead_id'=>$lead_id, 'integration'=>'brevo', 'status'=>'pending', 'attempts'=>0, 'next_attempt_at'=>$now, 'last_error'=>'', 'created_at'=>$now, 'updated_at'=>$now ) );
	}
	$wpdb->update( $tables['leads'], array( 'brevo_status'=>'pending', 'brevo_error'=>'', 'updated_at'=>$now ), array( 'id'=>$lead_id ) );
	awd_lm_process_brevo_queue( $lead_id );
	wp_safe_redirect( admin_url( 'admin-post.php?action=awd_lm_leads' ) ); exit;
}

function awd_lm_anonymize_lead() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', 403 ); }
	$lead_id = absint( $_POST['lead_id'] ?? 0 );
	check_admin_referer( 'awd_lm_anonymize_lead_' . $lead_id );
	if ( ! $lead_id ) { wp_die( 'Lead not found.', 404 ); }
	global $wpdb;
	$tables = awd_lm_lead_tables();
	$wpdb->delete( $tables['queue'], array( 'lead_id' => $lead_id ) );
	$wpdb->update( $tables['leads'], array(
		'name' => 'Anonymized', 'first_name' => '', 'last_name' => '', 'business' => '', 'email' => 'deleted+' . $lead_id . '@invalid.local', 'phone' => '',
		'system_other' => '', 'interests' => '[]', 'message' => '', 'source_url' => '', 'newsletter_status' => 'anonymized',
		'brevo_status' => 'anonymized', 'brevo_error' => '', 'updated_at' => current_time( 'mysql', true ),
	), array( 'id' => $lead_id ) );
	$wpdb->update( $tables['consents'], array( 'source_url' => '' ), array( 'lead_id' => $lead_id ) );
	if ( function_exists( 'awd_lm_analytics_tables' ) ) {
		$analytics_tables = awd_lm_analytics_tables();
		$wpdb->update( $analytics_tables['sessions'], array( 'lead_id' => 0 ), array( 'lead_id' => $lead_id ) );
	}
	wp_safe_redirect( admin_url( 'admin-post.php?action=awd_lm_leads' ) ); exit;
}
