<?php
defined( 'ABSPATH' ) || exit;

function awd_lm_leads_tool_settings() {
	$value = get_option( 'awd_lm_leads_tool_settings', array() );
	return wp_parse_args( is_array( $value ) ? $value : array(), array( 'base_url'=>'', 'token'=>'', 'tenant_name'=>'' ) );
}

function awd_lm_leads_tool_request( $path, $settings = null ) {
	$settings = is_array( $settings ) ? $settings : awd_lm_leads_tool_settings();
	if ( empty( $settings['base_url'] ) || empty( $settings['token'] ) ) { return new WP_Error( 'leads_tool_not_configured', 'Lead Manager connection is not configured.' ); }
	$url = trailingslashit( $settings['base_url'] ) . ltrim( $path, '/' );
	$response = wp_remote_get( $url, array( 'timeout'=>20, 'redirection'=>2, 'headers'=>array( 'accept'=>'application/json', 'authorization'=>'Bearer ' . $settings['token'] ) ) );
	if ( is_wp_error( $response ) ) { return $response; }
	$code = wp_remote_retrieve_response_code( $response );
	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( $code < 200 || $code >= 300 || ! is_array( $data ) ) { return new WP_Error( 'leads_tool_api', sanitize_text_field( $data['error'] ?? ( 'Lead Manager returned HTTP ' . $code ) ) ); }
	return $data;
}

function awd_lm_save_leads_tool_connection() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', 403 ); }
	check_admin_referer( 'awd_lm_save_leads_tool_connection' );
	$current = awd_lm_leads_tool_settings();
	$base_url = untrailingslashit( esc_url_raw( wp_unslash( $_POST['leads_tool_url'] ?? '' ), array( 'http','https' ) ) );
	$token = trim( sanitize_text_field( wp_unslash( $_POST['leads_tool_token'] ?? '' ) ) );
	if ( '' === $token ) { $token = $current['token']; }
	if ( ! $base_url || ! $token ) { wp_die( 'Lead Manager URL and connection key are required.', 400 ); }
	$candidate = array( 'base_url'=>$base_url, 'token'=>$token, 'tenant_name'=>'' );
	$result = awd_lm_leads_tool_request( '/api/integrations/wordpress/lists', $candidate );
	if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ), 400 ); }
	$candidate['tenant_name'] = sanitize_text_field( $result['tenant']['name'] ?? '' );
	update_option( 'awd_lm_leads_tool_settings', $candidate, false );
	wp_safe_redirect( add_query_arg( array( 'action'=>'awd_lm_urls_settings', 'lead_tool_notice'=>'connected' ), admin_url( 'admin-post.php' ) ) . '#lead-manager' );
	exit;
}

function awd_lm_create_import_list( $name ) {
	global $wpdb;
	$t = awd_lm_email_tables();
	$now = current_time( 'mysql', true );
	$base = sanitize_title( $name ) ?: 'lead-manager-list';
	$slug = $base;
	$suffix = 2;
	while ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t['lists']} WHERE slug=%s", $slug ) ) ) { $slug = $base . '-' . $suffix++; }
	$wpdb->insert( $t['lists'], array( 'name'=>substr( sanitize_text_field( $name ), 0, 190 ), 'slug'=>$slug, 'utm_source'=>'brevo', 'utm_medium'=>'email', 'utm_campaign'=>$slug, 'utm_id'=>'awd-email-list-' . wp_generate_uuid4(), 'created_at'=>$now ) );
	return (int) $wpdb->insert_id;
}

function awd_lm_import_leads_tool_members( $local_list_id, $leads ) {
	global $wpdb;
	$t = awd_lm_email_tables();
	$now = current_time( 'mysql', true );
	$counts = array( 'imported'=>0, 'duplicates'=>0, 'missing_email'=>0 );
	foreach ( (array) $leads as $lead ) {
		$email = strtolower( sanitize_email( $lead['email'] ?? '' ) );
		if ( ! is_email( $email ) ) { $counts['missing_email']++; continue; }
		$inserted = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$t['members']} (list_id,lead_id,first_name,last_name,company,email,created_at) VALUES (%d,0,%s,%s,%s,%s,%s)", $local_list_id, substr( sanitize_text_field( $lead['first_name'] ?? '' ), 0, 100 ), substr( sanitize_text_field( $lead['last_name'] ?? '' ), 0, 100 ), substr( sanitize_text_field( $lead['company'] ?? '' ), 0, 190 ), $email, $now ) );
		if ( $inserted ) { $counts['imported']++; } else { $counts['duplicates']++; }
	}
	return $counts;
}

function awd_lm_import_leads_tool_list() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', 403 ); }
	check_admin_referer( 'awd_lm_import_leads_tool_list' );
	awd_lm_maybe_upgrade_email_tables();
	$remote_list_id = absint( $_POST['remote_list_id'] ?? 0 );
	$remote_stage = in_array( sanitize_key( wp_unslash( $_POST['remote_stage'] ?? '' ) ), array( 'lead','client' ), true ) ? sanitize_key( wp_unslash( $_POST['remote_stage'] ) ) : 'lead';
	if ( ! $remote_list_id ) { wp_die( 'Choose a Lead Manager list.', 400 ); }
	$result = awd_lm_leads_tool_request( '/api/integrations/wordpress/lists/' . $remote_list_id . '/leads?stage=' . rawurlencode( $remote_stage ) );
	if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ), 400 ); }
	$destination = sanitize_text_field( wp_unslash( $_POST['local_list_id'] ?? 'new' ) );
	$local_list_id = 'new' === $destination ? awd_lm_create_import_list( $result['list']['name'] ?? 'Lead Manager import' ) : absint( $destination );
	global $wpdb;
	$t = awd_lm_email_tables();
	if ( ! $local_list_id || ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t['lists']} WHERE id=%d", $local_list_id ) ) ) { wp_die( 'Choose a valid local recipient list.', 400 ); }
	$counts = awd_lm_import_leads_tool_members( $local_list_id, $result['leads'] ?? array() );
	wp_safe_redirect( add_query_arg( array( 'action'=>'awd_lm_email_campaigns', 'lead_tool_notice'=>'imported', 'imported'=>$counts['imported'], 'duplicates'=>$counts['duplicates'], 'missing_email'=>$counts['missing_email'], 'local_list'=>$local_list_id ), admin_url( 'admin-post.php' ) ) );
	exit;
}

