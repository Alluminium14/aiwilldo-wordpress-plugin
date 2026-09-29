<?php
defined( 'ABSPATH' ) || exit;

function awd_lm_register_admin_page() {
	add_menu_page( 'Landing Manager', 'Landing Manager', 'edit_pages', 'awd-landing-manager', 'awd_lm_render_admin_page', 'dashicons-welcome-widgets-menus', 58 );
}

/*
 * Admin menu callbacks run after WordPress has begun rendering the admin
 * chrome, so an HTTP redirect from that callback is already too late on many
 * servers. Route the menu request during admin_init instead, before output.
 */
function awd_lm_route_visual_editor() {
	if ( ! is_admin() || ! current_user_can( 'edit_pages' ) ) { return; }
	if ( empty( $_GET['page'] ) || 'awd-landing-manager' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) { return; }
	awd_lm_visual_editor();
	exit;
}

function awd_lm_admin_assets( $hook ) {
	if ( 'toplevel_page_awd-landing-manager' !== $hook ) { return; }
	wp_enqueue_media();
	wp_enqueue_style( 'awd-lm-admin', AWD_LM_URL . 'admin/admin.css', array(), AWD_LM_VERSION );
	wp_enqueue_script( 'awd-lm-admin', AWD_LM_URL . 'admin/admin.js', array(), AWD_LM_VERSION, true );
}

function awd_lm_render_admin_page() {
	if ( ! current_user_can( 'edit_pages' ) ) { wp_die( esc_html__( 'You are not allowed to edit landing pages.', 'awd-landing-manager' ) ); }
	/* Normally handled by awd_lm_route_visual_editor() before admin output. */
	return;
	/* Legacy form editor is retained below as a maintenance fallback. */
	$key = 'gloriafood';
	$definition = awd_lm_definition( $key );
	$content = awd_lm_get_content( $key );
	$languages = awd_lm_languages();
	$status = get_option( 'awd_lm_publish_status_gloriafood', array() );
	$notice = isset( $_GET['awd_notice'] ) ? sanitize_key( wp_unslash( $_GET['awd_notice'] ) ) : '';
	$results = get_transient( 'awd_lm_results_' . get_current_user_id() );
	delete_transient( 'awd_lm_results_' . get_current_user_id() );
	?>
	<div class="wrap awd-lm-wrap">
		<h1><?php echo esc_html( $definition['name'] ); ?></h1>
		<p class="description">Content-only editor. Layout and design remain developer-controlled.</p>
		<?php if ( 'saved' === $notice ) : ?><div class="notice notice-success is-dismissible"><p>Draft saved. The public landing was not changed.</p></div><?php endif; ?>
		<?php if ( is_array( $results ) ) : ?>
			<div class="notice <?php echo count( array_filter( $results, 'is_wp_error' ) ) ? 'notice-warning' : 'notice-success'; ?>"><p><strong>Publication result</strong></p><ul>
			<?php foreach ( $results as $lang => $result ) : ?><li><?php echo esc_html( $languages[ $lang ] ); ?> — <?php echo is_wp_error( $result ) ? '✕ ' . esc_html( $result->get_error_message() ) : '✓ Published'; ?></li><?php endforeach; ?>
			</ul></div>
		<?php endif; ?>
		<div class="awd-lm-status-grid">
		<?php foreach ( $languages as $lang => $label ) :
			$current_hash = awd_lm_content_hash( $content, $lang );
			$published = isset( $status[ $lang ] ) ? $status[ $lang ] : null;
			$dirty = $published && $published['hash'] !== $current_hash;
		?>
			<div class="awd-lm-status"><strong><?php echo esc_html( $label ); ?></strong><span><?php echo ! $published ? '○ Not published' : ( $dirty ? '● Draft changes not published' : '✓ Published' ); ?></span><?php if ( $published ) : ?><small><?php echo esc_html( mysql2date( 'j M Y — H:i', $published['time'] ) ); ?></small><?php endif; ?></div>
		<?php endforeach; ?>
		</div>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="awd-lm-form">
			<input type="hidden" name="action" value="awd_lm_save">
			<input type="hidden" name="landing" value="gloriafood">
			<?php wp_nonce_field( 'awd_lm_save_gloriafood' ); ?>
			<nav class="awd-lm-tabs" aria-label="Languages">
			<?php foreach ( $languages as $lang => $label ) : ?><button type="button" class="button awd-lm-tab<?php echo 'de' === $lang ? ' is-active' : ''; ?>" data-lang="<?php echo esc_attr( $lang ); ?>"><?php echo esc_html( $label ); ?> <span data-completeness="<?php echo esc_attr( $lang ); ?>"></span></button><?php endforeach; ?>
			</nav>
			<?php foreach ( $definition['sections'] as $section_key => $section ) : ?>
			<details class="awd-lm-section" <?php echo 'hero' === $section_key ? 'open' : ''; ?>>
				<summary><?php echo esc_html( $section['label'] ); ?></summary>
				<div class="awd-lm-section-body">
				<?php if ( ! isset( $section['toggle'] ) || false !== $section['toggle'] ) : ?><label class="awd-lm-toggle"><input type="checkbox" name="content[<?php echo esc_attr( $section_key ); ?>][enabled]" value="1" <?php checked( ! empty( $content[ $section_key ]['enabled'] ) ); ?>> Show section</label><?php endif; ?>
				<?php foreach ( $section['fields'] as $field => $type ) :
					if ( 'image' === $type ) { awd_lm_image_control( $section_key, $field, isset( $content[ $section_key ][ $field ] ) ? absint( $content[ $section_key ][ $field ] ) : 0 ); continue; }
					foreach ( $languages as $lang => $label ) :
						$value = isset( $content[ $section_key ][ $field ][ $lang ] ) ? $content[ $section_key ][ $field ][ $lang ] : '';
				?>
					<div class="awd-lm-field awd-lm-lang" data-lang-panel="<?php echo esc_attr( $lang ); ?>">
						<label for="awd-<?php echo esc_attr( "$section_key-$field-$lang" ); ?>"><?php echo esc_html( ucwords( str_replace( '_', ' ', $field ) ) ); ?></label>
						<?php if ( in_array( $type, array( 'textarea', 'lines', 'faq' ), true ) ) : ?><textarea rows="<?php echo 'faq' === $type ? 8 : 4; ?>" id="awd-<?php echo esc_attr( "$section_key-$field-$lang" ); ?>" name="content[<?php echo esc_attr( $section_key ); ?>][<?php echo esc_attr( $field ); ?>][<?php echo esc_attr( $lang ); ?>]" data-required-content><?php echo esc_textarea( $value ); ?></textarea><?php if ( 'lines' === $type ) : ?><small>One item per line.</small><?php elseif ( 'faq' === $type ) : ?><small>One question and answer per line, separated with |</small><?php endif; ?>
						<?php else : ?><input type="<?php echo 'url' === $type ? 'text' : 'text'; ?>" id="awd-<?php echo esc_attr( "$section_key-$field-$lang" ); ?>" name="content[<?php echo esc_attr( $section_key ); ?>][<?php echo esc_attr( $field ); ?>][<?php echo esc_attr( $lang ); ?>]" value="<?php echo esc_attr( $value ); ?>" data-required-content><?php endif; ?>
					</div>
				<?php endforeach; endforeach; ?>
				</div>
			</details>
			<?php endforeach; ?>
			<div class="awd-lm-actions">
				<button type="submit" class="button button-primary button-large">Save Draft</button>
				<?php foreach ( $languages as $lang => $label ) : ?><a class="button button-large awd-lm-preview<?php echo 'de' !== $lang ? ' is-hidden' : ''; ?>" data-preview-lang="<?php echo esc_attr( $lang ); ?>" target="_blank" href="<?php echo esc_url( add_query_arg( array( 'awd-preview' => 1, 'landing' => 'gloriafood', 'lang' => $lang ), home_url( '/' ) ) ); ?>">Preview <?php echo esc_html( $label ); ?></a><?php endforeach; ?>
			</div>
		</form>
		<form class="awd-lm-publish" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="awd_lm_publish"><input type="hidden" name="landing" value="gloriafood"><?php wp_nonce_field( 'awd_lm_publish_gloriafood' ); ?>
			<button type="submit" class="button button-primary button-hero">Publish all languages</button><p>Publishes the latest saved draft. Unsaved form changes are not included.</p>
		</form>
	</div>
	<?php
}

