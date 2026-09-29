<?php
defined( 'ABSPATH' ) || exit;

function awd_lm_email_tables() {
	global $wpdb;
	return array(
		'campaigns' => $wpdb->prefix . 'awd_lm_email_campaigns',
		'lists'     => $wpdb->prefix . 'awd_lm_email_lists',
		'members'   => $wpdb->prefix . 'awd_lm_email_list_members',
		'emails'    => $wpdb->prefix . 'awd_lm_emails',
		'sends'     => $wpdb->prefix . 'awd_lm_email_sends',
		'events'    => $wpdb->prefix . 'awd_lm_email_events',
		'clicks'    => $wpdb->prefix . 'awd_lm_email_click_tokens',
	);
}

function awd_lm_install_email_tables() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$t = awd_lm_email_tables();
	$c = $wpdb->get_charset_collate();
	dbDelta( "CREATE TABLE {$t['campaigns']} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		name varchar(190) NOT NULL,
		slug varchar(190) NOT NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id), UNIQUE KEY slug (slug)
	) $c;" );
	dbDelta( "CREATE TABLE {$t['lists']} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		name varchar(190) NOT NULL,
		slug varchar(190) NOT NULL,
		utm_source varchar(100) NOT NULL DEFAULT 'brevo',
		utm_medium varchar(100) NOT NULL DEFAULT 'email',
		utm_campaign varchar(190) NOT NULL,
		utm_id varchar(190) NOT NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id), UNIQUE KEY slug (slug), UNIQUE KEY utm_id (utm_id)
	) $c;" );
	dbDelta( "CREATE TABLE {$t['members']} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		list_id bigint(20) unsigned NOT NULL,
		lead_id bigint(20) unsigned NOT NULL DEFAULT 0,
		salutation varchar(10) NOT NULL DEFAULT '',
		first_name varchar(100) NOT NULL DEFAULT '',
		last_name varchar(100) NOT NULL DEFAULT '',
		company varchar(190) NOT NULL DEFAULT '',
		email varchar(190) NOT NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id), UNIQUE KEY list_email (list_id,email), KEY list_id (list_id), KEY lead_id (lead_id)
	) $c;" );
	dbDelta( "CREATE TABLE {$t['emails']} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		campaign_id bigint(20) unsigned NOT NULL,
		list_id bigint(20) unsigned NOT NULL DEFAULT 0,
		audience_type varchar(20) NOT NULL DEFAULT 'standalone',
		standalone_salutation varchar(10) NOT NULL DEFAULT '',
		standalone_first_name varchar(100) NOT NULL DEFAULT '',
		standalone_last_name varchar(100) NOT NULL DEFAULT '',
		standalone_company varchar(190) NOT NULL DEFAULT '',
		standalone_email varchar(190) NOT NULL DEFAULT '',
		name varchar(190) NOT NULL,
		slug varchar(190) NOT NULL,
		subject varchar(255) NOT NULL,
		preheader varchar(255) NOT NULL DEFAULT '',
		body_text longtext NULL,
		cta_links longtext NULL,
		status varchar(30) NOT NULL DEFAULT 'draft',
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id), KEY campaign_id (campaign_id), KEY list_id (list_id), KEY status (status)
	) $c;" );
	dbDelta( "CREATE TABLE {$t['sends']} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		email_id bigint(20) unsigned NOT NULL,
		lead_id bigint(20) unsigned NOT NULL DEFAULT 0,
		recipient_email varchar(190) NOT NULL,
		brevo_message_id varchar(255) NOT NULL DEFAULT '',
		status varchar(40) NOT NULL DEFAULT 'queued',
		sent_at datetime NULL,
		updated_at datetime NOT NULL,
		last_error text NULL,
		PRIMARY KEY  (id), KEY email_id (email_id), KEY lead_id (lead_id), KEY message_id (brevo_message_id(100)), KEY status (status)
	) $c;" );
	dbDelta( "CREATE TABLE {$t['events']} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		event_key varchar(190) NOT NULL,
		send_id bigint(20) unsigned NOT NULL DEFAULT 0,
		event_type varchar(50) NOT NULL,
		occurred_at datetime NOT NULL,
		clicked_url text NULL,
		metadata longtext NULL,
		PRIMARY KEY  (id), UNIQUE KEY event_key (event_key), KEY send_time (send_id,occurred_at), KEY type_time (event_type,occurred_at)
	) $c;" );
	dbDelta( "CREATE TABLE {$t['clicks']} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		send_id bigint(20) unsigned NOT NULL,
		cta_slug varchar(190) NOT NULL,
		token_hash char(64) NOT NULL,
		created_at datetime NOT NULL,
		first_landed_at datetime NULL,
		PRIMARY KEY  (id), UNIQUE KEY token_hash (token_hash), KEY send_cta (send_id,cta_slug)
	) $c;" );
	update_option( 'awd_lm_email_db_version', '1.3', false );
}

function awd_lm_maybe_upgrade_email_tables() {
	if ( '1.3' !== get_option( 'awd_lm_email_db_version' ) ) { awd_lm_install_email_tables(); }
}

function awd_lm_email_webhook_token() {
	$token = (string) get_option( 'awd_lm_email_webhook_token', '' );
	if ( strlen( $token ) < 32 ) { $token = wp_generate_password( 48, false, false ); update_option( 'awd_lm_email_webhook_token', $token, false ); }
	return $token;
}

function awd_lm_email_message_id( $value ) {
	$value = preg_replace( '/[\x00-\x1F\x7F]/', '', (string) $value );
	return substr( trim( $value, " <>\t\r\n" ), 0, 255 );
}

function awd_lm_email_preview_recipients() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'Forbidden' ), 403 ); }
	check_ajax_referer( 'awd_lm_email_preview_recipients', 'nonce' );
	global $wpdb;
	$t = awd_lm_email_tables();
	$list_id = absint( $_GET['list_id'] ?? 0 );
	if ( ! $list_id ) { wp_send_json_success( array( 'recipients' => array() ) ); }
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id,salutation,first_name,last_name,company,email FROM {$t['members']} WHERE list_id=%d AND email<>'' ORDER BY id LIMIT 1000", $list_id ), ARRAY_A );
	wp_send_json_success( array( 'recipients' => $rows ?: array() ) );
}

function awd_lm_email_unsubscribe_token( $email ) {
	$email = strtolower( sanitize_email( $email ) );
	$data = rtrim( strtr( base64_encode( $email ), '+/', '-_' ), '=' );
	return $data . '.' . hash_hmac( 'sha256', $data, wp_salt( 'auth' ) );
}

function awd_lm_email_from_unsubscribe_token( $token ) {
	$parts = explode( '.', sanitize_text_field( (string) $token ), 2 );
	if ( 2 !== count( $parts ) || ! hash_equals( hash_hmac( 'sha256', $parts[0], wp_salt( 'auth' ) ), $parts[1] ) ) { return ''; }
	$decoded = base64_decode( strtr( $parts[0], '-_', '+/' ) . str_repeat( '=', ( 4 - strlen( $parts[0] ) % 4 ) % 4 ), true );
	return is_email( $decoded ) ? strtolower( $decoded ) : '';
}

function awd_lm_email_merge_tags( $text, $recipient ) {
	$values = array(
		'{{SALUTATION}}' => (string) ( $recipient->salutation ?? '' ),
		'{{FIRSTNAME}}' => (string) ( $recipient->first_name ?? '' ),
		'{{LASTNAME}}'  => (string) ( $recipient->last_name ?? '' ),
		'{{COMPANY}}'   => (string) ( $recipient->company ?? $recipient->business ?? '' ),
		'{{EMAIL}}'     => (string) ( $recipient->email ?? '' ),
	);
	return strtr( (string) $text, $values );
}

function awd_lm_email_utm_url( $url, $email, $list, $cta_slug ) {
	$source = $list ? $list->utm_source : 'brevo';
	$medium = $list ? $list->utm_medium : 'email';
	$campaign = $list ? $list->utm_campaign : 'standalone-' . $email->slug;
	$utm_id = $list ? $list->utm_id : 'awd-standalone-email-' . absint( $email->id );
	return add_query_arg( array( 'utm_source'=>$source, 'utm_medium'=>$medium, 'utm_campaign'=>$campaign, 'utm_content'=>$cta_slug, 'utm_term'=>$email->slug, 'utm_id'=>$utm_id ), esc_url_raw( $url ) );
}

function awd_lm_email_click_token_create( $send_id, $cta_slug ) {
	global $wpdb;
	$t = awd_lm_email_tables();
	$send_id = absint( $send_id );
	$cta_slug = substr( sanitize_title( $cta_slug ), 0, 190 );
	if ( ! $send_id || '' === $cta_slug ) { return ''; }
	for ( $attempt = 0; $attempt < 3; $attempt++ ) {
		$token = rtrim( strtr( base64_encode( random_bytes( 24 ) ), '+/', '-_' ), '=' );
		$inserted = $wpdb->insert( $t['clicks'], array( 'send_id'=>$send_id, 'cta_slug'=>$cta_slug, 'token_hash'=>hash( 'sha256', $token ), 'created_at'=>current_time( 'mysql', true ) ) );
		if ( false !== $inserted ) { return $token; }
	}
	return '';
}

function awd_lm_email_click_resolve( $token ) {
	global $wpdb;
	$token = sanitize_text_field( (string) $token );
	if ( ! preg_match( '/^[A-Za-z0-9_-]{24,64}$/', $token ) ) { return 0; }
	$t = awd_lm_email_tables();
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT id,send_id FROM {$t['clicks']} WHERE token_hash=%s", hash( 'sha256', $token ) ) );
	if ( ! $row ) { return 0; }
	if ( function_exists( 'awd_lm_email_note_landing' ) ) { awd_lm_email_note_landing( $row->id ); }
	return (int) $row->send_id;
}

function awd_lm_email_note_landing( $click_id ) {
	global $wpdb;
	$t = awd_lm_email_tables();
	$wpdb->query( $wpdb->prepare( "UPDATE {$t['clicks']} SET first_landed_at=COALESCE(first_landed_at,%s) WHERE id=%d", current_time( 'mysql', true ), absint( $click_id ) ) );
}

function awd_lm_email_html( $email, $list, $recipient, $ctas, $tracked_urls = array() ) {
	$buttons = '';
	foreach ( $ctas as $cta ) {
		$url = $tracked_urls[ $cta['slug'] ] ?? awd_lm_email_utm_url( $cta['url'], $email, $list, $cta['slug'] );
		$buttons .= '<p style="margin:22px 0"><a href="' . esc_url( $url ) . '" style="display:inline-block;background:#cda574;color:#fff;padding:12px 18px;text-decoration:none;border-radius:4px;font-weight:700">' . esc_html( awd_lm_email_merge_tags( $cta['label'], $recipient ) ) . '</a></p>';
	}
	$unsubscribe = add_query_arg( array( 'action'=>'awd_lm_email_unsubscribe', 'token'=>awd_lm_email_unsubscribe_token( $recipient->email ) ), admin_url( 'admin-post.php' ) );
	return '<!doctype html><html><body style="margin:0;background:#f5f5f3;color:#182019;font:16px/1.6 Arial,sans-serif"><div style="max-width:640px;margin:auto;background:#fff;padding:32px"><div style="display:none;max-height:0;overflow:hidden">' . esc_html( awd_lm_email_merge_tags( $email->preheader, $recipient ) ) . '</div>' . wpautop( wp_kses_post( awd_lm_email_merge_tags( $email->body_text, $recipient ) ) ) . $buttons . '<hr style="border:0;border-top:1px solid #ddd;margin:32px 0"><p style="font-size:12px;color:#68716b"><a href="' . esc_url( $unsubscribe ) . '" style="color:#68716b">Unsubscribe / Abmelden / Se désabonner</a></p></div></body></html>';
}

