<?php
/**
 * Plugin Name: AWD Landing Manager
 * Description: Content-only multilingual editor and static publisher for AWD landing pages.
 * Version: 1.20.52
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: AWD
 * Text Domain: awd-landing-manager
 */

defined( 'ABSPATH' ) || exit;

define( 'AWD_LM_VERSION', '1.20.53' );
define( 'AWD_LM_FILE', __FILE__ );
define( 'AWD_LM_DIR', plugin_dir_path( __FILE__ ) );
define( 'AWD_LM_URL', plugin_dir_url( __FILE__ ) );

require_once AWD_LM_DIR . 'includes/landing-registry.php';
require_once AWD_LM_DIR . 'includes/schema.php';
require_once AWD_LM_DIR . 'includes/prototype-translations.php';
require_once AWD_LM_DIR . 'includes/urls.php';
require_once AWD_LM_DIR . 'includes/helpers.php';
require_once AWD_LM_DIR . 'includes/admin-navigation.php';
require_once AWD_LM_DIR . 'includes/language.php';
require_once AWD_LM_DIR . 'includes/renderer.php';
require_once AWD_LM_DIR . 'includes/publisher.php';
require_once AWD_LM_DIR . 'includes/pages.php';
require_once AWD_LM_DIR . 'includes/routing.php';
require_once AWD_LM_DIR . 'includes/forms.php';
require_once AWD_LM_DIR . 'includes/leads.php';
require_once AWD_LM_DIR . 'includes/analytics.php';
require_once AWD_LM_DIR . 'includes/analytics-dashboard.php';
require_once AWD_LM_DIR . 'includes/email-campaigns.php';
require_once AWD_LM_DIR . 'includes/leads-tool-integration.php';
require_once AWD_LM_DIR . 'includes/privacy-cookies.php';
require_once AWD_LM_DIR . 'includes/content-backup.php';

if ( is_admin() ) {
	require_once AWD_LM_DIR . 'admin/admin-page.php';
}

register_activation_hook( __FILE__, 'awd_lm_activate' );
register_deactivation_hook( __FILE__, 'awd_lm_deactivate' );

function awd_lm_activate() {
	foreach ( awd_lm_definitions() as $key => $definition ) {
		if ( false === get_option( $definition['option'], false ) ) {
			add_option( $definition['option'], awd_lm_default_content_for( $key ), '', false );
		}
	}
	awd_lm_ensure_landing_pages();
	awd_lm_install_lead_tables();
	awd_lm_install_analytics_tables();
	awd_lm_install_email_tables();
	awd_lm_schedule_brevo_queue();
	awd_lm_refresh_published_assets();
	awd_lm_add_rewrite_rules();
	flush_rewrite_rules();
}

function awd_lm_deactivate() {
	wp_clear_scheduled_hook( 'awd_lm_process_brevo_queue' );
	wp_clear_scheduled_hook( 'awd_lm_process_brevo_queue_once' );
}

