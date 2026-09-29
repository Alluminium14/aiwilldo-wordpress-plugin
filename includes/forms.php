<?php
defined( 'ABSPATH' ) || exit;

function awd_lm_form_recipients() {
	/* The WordPress site address is always the primary recipient. */
	$recipients = array( sanitize_email( get_option( 'admin_email' ) ) );
	$stored = get_option( 'awd_lm_form_copy_recipients', '' );
	/* Keep installations made before multi-recipient support working. */
	$legacy = get_option( 'awd_lm_form_recipient', '' );
	foreach ( array( $stored, $legacy ) as $list ) {
		foreach ( preg_split( '/[\s,;]+/', (string) $list ) as $email ) {
			$email = sanitize_email( $email );
			if ( is_email( $email ) ) { $recipients[] = $email; }
		}
	}
	return array_values( array_unique( array_filter( $recipients, 'is_email' ) ) );
}

function awd_lm_turnstile_settings() {
	$stored = get_option( 'awd_lm_turnstile_settings', array() );
	return wp_parse_args( is_array( $stored ) ? $stored : array(), array(
		'enabled' => false,
		'site_key' => '',
		'secret_key' => '',
	) );
}

function awd_lm_turnstile_enabled() {
	$settings = awd_lm_turnstile_settings();
	return ! empty( $settings['enabled'] ) && '' !== trim( (string) $settings['site_key'] ) && '' !== trim( (string) $settings['secret_key'] );
}

function awd_lm_verify_turnstile_response( $token, $remote_ip = '' ) {
	if ( ! awd_lm_turnstile_enabled() ) { return true; }
	$token = sanitize_text_field( (string) $token );
	if ( '' === $token || strlen( $token ) > 2048 ) { return false; }
	$settings = awd_lm_turnstile_settings();
	$body = array(
		'secret' => $settings['secret_key'],
		'response' => $token,
	);
	if ( '' !== $remote_ip ) { $body['remoteip'] = $remote_ip; }
	$response = wp_remote_post( 'https://challenges.cloudflare.com/turnstile/v0/siteverify', array(
		'timeout' => 10,
		'body' => $body,
	) );
	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) { return false; }
	$result = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $result ) || empty( $result['success'] ) ) { return false; }
	$is_testing = ! empty( $result['metadata']['result_with_testing_key'] );
	if ( ! empty( $result['action'] ) && 'demo_form' !== $result['action'] ) { return false; }
	$expected_host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	$verified_host = strtolower( (string) ( $result['hostname'] ?? '' ) );
	if ( ! $is_testing && '' !== $expected_host && '' !== $verified_host && $expected_host !== $verified_host ) { return false; }
	return true;
}

function awd_lm_form_messages( $lang ) {
	$messages = array(
		'de' => array( 'success' => 'Danke! Ihre Anfrage wurde gesendet. Wir melden uns zeitnah.', 'invalid' => 'Bitte füllen Sie Vorname, Nachname, Betrieb und eine gültige E-Mail-Adresse aus.', 'honeypot' => 'Ihr Browser hat ein verborgenes Formularfeld automatisch ausgefüllt. Bitte laden Sie die Seite neu und senden Sie das Formular erneut.', 'phone' => 'Bitte geben Sie eine gültige Telefonnummer für das gewählte Land ein.', 'failed' => 'Die Anfrage konnte nicht gesendet werden. Bitte versuchen Sie es später erneut.', 'rate' => 'Zu viele Anfragen in kurzer Zeit. Bitte versuchen Sie es in einer Stunde erneut.', 'captcha' => 'Die Sicherheitsprüfung ist fehlgeschlagen. Bitte versuchen Sie es erneut.', 'privacy' => 'Bitte stimmen Sie der Datenschutzerklärung zu, um die Anfrage zu senden.', 'privacy_note' => 'Mit dem Absenden stimmen Sie der Verarbeitung Ihrer Angaben zur Bearbeitung Ihrer Anfrage zu.' ),
		'en' => array( 'success' => 'Thank you! Your request has been sent. We will get back to you shortly.', 'invalid' => 'Please enter your first name, last name, business and a valid email address.', 'honeypot' => 'Your browser automatically filled a hidden form field. Please refresh the page and submit the form again.', 'phone' => 'Please enter a valid phone number for the selected country.', 'failed' => 'Your request could not be sent. Please try again later.', 'rate' => 'Too many requests in a short time. Please try again in one hour.', 'captcha' => 'The security check failed. Please try again.', 'privacy' => 'Please accept the privacy policy to send your request.', 'privacy_note' => 'By submitting, you agree that we may process your details to respond to your request.' ),
		'fr' => array( 'success' => 'Merci ! Votre demande a été envoyée. Nous vous répondrons rapidement.', 'invalid' => 'Veuillez indiquer votre prénom, votre nom, votre établissement et une adresse e-mail valide.', 'honeypot' => 'Votre navigateur a rempli automatiquement un champ caché du formulaire. Actualisez la page puis renvoyez le formulaire.', 'phone' => 'Veuillez saisir un numéro de téléphone valide pour le pays sélectionné.', 'failed' => 'Votre demande n’a pas pu être envoyée. Veuillez réessayer plus tard.', 'rate' => 'Trop de demandes en peu de temps. Veuillez réessayer dans une heure.', 'captcha' => 'La vérification de sécurité a échoué. Veuillez réessayer.', 'privacy' => 'Veuillez accepter la politique de confidentialité pour envoyer votre demande.', 'privacy_note' => 'En envoyant ce formulaire, vous acceptez le traitement de vos données afin de répondre à votre demande.' ),
	);
	return $messages[ awd_get_current_language( $lang ) ];
}