function awd_lm_send_email_campaign( $email_id, $only_recipient = '' ) {
	global $wpdb;
	$t = awd_lm_email_tables(); $lt = awd_lm_lead_tables(); $s = awd_lm_brevo_settings();
	$email = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t['emails']} WHERE id=%d", absint( $email_id ) ) );
	if ( ! $email || empty( $s['api_key'] ) || empty( $s['sender_email'] ) ) { return new WP_Error( 'email_not_ready', 'Brevo API key and sender email are required.' ); }
	$list = $email->list_id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t['lists']} WHERE id=%d", $email->list_id ) ) : null;
	$ctas = json_decode( $email->cta_links, true ); if ( ! is_array( $ctas ) ) { $ctas = array(); }
	if ( 'list' === $email->audience_type ) {
		if ( ! $list ) { return new WP_Error( 'list_missing', 'Recipient list not found.' ); }
		if ( $only_recipient ) {
			$recipients = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t['members']} WHERE list_id=%d AND email=%s ORDER BY id", $list->id, strtolower( sanitize_email( $only_recipient ) ) ) );
		} else {
			$recipients = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t['members']} WHERE list_id=%d AND email<>'' ORDER BY id", $list->id ) );
		}
	} else {
		$recipients = array( (object) array( 'lead_id'=>0, 'salutation'=>$email->standalone_salutation, 'first_name'=>$email->standalone_first_name, 'last_name'=>$email->standalone_last_name, 'company'=>$email->standalone_company, 'email'=>$email->standalone_email ) );
		if ( $only_recipient && strtolower( sanitize_email( $only_recipient ) ) !== strtolower( $email->standalone_email ) ) { $recipients = array(); }
	}
	if ( ! $recipients || ! is_email( $recipients[0]->email ?? '' ) ) { return new WP_Error( 'no_recipients', 'No valid recipients were selected.' ); }
	$sent = 0; $failed = 0; $now = current_time( 'mysql', true );
	foreach ( $recipients as $recipient ) {
		if ( ! is_email( $recipient->email ) ) { $failed++; continue; }
		$wpdb->insert( $t['sends'], array( 'email_id'=>$email->id, 'lead_id'=>absint( $recipient->lead_id ?? 0 ), 'recipient_email'=>$recipient->email, 'status'=>'sending', 'updated_at'=>$now ) );
		$send_id = (int) $wpdb->insert_id;
		$tracked_urls = array();
		foreach ( $ctas as $cta ) {
			$url = awd_lm_email_utm_url( $cta['url'], $email, $list, $cta['slug'] );
			$token = awd_lm_email_click_token_create( $send_id, $cta['slug'] );
			$tracked_urls[ $cta['slug'] ] = $token ? add_query_arg( 'awd_click', $token, $url ) : $url;
		}
		$text_links = array(); foreach ( $ctas as $cta ) { $text_links[] = awd_lm_email_merge_tags( $cta['label'], $recipient ) . ': ' . $tracked_urls[ $cta['slug'] ]; }
		$recipient_name = trim( (string) ( $recipient->first_name ?? '' ) . ' ' . (string) ( $recipient->last_name ?? '' ) );
		$to = array( 'email'=>$recipient->email, 'contactPixelTrackingConsent'=>! empty( $s['tracking_consent'] ) );
		if ( '' !== $recipient_name ) { $to['name'] = $recipient_name; }
		$payload = array(
			'sender'=>array( 'name'=>$s['sender_name'], 'email'=>$s['sender_email'] ),
			'to'=>array( $to ),
			'subject'=>awd_lm_email_merge_tags( $email->subject, $recipient ), 'htmlContent'=>awd_lm_email_html( $email, $list, $recipient, $ctas, $tracked_urls ),
			'textContent'=>awd_lm_email_merge_tags( wp_strip_all_tags( $email->body_text ), $recipient ) . "\n\n" . implode( "\n", $text_links ) . "\n\nUnsubscribe: " . add_query_arg( array( 'action'=>'awd_lm_email_unsubscribe','token'=>awd_lm_email_unsubscribe_token( $recipient->email ) ), admin_url( 'admin-post.php' ) ),
			'headers'=>array( 'X-Mailin-custom'=>'awd_send_id:' . $send_id ),
			'tags'=>array( $list ? 'awd-list-' . $list->id : 'awd-standalone', 'awd-email-' . $email->id ),
		);
		$response = wp_remote_post( 'https://api.brevo.com/v3/smtp/email', array( 'timeout'=>20, 'headers'=>array( 'accept'=>'application/json','api-key'=>$s['api_key'],'content-type'=>'application/json' ), 'body'=>wp_json_encode( $payload ) ) );
		$code = is_wp_error( $response ) ? 0 : wp_remote_retrieve_response_code( $response );
		$body = is_wp_error( $response ) ? array() : json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code >= 200 && $code < 300 ) { $wpdb->update( $t['sends'], array( 'status'=>'sent','sent_at'=>$now,'updated_at'=>$now,'brevo_message_id'=>awd_lm_email_message_id( $body['messageId'] ?? '' ) ), array( 'id'=>$send_id ) ); $sent++; }
		else { $error = is_wp_error( $response ) ? $response->get_error_message() : wp_remote_retrieve_body( $response ); $wpdb->update( $t['sends'], array( 'status'=>'failed','updated_at'=>$now,'last_error'=>substr( sanitize_text_field( $error ), 0, 2000 ) ), array( 'id'=>$send_id ) ); $failed++; }
	}
	$wpdb->update( $t['emails'], array( 'status'=>$sent ? 'sent' : 'failed', 'updated_at'=>$now ), array( 'id'=>$email->id ) );
	return array( 'sent'=>$sent, 'failed'=>$failed );
}

function awd_lm_email_campaigns_page_legacy() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', 403 ); }
	awd_lm_maybe_upgrade_email_tables(); global $wpdb; $t=awd_lm_email_tables(); $lt=awd_lm_lead_tables();
	$campaigns=$wpdb->get_results( "SELECT * FROM {$t['campaigns']} ORDER BY created_at DESC" );
	$emails=$wpdb->get_results( "SELECT e.*,c.name campaign_name,(SELECT COUNT(*) FROM {$t['sends']} s WHERE s.email_id=e.id) sends,(SELECT COUNT(DISTINCT v.send_id) FROM {$t['events']} v INNER JOIN {$t['sends']} s2 ON s2.id=v.send_id WHERE s2.email_id=e.id AND v.event_type='delivered') delivered,(SELECT COUNT(DISTINCT v.send_id) FROM {$t['events']} v INNER JOIN {$t['sends']} s2 ON s2.id=v.send_id WHERE s2.email_id=e.id AND v.event_type='unique_opened') opens,(SELECT COUNT(DISTINCT v.send_id) FROM {$t['events']} v INNER JOIN {$t['sends']} s2 ON s2.id=v.send_id WHERE s2.email_id=e.id AND v.event_type='click') clicks,(SELECT COUNT(DISTINCT v.send_id) FROM {$t['events']} v INNER JOIN {$t['sends']} s2 ON s2.id=v.send_id WHERE s2.email_id=e.id AND v.event_type='unsubscribed') unsubscribed FROM {$t['emails']} e LEFT JOIN {$t['campaigns']} c ON c.id=e.campaign_id ORDER BY e.created_at DESC" );
	$recent_events=$wpdb->get_results( "SELECT v.*,s.recipient_email,e.name email_name,c.name campaign_name FROM {$t['events']} v INNER JOIN {$t['sends']} s ON s.id=v.send_id INNER JOIN {$t['emails']} e ON e.id=s.email_id LEFT JOIN {$t['campaigns']} c ON c.id=e.campaign_id ORDER BY v.occurred_at DESC,v.id DESC LIMIT 100" );
	$subscribers=(int)$wpdb->get_var( "SELECT COUNT(*) FROM {$lt['leads']} WHERE newsletter_status='subscribed' AND email<>''" );
	$notice=sanitize_key( wp_unslash( $_GET['notice'] ?? '' ) );
	?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Email campaigns</title><style>body{margin:28px;font:14px/1.5 system-ui;background:#f5f8f6;color:#182019}main{max-width:1300px;margin:auto}a{color:#087a4c;font-weight:750;text-decoration:none}.bar,.row{display:flex;gap:12px;align-items:center;flex-wrap:wrap}.bar{justify-content:space-between}.card{background:#fff;border:1px solid #d6e8dc;border-radius:14px;padding:22px;margin-top:18px;overflow:auto}.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}label{display:block;font-weight:750;margin-top:12px}input,select,textarea{width:100%;box-sizing:border-box;padding:10px;border:1px solid #bcdac7;border-radius:7px;font:inherit}textarea{min-height:150px}button,.button{display:inline-block;border:0;border-radius:7px;padding:10px 14px;background:#008b55;color:#fff;font-weight:800;cursor:pointer}.secondary{background:#506159}.cta-row{display:grid;grid-template-columns:1fr 2fr auto;gap:8px;margin:8px 0}.notice{background:#e8f7ed;padding:12px;border-radius:8px}table{width:100%;border-collapse:collapse}th,td{text-align:left;vertical-align:top;padding:9px;border-bottom:1px solid #e1ebe4}.event{font-weight:800}.muted{color:#68756d;font-size:12px}@media(max-width:760px){.grid{grid-template-columns:1fr}.cta-row{grid-template-columns:1fr}}</style></head><body><main><div class="bar"><div><a href="<?php echo esc_url(admin_url('admin-post.php?action=awd_lm_visual_editor'));?>">← Editor</a> · <a href="<?php echo esc_url(admin_url('admin-post.php?action=awd_lm_analytics'));?>">Analytics</a><h1>Email campaigns</h1><p><?php echo esc_html($subscribers);?> subscribed recipients · delivered/opened/clicked events come from Brevo</p></div><a class="button secondary" href="<?php echo esc_url(admin_url('admin-post.php?action=awd_lm_form_settings'));?>">Brevo settings</a></div><?php if($notice):?><p class="notice"><?php echo 'sent'===$notice?'Email saved and sending completed.':'Draft saved.';?></p><?php endif;?><form class="card" method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><input type="hidden" name="action" value="awd_lm_save_email_campaign"><?php wp_nonce_field('awd_lm_save_email_campaign');?><h2>Create email</h2><div class="grid"><div><label>Email name</label><input name="email_name" required maxlength="190" placeholder="August follow-up"><label>Campaign</label><select name="campaign_id" id="campaignSelect" required><option value="">Choose…</option><?php foreach($campaigns as $c):?><option value="<?php echo esc_attr($c->id);?>"><?php echo esc_html($c->name);?></option><?php endforeach;?><option value="new">+ New campaign</option></select><div id="newCampaign" hidden><label>New campaign name / tagline</label><input name="campaign_name" maxlength="190" placeholder="GloriaFood Migration 2026"></div><label>Subject</label><input name="subject" required maxlength="255"><label>Preheader</label><input name="preheader" maxlength="255"></div><div><label>Message</label><textarea name="body_text" required placeholder="Plain text is converted into a simple email layout."></textarea></div></div><h3>CTA links</h3><p>UTM is generated automatically: source=brevo, medium=email, campaign=campaign, content=CTA name, term=email name and a stable campaign ID.</p><div id="ctaRows"><div class="cta-row"><input name="cta_label[]" placeholder="CTA name" required><input type="url" name="cta_url[]" placeholder="https://website-to-go.ch/..." required><button type="button" class="remove secondary">×</button></div></div><p><button type="button" id="addCta" class="secondary">+ Add CTA</button></p><p class="row"><button name="send_now" value="0">Save draft</button><button name="send_now" value="1" onclick="return confirm('Send this email to all subscribed recipients now?')">Save & send to <?php echo esc_html($subscribers);?></button></p></form><section class="card"><h2>Emails</h2><table><thead><tr><th>Name</th><th>Campaign</th><th>Status</th><th>Sent</th><th>Delivered</th><th>Unique opens</th><th>Unique clickers</th><th>Unsubscribed</th></tr></thead><tbody><?php foreach($emails as $e):?><tr><td><strong><?php echo esc_html($e->name);?></strong><br><?php echo esc_html($e->subject);?></td><td><?php echo esc_html($e->campaign_name);?></td><td><?php echo esc_html($e->status);?></td><td><?php echo esc_html($e->sends);?></td><td><?php echo esc_html($e->delivered);?></td><td><?php echo esc_html($e->opens);?></td><td><?php echo esc_html($e->clicks);?></td><td><?php echo esc_html($e->unsubscribed);?></td></tr><?php endforeach;?><?php if(!$emails):?><tr><td colspan="8">No emails yet.</td></tr><?php endif;?></tbody></table></section><section class="card"><h2>Recent Brevo events</h2><p class="muted">Opened means that Brevo detected its tracking pixel. It is not proof that the message was read; proxy opens can also occur.</p><table><thead><tr><th>Time</th><th>Event</th><th>Email / campaign</th><th>Recipient</th><th>Clicked URL</th></tr></thead><tbody><?php foreach($recent_events as $v):?><tr><td><?php echo esc_html(get_date_from_gmt($v->occurred_at,'Y-m-d H:i:s'));?></td><td class="event"><?php echo esc_html($v->event_type);?></td><td><?php echo esc_html($v->email_name);?><br><span class="muted"><?php echo esc_html($v->campaign_name);?></span></td><td><?php echo esc_html($v->recipient_email);?></td><td><?php echo esc_html($v->clicked_url?:'—');?></td></tr><?php endforeach;?><?php if(!$recent_events):?><tr><td colspan="5">No webhook events yet.</td></tr><?php endif;?></tbody></table></section></main><script>const s=document.getElementById('campaignSelect'),n=document.getElementById('newCampaign');s.addEventListener('change',()=>{n.hidden=s.value!=='new';n.querySelector('input').required=s.value==='new'});document.getElementById('addCta').onclick=()=>{const r=document.querySelector('.cta-row').cloneNode(true);r.querySelectorAll('input').forEach(i=>i.value='');document.getElementById('ctaRows').appendChild(r)};document.addEventListener('click',e=>{if(e.target.matches('.remove')&&document.querySelectorAll('.cta-row').length>1)e.target.closest('.cta-row').remove()});</script></body></html><?php exit;
}

