<?php
defined( 'ABSPATH' ) || exit;
$landing_key = isset( $key ) && awd_lm_definition( $key ) ? sanitize_key( $key ) : 'gloriafood';
$landing_root_class = 'gloriafood' === $landing_key ? 'awd-landing--gloriafood' : 'awd-landing--' . sanitize_html_class( $landing_key );
$brand_name = sanitize_text_field( $content['brand_name'] ?? 'website-to-go' );
if ( '' === $brand_name ) { $brand_name = 'website-to-go'; }
$page_title = str_ireplace( 'website-to-go', $brand_name, awd_field( 'seo.title', $lang ) );
$page_description = str_ireplace( 'website-to-go', $brand_name, awd_field( 'seo.description', $lang ) );
$consent_config = apply_filters( 'awd_lm_consent_frontend_config', array(
	'cookieName'        => 'awd_consent',
	'version'           => '1',
	'lifetimeDays'      => 180,
	'policyUrl'         => $content['prototype_links'][ $lang ]['l902'] ?? 'https://website-to-go.ch/cookie-richtlinie-eu/',
	'showFooterControl' => true,
), $lang, $content, $key );
?>
<!doctype html>
<html lang="<?php echo esc_attr( $lang ); ?>">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<title><?php echo esc_html( $page_title ); ?></title>
	<meta name="description" content="<?php echo esc_attr( $page_description ); ?>">
	<meta name="robots" content="<?php echo ! empty( $preview ) ? 'noindex,nofollow' : 'index,follow'; ?>">
	<link rel="canonical" href="<?php echo esc_url( $canonical ); ?>">
	<?php wp_site_icon(); ?>
	<?php foreach ( awd_lm_languages() as $code => $label ) : ?><link rel="alternate" hreflang="<?php echo esc_attr( $code ); ?>" href="<?php echo esc_url( awd_lm_public_url( $landing_key, $code ) ); ?>"><?php endforeach; ?>
	<link rel="alternate" hreflang="x-default" href="<?php echo esc_url( awd_lm_public_url( $landing_key, 'de' ) ); ?>">
	<meta property="og:type" content="website"><meta property="og:title" content="<?php echo esc_attr( $page_title ); ?>"><meta property="og:description" content="<?php echo esc_attr( $page_description ); ?>"><meta property="og:url" content="<?php echo esc_url( $canonical ); ?>">
	<?php if ( ! empty( $editor ) ) : ?>
		<!-- Preview the selected landing style; the editor stylesheet loads last and protects its controls. -->
		<link rel="stylesheet" href="<?php echo esc_url( awd_lm_landing_css_url( $landing_key ) ); ?>">
	<?php else : ?>
		<!-- Base CSS is shipped with the plugin, so plugin updates reach every site. -->
		<link rel="stylesheet" href="<?php echo esc_url( awd_lm_landing_css_url( $landing_key ) ); ?>">
	<?php endif; ?>
	<?php if ( ! empty( $editor ) ) : ?><link rel="stylesheet" href="<?php echo esc_url( AWD_LM_URL . 'admin/visual-editor.css?ver=' . $asset_version ); ?>"><?php endif; ?>
	<?php if ( awd_lm_turnstile_enabled() ) : ?><script src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit" async defer></script><?php endif; ?>