add_action( 'init', 'awd_lm_add_rewrite_rules' );
add_action( 'init', 'awd_lm_maybe_serve_content_backup', -100 );
add_action( 'init', 'awd_lm_repair_hero_text_keys_after_mock_removal', 1 );
add_action( 'init', 'awd_lm_restore_corrupted_text_layer_after_mock_removal', 2 );
add_action( 'init', 'awd_lm_migrate_section_keys_after_section_split', 3 );
add_action( 'init', 'awd_lm_ensure_css_profile_presets', 3 );
add_action( 'init', 'awd_lm_maybe_upgrade_lead_tables', 4 );
add_action( 'init', 'awd_lm_schedule_brevo_queue', 5 );
add_action( 'init', 'awd_lm_maybe_upgrade_brevo_contact_payload', 6 );
add_action( 'init', 'awd_lm_maybe_upgrade_analytics_tables', 7 );
add_action( 'init', 'awd_lm_maybe_upgrade_email_tables', 8 );
add_action( 'init', 'awd_lm_maybe_clone_restaurants_ordering_system', 9 );
add_action( 'init', 'awd_lm_maybe_set_restaurants_page_title', 10 );
add_action( 'parse_request', 'awd_lm_intercept_landing_path', 0 );
add_action( 'parse_request', 'awd_lm_intercept_cookie_policy_path', 1 );
add_action( 'admin_init', 'awd_lm_maybe_ensure_landing_pages' );
add_action( 'admin_init', 'awd_lm_maybe_refresh_published_assets', -10 );
add_action( 'admin_init', 'awd_lm_start_admin_navigation_buffer', 0 );
add_action( 'admin_init', 'awd_lm_route_visual_editor', 1 );
add_action( 'admin_menu', 'awd_lm_register_admin_page' );
add_action( 'admin_enqueue_scripts', 'awd_lm_admin_assets' );
add_action( 'admin_post_awd_lm_save', 'awd_lm_handle_save' );
add_action( 'admin_post_awd_lm_publish', 'awd_lm_handle_publish' );
add_action( 'admin_post_awd_lm_reset_text_defaults', 'awd_lm_reset_text_defaults' );
add_action( 'admin_post_awd_lm_visual_editor', 'awd_lm_visual_editor' );
add_action( 'admin_post_awd_lm_css_editor', 'awd_lm_css_editor' );
add_action( 'admin_post_awd_lm_save_css', 'awd_lm_save_css' );
add_action( 'admin_post_awd_lm_create_css_profile', 'awd_lm_create_css_profile' );
add_action( 'admin_post_awd_lm_activate_css_profile', 'awd_lm_activate_css_profile' );
add_action( 'admin_post_awd_lm_form_settings', 'awd_lm_form_settings_page' );
add_action( 'admin_post_awd_lm_urls_settings', 'awd_lm_urls_settings_page' );
add_action( 'admin_post_awd_lm_save_urls', 'awd_lm_save_urls' );
add_action( 'admin_post_awd_lm_generate_content_backup_key', 'awd_lm_generate_content_backup_key' );
add_action( 'admin_post_awd_lm_revoke_content_backup_key', 'awd_lm_revoke_content_backup_key' );
add_action( 'admin_post_awd_lm_privacy_cookies', 'awd_lm_privacy_cookies_page' );
add_action( 'admin_post_awd_lm_save_privacy_cookies', 'awd_lm_save_privacy_cookies' );
add_action( 'admin_post_awd_lm_save_form_settings', 'awd_lm_save_form_settings' );
add_action( 'admin_post_awd_lm_leads', 'awd_lm_leads_page' );
add_action( 'admin_post_awd_lm_export_leads', 'awd_lm_export_leads' );
add_action( 'admin_post_awd_lm_retry_brevo', 'awd_lm_retry_brevo' );
add_action( 'admin_post_awd_lm_sync_lead_brevo', 'awd_lm_sync_lead_brevo' );
add_action( 'admin_post_awd_lm_update_lead', 'awd_lm_update_lead' );
add_action( 'admin_post_awd_lm_anonymize_lead', 'awd_lm_anonymize_lead' );
add_action( 'admin_post_awd_lm_analytics', 'awd_lm_analytics_page' );
add_action( 'admin_post_awd_lm_email_campaigns', 'awd_lm_email_campaigns_page' );
add_action( 'admin_post_awd_lm_email_reports', 'awd_lm_email_reports_page' );
add_action( 'admin_post_awd_lm_copy_email', 'awd_lm_copy_email' );
add_action( 'admin_post_awd_lm_retry_email_recipient', 'awd_lm_retry_email_recipient' );
add_action( 'admin_post_awd_lm_save_email_list', 'awd_lm_save_email_list' );
add_action( 'admin_post_awd_lm_delete_email_list', 'awd_lm_delete_email_list' );
add_action( 'admin_post_awd_lm_save_email_campaign', 'awd_lm_save_email_campaign' );
add_action( 'wp_ajax_awd_lm_email_preview_recipients', 'awd_lm_email_preview_recipients' );
add_action( 'wp_ajax_awd_lm_save_email_draft', 'awd_lm_save_email_campaign' );
add_action( 'admin_post_awd_lm_save_leads_tool_connection', 'awd_lm_save_leads_tool_connection' );
add_action( 'admin_post_awd_lm_import_leads_tool_list', 'awd_lm_import_leads_tool_list' );
add_action( 'admin_post_awd_lm_register_brevo_webhook', 'awd_lm_register_brevo_webhook' );
add_action( 'admin_post_awd_lm_email_webhook', 'awd_lm_email_webhook' );
add_action( 'admin_post_nopriv_awd_lm_email_webhook', 'awd_lm_email_webhook' );
add_action( 'admin_post_awd_lm_email_unsubscribe', 'awd_lm_email_unsubscribe' );
add_action( 'admin_post_nopriv_awd_lm_email_unsubscribe', 'awd_lm_email_unsubscribe' );
add_action( 'admin_post_awd_lm_track', 'awd_lm_handle_analytics_track' );
add_action( 'admin_post_nopriv_awd_lm_track', 'awd_lm_handle_analytics_track' );
add_action( 'admin_post_awd_lm_submit_demo', 'awd_lm_handle_demo_form' );
add_action( 'admin_post_nopriv_awd_lm_submit_demo', 'awd_lm_handle_demo_form' );
add_action( 'template_redirect', 'awd_lm_handle_cookie_policy_front_request', -1 );
add_action( 'template_redirect', 'awd_lm_handle_front_request', 0 );
add_filter( 'nav_menu_link_attributes', 'awd_lm_localize_menu_landing_link', 10, 2 );
add_action( 'awd_lm_process_brevo_queue', 'awd_lm_process_brevo_queue' );
add_action( 'awd_lm_process_brevo_queue_once', 'awd_lm_process_brevo_queue' );