function awd_lm_email_campaigns_page() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', 403 ); }
	awd_lm_maybe_upgrade_email_tables();
	global $wpdb;
	$t = awd_lm_email_tables();
	$lists = $wpdb->get_results( "SELECT l.*,(SELECT COUNT(*) FROM {$t['members']} m WHERE m.list_id=l.id) member_count,(SELECT salutation FROM {$t['members']} m WHERE m.list_id=l.id ORDER BY m.id LIMIT 1) preview_salutation,(SELECT first_name FROM {$t['members']} m WHERE m.list_id=l.id ORDER BY m.id LIMIT 1) preview_first_name,(SELECT last_name FROM {$t['members']} m WHERE m.list_id=l.id ORDER BY m.id LIMIT 1) preview_last_name,(SELECT company FROM {$t['members']} m WHERE m.list_id=l.id ORDER BY m.id LIMIT 1) preview_company,(SELECT email FROM {$t['members']} m WHERE m.list_id=l.id ORDER BY m.id LIMIT 1) preview_email FROM {$t['lists']} l ORDER BY l.created_at DESC" );
	$emails = $wpdb->get_results( "SELECT e.*,l.name list_name,(SELECT COUNT(*) FROM {$t['sends']} s WHERE s.email_id=e.id) sends FROM {$t['emails']} e LEFT JOIN {$t['lists']} l ON l.id=e.list_id WHERE e.status='draft' ORDER BY e.updated_at DESC" );
	$edit_id = absint( $_GET['edit_email'] ?? 0 );
	$editing = $edit_id ? $wpdb->get_row( $wpdb->prepare( "SELECT e.*,(SELECT COUNT(*) FROM {$t['sends']} s WHERE s.email_id=e.id) sends FROM {$t['emails']} e WHERE e.id=%d", $edit_id ) ) : null;
	if ( $editing && ( 'draft' !== $editing->status || (int) $editing->sends > 0 ) ) { $editing = null; }
	$editing_payload = $editing ? array( 'id'=>(int)$editing->id, 'name'=>$editing->name, 'audience_type'=>$editing->audience_type, 'list_id'=>(int)$editing->list_id, 'standalone_salutation'=>$editing->standalone_salutation, 'standalone_first_name'=>$editing->standalone_first_name, 'standalone_last_name'=>$editing->standalone_last_name, 'standalone_company'=>$editing->standalone_company, 'standalone_email'=>$editing->standalone_email, 'subject'=>$editing->subject, 'preheader'=>$editing->preheader, 'body_text'=>$editing->body_text, 'ctas'=>json_decode( (string) $editing->cta_links, true ) ?: array() ) : null;
	$list_member_rows = $wpdb->get_results( "SELECT id,list_id,salutation,first_name,last_name,company,email FROM {$t['members']} ORDER BY list_id,id", ARRAY_A );
	$list_members = array();
	foreach ( $list_member_rows as $member_row ) {
		$list_members[ (int) $member_row['list_id'] ][] = $member_row;
	}
	$notice = sanitize_key( wp_unslash( $_GET['notice'] ?? '' ) );
	?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Emails &amp; Campaigns</title><style>
	body{margin:28px;font:14px/1.5 system-ui;background:#f5f8f6;color:#182019}main{max-width:1300px;margin:auto}a{color:#087a4c;font-weight:750;text-decoration:none}.bar,.row,.action-row{display:flex;gap:12px;align-items:center;flex-wrap:wrap}.bar{justify-content:space-between}.action-row{gap:7px}.card{background:#fff;border:1px solid #d6e8dc;border-radius:14px;padding:22px;margin-top:18px;overflow:auto}.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.five{display:grid;grid-template-columns:.7fr 1fr 1fr 1.3fr 1.5fr;gap:10px}label{display:block;font-weight:750;margin-top:12px}input,select,textarea{width:100%;box-sizing:border-box;padding:10px;border:1px solid #bcdac7;border-radius:7px;font:inherit}textarea{min-height:320px;resize:vertical}.message-field{margin-top:16px}.audience{padding:14px;margin-top:12px;border:1px solid #d6e8dc;border-radius:10px;background:#f8fbf9}.tags,.editor-tabs{display:flex;gap:7px;flex-wrap:wrap}.tag,.merge-button{padding:5px 8px;border-radius:6px;background:#e8f7ed;color:#087a4c;font-weight:800}.merge-button{border:1px solid #bcdac7;cursor:pointer}.editor-tabs{margin:10px 0}.editor-tab{background:#edf3ef;color:#315b48}.editor-tab.is-active{background:#008b55;color:#fff}.message-editor{box-sizing:border-box;width:100%;min-height:320px;padding:14px;border:1px solid #bcdac7;border-radius:8px;background:#fff;font:14px/1.6 ui-monospace,SFMono-Regular,Consolas,monospace;white-space:pre-wrap;outline:none}.message-editor:empty:before{content:attr(data-placeholder);color:#8a948e;white-space:pre-wrap;pointer-events:none}.message-editor:focus{border-color:#008b55;box-shadow:0 0 0 3px rgba(0,139,85,.1)}.merge-token{display:inline-block;padding:1px 4px;border-radius:4px;background:#e8f7ed;color:#008b55;font-weight:900}.message-source{position:absolute!important;left:-9999px!important;width:1px!important;height:1px!important}.email-preview{width:100%;height:560px;border:1px solid #bcdac7;border-radius:8px;background:#f5f5f3}.cta-row,.member-row{display:grid;grid-template-columns:1fr 2fr auto;gap:8px;margin:8px 0}.member-row{grid-template-columns:.7fr 1fr 1fr 1.3fr 1.5fr auto}button,.button{display:inline-block;border:0;border-radius:7px;padding:10px 14px;background:#008b55;color:#fff;font-weight:800;cursor:pointer}.secondary{background:#506159}.danger{background:#9a3d35}.notice{background:#e8f7ed;padding:12px;border-radius:8px}.muted{color:#68756d;font-size:12px}.utm{font:12px/1.5 ui-monospace,SFMono-Regular,Consolas,monospace;color:#506159;overflow-wrap:anywhere}table{width:100%;border-collapse:collapse}th,td{text-align:left;vertical-align:top;padding:9px;border-bottom:1px solid #e1ebe4}.event{font-weight:800}dialog{width:min(920px,calc(100% - 32px));max-height:calc(100vh - 48px);box-sizing:border-box;padding:0;border:1px solid #bcdac7;border-radius:14px;box-shadow:0 24px 80px rgba(18,42,31,.28);color:#182019}dialog::backdrop{background:rgba(16,35,27,.58)}.dialog-body{padding:22px;overflow:auto;max-height:calc(100vh - 92px)}.dialog-head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px}.dialog-head h2{margin:0}.dialog-close{background:#edf3ef;color:#315b48;font-size:20px;line-height:1;padding:8px 11px}.empty-state{padding:18px;border-radius:10px;background:#f4f9f6;color:#506159}.member-empty{padding:14px;border:1px dashed #bcdac7;border-radius:8px;color:#68756d}[hidden]{display:none!important}@media(max-width:760px){body{margin:16px}.grid,.five,.member-row,.cta-row{grid-template-columns:1fr}.card{padding:16px}.message-editor{min-height:260px}.email-preview{height:480px}.action-row{align-items:stretch}.action-row button{flex:1}}
	</style></head><body><main><div class="bar"><div><h1>Emails &amp; Campaigns</h1><p>Create recipient lists or send a standalone personalised email.</p></div><div class="row"><a class="button" href="<?php echo esc_url( admin_url( 'admin-post.php?action=awd_lm_email_reports' ) ); ?>">Email reports</a><a class="button secondary" href="<?php echo esc_url( admin_url( 'admin-post.php?action=awd_lm_form_settings' ) ); ?>">Brevo settings</a></div></div><?php if ( $notice ) : ?><?php $notice_messages = array( 'list_saved'=>'Recipient list created. You can add recipients separately.', 'list_updated'=>'Recipient list saved.', 'members_updated'=>'Recipients saved.', 'list_deleted'=>'Recipient list deleted.', 'sent'=>'Email saved and sent.', 'saved'=>'Draft saved.', 'copied'=>'A new draft was created from the sent email.' ); ?><p class="notice"><?php echo esc_html( $notice_messages[ $notice ] ?? 'Changes saved.' ); ?></p><?php endif; ?>
	<?php awd_lm_leads_tool_panel( $lists ); ?>
	<section class="card" id="recipient-lists"><div class="bar"><div><h2>Recipient lists</h2><p>Create the list first. Add and manage its recipients separately.</p></div><button type="button" data-open-list-create>+ Create list</button></div><?php if ( $lists ) : ?><table><thead><tr><th>List</th><th>Recipients</th><th>Generated UTM</th><th>Actions</th></tr></thead><tbody><?php foreach ( $lists as $list ) : ?><tr><td><strong><?php echo esc_html( $list->name ); ?></strong></td><td><strong><?php echo esc_html( $list->member_count ); ?></strong> contacts</td><td class="utm">utm_campaign=<?php echo esc_html( $list->utm_campaign ); ?><br>utm_id=<?php echo esc_html( $list->utm_id ); ?></td><td><div class="action-row"><button type="button" class="secondary" data-edit-list="<?php echo esc_attr( $list->id ); ?>">✎ Edit</button><button type="button" data-manage-members="<?php echo esc_attr( $list->id ); ?>">Recipients</button><button type="button" class="danger" data-delete-list="<?php echo esc_attr( $list->id ); ?>">Delete</button></div></td></tr><?php endforeach; ?></tbody></table><?php else : ?><div class="empty-state"><strong>No recipient lists yet.</strong><br>Create a list now; recipients can be added afterwards.</div><?php endif; ?></section>
	<dialog id="listDetailsDialog"><div class="dialog-body"><div class="dialog-head"><div><h2 id="listDetailsTitle">Create recipient list</h2><p class="muted">The stable UTM identity is generated automatically and stays attached to this list.</p></div><button type="button" class="dialog-close" data-close-dialog aria-label="Close">×</button></div><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="awd_lm_save_email_list"><input type="hidden" name="save_scope" value="details"><input type="hidden" name="list_id" value="0"><?php wp_nonce_field( 'awd_lm_save_email_list' ); ?><label>List name</label><input name="list_name" required maxlength="190" placeholder="GloriaFood restaurants Switzerland"><p class="row"><button type="button" class="secondary" data-close-dialog>Cancel</button><button type="submit" id="listDetailsSubmit">Create list</button></p></form></div></dialog>
	<dialog id="membersDialog"><div class="dialog-body"><div class="dialog-head"><div><h2>Recipients in <span id="membersListName"></span></h2><p class="muted">Add, edit or remove contacts belonging to this list.</p></div><button type="button" class="dialog-close" data-close-dialog aria-label="Close">×</button></div><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="awd_lm_save_email_list"><input type="hidden" name="save_scope" value="members"><input type="hidden" name="list_id" value="0"><?php wp_nonce_field( 'awd_lm_save_email_list' ); ?><div id="memberRows"></div><div id="memberEmpty" class="member-empty">This list has no recipients yet.</div><p class="row"><button type="button" id="addMember" class="secondary">+ Add recipient</button><button type="submit">Save recipients</button></p></form></div></dialog>
	<dialog id="deleteListDialog"><div class="dialog-body"><div class="dialog-head"><div><h2>Delete recipient list?</h2><p>This will permanently delete <strong id="deleteListName"></strong> and its recipients. Email history is preserved and lists already used by an email cannot be deleted.</p></div><button type="button" class="dialog-close" data-close-dialog aria-label="Close">×</button></div><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="awd_lm_delete_email_list"><input type="hidden" name="list_id" value="0"><?php wp_nonce_field( 'awd_lm_delete_email_list' ); ?><p class="row"><button type="button" class="secondary" data-close-dialog>Cancel</button><button type="submit" class="danger">Delete list</button></p></form></div></dialog>
	<form class="card" id="emailComposer" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="awd_lm_save_email_campaign"><?php wp_nonce_field( 'awd_lm_save_email_campaign' ); ?><h2>Create email</h2><div class="grid"><div><label>Internal email name <span class="muted">(not shown to recipient)</span></label><input name="email_name" required maxlength="190" placeholder="GloriaFood first contact"><label>Recipient type</label><select name="audience_type" id="audienceType"><option value="list">Recipient list</option><option value="standalone">One standalone recipient</option></select></div><div><label>Subject</label><input name="subject" required maxlength="255" placeholder="Hello {{SALUTATION}} {{LASTNAME}}"><label>Preheader</label><input name="preheader" maxlength="255"></div></div><div id="listAudience" class="audience"><label>Recipient list</label><select name="list_id" id="recipientList"><option value="">Choose a list…</option><?php foreach ( $lists as $list ) : ?><option value="<?php echo esc_attr( $list->id ); ?>" data-salutation="<?php echo esc_attr( $list->preview_salutation ); ?>" data-first-name="<?php echo esc_attr( $list->preview_first_name ); ?>" data-last-name="<?php echo esc_attr( $list->preview_last_name ); ?>" data-company="<?php echo esc_attr( $list->preview_company ); ?>" data-email="<?php echo esc_attr( $list->preview_email ); ?>"><?php echo esc_html( $list->name . ' (' . $list->member_count . ')' ); ?></option><?php endforeach; ?></select></div><div id="standaloneAudience" class="audience" hidden><div class="five"><div><label>Anrede</label><select name="standalone_salutation"><option value="">Choose…</option><option value="Herr">Herr</option><option value="Frau">Frau</option></select></div><div><label>First name</label><input name="standalone_first_name"></div><div><label>Last name</label><input name="standalone_last_name"></div><div><label>Company</label><input name="standalone_company"></div><div><label>Email</label><input type="email" name="standalone_email"></div></div><p class="muted">A stable standalone UTM ID will be generated when the email is saved.</p></div><div class="message-field"><label>Message</label><p class="tags"><button type="button" class="merge-button" data-token="{{SALUTATION}}">{{SALUTATION}}</button><button type="button" class="merge-button" data-token="{{FIRSTNAME}}">{{FIRSTNAME}}</button><button type="button" class="merge-button" data-token="{{LASTNAME}}">{{LASTNAME}}</button><button type="button" class="merge-button" data-token="{{COMPANY}}">{{COMPANY}}</button><button type="button" class="merge-button" data-token="{{EMAIL}}">{{EMAIL}}</button></p><div class="editor-tabs"><button type="button" class="editor-tab is-active" data-mode="edit">Edit</button><button type="button" class="editor-tab" data-mode="preview">Preview</button></div><div id="messageEditor" class="message-editor" contenteditable="true" role="textbox" aria-multiline="true" data-placeholder="Guten Tag {{SALUTATION}} {{LASTNAME}},&#10;&#10;...&#10;&#10;Freundliche Grüsse"></div><textarea id="messageSource" class="message-source" name="body_text" tabindex="-1" aria-hidden="true"></textarea><iframe id="emailPreview" class="email-preview" title="Email preview" hidden></iframe></div><h3>CTA links</h3><p>Every link receives the selected list UTM or a generated standalone UTM automatically. Check that the destination is the exact page you want the recipient to open.</p><div id="ctaRows"><div class="cta-row"><input name="cta_label[]" placeholder="CTA name" required><input type="url" name="cta_url[]" value="<?php echo esc_attr( awd_lm_public_url( 'second-landing', 'de' ) ); ?>" placeholder="https://aiwilldo.lu/de/restaurants-ordering-system/" required><button type="button" class="remove secondary">×</button></div></div><p><button type="button" id="addCta" class="secondary">+ Add CTA</button></p><p class="row"><button name="send_now" value="0">Save draft</button><button name="send_now" value="1" id="sendButton">Save &amp; send</button></p></form>
	<section class="card"><div class="bar"><div><h2>Saved drafts</h2><p class="muted">Drafts stay here until you send them.</p></div><a class="button secondary" href="<?php echo esc_url( admin_url( 'admin-post.php?action=awd_lm_email_reports' ) ); ?>">Open email reports</a></div><table><thead><tr><th>Name</th><th>Audience</th><th>Updated</th><th>Action</th></tr></thead><tbody><?php foreach ( $emails as $email ) : ?><tr><td><strong><?php echo esc_html( $email->name ); ?></strong><br><?php echo esc_html( $email->subject ); ?></td><td><?php echo esc_html( 'list' === $email->audience_type ? ( $email->list_name ?: 'Deleted list' ) : trim( $email->standalone_company . ' · ' . $email->standalone_email, ' ·' ) ); ?></td><td><?php echo esc_html( get_date_from_gmt( $email->updated_at, 'Y-m-d H:i' ) ); ?></td><td><?php if ( ! (int) $email->sends ) : ?><a class="button secondary" href="<?php echo esc_url( add_query_arg( array( 'action'=>'awd_lm_email_campaigns', 'edit_email'=>(int)$email->id ), admin_url( 'admin-post.php' ) ) ); ?>">Edit</a><?php else : ?>—<?php endif; ?></td></tr><?php endforeach; ?><?php if ( ! $emails ) : ?><tr><td colspan="4">No saved drafts.</td></tr><?php endif; ?></tbody></table></section></main><script>
	const listData=<?php echo wp_json_encode( array_map( static function( $list ) { return array( 'id'=>(int)$list->id, 'name'=>$list->name ); }, $lists ) ); ?>,listMembers=<?php echo wp_json_encode( $list_members ); ?>,listDetailsDialog=document.getElementById('listDetailsDialog'),membersDialog=document.getElementById('membersDialog'),deleteListDialog=document.getElementById('deleteListDialog'),memberRows=document.getElementById('memberRows'),memberEmpty=document.getElementById('memberEmpty');
	function findList(id){return listData.find(list=>String(list.id)===String(id));}
	function memberRow(member={}){const row=document.createElement('div');row.className='member-row';row.innerHTML='<select name="member_salutation[]"><option value="">Anrede (optional)</option><option value="Herr">Herr</option><option value="Frau">Frau</option></select><input name="member_first_name[]" placeholder="First name (optional)"><input name="member_last_name[]" placeholder="Last name (optional)"><input name="member_company[]" placeholder="Company"><input type="email" name="member_email[]" placeholder="name@company.com" required><button type="button" class="remove-member secondary" title="Remove this recipient">×</button>';row.querySelector('select').value=member.salutation||'';row.querySelector('[name="member_first_name[]"]').value=member.first_name||'';row.querySelector('[name="member_last_name[]"]').value=member.last_name||'';row.querySelector('[name="member_company[]"]').value=member.company||'';row.querySelector('[name="member_email[]"]').value=member.email||'';return row;}
	function updateMemberEmpty(){memberEmpty.hidden=memberRows.children.length>0;}
	document.querySelector('[data-open-list-create]').addEventListener('click',()=>{const form=listDetailsDialog.querySelector('form');form.reset();form.elements.list_id.value='0';document.getElementById('listDetailsTitle').textContent='Create recipient list';document.getElementById('listDetailsSubmit').textContent='Create list';listDetailsDialog.showModal();form.elements.list_name.focus();});
	document.querySelectorAll('[data-edit-list]').forEach(button=>button.addEventListener('click',()=>{const list=findList(button.dataset.editList),form=listDetailsDialog.querySelector('form');if(!list)return;form.elements.list_id.value=list.id;form.elements.list_name.value=list.name;document.getElementById('listDetailsTitle').textContent='Edit recipient list';document.getElementById('listDetailsSubmit').textContent='Save list';listDetailsDialog.showModal();form.elements.list_name.focus();}));
	document.querySelectorAll('[data-manage-members]').forEach(button=>button.addEventListener('click',()=>{const list=findList(button.dataset.manageMembers),form=membersDialog.querySelector('form');if(!list)return;form.elements.list_id.value=list.id;document.getElementById('membersListName').textContent=list.name;memberRows.replaceChildren();(listMembers[list.id]||[]).forEach(member=>memberRows.appendChild(memberRow(member)));updateMemberEmpty();membersDialog.showModal();}));
	document.querySelectorAll('[data-delete-list]').forEach(button=>button.addEventListener('click',()=>{const list=findList(button.dataset.deleteList),form=deleteListDialog.querySelector('form');if(!list)return;form.elements.list_id.value=list.id;document.getElementById('deleteListName').textContent=list.name;deleteListDialog.showModal();}));
	document.querySelectorAll('[data-close-dialog]').forEach(button=>button.addEventListener('click',()=>button.closest('dialog').close()));
	[listDetailsDialog,membersDialog,deleteListDialog].forEach(dialog=>dialog.addEventListener('click',event=>{if(event.target===dialog)dialog.close();}));
	const composer=document.getElementById('emailComposer'),audience=document.getElementById('audienceType'),listBox=document.getElementById('listAudience'),standaloneBox=document.getElementById('standaloneAudience'),recipientList=document.getElementById('recipientList'),editor=document.getElementById('messageEditor'),source=document.getElementById('messageSource'),preview=document.getElementById('emailPreview'),previewRecipient=document.createElement('select'),previewLabel=document.createElement('label'),previewStatus=document.createElement('p'),previewApi=<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>,previewNonce=<?php echo wp_json_encode( wp_create_nonce( 'awd_lm_email_preview_recipients' ) ); ?>,editingData=<?php echo wp_json_encode( $editing_payload ); ?>;let savedRange=null,lastMergeTarget=editor,lastTextSelection=null;previewLabel.textContent='Preview as';previewRecipient.id='previewRecipient';previewRecipient.disabled=true;previewRecipient.innerHTML='<option value="">Choose a list first…</option>';previewStatus.className='muted';recipientList.after(previewLabel,previewRecipient,previewStatus);
	function toggleAudience(){const single=audience.value==='standalone';listBox.hidden=single;standaloneBox.hidden=!single;recipientList.required=!single;standaloneBox.querySelector('input[type=email]').required=single;refreshPreview();}
	function rememberCaret(){const selection=window.getSelection();if(selection&&selection.rangeCount){const range=selection.getRangeAt(0);if(editor.contains(range.commonAncestorContainer))savedRange=range.cloneRange();}}
	function syncSource(){source.value=editor.innerText.replace(/\u00a0/g,' ');}
	function rememberTextSelection(field){lastMergeTarget=field;lastTextSelection={start:field.selectionStart??field.value.length,end:field.selectionEnd??field.value.length};}
	function insertToken(token){if(lastMergeTarget!==editor&&lastMergeTarget&&typeof lastMergeTarget.setSelectionRange==='function'){const field=lastMergeTarget,start=lastTextSelection?.start??field.value.length,end=lastTextSelection?.end??start;field.value=field.value.slice(0,start)+token+field.value.slice(end);const caret=start+token.length;field.focus();field.setSelectionRange(caret,caret);lastTextSelection={start:caret,end:caret};field.dispatchEvent(new Event('input',{bubbles:true}));return;}editor.focus();const selection=window.getSelection();let range=savedRange;if(!range||!editor.contains(range.commonAncestorContainer)){range=document.createRange();range.selectNodeContents(editor);range.collapse(false);}const badge=document.createElement('span');badge.className='merge-token';badge.contentEditable='false';badge.dataset.token=token;badge.textContent=token;const spacer=document.createTextNode('\u00a0');range.deleteContents();range.insertNode(badge);badge.after(spacer);range.setStartAfter(spacer);range.collapse(true);selection.removeAllRanges();selection.addRange(range);savedRange=range.cloneRange();syncSource();editor.dispatchEvent(new Event('input',{bubbles:true}));}
	function setEditorText(text){editor.textContent='';String(text||'').split(/(\{\{(?:SALUTATION|FIRSTNAME|LASTNAME|COMPANY|EMAIL)\}\})/g).forEach(part=>{if(/^\{\{/.test(part)){const badge=document.createElement('span');badge.className='merge-token';badge.contentEditable='false';badge.dataset.token=part;badge.textContent=part;editor.appendChild(badge);}else editor.appendChild(document.createTextNode(part));});syncSource();}
	function hydrateDraft(){if(!editingData)return;const id=document.createElement('input');id.type='hidden';id.name='email_id';id.value=editingData.id;composer.appendChild(id);composer.querySelector('h2').textContent='Edit draft';composer.elements.email_name.value=editingData.name||'';composer.elements.subject.value=editingData.subject||'';composer.elements.preheader.value=editingData.preheader||'';audience.value=editingData.audience_type||'standalone';recipientList.value=String(editingData.list_id||'');composer.elements.standalone_salutation.value=editingData.standalone_salutation||'';composer.elements.standalone_first_name.value=editingData.standalone_first_name||'';composer.elements.standalone_last_name.value=editingData.standalone_last_name||'';composer.elements.standalone_company.value=editingData.standalone_company||'';composer.elements.standalone_email.value=editingData.standalone_email||'';setEditorText(editingData.body_text||'');const rows=document.getElementById('ctaRows');rows.innerHTML='';(editingData.ctas.length?editingData.ctas:[{label:'',url:''}]).forEach(cta=>{const row=document.createElement('div');row.className='cta-row';row.innerHTML='<input name="cta_label[]" placeholder="CTA name" required><input type="url" name="cta_url[]" placeholder="https://website-to-go.ch/..." required><button type="button" class="remove secondary">×</button>';row.querySelector('[name="cta_label[]"]').value=cta.label||'';row.querySelector('[name="cta_url[]"]').value=cta.url||'';rows.appendChild(row);});toggleAudience();if(recipientList.value)loadPreviewRecipients();}
	function escapeHtml(value){return String(value??'').replace(/[&<>"']/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));}
	async function loadPreviewRecipients(){previewRecipient.disabled=true;previewRecipient.innerHTML='<option value="">Loading…</option>';previewStatus.textContent='';if(!recipientList.value){previewRecipient.innerHTML='<option value="">Choose a list first…</option>';return;}try{const url=new URL(previewApi);url.searchParams.set('action','awd_lm_email_preview_recipients');url.searchParams.set('nonce',previewNonce);url.searchParams.set('list_id',recipientList.value);const response=await fetch(url,{credentials:'same-origin'}),payload=await response.json(),rows=payload?.data?.recipients||[];previewRecipient.innerHTML='';rows.forEach((person,index)=>{const option=document.createElement('option');option.value=person.id;option.dataset.salutation=person.salutation||'';option.dataset.firstName=person.first_name||'';option.dataset.lastName=person.last_name||'';option.dataset.company=person.company||'';option.dataset.email=person.email||'';option.textContent=((person.salutation||'')+' '+(person.first_name||'')+' '+(person.last_name||'')).trim()+' · '+person.email;previewRecipient.appendChild(option);if(index===0)option.selected=true;});previewRecipient.disabled=!rows.length;previewStatus.textContent=rows.length?rows.length+' recipients available for preview.':'This list has no recipients with email.';refreshPreview();}catch(error){previewRecipient.innerHTML='<option value="">Could not load recipients</option>';previewStatus.textContent='Preview recipients could not be loaded.';}}
	function recipient(){if(audience.value==='list'){const option=previewRecipient.options[previewRecipient.selectedIndex]||recipientList.options[recipientList.selectedIndex];return{SALUTATION:option?.dataset.salutation||'',FIRSTNAME:option?.dataset.firstName||'',LASTNAME:option?.dataset.lastName||'',COMPANY:option?.dataset.company||'',EMAIL:option?.dataset.email||'email@example.com'};}return{SALUTATION:composer.elements.standalone_salutation.value||'',FIRSTNAME:composer.elements.standalone_first_name.value||'',LASTNAME:composer.elements.standalone_last_name.value||'',COMPANY:composer.elements.standalone_company.value||'',EMAIL:composer.elements.standalone_email.value||'email@example.com'};}
	function merge(value,data){let result=String(value??'');Object.entries(data).forEach(([key,replacement])=>{result=result.split('{{'+key+'}}').join(replacement)});return result;}
	let generatedLinks=[];function buildPreview(){if(!editor.hidden)syncSource();const data=recipient(),subject=merge(composer.elements.subject.value||'Email subject',data),preheader=merge(composer.elements.preheader.value||'',data),message=escapeHtml(merge(source.value,data)).replace(/\n/g,'<br>');let buttons='';document.querySelectorAll('.cta-row').forEach((row,index)=>{const label=merge(row.querySelector('[name="cta_label[]"]').value,data),rawUrl=row.querySelector('[name="cta_url[]"]').value,url=generatedLinks[index]||rawUrl;if(label&&url)buttons+='<div style="margin:22px 0"><a href="'+escapeHtml(url)+'" style="display:inline-block;padding:13px 20px;border-radius:5px;background:#caa476;color:#fff;font-weight:700;text-decoration:none">'+escapeHtml(label)+'</a><details style="margin-top:9px;color:#68756d;font-size:12px"><summary style="cursor:pointer;color:#087a4c">View generated URL</summary><code style="display:block;margin-top:6px;overflow-wrap:anywhere;white-space:normal">'+escapeHtml(url)+'</code></details></div>';});preview.srcdoc='<!doctype html><html><body style="margin:0;background:#eef2ef;font:14px/1.5 Arial,sans-serif;color:#162019"><div style="padding:14px 20px;background:#dde6e0;color:#526159"><strong>To:</strong> '+escapeHtml(data.FIRSTNAME+' '+data.LASTNAME+' <'+data.EMAIL+'>')+'<br><strong>Subject:</strong> '+escapeHtml(subject)+'</div><div style="display:none">'+escapeHtml(preheader)+'</div><main style="box-sizing:border-box;max-width:680px;margin:28px auto;padding:34px;background:#fff;border-radius:10px;box-shadow:0 8px 28px rgba(16,48,31,.09)"><div style="white-space:normal">'+message+'</div>'+buttons+'<p style="margin-top:34px;padding-top:18px;border-top:1px solid #e2e8e4;color:#68756d;font-size:12px"><a href="#" style="color:#087a4c">Unsubscribe</a></p></main></body></html>';}
	function refreshPreview(){if(!preview.hidden)buildPreview();}
	audience.addEventListener('change',toggleAudience);recipientList.addEventListener('change',loadPreviewRecipients);previewRecipient.addEventListener('change',refreshPreview);toggleAudience();['keyup','mouseup','input','focus'].forEach(name=>editor.addEventListener(name,()=>{lastMergeTarget=editor;rememberCaret();syncSource();refreshPreview();}));[composer.elements.subject,composer.elements.preheader].forEach(field=>['focus','click','keyup','select','input'].forEach(name=>field.addEventListener(name,()=>rememberTextSelection(field))));document.addEventListener('selectionchange',rememberCaret);editor.addEventListener('paste',event=>{event.preventDefault();const selection=window.getSelection(),range=selection&&selection.rangeCount?selection.getRangeAt(0):null;if(!range||!editor.contains(range.commonAncestorContainer))return;range.deleteContents();const text=document.createTextNode(event.clipboardData.getData('text/plain'));range.insertNode(text);range.setStartAfter(text);range.collapse(true);selection.removeAllRanges();selection.addRange(range);savedRange=range.cloneRange();syncSource();});document.querySelectorAll('.merge-button').forEach(button=>{button.addEventListener('mousedown',event=>event.preventDefault());button.addEventListener('click',()=>insertToken(button.dataset.token));});
	const saveStatus=document.createElement('span');saveStatus.className='muted';document.querySelector('.editor-tabs').appendChild(saveStatus);async function autoSaveDraft(){syncSource();if(!source.value.trim()||!composer.reportValidity()){saveStatus.textContent='Complete the required fields before preview.';return false;}saveStatus.textContent='Saving draft…';const data=new FormData(composer);data.set('action','awd_lm_save_email_draft');data.set('autosave','1');data.set('send_now','0');try{const response=await fetch(previewApi,{method:'POST',body:data,credentials:'same-origin'}),raw=await response.text();let payload;try{payload=JSON.parse(raw);}catch(parseError){throw new Error('The server returned an HTML page instead of the draft result. Please reload the editor and try again.');}if(!response.ok||!payload.success)throw new Error(payload?.data?.message||'Draft could not be saved.');let id=composer.elements.email_id;if(!id){id=document.createElement('input');id.type='hidden';id.name='email_id';composer.appendChild(id);}id.value=payload.data.email_id;generatedLinks=Array.isArray(payload.data.generated_links)?payload.data.generated_links:[];if(typeof payload.data.body_text==='string')setEditorText(payload.data.body_text);if(typeof payload.data.subject==='string')composer.elements.subject.value=payload.data.subject;if(typeof payload.data.preheader==='string')composer.elements.preheader.value=payload.data.preheader;const url=new URL(window.location.href);url.searchParams.set('edit_email',payload.data.email_id);url.searchParams.delete('notice');history.replaceState({},'',url);saveStatus.textContent='Draft saved · preview uses saved version.';return true;}catch(error){saveStatus.textContent='Save failed.';alert(error.message);return false;}}
	document.querySelectorAll('.editor-tab').forEach(tab=>tab.addEventListener('click',async()=>{const isPreview=tab.dataset.mode==='preview';if(isPreview&&!(await autoSaveDraft()))return;document.querySelectorAll('.editor-tab').forEach(item=>item.classList.toggle('is-active',item===tab));editor.hidden=isPreview;preview.hidden=!isPreview;if(isPreview)buildPreview();else editor.focus();}));
	document.getElementById('addCta').onclick=()=>{const row=document.querySelector('.cta-row').cloneNode(true);row.querySelectorAll('input').forEach(input=>input.value='');document.getElementById('ctaRows').appendChild(row);refreshPreview();};document.getElementById('addMember').onclick=()=>{memberRows.appendChild(memberRow());updateMemberEmpty();};document.addEventListener('click',event=>{if(event.target.matches('.remove')&&document.querySelectorAll('.cta-row').length>1){event.target.closest('.cta-row').remove();refreshPreview();}if(event.target.matches('.remove-member')){event.target.closest('.member-row').remove();updateMemberEmpty();}});composer.addEventListener('input',event=>{if(event.target!==editor)refreshPreview();});composer.addEventListener('submit',event=>{syncSource();if(!source.value.trim()){event.preventDefault();alert('Message is required.');document.querySelector('[data-mode="edit"]').click();editor.focus();}});document.getElementById('sendButton').addEventListener('click',event=>{if(!confirm(audience.value==='list'?'Send this email to every recipient in the selected list?':'Send this email to the standalone recipient?'))event.preventDefault();});hydrateDraft();
	</script></body></html><?php exit;
}

function awd_lm_email_report_rate( $value, $total ) {
	return $total > 0 ? round( ( $value / $total ) * 100, 1 ) : 0;
}

function awd_lm_email_reports_page() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', 403 ); }
	awd_lm_maybe_upgrade_email_tables();
	global $wpdb;
	$t = awd_lm_email_tables();
	$mode = sanitize_key( wp_unslash( $_GET['mode'] ?? 'campaigns' ) );
	$mode = 'standalone' === $mode ? 'standalone' : 'campaigns';
	$list_id = 'campaigns' === $mode ? absint( $_GET['list_id'] ?? 0 ) : 0;
	$email_id = absint( $_GET['email_id'] ?? 0 );
	$lists = $wpdb->get_results( "SELECT id,name,utm_campaign,utm_id FROM {$t['lists']} ORDER BY name" );
	$where = "e.audience_type='" . ( 'standalone' === $mode ? 'standalone' : 'list' ) . "' AND EXISTS (SELECT 1 FROM {$t['sends']} sx WHERE sx.email_id=e.id)";
	if ( $list_id ) { $where .= $wpdb->prepare( ' AND e.list_id=%d', $list_id ); }
	$reports = $wpdb->get_results( "SELECT e.*,l.name list_name,
		(SELECT COUNT(*) FROM {$t['sends']} s WHERE s.email_id=e.id) sends,
		(SELECT COUNT(DISTINCT v.send_id) FROM {$t['events']} v INNER JOIN {$t['sends']} s ON s.id=v.send_id WHERE s.email_id=e.id AND v.event_type='delivered') delivered,
		(SELECT COUNT(DISTINCT v.send_id) FROM {$t['events']} v INNER JOIN {$t['sends']} s ON s.id=v.send_id WHERE s.email_id=e.id AND v.event_type IN ('unique_opened','opened')) opens,
		(SELECT COUNT(DISTINCT v.send_id) FROM {$t['events']} v INNER JOIN {$t['sends']} s ON s.id=v.send_id WHERE s.email_id=e.id AND v.event_type='click') clicks,
		(SELECT COUNT(DISTINCT v.send_id) FROM {$t['events']} v INNER JOIN {$t['sends']} s ON s.id=v.send_id WHERE s.email_id=e.id AND v.event_type='unsubscribed') unsubscribed,
		(SELECT COUNT(DISTINCT v.send_id) FROM {$t['events']} v INNER JOIN {$t['sends']} s ON s.id=v.send_id WHERE s.email_id=e.id AND v.event_type IN ('hard_bounce','invalid','blocked','spam','error')) failed
		FROM {$t['emails']} e LEFT JOIN {$t['lists']} l ON l.id=e.list_id WHERE {$where} ORDER BY e.created_at DESC" );
	$totals = array( 'sends'=>0, 'delivered'=>0, 'opens'=>0, 'clicks'=>0, 'unsubscribed'=>0, 'failed'=>0 );
	$detail = null;
	foreach ( $reports as $report ) {
		foreach ( $totals as $key=>$unused ) { $totals[ $key ] += (int) $report->$key; }
		if ( $email_id === (int) $report->id ) { $detail = $report; }
	}
	$send_rows = array();
	$event_rows = array();
	if ( $detail ) {
		$send_rows = $wpdb->get_results( $wpdb->prepare( "SELECT s.*,
			(SELECT COUNT(*) FROM {$t['events']} v WHERE v.send_id=s.id AND v.event_type='delivered') delivered,
			(SELECT COUNT(*) FROM {$t['events']} v WHERE v.send_id=s.id AND v.event_type IN ('unique_opened','opened')) opens,
			(SELECT COUNT(*) FROM {$t['events']} v WHERE v.send_id=s.id AND v.event_type='click') clicks,
			(SELECT COUNT(*) FROM {$t['events']} v WHERE v.send_id=s.id AND v.event_type='unsubscribed') unsubscribed
			FROM {$t['sends']} s WHERE s.email_id=%d ORDER BY s.sent_at DESC,s.id DESC", $detail->id ) );
		$event_rows = $wpdb->get_results( $wpdb->prepare( "SELECT v.*,s.recipient_email FROM {$t['events']} v INNER JOIN {$t['sends']} s ON s.id=v.send_id WHERE s.email_id=%d ORDER BY v.occurred_at DESC,v.id DESC LIMIT 500", $detail->id ) );
	}
	$base_url = admin_url( 'admin-post.php?action=awd_lm_email_reports' );
	?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Email reports</title><style>
	body{margin:28px;font:14px/1.5 system-ui;background:#f5f8f6;color:#182019}main{max-width:1440px;margin:auto}a{color:#087a4c;font-weight:800;text-decoration:none}.bar,.row,.tabs{display:flex;gap:10px;align-items:center;flex-wrap:wrap}.bar{justify-content:space-between}.card{background:#fff;border:1px solid #d6e8dc;border-radius:16px;padding:22px;margin-top:18px;overflow:auto}.button,.tab{display:inline-flex;align-items:center;border:0;border-radius:9px;padding:10px 14px;background:#008b55;color:#fff;font-weight:850;text-decoration:none}.button.secondary,.tab{background:#edf3ef;color:#315b48}.tab.is-active{background:#008b55;color:#fff}.filters{display:flex;gap:10px;align-items:end;flex-wrap:wrap;margin-top:16px}.filters label{font-weight:800}.filters select{display:block;min-width:280px;margin-top:5px;padding:10px;border:1px solid #bcdac7;border-radius:8px;background:#fff}.metrics{display:grid;grid-template-columns:repeat(6,minmax(140px,1fr));gap:12px;margin-top:18px}.metric{padding:15px;border:1px solid #d6e8dc;border-radius:13px;background:#fff}.metric strong{display:block;font-size:27px;line-height:1.1}.metric span{color:#68756d}.metric.delivered{background:#eef8f2;border-color:#bfe3cc}.metric.opened{background:#eef5fb;border-color:#c9deed}.metric.clicked{background:#fff7e9;border-color:#ecd3a5}.metric.unsubscribe{background:#fbf1ef;border-color:#e7c7c1}.metric.failed{background:#f9eceb;border-color:#e3b9b4}.badge{display:inline-flex;align-items:center;gap:5px;padding:5px 9px;border-radius:999px;font-weight:850;white-space:nowrap;background:#edf3ef;color:#315b48}.badge.good{background:#e4f5ea;color:#087a4c}.badge.info{background:#e7f1f8;color:#28617f}.badge.click{background:#fff0d8;color:#805718}.badge.warn{background:#f8e9e6;color:#8e3d35}.badge.neutral{background:#eef1ef;color:#506159}.badge b{font-size:15px}.muted{color:#68756d;font-size:12px}.audience{color:#506159}.subject{max-width:420px}.nowrap{white-space:nowrap}table{width:100%;border-collapse:collapse}th,td{text-align:left;vertical-align:top;padding:11px 9px;border-bottom:1px solid #e1ebe4}.event-name{text-transform:capitalize}.detail-head{padding-bottom:14px;border-bottom:1px solid #e1ebe4}.empty{padding:26px;border-radius:12px;background:#f4f9f6;color:#506159;text-align:center}@media(max-width:980px){.metrics{grid-template-columns:repeat(3,1fr)}}@media(max-width:680px){body{margin:16px}.metrics{grid-template-columns:repeat(2,1fr)}.card{padding:16px}.filters select{min-width:0;width:100%}.filters label{width:100%}}
	</style></head><body><main><div class="bar"><div><h1>Email reports</h1><p>Brevo delivery activity, grouped separately for campaigns and standalone emails.</p></div><a class="button secondary" href="<?php echo esc_url( admin_url( 'admin-post.php?action=awd_lm_email_campaigns' ) ); ?>">← Emails &amp; lists</a></div>
	<div class="tabs"><a class="tab <?php echo 'campaigns'===$mode?'is-active':''; ?>" href="<?php echo esc_url( add_query_arg( 'mode','campaigns',$base_url ) ); ?>">Campaigns</a><a class="tab <?php echo 'standalone'===$mode?'is-active':''; ?>" href="<?php echo esc_url( add_query_arg( 'mode','standalone',$base_url ) ); ?>">Standalone</a></div>
	<?php if ( 'campaigns' === $mode ) : ?><form class="filters" method="get" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="awd_lm_email_reports"><input type="hidden" name="mode" value="campaigns"><label>Campaign / recipient list<select name="list_id"><option value="0">All campaigns</option><?php foreach ( $lists as $list ) : ?><option value="<?php echo esc_attr( $list->id ); ?>" <?php selected( $list_id, $list->id ); ?>><?php echo esc_html( $list->name ); ?></option><?php endforeach; ?></select></label><button class="button" type="submit">Show report</button></form><?php endif; ?>
	<div class="metrics"><div class="metric"><strong><?php echo esc_html( $totals['sends'] ); ?></strong><span>Sent</span></div><div class="metric delivered"><strong><?php echo esc_html( $totals['delivered'] ); ?></strong><span>Delivered · <?php echo esc_html( awd_lm_email_report_rate( $totals['delivered'], $totals['sends'] ) ); ?>%</span></div><div class="metric opened"><strong><?php echo esc_html( $totals['opens'] ); ?></strong><span>Unique opens · <?php echo esc_html( awd_lm_email_report_rate( $totals['opens'], $totals['sends'] ) ); ?>%</span></div><div class="metric clicked"><strong><?php echo esc_html( $totals['clicks'] ); ?></strong><span>Unique clickers · <?php echo esc_html( awd_lm_email_report_rate( $totals['clicks'], $totals['sends'] ) ); ?>%</span></div><div class="metric unsubscribe"><strong><?php echo esc_html( $totals['unsubscribed'] ); ?></strong><span>Unsubscribed · <?php echo esc_html( awd_lm_email_report_rate( $totals['unsubscribed'], $totals['sends'] ) ); ?>%</span></div><div class="metric failed"><strong><?php echo esc_html( $totals['failed'] ); ?></strong><span>Failed · <?php echo esc_html( awd_lm_email_report_rate( $totals['failed'], $totals['sends'] ) ); ?>%</span></div></div>
	<?php if ( $detail ) : ?><section class="card"><div class="bar detail-head"><div><a href="<?php echo esc_url( add_query_arg( array_filter( array( 'mode'=>$mode, 'list_id'=>$list_id ) ), $base_url ) ); ?>">← All emails</a><h2><?php echo esc_html( $detail->name ); ?></h2><p><?php echo esc_html( $detail->subject ); ?></p><p class="audience"><?php echo esc_html( 'list'===$detail->audience_type ? ( $detail->list_name ?: 'Deleted campaign' ) : trim( $detail->standalone_company . ' · ' . $detail->standalone_email, ' ·' ) ); ?></p></div><div class="row"><span class="badge neutral"><?php echo esc_html( ucfirst( $detail->status ) ); ?></span><a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action'=>'awd_lm_copy_email', 'email_id'=>(int)$detail->id ), admin_url( 'admin-post.php' ) ), 'awd_lm_copy_email_' . (int)$detail->id ) ); ?>">Copy as draft</a></div></div><h3>Recipients</h3><table><thead><tr><th>Recipient</th><th>Status</th><th>Delivered</th><th>Opened</th><th>Clicked</th><th>Unsubscribed</th><th>Sent</th><th></th></tr></thead><tbody><?php foreach ( $send_rows as $send ) : ?><tr><td><?php echo esc_html( $send->recipient_email ); ?></td><td><span class="badge neutral"><?php echo esc_html( $send->status ); ?></span><?php if($send->last_error):?><br><span class="muted"><?php echo esc_html( $send->last_error ); ?></span><?php endif;?></td><td><?php echo $send->delivered?'<span class="badge good">Yes</span>':'—'; ?></td><td><?php echo $send->opens?'<span class="badge info">Yes</span>':'—'; ?></td><td><?php echo $send->clicks?'<span class="badge click">Yes</span>':'—'; ?></td><td><?php echo $send->unsubscribed?'<span class="badge warn">Yes</span>':'—'; ?></td><td class="nowrap"><?php echo esc_html( get_date_from_gmt( $send->sent_at, 'Y-m-d H:i' ) ); ?></td><td><?php if('failed'===$send->status):?><a class="button" href="<?php echo esc_url(wp_nonce_url(add_query_arg(array('action'=>'awd_lm_retry_email_recipient','send_id'=>(int)$send->id),admin_url('admin-post.php')),'awd_lm_retry_email_recipient_'.(int)$send->id));?>">Retry</a><?php endif;?></td></tr><?php endforeach; ?></tbody></table><h3>Event chronology</h3><p class="muted">Opened means Brevo detected its tracking pixel. It is not proof that the message was read.</p><table><thead><tr><th>Time</th><th>Event</th><th>Recipient</th><th>Link / details</th></tr></thead><tbody><?php foreach ( $event_rows as $event ) : ?><tr><td class="nowrap"><?php echo esc_html( get_date_from_gmt( $event->occurred_at, 'Y-m-d H:i:s' ) ); ?></td><td><span class="badge <?php echo esc_attr( 'delivered'===$event->event_type?'good':('click'===$event->event_type?'click':(false!==strpos($event->event_type,'open')?'info':(in_array($event->event_type,array('unsubscribed','hard_bounce','invalid','blocked','spam','error'),true)?'warn':'neutral')))); ?> event-name"><?php echo esc_html( str_replace( '_',' ', $event->event_type ) ); ?></span></td><td><?php echo esc_html( $event->recipient_email ); ?></td><td><?php echo esc_html( $event->clicked_url ?: '—' ); ?></td></tr><?php endforeach; ?><?php if(!$event_rows):?><tr><td colspan="4">No Brevo events recorded.</td></tr><?php endif;?></tbody></table></section>
	<?php else : ?><section class="card"><div class="bar"><div><h2><?php echo 'campaigns'===$mode?'Campaign emails':'Standalone emails'; ?></h2><p class="muted">Each row is one sent email. Open it for recipient status and the full Brevo event timeline.</p></div></div><?php if($reports):?><table><thead><tr><th>Email</th><th><?php echo 'campaigns'===$mode?'Campaign':'Company / recipient'; ?></th><th>Sent</th><th>Delivered</th><th>Opened</th><th>Clicked</th><th>Unsubscribed</th><th>Failed</th><th>Actions</th></tr></thead><tbody><?php foreach($reports as $report):?><tr><td class="subject"><strong><?php echo esc_html($report->name);?></strong><br><?php echo esc_html($report->subject);?><br><span class="muted"><?php echo esc_html(get_date_from_gmt($report->created_at,'Y-m-d H:i'));?></span></td><td><?php echo esc_html('campaigns'===$mode?($report->list_name?:'Deleted campaign'):trim($report->standalone_company.' · '.$report->standalone_email,' ·'));?></td><td><span class="badge neutral"><b><?php echo esc_html($report->sends);?></b></span></td><td><span class="badge good"><b><?php echo esc_html($report->delivered);?></b> <?php echo esc_html(awd_lm_email_report_rate($report->delivered,$report->sends));?>%</span></td><td><span class="badge info"><b><?php echo esc_html($report->opens);?></b> <?php echo esc_html(awd_lm_email_report_rate($report->opens,$report->sends));?>%</span></td><td><span class="badge click"><b><?php echo esc_html($report->clicks);?></b> <?php echo esc_html(awd_lm_email_report_rate($report->clicks,$report->sends));?>%</span></td><td><span class="badge warn"><b><?php echo esc_html($report->unsubscribed);?></b></span></td><td><span class="badge <?php echo $report->failed?'warn':'neutral'; ?>"><b><?php echo esc_html($report->failed);?></b></span></td><td><div class="row"><a class="button secondary" target="_blank" rel="noopener" href="<?php echo esc_url(add_query_arg(array('mode'=>$mode,'list_id'=>$list_id,'email_id'=>(int)$report->id),$base_url));?>">Details</a><a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action'=>'awd_lm_copy_email', 'email_id'=>(int)$report->id ), admin_url( 'admin-post.php' ) ), 'awd_lm_copy_email_' . (int)$report->id ) ); ?>">Copy</a></div></td></tr><?php endforeach;?></tbody></table><?php else:?><div class="empty">No sent emails in this section yet.</div><?php endif;?></section><?php endif;?>
	</main></body></html><?php exit;
}

function awd_lm_copy_email() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', 403 ); }
	awd_lm_maybe_upgrade_email_tables();
	$email_id = absint( $_GET['email_id'] ?? 0 );
	check_admin_referer( 'awd_lm_copy_email_' . $email_id );
	global $wpdb;
	$t = awd_lm_email_tables();
	$source = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t['emails']} WHERE id=%d", $email_id ), ARRAY_A );
	if ( ! $source ) { wp_die( 'Email not found.', 404 ); }
	unset( $source['id'] );
	$now = current_time( 'mysql', true );
	$source['name'] = substr( 'Copy of ' . $source['name'], 0, 190 );
	$source['slug'] = substr( sanitize_title( $source['name'] ) . '-' . strtolower( wp_generate_password( 6, false, false ) ), 0, 190 );
	$source['status'] = 'draft';
	$source['created_at'] = $now;
	$source['updated_at'] = $now;
	if ( ! $wpdb->insert( $t['emails'], $source ) ) { wp_die( 'The draft copy could not be created.', 500 ); }
	$new_id = (int) $wpdb->insert_id;
	wp_safe_redirect( add_query_arg( array( 'action'=>'awd_lm_email_campaigns', 'edit_email'=>$new_id, 'notice'=>'copied' ), admin_url( 'admin-post.php' ) ) );
	exit;
}

function awd_lm_retry_email_recipient() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', 403 ); }
	$send_id = absint( $_GET['send_id'] ?? 0 );
	check_admin_referer( 'awd_lm_retry_email_recipient_' . $send_id );
	global $wpdb;
	$t = awd_lm_email_tables();
	$failed_send = $wpdb->get_row( $wpdb->prepare( "SELECT id,email_id,recipient_email,status FROM {$t['sends']} WHERE id=%d", $send_id ) );
	if ( ! $failed_send || 'failed' !== $failed_send->status ) { wp_die( 'This delivery cannot be retried.', 400 ); }
	$result = awd_lm_send_email_campaign( $failed_send->email_id, $failed_send->recipient_email );
	if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ), 400 ); }
	$email = $wpdb->get_row( $wpdb->prepare( "SELECT audience_type,list_id FROM {$t['emails']} WHERE id=%d", $failed_send->email_id ) );
	wp_safe_redirect( add_query_arg( array( 'action'=>'awd_lm_email_reports', 'mode'=>$email&&'list'===$email->audience_type?'campaigns':'standalone', 'list_id'=>$email?absint($email->list_id):0, 'email_id'=>absint($failed_send->email_id) ), admin_url( 'admin-post.php' ) ) );
	exit;
}

function awd_lm_save_email_list() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', 403 ); }
	check_admin_referer( 'awd_lm_save_email_list' );
	awd_lm_maybe_upgrade_email_tables();
	global $wpdb;
	$t = awd_lm_email_tables();
	$now = current_time( 'mysql', true );
	$list_id = absint( $_POST['list_id'] ?? 0 );
	$scope = sanitize_key( wp_unslash( $_POST['save_scope'] ?? 'details' ) );
	if ( 'details' === $scope ) {
		$name = substr( sanitize_text_field( wp_unslash( $_POST['list_name'] ?? '' ) ), 0, 190 );
		if ( '' === $name ) { wp_die( 'List name is required.', 400 ); }
		$is_update = $list_id && $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t['lists']} WHERE id=%d", $list_id ) );
		if ( $list_id && ! $is_update ) { wp_die( 'Recipient list not found.', 404 ); }
		if ( $is_update ) {
			if ( false === $wpdb->update( $t['lists'], array( 'name'=>$name ), array( 'id'=>$list_id ) ) ) { wp_die( 'The list could not be updated.', 500 ); }
			$notice = 'list_updated';
		} else {
			$base = sanitize_title( $name ) ?: 'recipient-list';
			$slug = $base;
			$suffix = 2;
			while ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t['lists']} WHERE slug=%s", $slug ) ) ) { $slug = $base . '-' . $suffix++; }
			if ( ! $wpdb->insert( $t['lists'], array( 'name'=>$name, 'slug'=>$slug, 'utm_source'=>'brevo', 'utm_medium'=>'email', 'utm_campaign'=>$slug, 'utm_id'=>'awd-email-list-' . wp_generate_uuid4(), 'created_at'=>$now ) ) ) { wp_die( 'The recipient list could not be created.', 500 ); }
			$list_id = (int) $wpdb->insert_id;
			$notice = 'list_saved';
		}
	} elseif ( 'members' === $scope ) {
		if ( ! $list_id || ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t['lists']} WHERE id=%d", $list_id ) ) ) { wp_die( 'Recipient list not found.', 404 ); }
		$salutations = (array) ( $_POST['member_salutation'] ?? array() );
		$first_names = (array) ( $_POST['member_first_name'] ?? array() );
		$last_names = (array) ( $_POST['member_last_name'] ?? array() );
		$companies = (array) ( $_POST['member_company'] ?? array() );
		$emails = (array) ( $_POST['member_email'] ?? array() );
		$members = array();
		foreach ( $emails as $index => $raw_email ) {
			$raw_email = trim( (string) wp_unslash( $raw_email ) );
			if ( '' === $raw_email ) { continue; }
			$email = strtolower( sanitize_email( $raw_email ) );
			if ( ! is_email( $email ) ) { wp_die( 'One of the recipient email addresses is invalid.', 400 ); }
			$salutation = sanitize_text_field( wp_unslash( $salutations[$index] ?? '' ) );
			if ( $salutation && ! in_array( $salutation, array( 'Herr', 'Frau' ), true ) ) { wp_die( 'The recipient salutation is invalid.', 400 ); }
			$members[ $email ] = array( 'salutation'=>$salutation, 'first_name'=>substr( sanitize_text_field( wp_unslash( $first_names[$index] ?? '' ) ), 0, 100 ), 'last_name'=>substr( sanitize_text_field( wp_unslash( $last_names[$index] ?? '' ) ), 0, 100 ), 'company'=>substr( sanitize_text_field( wp_unslash( $companies[$index] ?? '' ) ), 0, 190 ), 'email'=>$email );
		}
		$wpdb->query( 'START TRANSACTION' );
		try {
			if ( false === $wpdb->delete( $t['members'], array( 'list_id'=>$list_id ) ) ) { throw new RuntimeException( 'Existing recipients could not be updated.' ); }
			foreach ( $members as $member ) {
				if ( ! $wpdb->insert( $t['members'], array( 'list_id'=>$list_id, 'lead_id'=>0, 'salutation'=>$member['salutation'], 'first_name'=>$member['first_name'], 'last_name'=>$member['last_name'], 'company'=>$member['company'], 'email'=>$member['email'], 'created_at'=>$now ) ) ) { throw new RuntimeException( 'A recipient could not be saved.' ); }
			}
			$wpdb->query( 'COMMIT' );
		} catch ( Throwable $error ) {
			$wpdb->query( 'ROLLBACK' );
			wp_die( esc_html( $error->getMessage() ), 500 );
		}
		$notice = 'members_updated';
	} else {
		wp_die( 'Unknown list operation.', 400 );
	}
	wp_safe_redirect( add_query_arg( array( 'action'=>'awd_lm_email_campaigns', 'notice'=>$notice ), admin_url( 'admin-post.php' ) ) . '#recipient-lists' );
	exit;
}