</head>
<body class="<?php echo esc_attr( $landing_root_class ); ?><?php echo ! empty( $editor ) ? ' awd-lm-visual' : ''; ?>">
<?php if ( ! empty( $editor ) ) : ?>
<input type="hidden" form="awd-lm-visual-form" name="brand_original" value="<?php echo esc_attr( $content['brand_name'] ?? 'website-to-go' ); ?>">
<form id="awd-lm-visual-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="awd_lm_save"><input type="hidden" name="landing" value="<?php echo esc_attr( $landing_key ); ?>"><input type="hidden" name="editor_lang" value="<?php echo esc_attr( $lang ); ?>"><?php wp_nonce_field( 'awd_lm_save_' . $landing_key ); ?></form>
<div class="awd-lm-toolbar">
	<div class="awd-lm-toolbar-brand">
		<a class="awd-lm-back" href="<?php echo esc_url( admin_url() ); ?>">← WordPress</a>
		<span class="awd-lm-toolbar-divider" aria-hidden="true"></span>
		<strong><span class="awd-lm-toolbar-dot" aria-hidden="true"></span>Landing Manager</strong>
		<label class="awd-lm-landing-picker"><span>Landing</span><select data-awd-landing-switcher aria-label="Select landing page"><?php foreach ( awd_lm_definitions() as $switch_key => $landing_definition ) : ?><option value="<?php echo esc_url( add_query_arg( array( 'action' => 'awd_lm_visual_editor', 'landing' => $switch_key, 'lang' => $lang ), admin_url( 'admin-post.php' ) ) ); ?>" <?php selected( $landing_key, $switch_key ); ?>><?php echo esc_html( $landing_definition['name'] ); ?></option><?php endforeach; ?></select></label>
		<label class="awd-lm-brand-name" for="awd-lm-brand-name"><span>Brand</span><input id="awd-lm-brand-name" form="awd-lm-visual-form" name="brand_name" maxlength="80" value="<?php echo esc_attr( $content['brand_name'] ?? 'website-to-go' ); ?>" aria-label="Brand name used across the landing"></label>
	</div>
	<div class="awd-lm-toolbar-actions"><div class="awd-lm-language-tabs" aria-label="Editing language"><?php foreach ( awd_lm_languages() as $code => $label ) : ?><a class="<?php echo $code === $lang ? 'is-current' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'action' => 'awd_lm_visual_editor', 'landing' => $landing_key, 'lang' => $code ), admin_url( 'admin-post.php' ) ) ); ?>"><?php echo esc_html( $label ); ?></a><?php endforeach; ?></div><span class="awd-lm-toolbar-divider awd-lm-actions-divider" aria-hidden="true"></span><?php echo awd_lm_admin_navigation( 'website', false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><a class="awd-lm-preview-link" href="<?php echo esc_url( awd_lm_public_url( $landing_key, $lang ) ); ?>" target="_blank" rel="noopener">View published <span aria-hidden="true">↗</span></a><?php if ( 'second-landing' === $landing_key ) : ?><a class="awd-lm-reset-text-link" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action'=>'awd_lm_reset_text_defaults', 'landing'=>$landing_key, 'lang'=>$lang ), admin_url( 'admin-post.php' ) ), 'awd_lm_reset_text_defaults_' . $landing_key ) ); ?>" onclick="return confirm('Reset all DE, EN and FR texts to the plugin defaults? A backup will be created first.')">Reset texts</a><?php endif; ?><button type="submit" form="awd-lm-visual-form">Save draft</button><button type="submit" form="awd-lm-visual-form" name="awd_publish_after_save" value="1" class="awd-lm-publish-button">Publish all <span>→</span></button></div>
</div>
<?php endif; ?>

<?php echo awd_lm_render_prototype_body( $content, $lang, $landing_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

<script src="<?php echo esc_url( AWD_LM_URL . 'assets/vendor/libphonenumber-max.js?ver=1.13.11' ); ?>" defer></script>
<?php if ( empty( $editor ) && empty( $preview ) ) : ?><script src="<?php echo esc_url( AWD_LM_URL . 'assets/consent.js?ver=' . $asset_version ); ?>" data-language="<?php echo esc_attr( $lang ); ?>" data-config="<?php echo esc_attr( wp_json_encode( $consent_config ) ); ?>" defer></script><?php endif; ?>
<?php if ( empty( $editor ) && empty( $preview ) ) : ?><script src="<?php echo esc_url( AWD_LM_URL . 'assets/analytics.js?ver=' . $asset_version ); ?>" data-enabled="1" data-endpoint="<?php echo esc_url( add_query_arg( 'action', 'awd_lm_track', admin_url( 'admin-post.php' ) ) ); ?>" data-language="<?php echo esc_attr( $lang ); ?>" data-landing="<?php echo esc_attr( $landing_key ); ?>" defer></script><?php endif; ?>
<script src="<?php echo esc_url( AWD_LM_URL . 'assets/landing-v1.js?ver=' . $asset_version ); ?>" defer></script>
<?php
$landing_module = awd_lm_get_landing_module( $landing_key );
if ( $landing_module && ! empty( $landing_module['script'] ) && wp_normalize_path( $landing_module['script'] ) !== wp_normalize_path( AWD_LM_DIR . 'assets/landing-v1.js' ) ) :
	$landing_module_script = trailingslashit( AWD_LM_URL ) . ltrim( str_replace( wp_normalize_path( AWD_LM_DIR ), '', wp_normalize_path( $landing_module['script'] ) ), '/' );
?>
<script src="<?php echo esc_url( $landing_module_script . '?ver=' . (string) filemtime( $landing_module['script'] ) ); ?>" defer></script>
<?php endif; ?>
<?php if ( ! empty( $editor ) ) : ?><script src="<?php echo esc_url( AWD_LM_URL . 'admin/visual-editor.js?ver=' . $asset_version ); ?>" defer></script><?php wp_print_footer_scripts(); wp_print_media_templates(); endif; ?>
</body></html>
