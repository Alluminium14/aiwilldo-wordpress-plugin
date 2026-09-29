<?php
defined( 'ABSPATH' ) || exit;

function awd_lm_content_backup_key_exists() {
	return '' !== (string) get_option( 'awd_lm_content_backup_key_hash', '' );
}

function awd_lm_content_backup_panel() {
	$configured = awd_lm_content_backup_key_exists();
	?>
	<section class="card" id="content-backup">
		<h2>Content backup</h2>
		<p>Exports the exact Landing Manager content model: multilingual text keys, links, image references, menus, section visibility, URL settings and cookie-policy copy.</p>
		<p><strong>Status:</strong> <?php echo $configured ? '<span class="connection-tag">Backup access configured</span>' : 'No backup key yet.'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
		<?php if ( $configured ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Replace the existing backup key? The local backup tool must then be configured again.')">
				<input type="hidden" name="action" value="awd_lm_generate_content_backup_key"><?php wp_nonce_field( 'awd_lm_generate_content_backup_key' ); ?>
				<button type="submit">Replace backup key</button>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:10px" onsubmit="return confirm('Disable automatic content backups?')">
				<input type="hidden" name="action" value="awd_lm_revoke_content_backup_key"><?php wp_nonce_field( 'awd_lm_revoke_content_backup_key' ); ?>
				<button type="submit" style="background:#8b3028">Disable backup access</button>
			</form>
		<?php else : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="awd_lm_generate_content_backup_key"><?php wp_nonce_field( 'awd_lm_generate_content_backup_key' ); ?>
				<button type="submit">Create backup key</button>
			</form>
		<?php endif; ?>
	</section>
	<?php
}

function awd_lm_generate_content_backup_key() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', 403 ); }
	check_admin_referer( 'awd_lm_generate_content_backup_key' );
	$key = 'awdb_' . bin2hex( random_bytes( 24 ) );
	update_option( 'awd_lm_content_backup_key_hash', wp_hash_password( $key ), false );
	?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Backup key</title><style>body{margin:30px;background:#f5f8f6;color:#182019;font:15px/1.5 system-ui}.card{max-width:760px;margin:auto;padding:26px;background:#fff;border:1px solid #d6e8dc;border-radius:14px}code{display:block;overflow-wrap:anywhere;padding:14px;background:#e8f7ed;border-radius:8px;font:14px/1.5 ui-monospace,monospace}a{display:inline-block;margin-top:12px;padding:10px 14px;border-radius:8px;background:#008b55;color:#fff;text-decoration:none;font-weight:800}</style></head><body><main class="card"><h1>Backup key created</h1><p>Copy this key now. WordPress stores only its secure hash, so the key cannot be shown again.</p><code><?php echo esc_html( $key ); ?></code><p>Run <strong>Configure Production Backup Key</strong> on the local computer and paste this value. Then the desktop backup tool will include <code>content-model.json</code>.</p><a href="<?php echo esc_url( admin_url( 'admin-post.php?action=awd_lm_urls_settings#content-backup' ) ); ?>">Back to Settings</a></main></body></html><?php
	exit;
}

function awd_lm_revoke_content_backup_key() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', 403 ); }
	check_admin_referer( 'awd_lm_revoke_content_backup_key' );
	delete_option( 'awd_lm_content_backup_key_hash' );
	wp_safe_redirect( admin_url( 'admin-post.php?action=awd_lm_urls_settings#content-backup' ) );
	exit;
}

function awd_lm_content_backup_payload() {
	$options = array(
		'awd_lm_privacy_cookies' => get_option( 'awd_lm_privacy_cookies', array() ),
		'awd_lm_css_profiles'    => get_option( 'awd_lm_css_profiles', array() ),
		'awd_lm_css_profiles_backup_before_single_stylesheet_2' => get_option( 'awd_lm_css_profiles_backup_before_single_stylesheet_2', array() ),
	);
	foreach ( awd_lm_definitions() as $key => $definition ) {
		$options[ $definition['option'] ] = get_option( $definition['option'], array() );
		$url_option = 'gloriafood' === $key ? 'awd_lm_url_settings' : 'awd_lm_url_settings_' . $key;
		$options[ $url_option ] = get_option( $url_option, array() );
	}
	return array(
		'format'       => 'awd-landing-manager-content-backup',
		'format_version'=> 1,
		'plugin_version'=> AWD_LM_VERSION,
		'site_url'     => home_url( '/' ),
		'exported_at'  => gmdate( 'c' ),
		'options'      => $options,
	);
}

function awd_lm_maybe_serve_content_backup() {
	if ( ! isset( $_GET['awd_lm_content_backup'] ) ) { return; }
	$stored_hash = (string) get_option( 'awd_lm_content_backup_key_hash', '' );
	$key = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_AWD_BACKUP_KEY'] ?? '' ) );
	if ( '' === $stored_hash || '' === $key || ! wp_check_password( $key, $stored_hash ) ) {
		status_header( 401 );
		header( 'Content-Type: application/json; charset=utf-8' );
		echo wp_json_encode( array( 'error' => 'unauthorized' ) );
		exit;
	}
	nocache_headers();
	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="awd-landing-content-' . gmdate( 'Y-m-d-His' ) . '.json"' );
	echo wp_json_encode( awd_lm_content_backup_payload(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	exit;
}