function awd_lm_delete_email_list() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', 403 ); }
	check_admin_referer( 'awd_lm_delete_email_list' );
	awd_lm_maybe_upgrade_email_tables();
	global $wpdb;
	$t = awd_lm_email_tables();
	$list_id = absint( $_POST['list_id'] ?? 0 );
	if ( ! $list_id || ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t['lists']} WHERE id=%d", $list_id ) ) ) { wp_die( 'Recipient list not found.', 404 ); }
	if ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t['emails']} WHERE list_id=%d LIMIT 1", $list_id ) ) ) { wp_die( 'This list is already used by an email and cannot be deleted. Remove or archive the related email first.', 409 ); }
	$wpdb->query( 'START TRANSACTION' );
	try {
		if ( false === $wpdb->delete( $t['members'], array( 'list_id'=>$list_id ) ) ) { throw new RuntimeException( 'The recipients could not be deleted.' ); }
		if ( false === $wpdb->delete( $t['lists'], array( 'id'=>$list_id ) ) ) { throw new RuntimeException( 'The recipient list could not be deleted.' ); }
		$wpdb->query( 'COMMIT' );
	} catch ( Throwable $error ) {
		$wpdb->query( 'ROLLBACK' );
		wp_die( esc_html( $error->getMessage() ), 500 );
	}
	wp_safe_redirect( add_query_arg( array( 'action'=>'awd_lm_email_campaigns', 'notice'=>'list_deleted' ), admin_url( 'admin-post.php' ) ) . '#recipient-lists' );
	exit;
}