/**
 * Detailed form-validation feedback for the asynchronous public form.
 * Keys stay stable for analytics; values are safe visitor-facing messages.
 */
function awd_lm_form_invalid_field_messages( $lang ) {
	$messages = array(
		'de' => array(
			'first_name'   => 'Bitte geben Sie Ihren Vornamen ein.',
			'last_name'    => 'Bitte geben Sie Ihren Nachnamen ein.',
			'business'     => 'Bitte geben Sie den Namen Ihres Betriebs ein.',
			'email'        => 'Bitte geben Sie eine gültige E-Mail-Adresse ein.',
			'system'       => 'Bitte wählen Sie Ihr aktuelles System aus.',
			'system_other' => 'Bitte beschreiben Sie kurz Ihr aktuelles System.',
		),
		'en' => array(
			'first_name'   => 'Please enter your first name.',
			'last_name'    => 'Please enter your last name.',
			'business'     => 'Please enter your restaurant or business name.',
			'email'        => 'Please enter a valid email address.',
			'system'       => 'Please select your current system.',
			'system_other' => 'Please briefly describe your current system.',
		),
		'fr' => array(
			'first_name'   => 'Veuillez indiquer votre prénom.',
			'last_name'    => 'Veuillez indiquer votre nom.',
			'business'     => 'Veuillez indiquer le nom de votre établissement.',
			'email'        => 'Veuillez indiquer une adresse e-mail valide.',
			'system'       => 'Veuillez sélectionner votre système actuel.',
			'system_other' => 'Veuillez décrire brièvement votre système actuel.',
		),
	);
	return $messages[ awd_get_current_language( $lang ) ];
}

function awd_lm_form_system_fields( $lang ) {
	$fields = array(
		'de' => array(
			'label' => 'Aktuelles System',
			'options' => array(
				'none' => 'Kein System',
				'gloriafood' => 'GloriaFood',
				'other_ordering' => 'Anderes Bestellsystem',
				'mail_phone' => 'Nur Mail und/oder Telefon',
				'other' => 'Sonstiges',
			),
			'other_label' => 'Welches System nutzen Sie?',
			'other_placeholder' => 'Bitte kurz beschreiben',
		),
		'en' => array(
			'label' => 'Current system',
			'options' => array(
				'none' => 'No system',
				'gloriafood' => 'GloriaFood',
				'other_ordering' => 'Another ordering system',
				'mail_phone' => 'Email and/or phone only',
				'other' => 'Other',
			),
			'other_label' => 'Which system do you use?',
			'other_placeholder' => 'Please describe briefly',
		),
		'fr' => array(
			'label' => 'Système actuel',
			'options' => array(
				'none' => 'Aucun système',
				'gloriafood' => 'GloriaFood',
				'other_ordering' => 'Un autre système de commande',
				'mail_phone' => 'Uniquement e-mail et/ou téléphone',
				'other' => 'Autre',
			),
			'other_label' => 'Quel système utilisez-vous ?',
			'other_placeholder' => 'Veuillez préciser brièvement',
		),
	);
	return $fields[ awd_get_current_language( $lang ) ];
}