/**
 * Turn the former scaffold into an independent snapshot of the established
 * landing. Run once per installation; later edits never cross between options.
 */
function awd_lm_maybe_clone_restaurants_ordering_system() {
	if ( get_option( 'awd_lm_restaurants_ordering_system_cloned_1', false ) ) { return; }
	$source = awd_lm_get_content( 'gloriafood' );
	if ( empty( $source ) ) { return; }
	update_option( 'awd_landing_second_landing', $source, false );
	update_option( 'awd_lm_url_settings_second-landing', array(
		'slugs' => array_fill_keys( array_keys( awd_lm_languages() ), 'restaurants-ordering-system' ),
		'history' => array_fill_keys( array_keys( awd_lm_languages() ), array( array( 'slug' => 'second-landing', 'changed_at' => current_time( 'mysql' ) ) ) ),
	), false );
	foreach ( array_keys( awd_lm_languages() ) as $language ) { awd_publish_landing( 'second-landing', $language ); }
	update_option( 'awd_lm_restaurants_ordering_system_cloned_1', 1, false );
	flush_rewrite_rules( false );
}

/** Set the requested browser/SEO title once without affecting later edits. */
function awd_lm_maybe_set_restaurants_page_title() {
	if ( get_option( 'awd_lm_restaurants_page_title_1', false ) ) { return; }
	$content = get_option( 'awd_landing_second_landing', array() );
	if ( ! is_array( $content ) ) { return; }
	foreach ( array_keys( awd_lm_languages() ) as $language ) {
		$content['seo']['title'][ $language ] = 'AI Will Do Restaurants';
	}
	update_option( 'awd_landing_second_landing', $content, false );
	foreach ( array_keys( awd_lm_languages() ) as $language ) {
		awd_publish_landing( 'second-landing', $language );
	}
	update_option( 'awd_lm_restaurants_page_title_1', 1, false );
}