function awd_lm_save_email_campaign() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', 403 ); } check_admin_referer( 'awd_lm_save_email_campaign' );
	awd_lm_maybe_upgrade_email_tables(); global $wpdb; $t=awd_lm_email_tables(); $now=current_time('mysql',true);
	$audience_type = 'list' === sanitize_key( wp_unslash( $_POST['audience_type'] ?? '' ) ) ? 'list' : 'standalone';
	$list_id = 'list' === $audience_type ? absint( $_POST['list_id'] ?? 0 ) : 0;
	if ( 'list' === $audience_type && ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t['lists']} WHERE id=%d", $list_id ) ) ) { wp_die( 'Choose a recipient list.', 400 ); }
	$standalone_email = 'standalone' === $audience_type ? strtolower( sanitize_email( wp_unslash( $_POST['standalone_email'] ?? '' ) ) ) : '';
	if ( 'standalone' === $audience_type && ! is_email( $standalone_email ) ) { wp_die( 'Enter a valid standalone recipient email.', 400 ); }
	$standalone_salutation = sanitize_text_field( wp_unslash( $_POST['standalone_salutation'] ?? '' ) );
	$standalone_salutation = in_array( $standalone_salutation, array( 'Herr', 'Frau' ), true ) ? $standalone_salutation : '';
	$name=substr(sanitize_text_field(wp_unslash($_POST['email_name']??'')),0,190);$subject=substr(sanitize_text_field(wp_unslash($_POST['subject']??'')),0,255);$body=sanitize_textarea_field(wp_unslash($_POST['body_text']??''));if(!$name||!$subject||!$body)wp_die('Email name, subject and message are required.',400);
	$labels=(array)($_POST['cta_label']??array());$urls=(array)($_POST['cta_url']??array());$ctas=array();foreach($labels as $i=>$label){$label=substr(sanitize_text_field(wp_unslash($label)),0,190);$url=esc_url_raw(wp_unslash($urls[$i]??''),array('http','https'));if($label&&$url)$ctas[]=array('label'=>$label,'slug'=>sanitize_title($label),'url'=>$url);}if(!$ctas)wp_die('At least one valid CTA is required.',400);
	$email_id=absint($_POST['email_id']??0);$data=array('campaign_id'=>0,'list_id'=>$list_id,'audience_type'=>$audience_type,'standalone_salutation'=>$standalone_salutation,'standalone_first_name'=>substr(sanitize_text_field(wp_unslash($_POST['standalone_first_name']??'')),0,100),'standalone_last_name'=>substr(sanitize_text_field(wp_unslash($_POST['standalone_last_name']??'')),0,100),'standalone_company'=>substr(sanitize_text_field(wp_unslash($_POST['standalone_company']??'')),0,190),'standalone_email'=>$standalone_email,'name'=>$name,'subject'=>$subject,'preheader'=>substr(sanitize_text_field(wp_unslash($_POST['preheader']??'')),0,255),'body_text'=>$body,'cta_links'=>wp_json_encode($ctas),'updated_at'=>$now);if($email_id){$editable=$wpdb->get_row($wpdb->prepare("SELECT e.status,(SELECT COUNT(*) FROM {$t['sends']} s WHERE s.email_id=e.id) sends FROM {$t['emails']} e WHERE e.id=%d",$email_id));if(!$editable||'draft'!==$editable->status||(int)$editable->sends>0)wp_die('This email can no longer be edited. Duplicate it to create a new version.',409);if(false===$wpdb->update($t['emails'],$data,array('id'=>$email_id)))wp_die('The draft could not be updated.',500);}else{$data['slug']=sanitize_title($name);$data['status']='draft';$data['created_at']=$now;$wpdb->insert($t['emails'],$data);$email_id=(int)$wpdb->insert_id;}if(!$email_id)wp_die('The email could not be saved.',500);
	$notice='saved';if(!empty($_POST['send_now'])){$result=awd_lm_send_email_campaign($email_id);if(is_wp_error($result))wp_die(esc_html($result->get_error_message()),400);$notice='sent';}
	if(!empty($_POST['autosave'])){$saved_email=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['emails']} WHERE id=%d",$email_id));$saved_list=$saved_email&&$saved_email->list_id?$wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['lists']} WHERE id=%d",$saved_email->list_id)):null;$generated_links=array();foreach($ctas as $cta)$generated_links[]=awd_lm_email_utm_url($cta['url'],$saved_email,$saved_list,$cta['slug']);wp_send_json_success(array('email_id'=>$email_id,'notice'=>$notice,'generated_links'=>$generated_links,'body_text'=>(string)$saved_email->body_text,'subject'=>(string)$saved_email->subject,'preheader'=>(string)$saved_email->preheader));}
	$redirect=array('action'=>'awd_lm_email_campaigns','notice'=>$notice);if('sent'!==$notice)$redirect['edit_email']=$email_id;wp_safe_redirect(add_query_arg($redirect,admin_url('admin-post.php')));exit;
}