function awd_lm_form_interest_fields( $lang ) {
	$fields = array(
		'de' => array(
			'label' => 'Bei Interesse',
			'options' => array(
				'ordering_system' => 'Bestellsystem',
				'table_reservation' => 'Tischreservation',
				'gastro_website' => 'Gastro Website',
				'ai_chat_assistant' => 'KI Chat Assistent',
				'complete_solution' => 'Komplettlösung',
				'gloriafood_switch' => 'GloriaFood Wechsel',
				'migration_service' => 'Wechsel- & Migrationsservice',
			),
		),
		'en' => array(
			'label' => 'Interested in',
			'options' => array(
				'ordering_system' => 'Ordering system',
				'table_reservation' => 'Table reservations',
				'gastro_website' => 'Restaurant website',
				'ai_chat_assistant' => 'AI chat assistant',
				'complete_solution' => 'Complete solution',
				'gloriafood_switch' => 'Switching from GloriaFood',
				'migration_service' => 'Switching & migration service',
			),
		),
		'fr' => array(
			'label' => 'Intéressé par',
			'options' => array(
				'ordering_system' => 'Système de commande',
				'table_reservation' => 'Réservation de table',
				'gastro_website' => 'Site web pour restaurant',
				'ai_chat_assistant' => 'Assistant IA',
				'complete_solution' => 'Solution complète',
				'gloriafood_switch' => 'Migration depuis GloriaFood',
				'migration_service' => 'Service de changement et migration',
			),
		),
	);
	return $fields[ awd_get_current_language( $lang ) ];
}