function awd_lm_leads_tool_panel( $local_lists ) {
	$settings = awd_lm_leads_tool_settings();
	$remote = $settings['token'] ? awd_lm_leads_tool_request( '/api/integrations/wordpress/lists', $settings ) : null;
	$notice = sanitize_key( wp_unslash( $_GET['lead_tool_notice'] ?? '' ) );
	if ( 'imported' === $notice ) {
		$missing = absint( $_GET['missing_email'] ?? 0 );
		echo '<p class="notice">Imported: <strong>' . esc_html( absint( $_GET['imported'] ?? 0 ) ) . '</strong>. Existing duplicates skipped: <strong>' . esc_html( absint( $_GET['duplicates'] ?? 0 ) ) . '</strong>.</p>';
		if ( $missing ) { echo '<p class="notice" style="background:#fff1ed;color:#8b3028"><strong>Import warning:</strong> ' . esc_html( $missing ) . ' lead(s) were discarded because they have no valid email address.</p>'; }
	}
	?>
	<section class="card"><h2>Import from Lead Manager</h2><p>Choose an exact Lead Manager list and pull contacts into a local recipient list. Repeated pulls do not create duplicates.</p>
	<?php if ( ! $settings['token'] ) : ?><p class="notice">Lead Manager is not connected. <a href="<?php echo esc_url( admin_url( 'admin-post.php?action=awd_lm_urls_settings#lead-manager' ) ); ?>">Configure it in Settings</a>.</p><?php elseif ( is_wp_error( $remote ) ) : ?><p class="notice" style="background:#fff1ed;color:#8b3028"><?php echo esc_html( $remote->get_error_message() ); ?> <a href="<?php echo esc_url( admin_url( 'admin-post.php?action=awd_lm_urls_settings#lead-manager' ) ); ?>">Check connection settings</a>.</p><?php elseif ( is_array( $remote ) ) : ?><p><span class="tag">Connected tenant: <?php echo esc_html( $settings['tenant_name'] ?: 'Lead Manager' ); ?></span></p><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="awd_lm_import_leads_tool_list"><?php wp_nonce_field( 'awd_lm_import_leads_tool_list' ); ?><div class="grid"><div><label>Lead Manager source list</label><select id="remoteLeadList" name="remote_list_id" required><option value="">Choose…</option><?php foreach ( (array) ( $remote['lists'] ?? array() ) as $list ) : ?><option value="<?php echo esc_attr( $list['id'] ); ?>" data-stage="<?php echo esc_attr( $list['stage'] ); ?>"><?php echo esc_html( $list['name'] . ' · ' . $list['stage'] . ' (' . $list['lead_count'] . ')' ); ?></option><?php endforeach; ?></select><input type="hidden" id="remoteLeadStage" name="remote_stage" value="lead"></div><div><label>Local recipient list</label><select name="local_list_id"><option value="new">Create a new local list with the same name</option><?php foreach ( (array) $local_lists as $list ) : ?><option value="<?php echo esc_attr( $list->id ); ?>"><?php echo esc_html( $list->name ); ?></option><?php endforeach; ?></select></div></div><p><button type="submit">Pull leads into list</button></p></form><script>document.getElementById('remoteLeadList').addEventListener('change',function(){const option=this.options[this.selectedIndex];document.getElementById('remoteLeadStage').value=option.dataset.stage||'lead';});</script><?php endif; ?></section>
	<?php
}

function awd_lm_leads_tool_connection_panel() {
	$settings = awd_lm_leads_tool_settings();
	$notice = sanitize_key( wp_unslash( $_GET['lead_tool_notice'] ?? '' ) );
	?>
	<section class="card" id="lead-manager"><h2>Lead Manager connection</h2><p>Connect this WordPress installation to one Lead Manager tenant. List selection and importing remain in Emails.</p><?php if ( 'connected' === $notice ) : ?><p class="notice">Lead Manager connected. The key is scoped to <strong><?php echo esc_html( $settings['tenant_name'] ?: 'the selected tenant' ); ?></strong>.</p><?php endif; ?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="awd_lm_save_leads_tool_connection"><?php wp_nonce_field( 'awd_lm_save_leads_tool_connection' ); ?><div class="lead-tool-grid"><div><label>Lead Manager URL</label><input type="url" name="leads_tool_url" required value="<?php echo esc_attr( $settings['base_url'] ); ?>" placeholder="https://leads.example.com"></div><div><label>Tenant connection key</label><input type="password" name="leads_tool_token" placeholder="<?php echo $settings['token'] ? 'Saved — leave blank to keep it' : 'ltw_…'; ?>"></div></div><p><button type="submit">Test &amp; save connection</button><?php if ( $settings['tenant_name'] ) : ?> <span class="connection-tag">Connected tenant: <?php echo esc_html( $settings['tenant_name'] ); ?></span><?php endif; ?></p></form></section>
	<?php
}