function awd_lm_email_webhook() {
	if('POST'!==strtoupper($_SERVER['REQUEST_METHOD']??'')){status_header(405);exit;}if(absint($_SERVER['CONTENT_LENGTH']??0)>262144){status_header(413);exit;}
	$provided=sanitize_text_field(wp_unslash($_SERVER['HTTP_X_AWD_WEBHOOK_TOKEN']??''));if(!$provided||!hash_equals(awd_lm_email_webhook_token(),$provided)){status_header(403);exit;}
	$payload=json_decode((string)file_get_contents('php://input'),true);if(!is_array($payload)){status_header(400);exit;}global $wpdb;$t=awd_lm_email_tables();
	$custom=sanitize_text_field($payload['X-Mailin-custom']??$payload['x-mailin-custom']??'');preg_match('/awd_send_id:(\d+)/',$custom,$m);$send_id=absint($m[1]??0);if(!$send_id&&!empty($payload['message-id']))$send_id=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$t['sends']} WHERE brevo_message_id=%s",awd_lm_email_message_id($payload['message-id'])));if(!$send_id){status_header(202);exit;}
	$type=sanitize_key($payload['event']??'');$map=array('request'=>'sent','delivered'=>'delivered','opened'=>'opened','unique_opened'=>'unique_opened','click'=>'click','soft_bounce'=>'soft_bounce','hard_bounce'=>'hard_bounce','hardbounce'=>'hard_bounce','softbounce'=>'soft_bounce','spam'=>'spam','unsubscribed'=>'unsubscribed','blocked'=>'blocked','invalid'=>'invalid','deferred'=>'deferred','error'=>'error','proxy_open'=>'proxy_open');$type=$map[$type]??$type;if(!$type){status_header(400);exit;}
	$stamp=absint($payload['ts_event']??$payload['ts']??0);$occurred=$stamp?gmdate('Y-m-d H:i:s',$stamp):current_time('mysql',true);$key=hash('sha256',$send_id.'|'.$type.'|'.$occurred.'|'.($payload['url']??$payload['URL']??''));$wpdb->insert($t['events'],array('event_key'=>$key,'send_id'=>$send_id,'event_type'=>$type,'occurred_at'=>$occurred,'clicked_url'=>esc_url_raw($payload['url']??$payload['URL']??''),'metadata'=>wp_json_encode(array('device'=>sanitize_text_field($payload['device_used']??''),'reason'=>sanitize_text_field($payload['reason']??$payload['description']??'')))));$wpdb->update($t['sends'],array('status'=>$type,'updated_at'=>$occurred),array('id'=>$send_id));
	if(in_array($type,array('unsubscribed','spam','hard_bounce','invalid','blocked'),true)&&!empty($payload['email']))awd_lm_email_unsubscribe_address($payload['email'],'brevo_'.$type);wp_send_json_success();
}