function awd_lm_image_control( $section, $field, $attachment_id ) {
	$url = $attachment_id ? wp_get_attachment_image_url( $attachment_id, 'medium' ) : '';
	?><div class="awd-lm-field awd-lm-image"><label>Image</label><input type="hidden" name="content[<?php echo esc_attr( $section ); ?>][<?php echo esc_attr( $field ); ?>]" value="<?php echo esc_attr( $attachment_id ); ?>"><img src="<?php echo esc_url( $url ); ?>" alt="" <?php echo $url ? '' : 'hidden'; ?>><div><button type="button" class="button awd-lm-select-image">Select / Change image</button> <button type="button" class="button awd-lm-remove-image">Remove</button></div></div><?php
}

function awd_lm_handle_save() {
	if ( ! current_user_can( 'edit_pages' ) ) { wp_die( 'Forbidden', 403 ); }
	$key = sanitize_key( wp_unslash( $_POST['landing'] ?? 'gloriafood' ) );
	$definition = awd_lm_definition( $key );
	if ( ! $definition ) { wp_die( 'Unknown landing.', 404 ); }
	check_admin_referer( 'awd_lm_save_' . $key );
	/*
	 * The classic settings screen posts its fields inside content[...], while
	 * the visual editor posts its generated fields at the top level.  Keep both
	 * shapes so an editor save never silently drops inline text, links or media.
	 */
	$raw = isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : array();
	foreach ( array( 'prototype_text', 'prototype_links', 'prototype_images', 'prototype_sections', 'prototype_menu' ) as $visual_key ) {
		if ( isset( $_POST[ $visual_key ] ) ) {
			$raw[ $visual_key ] = wp_unslash( $_POST[ $visual_key ] );
		}
	}
	if ( isset( $_POST['brand_name'] ) ) { $raw['brand_name'] = wp_unslash( $_POST['brand_name'] ); }
	if ( isset( $_POST['brand_original'] ) ) { $raw['brand_original'] = wp_unslash( $_POST['brand_original'] ); }
	$clean = awd_lm_sanitize_content( $key, $raw );
	update_option( $definition['option'], $clean, false );
	if ( ! empty( $_POST['awd_publish_after_save'] ) ) {
		$results = array(); foreach ( awd_lm_languages() as $lang => $label ) { $results[ $lang ] = awd_publish_landing( $key, $lang ); }
		set_transient( 'awd_lm_results_' . get_current_user_id(), $results, 60 );
	}
	$lang = isset( $_POST['editor_lang'] ) ? awd_get_current_language( wp_unslash( $_POST['editor_lang'] ) ) : 'de';
	wp_safe_redirect( add_query_arg( array( 'action' => 'awd_lm_visual_editor', 'landing' => $key, 'awd_notice' => 'saved', 'lang' => $lang ), admin_url( 'admin-post.php' ) ) ); exit;
}

function awd_lm_visual_editor() {
	if ( ! is_user_logged_in() || ! current_user_can( 'edit_pages' ) ) { wp_die( 'You are not allowed to edit landing pages.', 403 ); }
	$key = sanitize_key( wp_unslash( $_GET['landing'] ?? 'gloriafood' ) );
	if ( ! awd_lm_definition( $key ) ) { $key = 'gloriafood'; }
	$lang = isset( $_GET['lang'] ) ? awd_get_current_language( wp_unslash( $_GET['lang'] ) ) : 'de';
	$content = awd_lm_get_content( $key );
	$definition = awd_lm_definition( $key );
	$status = get_option( 'awd_lm_publish_status_' . $key, array() );
	$notice = isset( $_GET['awd_notice'] ) ? sanitize_key( wp_unslash( $_GET['awd_notice'] ) ) : '';
	$results = get_transient( 'awd_lm_results_' . get_current_user_id() );
	delete_transient( 'awd_lm_results_' . get_current_user_id() );
	$editor = true;
	$GLOBALS['awd_lm_visual_editor'] = true;
	/* The visual editor uses the same field helper as the public renderer. */
	$GLOBALS['awd_lm_render_content'] = $content;
	$canonical = awd_lm_public_url( $key, $lang );
	$asset_version = AWD_LM_VERSION;
	wp_enqueue_media();
	require_once AWD_LM_DIR . 'templates/template-helpers.php';
	$template = ! empty( $definition['module'] ) ? AWD_LM_DIR . 'templates/landing-module.php' : AWD_LM_DIR . 'templates/' . $definition['template'];
	include $template;
	unset( $GLOBALS['awd_lm_visual_editor'] );
	unset( $GLOBALS['awd_lm_render_content'] );
}