function awd_lm_handle_demo_form() {
	$lang = awd_get_current_language( isset( $_POST['lang'] ) ? wp_unslash( $_POST['lang'] ) : 'de' );
	$landing_key = sanitize_key( wp_unslash( $_POST['landing'] ?? 'gloriafood' ) );
	if ( ! awd_lm_definition( $landing_key ) ) { $landing_key = 'gloriafood'; }
	$is_ajax = isset( $_POST['awd_ajax'] ) && '1' === (string) wp_unslash( $_POST['awd_ajax'] );
	$redirect = function ( $status, $errors = array() ) use ( $landing_key, $lang, $is_ajax ) {
		if ( $is_ajax ) {
			wp_send_json( array( 'success' => 'success' === $status, 'status' => $status, 'errors' => array_values( (array) $errors ) ) );
		}
		$url = add_query_arg( 'awd_form', $status, awd_lm_public_url( $landing_key, $lang ) );
		wp_safe_redirect( $url . '#demo' );
		exit;
	};

	if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { $redirect( 'invalid' ); }
	if ( ! empty( $_POST['website_url_confirm'] ) ) { $redirect( 'honeypot' ); }
	$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
	$rate_key = 'awd_lm_demo_form_' . md5( $ip );
	$attempts = absint( get_transient( $rate_key ) );
	if ( $attempts >= 10 ) { $redirect( 'rate' ); }
	$turnstile_token = isset( $_POST['cf-turnstile-response'] ) ? wp_unslash( $_POST['cf-turnstile-response'] ) : '';
	if ( ! awd_lm_verify_turnstile_response( $turnstile_token, $ip ) ) { $redirect( 'captcha' ); }

	$first_name = sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) );
	$last_name = sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) );
	$name = trim( $first_name . ' ' . $last_name );
	$business = sanitize_text_field( wp_unslash( $_POST['business'] ?? '' ) );
	$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
	$phone = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
	$system = sanitize_key( wp_unslash( $_POST['system'] ?? '' ) );
	$system_other = sanitize_text_field( wp_unslash( $_POST['system_other'] ?? '' ) );
	$interest_fields = awd_lm_form_interest_fields( $lang );
	$interest_raw = isset( $_POST['interest'] ) && is_array( $_POST['interest'] ) ? wp_unslash( $_POST['interest'] ) : array();
	$interest = array();
	foreach ( $interest_raw as $interest_value ) {
		$interest_value = sanitize_key( $interest_value );
		if ( isset( $interest_fields['options'][ $interest_value ] ) ) { $interest[ $interest_value ] = $interest_fields['options'][ $interest_value ]; }
	}
	$message = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );
	$privacy_accepted = isset( $_POST['privacy_consent'] ) && '1' === (string) wp_unslash( $_POST['privacy_consent'] );
	$newsletter = isset( $_POST['newsletter'] ) && '1' === (string) wp_unslash( $_POST['newsletter'] );
	$analytics_session_id = awd_lm_analytics_valid_session_key( wp_unslash( $_POST['analytics_session_id'] ?? '' ) );
	$system_fields = awd_lm_form_system_fields( $lang );
	$invalid_messages = awd_lm_form_invalid_field_messages( $lang );
	$invalid_errors = array();
	if ( ! $first_name ) { $invalid_errors[] = $invalid_messages['first_name']; }
	if ( ! $last_name ) { $invalid_errors[] = $invalid_messages['last_name']; }
	if ( ! $business ) { $invalid_errors[] = $invalid_messages['business']; }
	if ( ! is_email( $email ) ) { $invalid_errors[] = $invalid_messages['email']; }
	if ( ! isset( $system_fields['options'][ $system ] ) ) { $invalid_errors[] = $invalid_messages['system']; }
	if ( 'other' === $system && ! $system_other ) { $invalid_errors[] = $invalid_messages['system_other']; }
	if ( $invalid_errors ) { $redirect( 'invalid', $invalid_errors ); }
	/* The browser normalises valid numbers to E.164. Keep the same hard limits
	 * Brevo applies to its SMS contact attribute when JavaScript is bypassed. */
	if ( '' !== $phone && ! preg_match( '/^\+[1-9][0-9]{6,14}$/', $phone ) ) { $redirect( 'phone' ); }
	if ( ! $privacy_accepted ) { $redirect( 'privacy' ); }
	/* Count only complete, human-verified submissions. Invalid fields and a
	 * failed CAPTCHA must not lock a real visitor out of the form. */
	set_transient( $rate_key, $attempts + 1, HOUR_IN_SECONDS );
	$system_label = $system_fields['options'][ $system ];
	if ( 'other' === $system ) { $system_label .= ': ' . $system_other; }
	$source_url = awd_lm_public_url( $landing_key, $lang );
	$landing_content = awd_lm_get_content( $landing_key );
	$privacy_url = $landing_content['prototype_links'][ $lang ]['l904'] ?? 'https://website-to-go.ch/datenschutz/';
	$lead_id = awd_lm_store_lead( array(
		'name' => $name, 'first_name' => $first_name, 'last_name' => $last_name, 'business' => $business, 'email' => $email, 'phone' => $phone,
		'system' => $system, 'system_other' => $system_other, 'interest' => $interest,
		'message' => $message, 'lang' => $lang, 'source_url' => $source_url, 'privacy_url' => esc_url_raw( $privacy_url ), 'newsletter' => $newsletter,
	) );
	if ( is_wp_error( $lead_id ) ) { $redirect( 'failed' ); }
	awd_lm_link_analytics_session_to_lead( $analytics_session_id, $lead_id, $newsletter );
	if ( $newsletter && awd_lm_brevo_ready() ) { awd_lm_process_brevo_queue(); }

	$recipients = awd_lm_form_recipients();
	if ( empty( $recipients ) ) {
		awd_lm_set_notification_status( $lead_id, 'failed', 'No valid notification recipient is configured.' );
		$redirect( 'success' );
	}
	$primary_recipient = array_shift( $recipients );
	$subject = sprintf( '[AI Will Do] Demo request from %s', $name );
	$interest_label = empty( $interest ) ? '—' : implode( ', ', array_values( $interest ) );
	$body = "Lead ID: {$lead_id}\nFirst name: {$first_name}\nLast name: {$last_name}\nBusiness: {$business}\nEmail: {$email}\nPhone: {$phone}\nCurrent system: {$system_label}\nInterest: {$interest_label}\nNewsletter requested: " . ( $newsletter ? 'Yes — Brevo sync pending' : 'No' ) . "\nLanguage: " . strtoupper( $lang ) . "\n\nMessage:\n{$message}";
	$headers = array( 'Content-Type: text/plain; charset=UTF-8', 'Reply-To: ' . $name . ' <' . $email . '>' );
	/* Extra recipients receive the same request without exposing their addresses. */
	if ( ! empty( $recipients ) ) { $headers[] = 'Bcc: ' . implode( ', ', $recipients ); }
	$sent = wp_mail( $primary_recipient, $subject, $body, $headers );
	awd_lm_set_notification_status( $lead_id, $sent ? 'sent' : 'failed', $sent ? '' : 'wp_mail() returned false.' );
	/* The local leads database is the source of truth. A notification problem
	 * must not tell the visitor that a safely stored request was lost. */
	$redirect( 'success' );
}