function awd_lm_email_unsubscribe_address($email,$source='link'){
	global $wpdb;$email=strtolower(sanitize_email($email));if(!$email)return false;$lt=awd_lm_lead_tables();$now=current_time('mysql',true);$leads=$wpdb->get_col($wpdb->prepare("SELECT id FROM {$lt['leads']} WHERE email=%s",$email));foreach($leads as $id){$wpdb->update($lt['leads'],array('newsletter_status'=>'unsubscribed','updated_at'=>$now),array('id'=>$id));$wpdb->insert($lt['consents'],array('lead_id'=>$id,'created_at'=>$now,'event_type'=>'newsletter_unsubscribed','consent_text'=>$source,'language'=>'','source_url'=>''));}$s=awd_lm_brevo_settings();if(!empty($s['api_key'])&&!empty($s['list_id'])){wp_remote_request('https://api.brevo.com/v3/contacts/'.rawurlencode($email),array('method'=>'PUT','timeout'=>10,'headers'=>array('accept'=>'application/json','api-key'=>$s['api_key'],'content-type'=>'application/json'),'body'=>wp_json_encode(array('unlinkListIds'=>array(absint($s['list_id']))))));}return true;
}

function awd_lm_email_unsubscribe(){ $token=sanitize_text_field(wp_unslash($_REQUEST['token']??''));$email=awd_lm_email_from_unsubscribe_token($token);if(!$email)wp_die('Invalid unsubscribe link.',400);$done=false;if('POST'===strtoupper($_SERVER['REQUEST_METHOD']??'GET')){awd_lm_email_unsubscribe_address($email);$done=true;}?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Unsubscribe</title></head><body style="font:16px/1.5 system-ui;max-width:680px;margin:60px auto;padding:20px"><h1><?php echo $done?'Unsubscribed':'Unsubscribe';?></h1><?php if($done):?><p>You will no longer receive these emails. / Sie wurden abgemeldet. / Vous êtes désabonné.</p><?php else:?><p>Please confirm your unsubscribe request. / Bitte bestätigen Sie Ihre Abmeldung. / Veuillez confirmer votre désinscription.</p><form method="post"><input type="hidden" name="action" value="awd_lm_email_unsubscribe"><input type="hidden" name="token" value="<?php echo esc_attr($token);?>"><button type="submit" style="padding:11px 16px;background:#142a42;color:#fff;border:0;border-radius:5px;font-weight:700">Confirm unsubscribe</button></form><?php endif;?></body></html><?php exit; }