function awd_lm_sanitize_content( $key, $raw ) {
	$definition = awd_lm_definition( $key ); $output = awd_lm_get_content( $key );
	/* Keep prior brand labels as aliases so every later rename also works. */
	$previous_brand = sanitize_text_field( $output['brand_name'] ?? 'website-to-go' );
	$posted_original_brand = sanitize_text_field( $raw['brand_original'] ?? $previous_brand );
	$brand_aliases = array_filter( array_unique( array_merge( array( 'website-to-go', 'website to go', 'Take Order' ), (array) ( $output['brand_history'] ?? array() ), array( $previous_brand, $posted_original_brand ) ) ) );
	usort( $brand_aliases, function ( $a, $b ) { return strlen( $b ) <=> strlen( $a ); } );
	foreach ( $definition['sections'] as $section_key => $section ) {
		if ( ! isset( $raw[ $section_key ] ) ) { continue; }
		$output[ $section_key ]['enabled'] = isset( $raw[ $section_key ]['enabled'] );
		foreach ( $section['fields'] as $field => $type ) {
			if ( 'image' === $type ) { $output[ $section_key ][ $field ] = isset( $raw[ $section_key ][ $field ] ) ? absint( $raw[ $section_key ][ $field ] ) : 0; continue; }
			foreach ( awd_lm_languages() as $lang => $label ) {
				// Module editors may submit only the active language. Preserve every
				// translation that was not present in this request.
				if ( ! isset( $raw[ $section_key ][ $field ] ) || ! is_array( $raw[ $section_key ][ $field ] ) || ! array_key_exists( $lang, $raw[ $section_key ][ $field ] ) ) { continue; }
				$value = $raw[ $section_key ][ $field ][ $lang ];
				$output[ $section_key ][ $field ][ $lang ] = 'url' === $type ? esc_url_raw( $value, array( 'http', 'https', 'mailto' ) ) : ( in_array( $type, array( 'textarea', 'lines', 'faq' ), true ) ? sanitize_textarea_field( $value ) : sanitize_text_field( $value ) );
			}
		}
	}
	if ( ! empty( $raw['prototype_text'] ) && is_array( $raw['prototype_text'] ) ) {
		foreach ( awd_lm_languages() as $lang => $label ) {
			if ( empty( $raw['prototype_text'][ $lang ] ) || ! is_array( $raw['prototype_text'][ $lang ] ) ) { continue; }
			foreach ( $raw['prototype_text'][ $lang ] as $text_key => $value ) {
				if ( preg_match( '/^t[1-9][0-9]*$/', $text_key ) ) { $output['prototype_text'][ $lang ][ $text_key ] = str_ireplace( $brand_aliases, 'website-to-go', sanitize_textarea_field( $value ) ); }
			}
		}
	}
	if ( ! empty( $raw['prototype_links'] ) && is_array( $raw['prototype_links'] ) ) { foreach ( awd_lm_languages() as $lang => $label ) { foreach ( (array) ( $raw['prototype_links'][ $lang ] ?? array() ) as $link_key => $value ) { if ( preg_match( '/^l[1-9][0-9]*$/', $link_key ) ) { $output['prototype_links'][ $lang ][ $link_key ] = esc_url_raw( $value, array( 'http', 'https', 'mailto', 'tel', '#' ) ); } } } }
	if ( ! empty( $raw['prototype_images'] ) && is_array( $raw['prototype_images'] ) ) { foreach ( $raw['prototype_images'] as $image_key => $image_id ) { if ( in_array( $image_key, array( 'hero', 'logo', 'chat-avatar' ), true ) || preg_match( '/^(mock|product|panel)[1-9][0-9]*$/', $image_key ) ) { $output['prototype_images'][ $image_key ] = absint( $image_id ); } } }
	if ( ! empty( $raw['prototype_sections'] ) ) { foreach ( (array) $raw['prototype_sections'] as $section_key => $section ) { if ( preg_match( '/^section[1-9][0-9]*$/', $section_key ) ) { $output['prototype_sections'][ $section_key ] = array( 'anchor' => sanitize_title( $section['anchor'] ?? '' ), 'enabled' => ! empty( $section['enabled'] ), 'analytics_name' => substr( sanitize_text_field( $section['analytics_name'] ?? '' ), 0, 190 ) ); } } }
	if ( ! empty( $raw['prototype_menu'] ) ) {
		foreach ( (array) $raw['prototype_menu'] as $menu_key => $item ) {
			if ( 'added' === $menu_key ) {
				$previous_added = (array) ( $output['prototype_menu']['added'] ?? array() );
				$output['prototype_menu']['added'] = array();
				foreach ( (array) $item as $added_index => $added ) {
					$previous = (array) ( $previous_added[ $added_index ] ?? array() );
					$labels = (array) ( $previous['labels'] ?? array() );
					foreach ( awd_lm_languages() as $language_code => $language_name ) {
						if ( isset( $added['labels'][ $language_code ] ) ) { $labels[ $language_code ] = sanitize_text_field( $added['labels'][ $language_code ] ); }
					}
					$legacy_label = sanitize_text_field( $added['label'] ?? ( $previous['label'] ?? '' ) );
					$anchor = sanitize_title( $added['anchor'] ?? ( $previous['anchor'] ?? '' ) );
					if ( $anchor && ( $legacy_label || array_filter( $labels ) ) ) {
						$output['prototype_menu']['added'][] = array( 'labels' => $labels, 'label' => $legacy_label, 'anchor' => $anchor, 'deleted' => ! empty( $added['deleted'] ) );
					}
				}
			} elseif ( preg_match( '/^existing[1-9][0-9]*$/', $menu_key ) ) {
				$previous = (array) ( $output['prototype_menu'][ $menu_key ] ?? array() );
				$labels = (array) ( $previous['labels'] ?? array() );
				foreach ( awd_lm_languages() as $language_code => $language_name ) {
					if ( isset( $item['labels'][ $language_code ] ) ) {
						$labels[ $language_code ] = sanitize_text_field( $item['labels'][ $language_code ] );
					}
				}
				$output['prototype_menu'][ $menu_key ] = array(
					'labels'  => $labels,
					'anchor'  => sanitize_title( $item['anchor'] ?? ( $previous['anchor'] ?? '' ) ),
					'deleted' => ! empty( $item['deleted'] ),
				);
			}
		}
	}
	/* The brand is deliberately global: changing it updates the rendered copy in all languages. */
	if ( array_key_exists( 'brand_name', $raw ) ) {
		$output['brand_name'] = sanitize_text_field( $raw['brand_name'] );
	}
	if ( empty( $output['brand_name'] ) ) { $output['brand_name'] = 'website-to-go'; }
	$output['brand_history'] = array_values( array_unique( array_filter( array_merge( $brand_aliases, array( $previous_brand ) ) ) ) );
	return $output;
}

function awd_lm_reset_text_defaults() {
	if ( ! current_user_can( 'edit_pages' ) ) { wp_die( 'Forbidden', 403 ); }
	$key = isset( $_GET['landing'] ) ? sanitize_key( wp_unslash( $_GET['landing'] ) ) : '';
	$lang = isset( $_GET['lang'] ) ? awd_get_current_language( sanitize_key( wp_unslash( $_GET['lang'] ) ) ) : 'en';
	check_admin_referer( 'awd_lm_reset_text_defaults_' . $key );
	$definition = awd_lm_definition( $key );
	$defaults = awd_lm_default_content_for( $key );
	if ( ! $definition || empty( $defaults['prototype_text'] ) ) { wp_die( 'Text defaults are not available for this landing.', 400 ); }
	$current = awd_lm_get_content( $key );
	$backup_key = 'awd_lm_text_reset_backup_' . $key . '_' . gmdate( 'Ymd_His' );
	update_option( $backup_key, array( 'created_at'=>current_time( 'mysql' ), 'prototype_text'=>$current['prototype_text'] ?? array() ), false );
	$current['prototype_text'] = $defaults['prototype_text'];
	update_option( $definition['option'], $current, false );
	foreach ( awd_lm_languages() as $language => $label ) {
		$result = awd_publish_landing( $key, $language );
		if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ), 500 ); }
	}
	wp_safe_redirect( add_query_arg( array( 'action'=>'awd_lm_visual_editor', 'landing'=>$key, 'lang'=>$lang, 'awd_notice'=>'texts-reset' ), admin_url( 'admin-post.php' ) ) );
	exit;
}

