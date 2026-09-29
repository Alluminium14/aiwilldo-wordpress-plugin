<?php
defined( 'ABSPATH' ) || exit;

function awd_lm_admin_navigation_items() {
	$admin_post = admin_url( 'admin-post.php' );
	return array(
		'website'   => array( 'label'=>'Website', 'url'=>add_query_arg( 'action','awd_lm_visual_editor',$admin_post ) ),
		'contacts'  => array( 'label'=>'Contacts', 'url'=>add_query_arg( 'action','awd_lm_leads',$admin_post ) ),
		'emails'    => array(
			'label'=>'Emails',
			'url'=>add_query_arg( 'action','awd_lm_email_campaigns',$admin_post ),
			'children'=>array(
				array( 'label'=>'Create & lists', 'url'=>add_query_arg( 'action','awd_lm_email_campaigns',$admin_post ) ),
				array( 'label'=>'Email reports', 'url'=>add_query_arg( 'action','awd_lm_email_reports',$admin_post ) ),
			),
		),
		'analytics' => array( 'label'=>'Analytics', 'url'=>add_query_arg( 'action','awd_lm_analytics',$admin_post ) ),
		'settings'  => array( 'label'=>'Settings', 'url'=>add_query_arg( 'action','awd_lm_urls_settings',$admin_post ) ),
	);
}

function awd_lm_admin_navigation_menu( $active = '' ) {
	$html = '<nav class="awd-admin-nav" aria-label="Landing Manager">';
	foreach ( awd_lm_admin_navigation_items() as $key=>$item ) {
		if ( ! empty( $item['children'] ) ) {
			$html .= '<details class="awd-admin-nav-group' . ( $active===$key ? ' is-active' : '' ) . '"><summary class="awd-admin-nav-link">' . esc_html( $item['label'] ) . '</summary><div class="awd-admin-nav-menu">';
			foreach ( $item['children'] as $child ) { $html .= '<a href="' . esc_url( $child['url'] ) . '">' . esc_html( $child['label'] ) . '</a>'; }
			$html .= '</div></details>';
		} else {
			$html .= '<a class="awd-admin-nav-link' . ( $active===$key ? ' is-active' : '' ) . '" href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['label'] ) . '</a>';
		}
	}
	return $html . '</nav>';
}

function awd_lm_admin_navigation( $active = '', $standalone = true ) {
	$menu = awd_lm_admin_navigation_menu( $active );
	$style = $standalone ? '<style>.awd-admin-shell{position:relative;z-index:50;max-width:1440px;margin:0 auto 22px;display:flex;align-items:center;gap:18px;flex-wrap:wrap}.awd-admin-home{color:#087a4c!important;font-weight:850;text-decoration:none;white-space:nowrap}.awd-admin-nav{display:flex;align-items:center;gap:7px;flex-wrap:wrap}.awd-admin-nav-link{display:inline-flex!important;align-items:center;padding:9px 12px!important;border:1px solid #cfe5d7!important;border-radius:9px!important;background:#fff!important;color:#235c45!important;font-weight:800!important;text-decoration:none!important}.awd-admin-nav-link:hover,.awd-admin-nav-link.is-active,.awd-admin-nav-group.is-active>.awd-admin-nav-link{background:#e8f7ed!important;color:#087a4c!important}.awd-admin-nav-group{position:relative}.awd-admin-nav-group>summary{list-style:none;cursor:pointer}.awd-admin-nav-group>summary::-webkit-details-marker{display:none}.awd-admin-nav-menu{position:absolute;top:calc(100% + 7px);left:0;min-width:190px;padding:7px;border:1px solid #cfe5d7;border-radius:11px;background:#fff;box-shadow:0 16px 38px rgba(20,53,38,.16)}.awd-admin-nav-menu a{display:block;padding:10px;border-radius:7px;color:#235c45!important;font-weight:800;text-decoration:none}.awd-admin-nav-menu a:hover{background:#e8f7ed;color:#087a4c!important}@media(max-width:680px){.awd-admin-shell{align-items:flex-start;gap:10px}.awd-admin-nav{width:100%;display:grid;grid-template-columns:repeat(2,minmax(0,1fr))}.awd-admin-nav-link{justify-content:center}.awd-admin-nav-menu{left:0;right:auto}}</style>' : '';
	return $style . '<div class="awd-admin-shell"><a class="awd-admin-home" href="' . esc_url( admin_url( 'admin-post.php?action=awd_lm_visual_editor' ) ) . '">Landing Manager</a>' . $menu . '</div>';
}

function awd_lm_start_admin_navigation_buffer() {
	$action = sanitize_key( wp_unslash( $_REQUEST['action'] ?? '' ) );
	$map = array(
		'awd_lm_css_editor'=>'website', 'awd_lm_urls_settings'=>'settings', 'awd_lm_form_settings'=>'settings', 'awd_lm_leads'=>'contacts',
		'awd_lm_email_campaigns'=>'emails', 'awd_lm_email_reports'=>'emails', 'awd_lm_analytics'=>'analytics', 'awd_lm_privacy_cookies'=>'settings',
	);
	if ( empty( $map[ $action ] ) || ! current_user_can( 'edit_pages' ) ) { return; }
	$active = $map[ $action ];
	ob_start( static function( $html ) use ( $active ) {
		if ( false === stripos( $html, '<body' ) || false !== strpos( $html, 'class="awd-admin-shell"' ) ) { return $html; }
		return preg_replace( '/<body([^>]*)>/i', '<body$1>' . awd_lm_admin_navigation( $active ), $html, 1 );
	} );
}