function awd_lm_register_brevo_webhook(){if(!current_user_can('manage_options'))wp_die('Forbidden',403);check_admin_referer('awd_lm_register_brevo_webhook');$s=awd_lm_brevo_settings();if(empty($s['api_key']))wp_die('Brevo API key is required.',400);$payload=array('description'=>'AWD transactional email events','url'=>admin_url('admin-post.php?action=awd_lm_email_webhook'),'events'=>array('request','delivered','hardBounce','softBounce','blocked','spam','invalid','deferred','click','opened','uniqueOpened','unsubscribed'),'type'=>'transactional','channel'=>'email','headers'=>array(array('key'=>'X-AWD-Webhook-Token','value'=>awd_lm_email_webhook_token())));$r=wp_remote_post('https://api.brevo.com/v3/webhooks',array('timeout'=>20,'headers'=>array('accept'=>'application/json','api-key'=>$s['api_key'],'content-type'=>'application/json'),'body'=>wp_json_encode($payload)));$code=is_wp_error($r)?0:wp_remote_retrieve_response_code($r);if($code<200||$code>=300)wp_die(esc_html(is_wp_error($r)?$r->get_error_message():wp_remote_retrieve_body($r)),400);$body=json_decode(wp_remote_retrieve_body($r),true);update_option('awd_lm_brevo_webhook_id',absint($body['id']??0),false);wp_safe_redirect(admin_url('admin-post.php?action=awd_lm_form_settings&awd_form_notice=webhook'));exit;}