function awd_lm_css_editor_modern_v3() {
	if ( ! current_user_can( 'edit_pages' ) ) { wp_die( 'Forbidden', 403 ); }
	$collection = awd_lm_css_profiles( true );
	$requested = isset( $_GET['profile'] ) ? sanitize_key( wp_unslash( $_GET['profile'] ) ) : $collection['active'];
	$key = isset( $collection['profiles'][ $requested ] ) ? $requested : $collection['active'];
	$profile = $collection['profiles'][ $key ];
	$base_css = (string) file_get_contents( AWD_LM_DIR . 'assets/landing-v1.css' );
	$bundle_css = awd_lm_plugin_profile_css( $key );
	?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Landing CSS profiles</title><style>body{margin:0;padding:32px;background:#f3f7f4;color:#083e2b;font:14px/1.45 system-ui}main{max-width:1180px;margin:auto}h1{margin:0}.sub,.hint{color:#597064}.bar,.actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap}.bar{justify-content:space-between}.profile,button,a{padding:10px 14px;border:1px solid #cfe5d7;border-radius:9px;background:#fff;color:#176147;text-decoration:none;font:700 14px system-ui}.profile.active,button.primary{background:#008b55;border-color:#008b55;color:#fff}.card{margin-top:20px;padding:22px;background:#fff;border:1px solid #cfe5d7;border-radius:16px}label{display:block;font-weight:800;margin:22px 0 6px}.hint{margin:0}textarea{width:100%;min-height:270px;margin-top:10px;padding:14px;border:1px solid #bcdac7;border-radius:10px;background:#fbfefc;color:#172c22;font:12px/1.5 Consolas,monospace;box-sizing:border-box}.readonly{min-height:180px;background:#eef2ef;color:#52655b;cursor:text}.custom{min-height:230px}.actions{margin-top:18px}button.publish{background:#ff6846;border-color:#ff6846;color:#fff}.plugin-tag{display:inline-block;margin-left:8px;padding:3px 7px;border-radius:99px;background:#e1f1e6;color:#176147;font-size:11px}@media(max-width:650px){body{padding:16px}.bar{align-items:flex-start;flex-direction:column}}</style></head><body><main><div class="bar"><div><h1>Landing CSS profiles</h1><p class="sub">Plugin layers are read-only and update with the plugin. Custom layers are saved only on this WordPress site.</p></div><a href="<?php echo esc_url( admin_url( 'admin-post.php?action=awd_lm_visual_editor' ) ); ?>">← Back to editor</a></div><p class="bar"><?php foreach ( $collection['profiles'] as $profile_key => $item ) : ?><a class="profile<?php echo $key === $profile_key ? ' active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'action' => 'awd_lm_css_editor', 'profile' => $profile_key ), admin_url( 'admin-post.php' ) ) ); ?>"><?php echo esc_html( $item['name'] ); ?><?php if ( $profile_key === $collection['active'] ) : ?> · Active<?php endif; ?></a><?php endforeach; ?></p><?php if ( $key !== $collection['active'] ) : ?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="awd_lm_activate_css_profile"><input type="hidden" name="profile" value="<?php echo esc_attr( $key ); ?>"><?php wp_nonce_field( 'awd_lm_manage_css_profiles' ); ?><button class="primary">Use this style in editor</button></form><?php endif; ?><section class="card"><strong>Plugin-managed layers <span class="plugin-tag">Read-only · updates with plugin</span></strong><label for="awd-plugin-base-css">Base CSS from plugin</label><p class="hint">You can select and copy this text. It is never stored or edited in WordPress.</p><textarea class="readonly" id="awd-plugin-base-css" readonly spellcheck="false"><?php echo esc_textarea( $base_css ); ?></textarea><label for="awd-plugin-bundle-css">Style bundle from plugin — <?php echo esc_html( $profile['name'] ); ?></label><p class="hint"><?php echo '' === $bundle_css ? 'This profile uses only the base CSS.' : 'This style-specific layer is also updated with the plugin.'; ?></p><textarea class="readonly" id="awd-plugin-bundle-css" readonly spellcheck="false"><?php echo esc_textarea( $bundle_css ); ?></textarea></section><form class="card" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="awd_lm_save_css"><input type="hidden" name="profile" value="<?php echo esc_attr( $key ); ?>"><?php wp_nonce_field( 'awd_lm_save_css' ); ?><strong>Custom layers for this site</strong><label for="awd-shared-custom-css">Shared custom CSS</label><p class="hint">Applies to every style profile on this site. It survives plugin updates.</p><textarea id="awd-shared-custom-css" name="shared_custom_css" spellcheck="false"><?php echo esc_textarea( $collection['shared_custom_css'] ?? '' ); ?></textarea><label for="awd-profile-custom-css">Custom CSS for this style only</label><p class="hint">Applies only to “<?php echo esc_html( $profile['name'] ); ?>” and survives plugin updates.</p><textarea class="custom" id="awd-profile-custom-css" name="custom_css" spellcheck="false"><?php echo esc_textarea( $profile['custom_css'] ?? '' ); ?></textarea><div class="actions"><button type="submit">Save CSS</button><?php if ( $key !== $collection['active'] ) : ?><button class="primary" type="submit" name="set_active" value="1">Save & set active</button><button class="publish" type="submit" name="publish_active" value="1">Save, activate & publish all</button><?php else : ?><button class="publish" type="submit" name="publish_active" value="1">Save & publish all</button><?php endif; ?></div></form></main></body></html><?php exit;
}

function awd_lm_css_editor_modern_v2() {
	awd_lm_css_editor_modern_v3();
	return;
	if ( ! current_user_can( 'edit_pages' ) ) { wp_die( 'Forbidden', 403 ); }
	$collection = awd_lm_css_profiles( true );
	$requested = isset( $_GET['profile'] ) ? sanitize_key( wp_unslash( $_GET['profile'] ) ) : $collection['active'];
	$key = isset( $collection['profiles'][ $requested ] ) ? $requested : $collection['active'];
	$profile = $collection['profiles'][ $key ];
	?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Landing CSS profiles</title><style>body{margin:0;padding:32px;background:#f3f7f4;color:#083e2b;font:14px/1.45 system-ui}main{max-width:1180px;margin:auto}h1{margin:0}.sub{color:#597064}.bar,.actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap}.bar{justify-content:space-between}.profile,button,a{padding:10px 14px;border:1px solid #cfe5d7;border-radius:9px;background:#fff;color:#176147;text-decoration:none;font:700 14px system-ui}.profile.active,button.primary{background:#008b55;border-color:#008b55;color:#fff}.card{margin-top:20px;padding:22px;background:#fff;border:1px solid #cfe5d7;border-radius:16px}label{display:block;font-weight:800;margin:22px 0 6px}.hint{margin:0;color:#597064}textarea{width:100%;min-height:270px;margin-top:10px;padding:14px;border:1px solid #bcdac7;border-radius:10px;background:#fbfefc;color:#172c22;font:12px/1.5 Consolas,monospace;box-sizing:border-box}.custom{min-height:230px}.actions{margin-top:18px}button.publish{background:#ff6846;border-color:#ff6846;color:#fff}@media(max-width:650px){body{padding:16px}.bar{align-items:flex-start;flex-direction:column}}</style></head><body><main><div class="bar"><div><h1>Landing CSS profiles</h1><p class="sub">Base CSS and selected style CSS update with the plugin. Custom CSS below is stored only on this site.</p></div><a href="<?php echo esc_url( admin_url( 'admin-post.php?action=awd_lm_visual_editor' ) ); ?>">← Back to editor</a></div><p class="bar"><?php foreach ( $collection['profiles'] as $profile_key => $item ) : ?><a class="profile<?php echo $key === $profile_key ? ' active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'action' => 'awd_lm_css_editor', 'profile' => $profile_key ), admin_url( 'admin-post.php' ) ) ); ?>"><?php echo esc_html( $item['name'] ); ?><?php if ( $profile_key === $collection['active'] ) : ?> · Active<?php endif; ?></a><?php endforeach; ?></p><?php if ( $key !== $collection['active'] ) : ?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="awd_lm_activate_css_profile"><input type="hidden" name="profile" value="<?php echo esc_attr( $key ); ?>"><?php wp_nonce_field( 'awd_lm_manage_css_profiles' ); ?><button class="primary">Use this style in editor</button></form><?php endif; ?><form class="card" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="awd_lm_save_css"><input type="hidden" name="profile" value="<?php echo esc_attr( $key ); ?>"><?php wp_nonce_field( 'awd_lm_save_css' ); ?><strong>Editing: <?php echo esc_html( $profile['name'] ); ?></strong><label for="awd-shared-custom-css">Shared custom CSS</label><p class="hint">Applies to every style profile on this site. It is preserved during plugin updates.</p><textarea id="awd-shared-custom-css" name="shared_custom_css" spellcheck="false"><?php echo esc_textarea( $collection['shared_custom_css'] ?? '' ); ?></textarea><label for="awd-profile-custom-css">Custom CSS for this style only</label><p class="hint">Applies only to “<?php echo esc_html( $profile['name'] ); ?>” and is preserved during plugin updates.</p><textarea class="custom" id="awd-profile-custom-css" name="custom_css" spellcheck="false"><?php echo esc_textarea( $profile['custom_css'] ?? '' ); ?></textarea><div class="actions"><button type="submit">Save CSS</button><?php if ( $key !== $collection['active'] ) : ?><button class="primary" type="submit" name="set_active" value="1">Save & set active</button><button class="publish" type="submit" name="publish_active" value="1">Save, activate & publish all</button><?php else : ?><button class="publish" type="submit" name="publish_active" value="1">Save & publish all</button><?php endif; ?></div></form></main></body></html><?php exit;
}

function awd_lm_css_editor_modern() {
	awd_lm_css_editor_modern_v2();
	return;
	if ( ! current_user_can( 'edit_pages' ) ) { wp_die( 'Forbidden', 403 ); }
	$collection = awd_lm_css_profiles( true );
	$requested = isset( $_GET['profile'] ) ? sanitize_key( wp_unslash( $_GET['profile'] ) ) : $collection['active'];
	$profile_key = isset( $collection['profiles'][ $requested ] ) ? $requested : $collection['active'];
	$profile = $collection['profiles'][ $profile_key ];
	$notice = isset( $_GET['awd_css_notice'] ) ? sanitize_key( wp_unslash( $_GET['awd_css_notice'] ) ) : '';
	?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Landing CSS profiles</title><style>:root{--ink:#083e2b;--green:#008b55;--mint:#edf8f0;--line:#cfe5d7}*{box-sizing:border-box}body{margin:0;padding:30px;font:14px/1.45 system-ui,-apple-system,"Segoe UI",sans-serif;background:#f3f7f4;color:var(--ink)}main{max-width:1240px;margin:auto}.top{display:flex;align-items:center;justify-content:space-between;gap:18px;margin-bottom:24px}.back,.profile,.button{display:inline-flex;align-items:center;text-decoration:none;font-weight:800;border-radius:9px;padding:10px 13px}.back{color:#377360;background:#fff;border:1px solid var(--line)}h1{margin:0;font-size:28px;letter-spacing:-.04em}.sub,.hint{color:#597064;margin:6px 0 0}.notice{padding:11px 14px;border-radius:9px;background:#e1f8e8;color:#14663c;font-weight:700;margin:0 0 16px}.profiles{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin:18px 0}.profile{background:#fff;color:#47705c;border:1px solid var(--line)}.profile.active{background:var(--green);border-color:var(--green);color:#fff}.active-tag{font-size:10px;margin-left:7px;opacity:.8;text-transform:uppercase;letter-spacing:.08em}.card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:20px;box-shadow:0 12px 30px rgba(13,65,38,.06);margin-top:16px}label{display:block;font-weight:800;margin:18px 0 8px}.name{color:#4c705e;font-size:13px;margin-bottom:4px}textarea{width:100%;height:34vh;min-height:260px;border:1px solid #bcdac7;border-radius:10px;font:12px/1.5 Consolas,"Courier New",monospace;padding:16px;color:#172c22;background:#fbfefc;resize:vertical}.custom{height:30vh;min-height:220px}.actions{display:flex;gap:9px;flex-wrap:wrap;margin-top:15px}.button{border:1px solid var(--line);background:#fff;color:#176147;cursor:pointer}.button.primary{background:var(--green);border-color:var(--green);color:#fff}.button.publish{background:#ff6846;border-color:#ff6846;color:#fff}.new-profile{display:flex;gap:8px;align-items:center;margin:18px 0}.new-profile input{min-width:240px;padding:10px 12px;border:1px solid var(--line);border-radius:9px;font:inherit}@media(max-width:650px){body{padding:16px}.top{align-items:flex-start;flex-direction:column}textarea,.custom{height:42vh;min-height:220px}.new-profile{align-items:stretch;flex-direction:column}.new-profile input{min-width:0}}</style></head><body><main><div class="top"><div><h1>Landing CSS profiles</h1><p class="sub">The plugin updates the shared CSS. Each profile keeps its own custom CSS untouched.</p></div><a class="back" href="<?php echo esc_url( admin_url( 'admin-post.php?action=awd_lm_visual_editor' ) ); ?>">← Back to editor</a></div><?php if ( $notice ) : ?><p class="notice"><?php echo 'published' === $notice ? 'Shared and profile CSS saved. All languages published.' : ( 'active' === $notice ? 'Style activated. Publish all when you are ready.' : ( 'created' === $notice ? 'New CSS profile created.' : 'CSS saved.' ) ); ?></p><?php endif; ?><nav class="profiles" aria-label="CSS profiles"><?php foreach ( $collection['profiles'] as $key => $item ) : ?><a class="profile<?php echo $key === $profile_key ? ' active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'action' => 'awd_lm_css_editor', 'profile' => $key ), admin_url( 'admin-post.php' ) ) ); ?>"><?php echo esc_html( $item['name'] ); ?><?php if ( $key === $collection['active'] ) : ?><span class="active-tag">Active</span><?php endif; ?></a><?php endforeach; ?></nav><?php if ( $profile_key !== $collection['active'] ) : ?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="awd_lm_activate_css_profile"><input type="hidden" name="profile" value="<?php echo esc_attr( $profile_key ); ?>"><?php wp_nonce_field( 'awd_lm_manage_css_profiles' ); ?><button class="button primary" type="submit">Use this style in editor</button></form><?php endif; ?><form class="new-profile" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="awd_lm_create_css_profile"><input type="hidden" name="source_profile" value="<?php echo esc_attr( $profile_key ); ?>"><?php wp_nonce_field( 'awd_lm_manage_css_profiles' ); ?><input name="name" required maxlength="60" placeholder="New style name, e.g. Dark contrast"><button class="button" type="submit">+ Create profile</button></form><section class="card"><div class="name">Editing profile: <?php echo esc_html( $profile['name'] ); ?> · <?php echo esc_html( $profile_key ); ?></div><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="awd_lm_save_css"><input type="hidden" name="profile" value="<?php echo esc_attr( $profile_key ); ?>"><?php wp_nonce_field( 'awd_lm_save_css' ); ?><label for="awd-common-css">Shared CSS — used by every profile</label><p class="hint">This layer is where plugin CSS updates are applied. Your changes remain editable here.</p><textarea id="awd-common-css" name="common_css" spellcheck="false"><?php echo esc_textarea( $collection['common_css'] ?? '' ); ?></textarea><label for="awd-custom-css">Custom CSS — only for “<?php echo esc_html( $profile['name'] ); ?>”</label><p class="hint">Use this for site-specific branding and experiments. It is not changed by plugin updates.</p><textarea class="custom" id="awd-custom-css" name="custom_css" spellcheck="false"><?php echo esc_textarea( $profile['custom_css'] ?? '' ); ?></textarea><div class="actions"><button class="button" type="submit">Save CSS</button><?php if ( $profile_key !== $collection['active'] ) : ?><button class="button primary" type="submit" name="set_active" value="1">Save & set active</button><button class="button publish" type="submit" name="publish_active" value="1">Save, set active & publish all</button><?php else : ?><button class="button publish" type="submit" name="publish_active" value="1">Save & publish all languages</button><?php endif; ?></div></form></section></main></body></html><?php exit;
}

function awd_lm_css_editor() {
	if ( ! current_user_can( 'edit_pages' ) ) { wp_die( 'Forbidden', 403 ); }
	awd_lm_css_profiles( true );
	?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Landing design</title><style>body{margin:0;padding:32px;background:#f3f7f4;color:#083e2b;font:14px/1.5 system-ui}main{max-width:820px;margin:auto}.card{padding:24px;background:#fff;border:1px solid #cfe5d7;border-radius:16px;box-shadow:0 12px 30px rgba(13,65,38,.06)}h1{margin-top:0}p{color:#597064}.button{display:inline-flex;padding:10px 14px;border:1px solid #cfe5d7;border-radius:9px;background:#fff;color:#176147;text-decoration:none;font-weight:800}.path{padding:11px 13px;border-radius:8px;background:#eef5f0;color:#274c3c;font:13px/1.45 Consolas,monospace}@media(max-width:650px){body{padding:16px}}</style></head><body><main><section class="card"><h1>Landing design</h1><p>Each landing now has one plugin-managed stylesheet. CSS profiles and browser-editable custom layers were retired to keep designs isolated and predictable.</p><p>The GloriaFood design is maintained in:</p><p class="path">assets/landing-v1.css</p><p>Any CSS previously stored in WordPress was preserved in the private option <code>awd_lm_css_profiles_backup_before_single_stylesheet_2</code>. It is not loaded on public pages.</p><a class="button" href="<?php echo esc_url( admin_url( 'admin-post.php?action=awd_lm_visual_editor' ) ); ?>">← Back to editor</a></section></main></body></html><?php
	exit;
	/* Legacy profile UI below is intentionally unreachable during migration. */
	if ( ! current_user_can( 'edit_pages' ) ) { wp_die( 'Forbidden', 403 ); }
	$collection = awd_lm_css_profiles( true );
	$requested = isset( $_GET['profile'] ) ? sanitize_key( wp_unslash( $_GET['profile'] ) ) : $collection['active'];
	$profile_key = isset( $collection['profiles'][ $requested ] ) ? $requested : $collection['active'];
	$profile = $collection['profiles'][ $profile_key ];
	$notice = isset( $_GET['awd_css_notice'] ) ? sanitize_key( wp_unslash( $_GET['awd_css_notice'] ) ) : '';
	?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Landing CSS profiles</title><style>:root{--ink:#083e2b;--green:#008b55;--mint:#edf8f0;--line:#cfe5d7}*{box-sizing:border-box}body{margin:0;padding:30px;font:14px/1.45 system-ui,-apple-system,"Segoe UI",sans-serif;background:#f3f7f4;color:var(--ink)}main{max-width:1240px;margin:auto}.top{display:flex;align-items:center;justify-content:space-between;gap:18px;margin-bottom:24px}.back,.profile,.button{display:inline-flex;align-items:center;text-decoration:none;font-weight:800;border-radius:9px;padding:10px 13px}.back{color:#377360;background:#fff;border:1px solid var(--line)}h1{margin:0;font-size:28px;letter-spacing:-.04em}.sub{color:#597064;margin:6px 0 0}.notice{padding:11px 14px;border-radius:9px;background:#e1f8e8;color:#14663c;font-weight:700;margin:0 0 16px}.profiles{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin:18px 0}.profile{background:#fff;color:#47705c;border:1px solid var(--line)}.profile.active{background:var(--green);border-color:var(--green);color:#fff}.active-tag{font-size:10px;margin-left:7px;opacity:.8;text-transform:uppercase;letter-spacing:.08em}.card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:20px;box-shadow:0 12px 30px rgba(13,65,38,.06)}label{display:block;font-weight:800;margin-bottom:8px}.name{color:#4c705e;font-size:13px;margin-bottom:16px}textarea{width:100%;height:62vh;min-height:420px;border:1px solid #bcdac7;border-radius:10px;font:12px/1.5 Consolas,"Courier New",monospace;padding:16px;color:#172c22;background:#fbfefc;resize:vertical}.actions{display:flex;gap:9px;flex-wrap:wrap;margin-top:15px}.button{border:1px solid var(--line);background:#fff;color:#176147;cursor:pointer}.button.primary{background:var(--green);border-color:var(--green);color:#fff}.button.publish{background:#ff6846;border-color:#ff6846;color:#fff}.new-profile{display:flex;gap:8px;align-items:center;margin:18px 0}.new-profile input{min-width:240px;padding:10px 12px;border:1px solid var(--line);border-radius:9px;font:inherit}@media(max-width:650px){body{padding:16px}.top{align-items:flex-start;flex-direction:column}textarea{height:55vh}.new-profile{align-items:stretch;flex-direction:column}.new-profile input{min-width:0}}</style></head><body><main><div class="top"><div><h1>Landing CSS profiles</h1><p class="sub">The active style is previewed in the visual editor and used by Publish all. Editor controls remain protected.</p></div><a class="back" href="<?php echo esc_url( admin_url( 'admin-post.php?action=awd_lm_visual_editor' ) ); ?>">← Back to editor</a></div><?php if ( $notice ) : ?><p class="notice"><?php echo 'published' === $notice ? 'Active profile saved and all languages published.' : ( 'active' === $notice ? 'Active profile now shown in the editor. Publish all when you are ready.' : ( 'created' === $notice ? 'New CSS profile created.' : 'CSS profile saved.' ) ); ?></p><?php endif; ?><nav class="profiles" aria-label="CSS profiles"><?php foreach ( $collection['profiles'] as $key => $item ) : ?><a class="profile<?php echo $key === $profile_key ? ' active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'action' => 'awd_lm_css_editor', 'profile' => $key ), admin_url( 'admin-post.php' ) ) ); ?>"><?php echo esc_html( $item['name'] ); ?><?php if ( $key === $collection['active'] ) : ?><span class="active-tag">Active</span><?php endif; ?></a><?php endforeach; ?></nav><?php if ( $profile_key !== $collection['active'] ) : ?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="awd_lm_activate_css_profile"><input type="hidden" name="profile" value="<?php echo esc_attr( $profile_key ); ?>"><?php wp_nonce_field( 'awd_lm_manage_css_profiles' ); ?><button class="button primary" type="submit">Use this style in editor</button></form><?php endif; ?><form class="new-profile" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="awd_lm_create_css_profile"><input type="hidden" name="source_profile" value="<?php echo esc_attr( $profile_key ); ?>"><?php wp_nonce_field( 'awd_lm_manage_css_profiles' ); ?><input name="name" required maxlength="60" placeholder="New style name, e.g. Dark contrast"><button class="button" type="submit">+ Create copy of this style</button></form><section class="card"><label for="awd-css">Editing: <?php echo esc_html( $profile['name'] ); ?></label><div class="name">Profile key: <?php echo esc_html( $profile_key ); ?><?php echo $profile_key === $collection['active'] ? ' · Currently used by editor and Publish all' : ''; ?></div><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="awd_lm_save_css"><input type="hidden" name="profile" value="<?php echo esc_attr( $profile_key ); ?>"><?php wp_nonce_field( 'awd_lm_save_css' ); ?><textarea id="awd-css" name="css" spellcheck="false"><?php echo esc_textarea( $profile['css'] ?? '' ); ?></textarea><div class="actions"><button class="button" type="submit">Save profile</button><?php if ( $profile_key !== $collection['active'] ) : ?><button class="button primary" type="submit" name="set_active" value="1">Save & set active</button><button class="button publish" type="submit" name="publish_active" value="1">Save, set active & publish all</button><?php else : ?><button class="button publish" type="submit" name="publish_active" value="1">Save & publish all languages</button><?php endif; ?></div></form></section></main></body></html><?php exit;
}

function awd_lm_save_css() {
	if ( ! current_user_can( 'edit_pages' ) ) { wp_die( 'Forbidden', 403 ); }
	check_admin_referer( 'awd_lm_save_css' );
	awd_lm_css_profiles( true );
	wp_safe_redirect( admin_url( 'admin-post.php?action=awd_lm_css_editor' ) );
	exit;
	/* Legacy profile handler retained temporarily for request compatibility. */
	$collection = awd_lm_css_profiles( true );
	$key = isset( $_POST['profile'] ) ? sanitize_key( wp_unslash( $_POST['profile'] ) ) : $collection['active'];
	if ( empty( $collection['profiles'][ $key ] ) ) { wp_die( 'CSS profile not found.', 404 ); }
	$collection['shared_custom_css'] = isset( $_POST['shared_custom_css'] ) ? wp_unslash( $_POST['shared_custom_css'] ) : (string) ( $collection['shared_custom_css'] ?? '' );
	$collection['profiles'][ $key ]['custom_css'] = isset( $_POST['custom_css'] ) ? wp_unslash( $_POST['custom_css'] ) : (string) ( $collection['profiles'][ $key ]['custom_css'] ?? '' );
	if ( ! empty( $_POST['set_active'] ) || ! empty( $_POST['publish_active'] ) ) { $collection['active'] = $key; }
	update_option( 'awd_lm_css_profiles', $collection, false );
	if ( ! empty( $_POST['publish_active'] ) ) { foreach ( awd_lm_languages() as $lang => $label ) { awd_publish_landing( 'gloriafood', $lang ); } }
	$notice = ! empty( $_POST['publish_active'] ) ? 'published' : ( ! empty( $_POST['set_active'] ) ? 'active' : 'saved' );
	wp_safe_redirect( add_query_arg( array( 'action' => 'awd_lm_css_editor', 'profile' => $key, 'awd_css_notice' => $notice ), admin_url( 'admin-post.php' ) ) ); exit;
}

function awd_lm_create_css_profile() {
	if ( ! current_user_can( 'edit_pages' ) ) { wp_die( 'Forbidden', 403 ); }
	check_admin_referer( 'awd_lm_manage_css_profiles' );
	awd_lm_css_profiles( true );
	wp_safe_redirect( admin_url( 'admin-post.php?action=awd_lm_css_editor' ) );
	exit;
	/* Legacy profile handler retained temporarily for request compatibility. */
	$collection = awd_lm_css_profiles( true );
	$name = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
	$source = sanitize_key( wp_unslash( $_POST['source_profile'] ?? $collection['active'] ) );
	if ( '' === $name || empty( $collection['profiles'][ $source ] ) ) { wp_die( 'Please provide a profile name.', 400 ); }
	$base = sanitize_key( $name ); if ( '' === $base ) { $base = 'style'; }
	$key = $base; $suffix = 2; while ( isset( $collection['profiles'][ $key ] ) ) { $key = $base . '-' . $suffix; $suffix++; }
	$collection['profiles'][ $key ] = array( 'name' => $name, 'custom_css' => (string) ( $collection['profiles'][ $source ]['custom_css'] ?? '' ), 'created' => current_time( 'mysql' ) );
	update_option( 'awd_lm_css_profiles', $collection, false );
	wp_safe_redirect( add_query_arg( array( 'action' => 'awd_lm_css_editor', 'profile' => $key, 'awd_css_notice' => 'created' ), admin_url( 'admin-post.php' ) ) ); exit;
}

function awd_lm_activate_css_profile() {
	if ( ! current_user_can( 'edit_pages' ) ) { wp_die( 'Forbidden', 403 ); }
	check_admin_referer( 'awd_lm_manage_css_profiles' );
	awd_lm_css_profiles( true );
	wp_safe_redirect( admin_url( 'admin-post.php?action=awd_lm_css_editor' ) );
	exit;
	/* Legacy profile handler retained temporarily for request compatibility. */
	$collection = awd_lm_css_profiles( true );
	$key = isset( $_POST['profile'] ) ? sanitize_key( wp_unslash( $_POST['profile'] ) ) : '';
	if ( empty( $collection['profiles'][ $key ] ) ) { wp_die( 'CSS profile not found.', 404 ); }
	$collection['active'] = $key;
	update_option( 'awd_lm_css_profiles', $collection, false );
	wp_safe_redirect( add_query_arg( array( 'action' => 'awd_lm_css_editor', 'profile' => $key, 'awd_css_notice' => 'active' ), admin_url( 'admin-post.php' ) ) ); exit;
}

function awd_lm_form_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', 403 ); }
	$copies = get_option( 'awd_lm_form_copy_recipients', get_option( 'awd_lm_form_recipient', '' ) );
	$default = get_option( 'admin_email' );
	$turnstile = awd_lm_turnstile_settings();
	$brevo = awd_lm_brevo_settings();
	$notice = isset( $_GET['awd_form_notice'] ) ? sanitize_key( wp_unslash( $_GET['awd_form_notice'] ) ) : '';
	?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Form settings</title><style>body{margin:30px;font:14px/1.5 system-ui;background:#f5f8f6;color:#182019;max-width:760px}textarea,input[type=text],input[type=email],input[type=url],input[type=number],input[type=password]{display:block;width:100%;box-sizing:border-box;padding:12px;margin:8px 0;font:15px/1.45 ui-monospace,monospace;border:1px solid #bcdac7;border-radius:8px}textarea{min-height:150px}button,a{display:inline-block;padding:10px 14px;border:0;border-radius:7px;background:#008b55;color:#fff;text-decoration:none;font-weight:700;cursor:pointer}p{color:#58645c}.card{margin-top:18px;background:#fff;border:1px solid #d6e8dc;padding:22px;border-radius:14px}.site-email{padding:11px 13px;background:#e8f7ed;border-radius:8px;color:#195d3b;font-weight:700}small{display:block;color:#63746a}.toggle{display:flex;gap:9px;align-items:center;font-weight:800}.notice{padding:12px 14px;border-radius:8px;background:#e8f7ed;color:#195d3b;font-weight:700}h2{margin-top:0}.links{display:flex;gap:9px;flex-wrap:wrap}</style></head><body><p class="links"><a href="<?php echo esc_url( admin_url( 'admin-post.php?action=awd_lm_visual_editor' ) ); ?>">← Editor</a><a href="<?php echo esc_url( admin_url( 'admin-post.php?action=awd_lm_leads' ) ); ?>">Leads</a><a href="<?php echo esc_url( admin_url( 'admin-post.php?action=awd_lm_email_campaigns' ) ); ?>">Emails</a></p><h1>Demo form settings</h1><?php if ( in_array( $notice, array( 'saved','webhook' ), true ) ) : ?><p class="notice"><?php echo 'webhook' === $notice ? 'Brevo webhook registered.' : 'Settings saved and all languages published.'; ?></p><?php endif; ?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="awd_lm_save_form_settings"><?php wp_nonce_field( 'awd_lm_save_form_settings' ); ?><div class="card"><h2>Email notifications</h2><p>Every new request is always sent to the WordPress site email:</p><p class="site-email"><?php echo esc_html( $default ); ?></p><label><strong>Additional copy recipients</strong></label><textarea name="recipients"><?php echo esc_textarea( $copies ); ?></textarea></div><div class="card"><h2>Cloudflare Turnstile</h2><label class="toggle"><input type="checkbox" name="turnstile_enabled" value="1" <?php checked( ! empty( $turnstile['enabled'] ) ); ?>> Enable spam protection</label><label><strong>Site Key</strong></label><input type="text" name="turnstile_site_key" value="<?php echo esc_attr( $turnstile['site_key'] ); ?>"><label><strong>Secret Key</strong></label><input type="password" name="turnstile_secret_key" value="" placeholder="<?php echo '' !== $turnstile['secret_key'] ? 'Saved — leave blank to keep it' : 'Enter secret key'; ?>"></div><div class="card"><h2>Brevo</h2><label class="toggle"><input type="checkbox" name="brevo_enabled" value="1" <?php checked( ! empty( $brevo['enabled'] ) ); ?>> Enable Brevo</label><label><strong>API Key</strong></label><input type="password" name="brevo_api_key" value="" placeholder="<?php echo '' !== $brevo['api_key'] ? 'Saved — leave blank to keep it' : 'Enter API key'; ?>"><label><strong>List ID</strong></label><input type="number" min="1" name="brevo_list_id" value="<?php echo esc_attr( $brevo['list_id'] ); ?>"><label><strong>Verified sender name</strong></label><input type="text" name="brevo_sender_name" value="<?php echo esc_attr( $brevo['sender_name'] ); ?>"><label><strong>Verified sender email</strong></label><input type="email" name="brevo_sender_email" value="<?php echo esc_attr( $brevo['sender_email'] ); ?>"><label class="toggle"><input type="checkbox" name="brevo_tracking_consent" value="1" <?php checked( ! empty( $brevo['tracking_consent'] ) ); ?>> Recipient-level open/click tracking consent has been obtained</label><small>Do not enable this solely on the basis of newsletter consent. Without it, Brevo can still provide anonymized aggregate opens depending on account configuration.</small></div><p><button type="submit">Save settings & publish all languages</button></p></form><?php if ( ! empty( $brevo['api_key'] ) ) : ?><div class="card"><h2>Transactional webhook</h2><p>Registers delivery, opening, click, bounce, spam and unsubscribe events in Brevo.</p><a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=awd_lm_register_brevo_webhook' ), 'awd_lm_register_brevo_webhook' ) ); ?>">Register webhook in Brevo</a></div><?php endif; ?></body></html><?php exit;
}

function awd_lm_save_form_settings() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', 403 ); }
	check_admin_referer( 'awd_lm_save_form_settings' );
	$raw = isset( $_POST['recipients'] ) ? wp_unslash( $_POST['recipients'] ) : '';
	$recipients = array();
	foreach ( preg_split( '/[\s,;]+/', (string) $raw ) as $email ) { if ( '' === $email ) { continue; } $email = sanitize_email( $email ); if ( ! is_email( $email ) ) { wp_die( 'Please enter valid email addresses only.', 400 ); } $recipients[] = $email; }
	update_option( 'awd_lm_form_copy_recipients', implode( "\n", array_values( array_unique( $recipients ) ) ), false );
	$existing = awd_lm_turnstile_settings();
	$site_key = isset( $_POST['turnstile_site_key'] ) ? sanitize_text_field( wp_unslash( $_POST['turnstile_site_key'] ) ) : '';
	$posted_secret = isset( $_POST['turnstile_secret_key'] ) ? sanitize_text_field( wp_unslash( $_POST['turnstile_secret_key'] ) ) : '';
	$secret_key = '' !== $posted_secret ? $posted_secret : $existing['secret_key'];
	$enabled = ! empty( $_POST['turnstile_enabled'] );
	if ( $enabled && ( '' === $site_key || '' === $secret_key ) ) { wp_die( 'Both Turnstile keys are required before spam protection can be enabled.', 400 ); }
	update_option( 'awd_lm_turnstile_settings', array( 'enabled' => $enabled, 'site_key' => $site_key, 'secret_key' => $secret_key ), false );
	$brevo_existing = awd_lm_brevo_settings();
	$brevo_key_posted = sanitize_text_field( wp_unslash( $_POST['brevo_api_key'] ?? '' ) );
	$brevo_settings = array(
		'enabled' => ! empty( $_POST['brevo_enabled'] ),
		'api_key' => '' !== $brevo_key_posted ? $brevo_key_posted : $brevo_existing['api_key'],
		'list_id' => absint( $_POST['brevo_list_id'] ?? 0 ),
		'sender_name' => substr( sanitize_text_field( wp_unslash( $_POST['brevo_sender_name'] ?? '' ) ), 0, 190 ),
		'sender_email' => sanitize_email( wp_unslash( $_POST['brevo_sender_email'] ?? '' ) ),
		'tracking_consent' => ! empty( $_POST['brevo_tracking_consent'] ),
	);
	if ( $brevo_settings['enabled'] && ( '' === $brevo_settings['api_key'] || ! $brevo_settings['list_id'] ) ) { wp_die( 'Brevo requires an API key and List ID.', 400 ); }
	update_option( 'awd_lm_brevo_settings', $brevo_settings, false );
	if ( $brevo_settings['enabled'] ) { awd_lm_reset_brevo_queue_for_single_optin(); }
	/* The former single-recipient setting is now represented by the list above. */
	delete_option( 'awd_lm_form_recipient' );
	foreach ( array_keys( awd_lm_languages() ) as $lang ) {
		$result = awd_publish_landing( 'gloriafood', $lang );
		if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ), 500 ); }
	}
	wp_safe_redirect( add_query_arg( array( 'action' => 'awd_lm_form_settings', 'awd_form_notice' => 'saved' ), admin_url( 'admin-post.php' ) ) );
	exit;
}

function awd_lm_handle_publish() {
	if ( ! current_user_can( 'edit_pages' ) ) { wp_die( 'Forbidden', 403 ); }
	$key = sanitize_key( wp_unslash( $_POST['landing'] ?? 'gloriafood' ) );
	if ( ! awd_lm_definition( $key ) ) { wp_die( 'Unknown landing.', 404 ); }
	check_admin_referer( 'awd_lm_publish_' . $key );
	$results = array(); foreach ( awd_lm_languages() as $lang => $label ) { $results[ $lang ] = awd_publish_landing( $key, $lang ); }
	set_transient( 'awd_lm_results_' . get_current_user_id(), $results, 60 );
	wp_safe_redirect( add_query_arg( array( 'action' => 'awd_lm_visual_editor', 'landing' => $key ), admin_url( 'admin-post.php' ) ) ); exit;
}
