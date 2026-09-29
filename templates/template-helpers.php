<?php
defined( 'ABSPATH' ) || exit;

function awd_lm_lines( $value ) {
	return array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $value ) ) ) );
}

/** Render post-processed landing copy with stable editor keys. */
function awd_lm_render_stable_prototype_text( $content, $lang, $key, $default, $is_editor, $default_html = '' ) {
	$value = isset( $content['prototype_text'][ $lang ][ $key ] ) ? $content['prototype_text'][ $lang ][ $key ] : $default;
	if ( $is_editor ) {
		return '<span class="awd-lm-inline awd-lm-inline-multiline" contenteditable="true" role="textbox" data-awd-text-key="' . esc_attr( $key ) . '">' . esc_html( $value ) . '</span>';
	}
	if ( '' !== $default_html && ! isset( $content['prototype_text'][ $lang ][ $key ] ) ) {
		return wp_kses( $default_html, array( 'strong' => array( 'style' => array() ) ) );
	}
	return esc_html( $value );
}

/** Slot-owned image metadata survives attachment replacement and server import. */
function awd_lm_second_landing_image_metadata( $lang ) {
	$items = array(
		'logo' => array( 'AI Will Do logo', 'AI Will Do logo', 'Logo AI Will Do' ),
		'hero' => array( 'Digital restaurant sales platform', 'Digitale Verkaufsplattform für Restaurants', 'Plateforme de vente numérique pour restaurants' ),
		'mock1' => array( 'Restaurant product presentation', 'Produktpräsentation für Restaurants', 'Présentation des produits du restaurant' ),
		'mock2' => array( 'Digital restaurant menu', 'Digitale Speisekarte', 'Menu numérique du restaurant' ),
		'mock3' => array( 'Online order management', 'Verwaltung von Onlinebestellungen', 'Gestion des commandes en ligne' ),
		'mock4' => array( 'Online table reservation', 'Online-Tischreservation', 'Réservation de table en ligne' ),
		'mock5' => array( 'AI guest assistant', 'KI-Assistent für Gäste', 'Assistant IA pour les clients' ),
		'product1' => array( 'Online ordering system for restaurants', 'Online-Bestellsystem für Restaurants', 'Système de commande en ligne pour restaurants' ),
		'product2' => array( 'Direct restaurant ordering for pickup and delivery', 'Direktbestellung im Restaurant für Abholung und Lieferung', 'Commande directe au restaurant à emporter et en livraison' ),
		'product3' => array( 'Restaurant website on laptop and smartphone', 'Restaurant-Website auf Laptop und Smartphone', 'Site de restaurant sur ordinateur portable et smartphone' ),
		'product4' => array( 'AI assistant answering guest questions', 'KI-Assistent beantwortet Fragen von Gästen', 'Assistant IA répondant aux questions des clients' ),
		'product5' => array( 'AI restaurant assistant connected to digital guest channels', 'KI-Restaurantassistent für digitale Gästekanäle', 'Assistant IA pour restaurant connecté aux canaux numériques des clients' ),
		'panel1' => array( 'Online ordering dashboard', 'Dashboard für Onlinebestellungen', 'Tableau de bord des commandes en ligne' ),
		'panel2' => array( 'Menu performance analytics', 'Analyse der Menüleistung', 'Analyse des performances du menu' ),
		'panel3' => array( 'Table reservation management', 'Verwaltung von Tischreservationen', 'Gestion des réservations de tables' ),
		'panel4' => array( 'Custom restaurant website preview', 'Vorschau einer individuellen Restaurant-Website', 'Aperçu d’un site de restaurant sur mesure' ),
		'panel5' => array( 'AI assistant conversation on a restaurant website', 'Dialog mit einem KI-Assistenten auf einer Restaurant-Website', 'Conversation avec un assistant IA sur un site de restaurant' ),
		'mock99' => array( 'Restaurant orders app', 'Bestell-App für Restaurants', 'Application de commandes pour restaurants' ),
		'mock100' => array( 'Restaurant CRM dashboard and mobile newsletter', 'Restaurant-CRM-Dashboard und mobiler Newsletter', 'Tableau de bord CRM et newsletter mobile pour restaurant' ),
		'chat-avatar' => array( 'AI restaurant assistant avatar', 'Avatar des KI-Restaurantassistenten', 'Avatar de l’assistant IA du restaurant' ),
	);
	$position = 'de' === $lang ? 1 : ( 'fr' === $lang ? 2 : 0 );
	$result = array();
	foreach ( $items as $slot => $labels ) { $result[ $slot ] = array( 'title'=>$labels[ $position ], 'alt'=>$labels[ $position ] ); }
	return $result;
}

/** Render editable regional contacts without adding indexed prototype nodes. */
function awd_lm_render_contact_phones( $content, $lang, $is_editor ) {
	$defaults = array(
		'de' => array( 'Telefon', '+352 661 554 736' ),
		'en' => array( 'Call us', '+352 661 554 736' ),
		'fr' => array( 'Appelez-nous', '+352 661 554 736' ),
	);
	$values = $defaults[ $lang ] ?? $defaults['de'];
	$number = (string) ( $content['prototype_text'][ $lang ]['t9003'] ?? $values[1] );
	$telephone_href = static function ( $number ) {
		$number = preg_replace( '/(?!^)\+|[^0-9+]/', '', (string) $number );
		return 'tel:' . ( '' !== $number ? $number : '+' );
	};
	$label = $values[0];
	return '<div class="wtg-contact-phones"><a class="wtg-contact-phone wtg-contact-phone--icon" href="' . esc_url( $telephone_href( $number ) ) . '" aria-label="' . esc_attr( $label ) . '"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2C9.928 20.51 3.49 14.072 3 6a2 2 0 0 1 2-2z"/></svg></a></div>';
}

/** Render the second landing's independent five-slide marketing hook set.
 * Permanent high-numbered keys keep these hooks editable without changing the
 * historical V7 text/link indexes used by either landing's remaining content.
 */
function awd_lm_render_second_landing_hero_slides( $content, $lang, $is_editor ) {
	$copy = array(
		'en' => array(
			array( 'ALL-IN-ONE · ONE SYSTEM, NOT FIVE', 'One powerful sales engine', 'instead of 5 systems.', 'One shared dataset behind everything.', 'Explore the complete solution', '#platform' ),
			array( 'ONLINE ORDERING · SKIP THE AGGREGATOR', 'Take pickup and delivery orders', 'directly.', 'One tile among fifty. Or just you.', 'Explore online ordering', '#take-away' ),
			array( 'RESTAURANT WEBSITE · SELLS AND ADVISES', 'Not just a website that sells.', 'One that tells you how to sell more.', 'See what guests click, order, and skip.', 'Explore restaurant websites', '#webdesign' ),
			array( 'ONLINE RESERVATIONS · NO WASTED TABLES', 'A booking at 7:15 blocks the table at 6 and at 9.', 'Not on this calendar.', 'No booking blocks the ones around it.', 'Explore online reservations', '#reservation' ),
			array( 'AI CHAT ASSISTANT · ANSWERS, DAY AND NIGHT', 'Your guests have questions.', 'Also at 10:30 pm.', 'Answers from your real menu, day or night.', 'Explore the AI chat assistant', '#ki' ),
		),
		'de' => array(
			array( 'ALL-IN-ONE · EIN SYSTEM STATT FÜNF', 'Eine leistungsstarke Verkaufsmaschine', 'statt fünf verschiedener Systeme.', 'Eine gemeinsame Datenbasis für alles.', 'Komplettlösung entdecken', '#platform' ),
			array( 'ONLINE-BESTELLUNG · OHNE AGGREGATOR', 'Nehmen Sie Abhol- und Lieferaufträge direkt', 'entgegen.', 'Ein Listing unter vielen. Oder nur Sie.', 'Online-Bestellung entdecken', '#take-away' ),
			array( 'RESTAURANT-WEBSITE · VERKAUFT UND BERÄT', 'Nicht nur eine Website, die verkauft.', 'Eine, die Ihnen sagt, wie Sie mehr verkaufen können.', 'Sehen Sie, was Gäste anklicken, bestellen und überspringen.', 'Restaurant-Website entdecken', '#webdesign' ),
			array( 'ONLINE-RESERVIERUNG · KEINE VERSCHWENDETEN TISCHE', 'Eine Buchung um 7:15 Uhr blockiert die Tische um 6 Uhr und um 9 Uhr.', 'Nicht in diesem Kalender.', 'Keine Reservation blockiert die umliegenden Zeiten.', 'Online-Reservation entdecken', '#reservation' ),
			array( 'KI CHAT ASSISTENT · ANTWORTET TAG UND NACHT', 'Ihre Gäste haben Fragen.', 'Auch um 22:30 Uhr.', 'Antworten aus Ihrer echten Speisekarte – bei Tag und Nacht.', 'KI Chat Assistent entdecken', '#ki' ),
		),
		'fr' => array(
			array( 'TOUT-EN-UN · UN SYSTÈME, PAS CINQ', 'Un puissant moteur de vente', 'au lieu de cinq systèmes.', 'Une base de données commune pour tout.', 'Découvrir la solution complète', '#platform' ),
			array( 'COMMANDE EN LIGNE · SANS AGRÉGATEUR', 'Prenez les commandes à emporter et à livrer', 'directement.', 'Une fiche parmi tant d\'autres. Ou juste vous.', 'Découvrir la commande en ligne', '#take-away' ),
			array( 'SITE DE RESTAURANT · VEND ET CONSEILLE', 'Pas seulement un site qui vend.', 'Un site qui vous montre comment vendre davantage.', 'Voyez ce que les clients consultent, commandent ou ignorent.', 'Découvrir les sites de restaurant', '#webdesign' ),
			array( 'RÉSERVATIONS EN LIGNE · AUCUNE TABLE GASPILLÉE', 'Une réservation à 7h15 bloque les tables à 6h et à 9h.', 'Pas sur ce calendrier.', 'Aucune réservation ne bloque les créneaux voisins.', 'Découvrir les réservations en ligne', '#reservation' ),
			array( 'ASSISTANT IA · RÉPOND JOUR ET NUIT', 'Vos clients ont des questions.', 'Même à 22 h 30.', 'Des réponses issues de votre vrai menu, jour et nuit.', 'Découvrir l’assistant chat IA', '#ki' ),
		),
	);
	$slides = $copy[ $lang ] ?? $copy['de'];
	$html = '<div class="wtg-hero-copy">';
	foreach ( $slides as $index => $slide ) {
		$text_base = 9100 + ( $index * 6 );
		$link_base = 9100 + ( $index * 2 );
		$kicker = awd_lm_render_stable_prototype_text( $content, $lang, 't' . $text_base, $slide[0], $is_editor );
		$title = awd_lm_render_stable_prototype_text( $content, $lang, 't' . ( $text_base + 1 ), $slide[1], $is_editor );
		$accent = awd_lm_render_stable_prototype_text( $content, $lang, 't' . ( $text_base + 2 ), $slide[2], $is_editor );
		$lead = awd_lm_render_stable_prototype_text( $content, $lang, 't' . ( $text_base + 3 ), $slide[3], $is_editor );
		$primary = awd_lm_render_stable_prototype_text( $content, $lang, 't' . ( $text_base + 4 ), $slide[4], $is_editor );
		$demo_default = array( 'de' => 'Live-Demo anfragen', 'en' => 'Request a live demo', 'fr' => 'Demander une démo' );
		$demo = awd_lm_render_stable_prototype_text( $content, $lang, 't' . ( $text_base + 5 ), $demo_default[ $lang ] ?? $demo_default['de'], $is_editor );
		$primary_key = 'l' . $link_base;
		$demo_key = 'l' . ( $link_base + 1 );
		$primary_url = $content['prototype_links'][ $lang ][ $primary_key ] ?? $slide[5];
		$demo_url = $content['prototype_links'][ $lang ][ $demo_key ] ?? '#demo';
		$html .= '<article class="wtg-slide' . ( 0 === $index ? ' is-active' : '' ) . '" data-slide="' . absint( $index ) . '">';
		$html .= '<div class="wtg-kicker">' . $kicker . '</div>';
		$html .= '<h1 class="wtg-h1">' . $title . ' <span class="wtg-accent">' . $accent . '</span></h1>';
		$html .= '<p class="wtg-lead">' . $lead . '</p><div class="wtg-actions">';
		$html .= '<a class="wtg-btn wtg-btn--primary" href="' . esc_url( $primary_url ) . '"' . ( $is_editor ? ' data-awd-link-key="' . esc_attr( $primary_key ) . '"' : '' ) . '>' . $primary . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>';
		$html .= '<a class="wtg-btn wtg-btn--ghost" href="' . esc_url( $demo_url ) . '"' . ( $is_editor ? ' data-awd-link-key="' . esc_attr( $demo_key ) . '"' : '' ) . '>' . $demo . '</a>';
		$html .= '</div></article>';
	}
	return $html . '</div>';
}

/** Render the second landing's problem cards as an accessible before/after
 * interaction. Stable high-numbered keys keep every face independently
 * editable without remapping the original V7 landing copy. */
function awd_lm_render_second_landing_problem_section( $content, $lang, $is_editor ) {
	$copy = array(
		'en' => array(
			'head' => array( 'DOES THIS SOUND FAMILIAR?', 'Running a restaurant online', 'shouldn\'t feel like four separate jobs.', 'Doing this by phone, or stuck with a system that doesn’t fit anymore? It shouldn’t be this hard.' ),
			'cards' => array(
				array( 'One listing among many', 'A guest opens a delivery aggregator looking for you, and orders from whoever\'s listed next to you instead.', 'OUR SOLUTION', 'Your restaurant. No aggregator in between.', 'Guests order directly from your own page for pickup or delivery.' ),
				array( 'One change, 4 logins', 'With multiple systems, you type the same dish again for the website, again for the newsletter, again for the menu.', 'OUR SOLUTION', 'Update once. Stay current everywhere.', 'Your website, orders and guest communication all use the same information. Update prices, dishes or opening hours just once.' ),
				array( 'One booking, two ruined slots', 'A badly placed booking leaves the table half-empty before and after it, unnoticed until service starts.', 'OUR SOLUTION', 'Protect the slots around every booking.', 'Capacity-aware reservation rules place bookings without wasting the times around them.' ),
				array( 'Closed for questions after 9pm', 'A guest asks if you’re open tonight, gets no answer, and books somewhere that replies.', 'OUR SOLUTION', 'Answers from your real menu, day and night.', 'The AI assistant answers menu, opening-hours and booking questions immediately.' ),
			),
		),
		'de' => array(
			'head' => array( 'KOMMT IHNEN DAS BEKANNT VOR?', 'Ein Restaurant online zu führen', 'sollte sich nicht wie vier getrennte Jobs anfühlen.', 'Erledigen Sie das noch telefonisch oder stecken in einem System fest, das nicht mehr passt? So kompliziert sollte es nicht sein.' ),
			'cards' => array(
				array( 'Ein Eintrag unter vielen', 'Ein Gast öffnet einen Lieferdienst-Aggregator, um Sie zu finden, und bestellt stattdessen beim Eintrag direkt daneben.', 'UNSERE LÖSUNG', 'Ihr Restaurant. Kein Aggregator dazwischen.', 'Gäste bestellen direkt auf Ihrer eigenen Seite – zur Abholung oder Lieferung.' ),
				array( 'Eine Änderung, 4 Logins', 'Bei mehreren Systemen tippen Sie dasselbe Gericht erneut für die Website, erneut für den Newsletter, erneut für die Speisekarte ein.', 'UNSERE LÖSUNG', 'Einmal ändern. Überall aktuell.', 'Website, Bestellungen und Gästekommunikation greifen auf dieselben Informationen zu. Sie aktualisieren Preise, Gerichte oder Öffnungszeiten nur einmal.' ),
				array( 'Eine Reservation, zwei verlorene Zeitfenster', 'Eine ungünstige Reservation lässt den Tisch davor und danach halb leer – bemerkt wird es erst im Service.', 'UNSERE LÖSUNG', 'Die Zeiten rund um jede Reservation schützen.', 'Kapazitätsregeln platzieren Reservationen, ohne die Zeitfenster davor und danach zu verschwenden.' ),
				array( 'Nach 21 Uhr keine Antworten', 'Ein Gast fragt, ob Sie heute Abend geöffnet haben, erhält keine Antwort und bucht anderswo, wo man ihm antwortet.', 'UNSERE LÖSUNG', 'Antworten aus Ihrer echten Speisekarte – Tag und Nacht.', 'Der KI Assistent beantwortet Fragen zu Menü, Öffnungszeiten und Reservation sofort.' ),
			),
		),
		'fr' => array(
			'head' => array( 'CETTE SITUATION VOUS EST FAMILIÈRE ?', 'Gérer un restaurant en ligne', 'ne devrait pas ressembler à quatre métiers différents.', 'Vous gérez encore cela par téléphone ou avec un système qui ne vous convient plus ? Cela ne devrait pas être si compliqué.' ),
			'cards' => array(
				array( 'Une fiche parmi tant d’autres', 'Un client ouvre une plateforme de livraison à la recherche de vous, et commande plutôt chez l’établissement juste à côté.', 'NOTRE SOLUTION', 'Votre restaurant. Aucun agrégateur entre vous.', 'Les clients commandent directement sur votre propre page, à emporter ou en livraison.' ),
				array( 'Un changement, 4 connexions', 'Avec plusieurs systèmes, vous retapez le même plat encore pour le site web, encore pour la newsletter, encore pour le menu.', 'NOTRE SOLUTION', 'Une seule modification. À jour partout.', 'Votre site web, vos commandes et vos échanges avec les clients utilisent les mêmes informations. Modifiez les prix, les plats ou les horaires d’ouverture une seule fois.' ),
				array( 'Une réservation, deux créneaux perdus', 'Une réservation mal placée laisse la table à moitié vide avant et après, sans que personne ne le voie avant le service.', 'NOTRE SOLUTION', 'Préserver les créneaux autour de chaque réservation.', 'Des règles de capacité placent les réservations sans gaspiller les horaires voisins.' ),
				array( 'Fermé aux questions après 21 h', 'Un client demande si vous êtes ouvert, ne reçoit aucune réponse et réserve là où on lui répond.', 'NOTRE SOLUTION', 'Des réponses issues de votre vrai menu, jour et nuit.', 'L’assistant IA répond immédiatement aux questions sur le menu, les horaires et les réservations.' ),
			),
		),
	);
	$current = $copy[ $lang ] ?? $copy['de'];
	$text = static function ( $key, $default ) use ( $content, $lang, $is_editor ) {
		return awd_lm_render_stable_prototype_text( $content, $lang, $key, $default, $is_editor );
	};
	$icons = array(
		'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 4h1a1 1 0 0 1 1 1v1m-.29 3.704a1 1 0 0 1 -.71 .296h-4a1 1 0 0 1 -1 -1v-4c0 -.276 .111 -.525 .292 -.706"/><path d="M18 14h1a1 1 0 0 1 1 1v1m-.29 3.704a1 1 0 0 1 -.71 .296h-4a1 1 0 0 1 -1 -1v-4c0 -.276 .111 -.525 .292 -.706"/><path d="M4 15a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v4a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1l0 -4"/><path d="M14 7h6M17 4v6M3 3l18 18"/></svg>',
		'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 9.667a2.667 2.667 0 0 1 2.667 -2.667h8.666a2.667 2.667 0 0 1 2.667 2.667v8.666a2.667 2.667 0 0 1 -2.667 2.667h-8.666a2.667 2.667 0 0 1 -2.667 -2.667l0 -8.666"/><path d="M4.012 16.737a2 2 0 0 1 -1.012 -1.737v-10c0 -1.1 .9 -2 2 -2h10c.75 0 1.158 .385 1.5 1"/><path d="M11.5 11.5l4.9 5M16.5 11.5l-5.1 5"/></svg>',
		'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 21h-9a2 2 0 0 1 -2 -2v-12a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v5M16 3v4M8 3v4M4 11h16M11 15h1M12 15v3M19 16v3M19 22v.01"/></svg>',
		'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 9h1m4 0h3M8 13h5"/><path d="M8 4h10a3 3 0 0 1 3 3v8c0 .577 -.163 1.116 -.445 1.573m-2.555 1.427h-5l-5 3v-3h-2a3 3 0 0 1 -3 -3v-8c0 -1.085 .576 -2.036 1.439 -2.562M3 3l18 18"/></svg>',
	);
	$html = '<section class="wtg-section wtg-section--cream" id="problem"><div class="wtg-container">';
	$html .= '<header class="wtg-problem-flip-head" data-wtg-reveal><div class="wtg-kicker">' . $text( 't9200', $current['head'][0] ) . '</div>';
	$html .= '<h2 class="wtg-h2">' . $text( 't9201', $current['head'][1] ) . ' <span class="wtg-orange">' . $text( 't9202', $current['head'][2] ) . '</span></h2>';
	$html .= '<p class="wtg-lead">' . $text( 't9203', $current['head'][3] ) . '</p></header><div class="wtg-problem-flip-grid">';
	foreach ( $current['cards'] as $index => $card ) {
		$base = 9210 + ( $index * 5 );
		$html .= '<article class="wtg-problem-flip-card" data-wtg-reveal="delay' . absint( $index ) . '" tabindex="0" role="button" aria-pressed="false">';
		$html .= '<div class="wtg-problem-flip-inner"><div class="wtg-problem-flip-face wtg-problem-flip-front"><div class="wtg-icon" aria-hidden="true">' . $icons[ $index ] . '</div>';
		$html .= '<h3>' . $text( 't' . $base, $card[0] ) . '</h3><p>' . $text( 't' . ( $base + 1 ), $card[1] ) . '</p><span class="wtg-problem-flip-hint" aria-hidden="true">↻</span></div>';
		$html .= '<div class="wtg-problem-flip-face wtg-problem-flip-back"><span class="wtg-problem-solution-mark" aria-hidden="true">✓</span><span class="wtg-problem-face-label">' . $text( 't' . ( $base + 2 ), $card[2] ) . '</span>';
		$html .= '<h3>' . $text( 't' . ( $base + 3 ), $card[3] ) . '</h3><p>' . $text( 't' . ( $base + 4 ), $card[4] ) . '</p></div></div></article>';
	}
	return $html . '</div></div></section>';
}

/** Render the second landing's online-ordering copy with its own permanent
 * fields. Keeping this outside the historical indexed copy prevents client
 * edits on either landing from being remapped when the list grows. */
function awd_lm_render_second_landing_ordering_copy( $content, $lang, $is_editor ) {
	$copy = array(
		'en' => array(
			'kicker' => 'AI WILL DO · ONLINE ORDERING',
			'title' => 'Start with the menu.',
			'accent' => 'It’s ready to sell, not just to read.',
			'lead' => 'Set up your menu quickly and only once — no more copy-paste.',
			'bullets' => array(
				'AI writes polished descriptions in all 4 languages',
				'Update prices, photos and details in one place',
				'Set availability with one click',
				'Ready to use on your website, social media, printed menu and AI Assistant',
			),
			'button' => 'See demo',
		),
		'de' => array(
			'kicker' => 'AI WILL DO · ONLINE-BESTELLUNGEN',
			'title' => 'Beginnen Sie mit der Speisekarte.',
			'accent' => 'Bereit zum Verkaufen – nicht nur zum Lesen.',
			'lead' => 'Speisekarte schnell und nur einmal einrichten — kein Copy-Paste mehr.',
			'bullets' => array(
				'Die KI schreibt ausgefeilte Beschreibungen in allen 4 Sprachen',
				'Preise, Fotos und Details an einem Ort aktualisieren',
				'Verfügbarkeit mit einem Klick festlegen',
				'Bereit für Website, Social Media, gedruckte Speisekarte und KI-Assistent',
			),
			'button' => 'Demo ansehen',
		),
		'fr' => array(
			'kicker' => 'AI WILL DO · COMMANDES EN LIGNE',
			'title' => 'Commencez par le menu.',
			'accent' => 'Prêt à vendre, pas seulement à être lu.',
			'lead' => 'Créez votre menu rapidement et une seule fois — fini le copier-coller.',
			'bullets' => array(
				'L’IA rédige des descriptions soignées dans les 4 langues',
				'Mettez à jour prix, photos et détails en un seul endroit',
				'Définissez la disponibilité en un clic',
				'Prêt à être utilisé sur votre site web, les réseaux sociaux, le menu imprimé et par votre assistant IA',
			),
			'button' => 'Voir la démo',
		),
	);
	$current = $copy[ $lang ] ?? $copy['de'];
	$text = static function ( $key, $default ) use ( $content, $lang, $is_editor ) {
		return awd_lm_render_stable_prototype_text( $content, $lang, $key, $default, $is_editor );
	};
	$button_url = $content['prototype_links'][ $lang ]['l9300'] ?? '#demo';
	$html = '<div class="wtg-detail-copy" data-wtg-reveal><div class="wtg-kicker">' . $text( 't9300', $current['kicker'] ) . '</div>';
	$html .= '<h2 class="wtg-h2">' . $text( 't9301', $current['title'] ) . ' <span class="wtg-orange">' . $text( 't9302', $current['accent'] ) . '</span></h2>';
	$html .= '<p class="wtg-lead" style="margin-top:24px">' . $text( 't9303', $current['lead'] ) . '</p><div class="wtg-bullets wtg-ordering-benefits">';
	$bullet_keys = array( 't9304', 't9306', 't9307', 't9308' );
	foreach ( $current['bullets'] as $index => $bullet ) {
		$html .= '<div class="wtg-bullet' . ( 3 === $index ? ' wtg-ordering-benefit--highlight' : '' ) . '"><i>✓</i><div>' . $text( $bullet_keys[ $index ], $bullet ) . '</div></div>';
	}
	$html .= '</div></div><div class="wtg-actions wtg-ordering-demo-action"><a class="wtg-btn wtg-btn--primary" href="' . esc_url( $button_url ) . '"' . ( $is_editor ? ' data-awd-link-key="l9300"' : '' ) . '>' . $text( 't9309', $current['button'] ) . '</a></div>';
	return $html;
}

/** Render a compact reservation setup panel for the second landing. Unlike the
 * ordering bullets, these controls are presented as a 2 × 2 configuration
 * dashboard to give the next section its own visual rhythm. */
function awd_lm_render_second_landing_reservation_copy( $content, $lang, $is_editor ) {
	$copy = array(
		'en' => array(
			'kicker' => 'AI WILL DO · ONLINE RESERVATIONS',
			'title' => 'Set your hours and capacity the same way — once.',
			'accent' => 'That’s ready to book too.',
			'lead' => 'Tell the system when you’re open and how many tables you have, and reservations run on it automatically. Guests book inside the slots you actually allow — no call during the rush.',
			'features' => array(
				'Configurable time slots, capacity, and blackout periods',
				'Guest details captured automatically',
				'Your own rules: notice period, party size, and per-service schedules',
				'Calendar and daily-list views for the team',
			),
			'button' => 'Explore online reservations',
		),
		'de' => array(
			'kicker' => 'AI WILL DO · ONLINE-RESERVATIONEN',
			'title' => 'Öffnungszeiten und Kapazität legen Sie genauso fest – einmal.',
			'accent' => 'Schon kann gebucht werden.',
			'lead' => 'Teilen Sie dem System mit, wann Sie geöffnet haben und wie viele Tische verfügbar sind – die Reservationen richten sich automatisch danach. Gäste buchen nur innerhalb der Zeitfenster, die Sie tatsächlich freigeben. Kein Anruf mitten im Service.',
			'features' => array(
				'Konfigurierbare Zeitfenster, Kapazitäten und Sperrzeiten',
				'Gästedaten werden automatisch erfasst',
				'Ihre eigenen Regeln: Kündigungsfrist, Gruppengröße und Zeitpläne pro Service',
				'Kalender- und Tageslistenansicht für das Team',
			),
			'button' => 'Online-Reservationen entdecken',
		),
		'fr' => array(
			'kicker' => 'AI WILL DO · RÉSERVATIONS EN LIGNE',
			'title' => 'Définissez vos horaires et votre capacité de la même façon – une seule fois.',
			'accent' => 'Tout est prêt à réserver.',
			'lead' => 'Indiquez au système vos horaires d’ouverture et le nombre de tables disponibles : les réservations suivent automatiquement ces paramètres. Les clients réservent uniquement dans les créneaux que vous autorisez réellement, sans appel en plein service.',
			'features' => array(
				'Créneaux, capacités et périodes de fermeture configurables',
				'Coordonnées des clients enregistrées automatiquement',
				'Vos règles : délai, taille des groupes et horaires par service',
				'Vues calendrier et liste du jour pour l’équipe',
			),
			'button' => 'Découvrir les réservations en ligne',
		),
	);
	$current = $copy[ $lang ] ?? $copy['de'];
	$text = static function ( $key, $default ) use ( $content, $lang, $is_editor ) {
		return awd_lm_render_stable_prototype_text( $content, $lang, $key, $default, $is_editor );
	};
	$icons = array(
		'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 7v5l3 2M5 3v4M19 3v4M4 9h16"/><rect x="3" y="5" width="18" height="16" rx="2"/></svg>',
		'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0-8 0M6 21v-2a4 4 0 0 1 4-4h3"/><path d="M15 19l2 2l4-4"/></svg>',
		'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16M4 12h16M4 18h16M8 4v4M16 10v4M10 16v4"/></svg>',
		'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5h16v15H4zM8 3v4M16 3v4M4 10h16M8 14h2M14 14h2M8 17h2"/></svg>',
	);
	$button_url = $content['prototype_links'][ $lang ]['l9400'] ?? '#demo';
	$html = '<div class="wtg-detail-copy wtg-reservation-copy" data-wtg-reveal><div class="wtg-kicker">' . $text( 't9400', $current['kicker'] ) . '</div>';
	$html .= '<h2 class="wtg-h2">' . $text( 't9401', $current['title'] ) . ' <span class="wtg-accent">' . $text( 't9402', $current['accent'] ) . '</span></h2>';
	$html .= '<p class="wtg-lead" style="margin-top:24px">' . $text( 't9403', $current['lead'] ) . '</p><div class="wtg-reservation-settings">';
	foreach ( $current['features'] as $index => $feature ) {
		$html .= '<div class="wtg-reservation-setting" data-wtg-reveal="delay' . absint( $index ) . '"><span class="wtg-reservation-setting-icon" aria-hidden="true">' . $icons[ $index ] . '</span><span>' . $text( 't' . ( 9404 + $index ), $feature ) . '</span></div>';
	}
	$html .= '</div><div class="wtg-actions"><a class="wtg-btn wtg-btn--primary" href="' . esc_url( $button_url ) . '"' . ( $is_editor ? ' data-awd-link-key="l9400"' : '' ) . '>' . $text( 't9408', $current['button'] ) . '</a></div></div>';
	return $html;
}

/** Render the second landing's four-step operating loop with permanent keys. */
function awd_lm_render_second_landing_workflow_section( $content, $lang, $is_editor ) {
	$copy = array(
		'en' => array(
			'kicker' => 'ONE CONNECTED WORKFLOW',
			'title' => 'Add menus and offers once.',
			'lead' => 'Guests order and reserve directly.',
			'steps' => array(
				array( 'CREATE', 'Add it once.', 'Create or update a dish, offer or opening hours with AI assistance — once, in one place.' ),
				array( 'PUBLISH', 'Use it everywhere.', 'Website, ordering, AI, newsletters, social and printed materials — in every language.' ),
				array( 'SELL', 'Guests act directly.', 'They order, reserve and ask questions directly — without phone calls, aggregators or unnecessary steps.' ),
				array( 'LEARN', 'See what actually works.', 'Track what guests view, order and respond to — then improve what you offer next.' ),
			),
		),
		'de' => array(
			'kicker' => 'EIN VERBUNDENER ABLAUF',
			'title' => 'Menüs und Angebote einmal hinzufügen.',
			'lead' => 'Gäste bestellen und reservieren direkt.',
			'steps' => array(
				array( 'ERSTELLEN', 'Einmal hinzufügen.', 'Erstellen oder aktualisieren Sie ein Gericht, Angebot oder Öffnungszeiten mit KI-Unterstützung — einmal, an einem Ort.' ),
				array( 'VERÖFFENTLICHEN', 'Überall verwenden.', 'Website, Bestellsystem, KI, Newsletter, soziale Medien und Druckmaterialien — in jeder Sprache.' ),
				array( 'VERKAUFEN', 'Gäste handeln direkt.', 'Sie bestellen, reservieren und stellen direkt Fragen — ohne Telefonate, Aggregatoren oder unnötige Schritte.' ),
				array( 'LERNEN', 'Sehen, was wirklich funktioniert.', 'Verfolgen Sie, was Gäste ansehen, bestellen und worauf sie reagieren — und verbessern Sie anschließend Ihr nächstes Angebot.' ),
			),
		),
		'fr' => array(
			'kicker' => 'UN PROCESSUS CONNECTÉ',
			'title' => 'Ajoutez vos menus et offres une seule fois.',
			'lead' => 'Les clients commandent et réservent directement.',
			'steps' => array(
				array( 'CRÉER', 'Ajoutez-le une seule fois.', 'Créez ou mettez à jour un plat, une offre ou des horaires avec l’aide de l’IA — une seule fois, au même endroit.' ),
				array( 'PUBLIER', 'Utilisez-le partout.', 'Site web, commandes, IA, newsletters, réseaux sociaux et supports imprimés — dans toutes les langues.' ),
				array( 'VENDRE', 'Les clients agissent directement.', 'Ils commandent, réservent et posent leurs questions directement — sans appels téléphoniques, agrégateurs ni étapes inutiles.' ),
				array( 'APPRENDRE', 'Voyez ce qui fonctionne vraiment.', 'Suivez ce que les clients consultent, commandent et ce à quoi ils réagissent — puis améliorez votre prochaine offre.' ),
			),
		),
	);
	$current = $copy[ $lang ] ?? $copy['de'];
	$text = static function ( $key, $default ) use ( $content, $lang, $is_editor ) {
		return awd_lm_render_stable_prototype_text( $content, $lang, $key, $default, $is_editor );
	};
	$html = '<section class="wtg-section wtg-section--cream" id="platform"><div class="wtg-container">';
	$html .= '<header class="wtg-workflow-head" data-wtg-reveal><div class="wtg-kicker">' . $text( 't9600', $current['kicker'] ) . '</div>';
	$html .= '<h2 class="wtg-h2">' . $text( 't9601', $current['title'] ) . '</h2><p class="wtg-lead">' . $text( 't9602', $current['lead'] ) . '</p></header>';
	$html .= '<div class="wtg-flow">';
	foreach ( $current['steps'] as $index => $step ) {
		$base = 9610 + ( $index * 3 );
		$title_key = 1 === $index ? 't9651' : 't' . ( $base + 1 );
		$body_key  = 1 === $index ? 't9652' : 't' . ( $base + 2 );
		$html .= '<article class="wtg-flow-step" data-wtg-reveal="delay' . absint( $index ) . '"><div class="wtg-card-num">' . $text( 't' . $base, $step[0] ) . '</div>';
		$html .= '<h3>' . $text( $title_key, $step[1] ) . '</h3>';
		if ( '' !== $step[2] ) {
			$html .= '<p>' . $text( $body_key, $step[2] ) . '</p>';
		}
		if ( $index < 3 ) {
			$html .= '<span class="wtg-flow-arrow" aria-hidden="true">→</span>';
		}
		$html .= '</article>';
	}
	return $html . '</div></div></section>';
}

/** Render the second landing's menu-performance module with a wide visual and
 * three plain-language insight cards. */
function awd_lm_render_second_landing_menu_performance_section( $content, $lang, $is_editor ) {
	$copy = array(
		'en' => array(
			'kicker' => 'MENU PERFORMANCE',
			'title' => 'Every dish already tracks its own performance.',
			'accent' => 'No Google Analytics to set up. No dashboard to learn.',
			'cards' => array(
				array( 'Per-dish numbers', 'Viewed, considered, and ordered — not abstract site traffic.' ),
				array( 'Plain-language guidance', 'See what’s working, what’s underperforming, and what to try next.' ),
				array( 'Nothing extra to install', 'It runs on the same order and website data you already use.' ),
			),
		),
		'de' => array(
			'kicker' => 'MENÜ-PERFORMANCE',
			'title' => 'Jedes Gericht misst bereits seine eigene Performance.',
			'accent' => 'Kein Google Analytics einzurichten. Kein neues Dashboard zu lernen.',
			'cards' => array(
				array( 'Zahlen pro Gericht', 'Angesehen, in Betracht gezogen und bestellt — statt abstrakter Website-Zugriffe.' ),
				array( 'Hinweise in klarer Sprache', 'Erkennen Sie, was funktioniert, was unter den Erwartungen bleibt und was Sie als Nächstes testen können.' ),
				array( 'Nichts zusätzlich installieren', 'Die Auswertung nutzt dieselben Bestell- und Website-Daten, mit denen Sie bereits arbeiten.' ),
			),
		),
		'fr' => array(
			'kicker' => 'PERFORMANCE DU MENU',
			'title' => 'Chaque plat mesure déjà ses propres performances.',
			'accent' => 'Aucun Google Analytics à configurer. Aucun nouveau tableau de bord à apprendre.',
			'cards' => array(
				array( 'Statistiques par plat', 'Consulté, pris en compte et commandé – pas de trafic abstrait sur le site.' ),
				array( 'Des conseils en langage clair', 'Découvrez ce qui fonctionne, ce qui sous-performe et ce que vous pouvez essayer ensuite.' ),
				array( 'Rien de plus à installer', 'L’analyse repose sur les mêmes données de commande et de site que vous utilisez déjà.' ),
			),
		),
	);
	$current = $copy[ $lang ] ?? $copy['de'];
	$text = static function ( $key, $default ) use ( $content, $lang, $is_editor ) {
		return awd_lm_render_stable_prototype_text( $content, $lang, $key, $default, $is_editor );
	};
	$icons = array(
		'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19h16M6 16v-5M10 16V8M14 16v-3M18 16V5"/></svg>',
		'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 4h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-6l-4 3v-3H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/><path d="M8 9h8M8 13h5"/></svg>',
		'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 11v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/></svg>',
	);
	$html = '<section class="wtg-section wtg-section--cream" id="menu-marketing"><div class="wtg-container"><div class="wtg-switch-card wtg-menu-performance">';
	$html .= '<div class="wtg-menu-performance-visual wtg-detail-visual" data-wtg-reveal><div class="wtg-visual-panel"></div></div>';
	$html .= '<header class="wtg-menu-performance-head" data-wtg-reveal="delay1"><div class="wtg-kicker">' . $text( 't9700', $current['kicker'] ) . '</div>';
	$html .= '<h2 class="wtg-h2">' . $text( 't9701', $current['title'] ) . '</h2><p class="wtg-menu-performance-subhead">' . $text( 't9702', $current['accent'] ) . '</p></header>';
	$html .= '<div class="wtg-menu-performance-grid">';
	foreach ( $current['cards'] as $index => $card ) {
		$base = 9710 + ( $index * 2 );
		$html .= '<article class="wtg-menu-performance-card" data-wtg-reveal="delay' . absint( $index + 1 ) . '"><span class="wtg-menu-performance-icon" aria-hidden="true">' . $icons[ $index ] . '</span>';
		$html .= '<h3>' . $text( 't' . $base, $card[0] ) . '</h3><p>' . $text( 't' . ( $base + 1 ), $card[1] ) . '</p></article>';
	}
	return $html . '</div></div></div></section>';
}

/** Render the operational hand-off between ordering channels and the Orders App.
 * The dedicated mock99 slot avoids changing historical image-slot numbering. */
function awd_lm_render_second_landing_orders_app_section( $content, $lang, $is_editor ) {
	$copy = array(
		'en' => array(
			'kicker' => 'ONE QUEUE · EVERY ORDER',
			'title' => 'Order Management & the Orders App',
			'lead' => 'Website, AI assistant, or typed in by hand — every order lands in the same queue. That’s what the Orders App shows your team.',
			'cta' => 'Contact us',
			'cards' => array(
				array( 'Live order flow', 'Real-time order display, from new to complete.' ),
				array( 'Alert wherever work happens', 'A mobile app for kitchen, counter, or reception, with a sound alert for new orders.' ),
				array( 'The right view for every role', 'Role-based access keeps each team member focused on the work that matters.' ),
				array( 'One operational workflow', 'Pickup, delivery, and pre-order are handled the same way.' ),
			),
		),
		'de' => array(
			'kicker' => 'EINE WARTESCHLANGE · JEDE BESTELLUNG',
			'title' => 'Bestellmanagement & Orders App',
			'lead' => 'Ob über Website, KI-Assistent oder manuell erfasst – jede Bestellung landet in derselben Warteschlange. Genau diese zeigt die Orders App Ihrem Team.',
			'cta' => 'Kontakt aufnehmen',
			'cards' => array(
				array( 'Bestellungen in Echtzeit', 'Vom Eingang bis zum Abschluss bleibt jeder Status sofort sichtbar.' ),
				array( 'Alarm dort, wo gearbeitet wird', 'Mobile App für Küche, Theke oder Rezeption mit Tonsignal bei neuen Bestellungen.' ),
				array( 'Die passende Ansicht je Rolle', 'Rollenbasierte Zugriffsrechte halten jedes Teammitglied bei den relevanten Aufgaben.' ),
				array( 'Ein operativer Ablauf', 'Abholung, Lieferung und Vorbestellung werden auf dieselbe Weise bearbeitet.' ),
			),
		),
		'fr' => array(
			'kicker' => 'UNE FILE · TOUTES LES COMMANDES',
			'title' => 'Gestion des commandes & Orders App',
			'lead' => 'Qu’elles proviennent du site web, de l’assistant IA ou d’une saisie manuelle, toutes les commandes arrivent dans la même file. C’est exactement ce que l’Orders App affiche à votre équipe.',
			'cta' => 'Nous contacter',
			'cards' => array(
				array( 'Commandes en temps réel', 'Chaque statut reste visible, de la réception à la finalisation.' ),
				array( 'Une alerte là où l’équipe travaille', 'Application mobile pour la cuisine, le comptoir ou la réception, avec signal sonore à chaque nouvelle commande.' ),
				array( 'La bonne vue pour chaque rôle', 'Les accès selon les rôles permettent à chacun de se concentrer sur les tâches utiles.' ),
				array( 'Un seul flux opérationnel', 'À emporter, livraison et précommande sont gérés de la même manière.' ),
			),
		),
	);
	$current = $copy[ $lang ] ?? $copy['de'];
	$text = static function ( $key, $default ) use ( $content, $lang, $is_editor ) {
		return awd_lm_render_stable_prototype_text( $content, $lang, $key, $default, $is_editor );
	};
	$icons = array(
		'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 13h4l3-9 4 16 2-7h3"/></svg>',
		'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="7" y="2" width="10" height="20" rx="2"/><path d="M11 18h2M18 8a4 4 0 0 1 0 6M20 5a8 8 0 0 1 0 12"/></svg>',
		'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7l8-4z"/><path d="M9 12l2 2 4-4"/></svg>',
		'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h11M12 4l3 3-3 3M20 17H9M12 14l-3 3 3 3"/></svg>',
	);
	$image_id = isset( $content['prototype_images']['mock99'] ) ? absint( $content['prototype_images']['mock99'] ) : 0;
	$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'full' ) : '';
	if ( ! $image_url ) {
		$image_url = AWD_LM_URL . 'landings/second-landing/assets/order-management-orders-app.png';
	}
	$html = '<section class="wtg-section wtg-section--cream wtg-orders-app" id="orders-app"><div class="wtg-container">';
	$html .= '<header class="wtg-orders-app-head" data-wtg-reveal><div class="wtg-kicker">' . $text( 't9760', $current['kicker'] ) . '</div>';
	$html .= '<h2 class="wtg-h2">' . $text( 't9761', $current['title'] ) . '</h2><p class="wtg-lead">' . $text( 't9762', $current['lead'] ) . '</p></header>';
	$html .= '<div class="wtg-orders-app-layout"><div class="wtg-orders-app-visual awd-lm-slot-image" data-awd-image-slot="mock99" data-awd-image-id="' . esc_attr( $image_id ) . '" data-wtg-reveal="delay1" style="background-image:url(' . esc_url( $image_url ) . ')"><span class="wtg-orders-app-flow" aria-hidden="true"><i></i><i></i><i></i></span><a class="wtg-orders-app-contact wtg-btn wtg-btn--dark" href="#demo">' . $text( 't9778', $current['cta'] ) . '<span aria-hidden="true">→</span></a></div>';
	$html .= '<div class="wtg-orders-app-grid">';
	foreach ( $current['cards'] as $index => $card ) {
		$base = 9770 + ( $index * 2 );
		$html .= '<article class="wtg-orders-app-card" data-wtg-reveal="delay' . absint( $index + 1 ) . '"><span class="wtg-orders-app-icon" aria-hidden="true">' . $icons[ $index ] . '</span><div><h3>' . $text( 't' . $base, $card[0] ) . '</h3><p>' . $text( 't' . ( $base + 1 ), $card[1] ) . '</p></div></article>';
	}
	return $html . '</div></div></div></section>';
}

/** CRM and newsletter story. Rendered as a section-styled div so historical
 * section indexes, anchors, translations and analytics settings stay stable. */
function awd_lm_render_second_landing_crm_newsletter_block( $content, $lang, $is_editor ) {
	$copy = array(
		'en' => array(
			'kicker' => 'CRM & NEWSLETTER', 'title' => 'Every guest leaves a trace.', 'accent' => 'Turn it into loyalty.',
			'lead' => 'Every order or booking leaves useful guest information behind. That is the start of knowing who keeps coming back — and what to offer next.',
			'left_label' => 'CRM', 'right_label' => 'NEWSLETTER',
			'cards' => array(
				array( 'Segment your guests', 'By what they order, how often they visit, or any pattern that matters to you.' ),
				array( 'Recognized by phone number', 'See familiar guests as soon as a new order is entered, together with their usual favorites.' ),
				array( 'Your most loyal guests', 'Visible at a glance instead of hidden in a spreadsheet.' ),
				array( 'Newsletter consent, collected once', 'Guests opt in once, at order or booking — no separate signup tool needed.' ),
				array( 'Build newsletters', 'Use menus and offers you already set up, with nothing retyped.' ),
				array( 'See what is working', 'Opens and clicks are tracked automatically on every send.' ),
			),
			'benefits' => array( 'Know your guests', 'Increase repeat visits', 'Sell more, automatically', 'Build real loyalty' ),
		),
		'de' => array(
			'kicker' => 'CRM & NEWSLETTER', 'title' => 'Jeder Gast hinterlässt eine Spur.', 'accent' => 'Verwandeln Sie sie in Loyalität.',
			'lead' => 'Jede Bestellung oder Reservation hinterlässt wertvolle Gästeinformationen. So erkennen Sie, wer wiederkommt — und welches Angebot als Nächstes passt.',
			'left_label' => 'CRM', 'right_label' => 'NEWSLETTER',
			'cards' => array(
				array( 'Gäste segmentieren', 'Nach dem, was sie bestellen, wie oft sie kommen, oder jedem anderen Muster, das für Sie wichtig ist.' ),
				array( 'An der Telefonnummer erkannt', 'Bekannte Gäste erscheinen bereits beim Erfassen einer neuen Bestellung samt ihren Favoriten.' ),
				array( 'Ihre treuesten Gäste', 'Auf einen Blick sichtbar statt in einer Tabelle versteckt.' ),
				array( 'Newsletter-Einwilligung, einmal eingeholt', 'Gäste stimmen einmal zu, bei Bestellung oder Buchung — kein separates Anmelde-Tool nötig.' ),
				array( 'Newsletter erstellen', 'Vorhandene Menüs und Angebote verwenden, ohne Inhalte erneut einzutippen.' ),
				array( 'Erkennen, was funktioniert', 'Öffnungen und Klicks werden bei jedem Versand automatisch erfasst.' ),
			),
			'benefits' => array( 'Gäste besser kennen', 'Mehr Wiederkehrer', 'Automatisch mehr verkaufen', 'Echte Loyalität aufbauen' ),
		),
		'fr' => array(
			'kicker' => 'CRM & NEWSLETTER', 'title' => 'Chaque invité laisse une trace.', 'accent' => 'Transformez-la en fidélité.',
			'lead' => 'Chaque commande ou réservation fournit des informations utiles. Vous découvrez ainsi qui revient — et quelle offre proposer ensuite.',
			'left_label' => 'CRM', 'right_label' => 'NEWSLETTER',
			'cards' => array(
				array( 'Segmentez vos clients', 'Selon ce qu\'ils commandent, la fréquence de leurs visites, ou tout autre critère qui compte pour vous.' ),
				array( 'Reconnu par son numéro', 'Les clients connus apparaissent dès la saisie d’une commande, avec leurs favoris.' ),
				array( 'Vos clients les plus fidèles', 'Visibles d’un coup d’œil plutôt que cachés dans un tableau.' ),
				array( 'Consentement newsletter, recueilli une fois', 'Le client donne son accord une fois, à la commande ou à la réservation — aucun outil d\'inscription séparé nécessaire.' ),
				array( 'Créez des newsletters', 'Réutilisez les menus et offres déjà configurés, sans nouvelle saisie.' ),
				array( 'Voir ce qui fonctionne', 'Ouvertures et clics sont suivis automatiquement à chaque envoi.' ),
			),
			'benefits' => array( 'Connaissez vos invités', 'Augmentez les visites répétées', 'Vendez plus, automatiquement', 'Construisez une vraie fidélité' ),
		),
	);
	$current = $copy[ $lang ] ?? $copy['de'];
	$text = static function ( $key, $default ) use ( $content, $lang, $is_editor ) { return awd_lm_render_stable_prototype_text( $content, $lang, $key, $default, $is_editor ); };
	$icons = array(
		'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M8 7a4 4 0 1 0 8 0 4 4 0 0 0-8 0M4 21v-2a5 5 0 0 1 5-5h6a5 5 0 0 1 5 5v2"/></svg>',
		'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="7" y="2" width="10" height="20" rx="2"/><path d="M11 18h2"/></svg>',
		'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-2.9-5.6 2.9 1.1-6.2L3 9.6l6.2-.9L12 3z"/></svg>',
		'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 5h16v14H4zM4 7l8 6 8-6"/></svg>',
		'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m4 20 4-1 11-11-3-3L5 16l-1 4zM14 7l3 3"/></svg>',
		'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19h16M6 16v-4M10 16V8M14 16v-6M18 16V5"/></svg>',
	);
	$image_id = isset( $content['prototype_images']['mock100'] ) ? absint( $content['prototype_images']['mock100'] ) : 0;
	$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'full' ) : '';
	$image_style = $image_url ? ' style="background-image:url(' . esc_url( $image_url ) . ')"' : '';
	$html = '<div class="wtg-section wtg-section--cream wtg-crm-newsletter" id="crm-newsletter"><div class="wtg-container">';
	$html .= '<header class="wtg-crm-head" data-wtg-reveal><div class="wtg-kicker">' . $text( 't9900', $current['kicker'] ) . '</div><h2 class="wtg-h2">' . $text( 't9901', $current['title'] ) . ' <span class="wtg-accent">' . $text( 't9902', $current['accent'] ) . '</span></h2><p class="wtg-lead">' . $text( 't9903', $current['lead'] ) . '</p></header>';
	$html .= '<div class="wtg-crm-layout"><div class="wtg-crm-side"><div class="wtg-crm-label">' . $icons[0] . $text( 't9904', $current['left_label'] ) . '</div>';
	foreach ( array_slice( $current['cards'], 0, 3 ) as $i => $card ) { $base = 9910 + $i * 2; $html .= '<article class="wtg-crm-card" data-wtg-reveal="delay' . $i . '"><span>' . $icons[$i] . '</span><div><h3>' . $text( 't' . $base, $card[0] ) . '</h3><p>' . $text( 't' . ($base + 1), $card[1] ) . '</p></div></article>'; }
	$html .= '</div><div class="wtg-crm-visual' . ( $image_url ? ' awd-lm-slot-image' : '' ) . '" data-awd-image-slot="mock100" data-awd-image-id="' . esc_attr( $image_id ) . '"' . $image_style . ' data-wtg-reveal="delay1"><div class="wtg-crm-placeholder" aria-hidden="true"><div class="wtg-crm-sidebar">AI WILL DO<br><i></i><i></i><i></i><i></i><i></i></div><div class="wtg-crm-dashboard"><b>Guests</b><div class="wtg-crm-stats"><i></i><i></i><i></i></div><div class="wtg-crm-table"></div></div><div class="wtg-crm-phone"><b>NEWSLETTER</b><i></i><span></span><span></span></div></div></div>';
	$html .= '<div class="wtg-crm-side"><div class="wtg-crm-label">' . $icons[3] . $text( 't9905', $current['right_label'] ) . '</div>';
	foreach ( array_slice( $current['cards'], 3, 3 ) as $i => $card ) { $base = 9920 + $i * 2; $html .= '<article class="wtg-crm-card" data-wtg-reveal="delay' . $i . '"><span>' . $icons[$i + 3] . '</span><div><h3>' . $text( 't' . $base, $card[0] ) . '</h3><p>' . $text( 't' . ($base + 1), $card[1] ) . '</p></div></article>'; }
	$html .= '</div></div><div class="wtg-crm-benefits">';
	foreach ( $current['benefits'] as $i => $benefit ) { $html .= '<div>' . $icons[$i] . '<span>' . $text( 't' . (9940 + $i), $benefit ) . '</span></div>'; }
	return $html . '</div></div></div>';
}

/** Two clear website delivery paths, kept outside historical indexed copy. */
function awd_lm_render_second_landing_webdesign_options( $content, $lang, $is_editor ) {
	$copy = array(
		'en' => array(
			array( 'A website builder ready in days', 'Flexible blocks, a dynamic menu, automatic translation into 4 languages, basic SEO. Enough to launch without a developer.' ),
			array( 'A fully custom site — and you still own your data', 'Want something unique? Get a unique design while keeping full control of your own CMS, instead of handing everything to an agency that locks you out of your own updates. Change the menu once, and it retranslates and reflows across every language version automatically. Same engine. Your unique identity.' ),
		),
		'de' => array(
			array( 'Ein Website-Builder, der in wenigen Tagen startklar ist', 'Flexible Bausteine, ein dynamisches Menü, automatische Übersetzung in vier Sprachen und grundlegende SEO. Genug für den Start ganz ohne Entwickler.' ),
			array( 'Eine vollständig individuelle Website — und Ihre Daten bleiben Ihre', 'Sie wünschen etwas Einzigartiges? Sie erhalten ein individuelles Design und behalten trotzdem die volle Kontrolle über Ihr eigenes CMS, statt Aktualisierungen von einer Agentur abhängig zu machen. Ändern Sie das Menü einmal, und es wird automatisch in jeder Sprachversion neu übersetzt und passend angeordnet. Dieselbe technische Basis. Ihre einzigartige Identität.' ),
		),
		'fr' => array(
			array( 'Un constructeur de site prêt en quelques jours', 'Des blocs flexibles, un menu dynamique, la traduction automatique en quatre langues et un SEO de base. Tout ce qu’il faut pour lancer le site sans développeur.' ),
			array( 'Un site entièrement sur mesure — et vos données restent à vous', 'Vous voulez une présence unique ? Profitez d’un design personnalisé tout en gardant le contrôle total de votre propre CMS, sans dépendre d’une agence qui vous prive de vos mises à jour. Modifiez le menu une seule fois : il est automatiquement retraduit et réorganisé dans chaque version linguistique. Le même moteur. Votre identité unique.' ),
		),
	);
	$current = $copy[ $lang ] ?? $copy['de'];
	$text = static function ( $key, $default ) use ( $content, $lang, $is_editor ) { return awd_lm_render_stable_prototype_text( $content, $lang, $key, $default, $is_editor ); };
	$html = '<div class="wtg-webdesign-options">';
	foreach ( $current as $index => $card ) {
		$base = 9950 + ( $index * 2 );
		$html .= '<article class="wtg-webdesign-option wtg-webdesign-option--' . ( 0 === $index ? 'yellow' : 'purple' ) . '" data-wtg-reveal="delay' . absint( $index + 1 ) . '"><span class="wtg-webdesign-option-num" aria-hidden="true">0' . absint( $index + 1 ) . '</span><h3>' . $text( 't' . $base, $card[0] ) . '</h3><p>' . $text( 't' . ( $base + 1 ), $card[1] ) . '</p></article>';
	}
	return $html . '</div>';
}

/** Fifth building block for the Restaurants Ordering landing. Kept outside the
 * historical prototype indexes so existing saved copy retains its mapping. */
function awd_lm_render_second_landing_crm_product_card( $content, $lang, $is_editor ) {
	$copy = array(
		'en' => array( 'CRM & Newsletter', 'Turn guest data into loyalty.', 'Recognize returning guests, build useful segments and send relevant offers without maintaining separate lists.', 'Discover AI Assistant →' ),
		'de' => array( 'CRM & Newsletter', 'Aus Gästedaten wird Loyalität.', 'Wiederkehrende Gäste erkennen, sinnvolle Segmente bilden und passende Angebote versenden — ganz ohne separate Listenpflege.', 'KI-Assistent entdecken →' ),
		'fr' => array( 'CRM & Newsletter', 'Transformez les données clients en fidélité.', 'Identifiez les clients réguliers, créez des segments utiles et envoyez des offres pertinentes sans gérer de listes séparées.', 'Découvrir l’assistant IA →' ),
	);
	$current = $copy[ $lang ] ?? $copy['de'];
	$text = static function ( $key, $default ) use ( $content, $lang, $is_editor ) { return awd_lm_render_stable_prototype_text( $content, $lang, $key, $default, $is_editor ); };
	return '<article class="wtg-card wtg-product-card wtg-product-card--crm" data-wtg-reveal="delay4"><div class="wtg-product-visual crm"></div><div class="wtg-card-num">' . $text( 't9960', $current[0] ) . '</div><h3>' . $text( 't9961', $current[1] ) . '</h3><p>' . $text( 't9962', $current[2] ) . '</p><a class="wtg-card-link" href="#crm-newsletter">' . $text( 't9963', $current[3] ) . '</a></article>';
}

/** The FAQ is deliberately kept outside the indexed prototype copy so its new
 * items do not remap existing editable-text keys elsewhere on client sites. */
function awd_lm_gloriafood_faq_items( $lang ) {
	$items = array(
		'en' => array(
			array( 'Can you migrate my existing GloriaFood menu and settings?', 'Yes. We can help transfer your existing menu structure, products, prices, categories and other relevant content. Before the migration, we review your current setup and clarify what should be transferred, changed or simplified.' ),
			array( 'Will there be downtime when switching from GloriaFood?', 'Normally, the new system can be prepared and tested before the switch. This allows you to keep your existing solution running until the new setup is ready.' ),
			array( 'Can AI Will Do be connected to other systems we already use?', 'Yes, depending on the system and the required workflow. We review your existing setup and clarify which connections make sense and how they can be implemented.' ),
			array( 'How does my team receive and manage new orders?', 'New orders are passed directly to the operational order workflow, where your team can see and process them. A dedicated Orders App can also be used so that incoming orders are immediately visible to the responsible staff.' ),
			array( 'Can customers pay online when placing an order?', 'Yes. Online payment can be integrated into the ordering process, depending on the payment setup you want to use. We clarify the required payment flow and available options during implementation.' ),
			array( 'Can I control when individual dishes are available?', 'Yes. Availability can be managed separately for products and offers. This is useful for daily specials, seasonal dishes or items that are only available at certain times.' ),
			array( 'Can I define how far in advance guests can order or reserve?', 'Yes. Ordering and reservation rules can be adapted to your operation. This includes controlling when orders or reservations can be made and which time slots are available.' ),
			array( 'Can the reservation system take actual table capacity into account?', 'Yes. Reservations can be managed based on available capacity rather than simply accepting every request for a particular time. This helps avoid offering time slots that cannot realistically be accommodated.' ),
			array( 'Can different employees have different access rights?', 'Yes. Roles and permissions can be configured so that team members only see and manage the parts of the system relevant to their work.' ),
		),
		'de' => array(
			array( 'Können Sie mein bestehendes GloriaFood-Menü und meine Einstellungen migrieren?', 'Ja. Wir helfen bei der Übernahme Ihrer bestehenden Menüstruktur, Produkte, Preise, Kategorien und weiterer relevanter Inhalte. Vor der Migration prüfen wir Ihre aktuelle Einrichtung und klären, was übernommen, angepasst oder vereinfacht werden soll.' ),
			array( 'Gibt es beim Wechsel von GloriaFood Ausfallzeiten?', 'Normalerweise kann das neue System vor der Umstellung vorbereitet und getestet werden. So bleibt Ihre bisherige Lösung in Betrieb, bis die neue Einrichtung bereit ist.' ),
			array( 'Kann AI Will Do mit anderen Systemen verbunden werden, die wir bereits nutzen?', 'Ja, abhängig vom System und den benötigten Abläufen. Wir prüfen Ihre bestehende Einrichtung und klären, welche Verbindungen sinnvoll sind und wie sie umgesetzt werden können.' ),
			array( 'Wie erhält und verwaltet mein Team neue Bestellungen?', 'Neue Bestellungen werden direkt an den operativen Bestellablauf übergeben, wo Ihr Team sie einsehen und bearbeiten kann. Zusätzlich kann eine eigene Orders App eingesetzt werden, damit eingehende Bestellungen sofort für die zuständigen Mitarbeitenden sichtbar sind.' ),
			array( 'Können Kundinnen und Kunden bei einer Bestellung online bezahlen?', 'Ja. Eine Online-Zahlung kann je nach gewünschter Zahlungseinrichtung in den Bestellprozess integriert werden. Den benötigten Zahlungsablauf und die verfügbaren Optionen klären wir bei der Umsetzung.' ),
			array( 'Kann ich steuern, wann einzelne Gerichte verfügbar sind?', 'Ja. Die Verfügbarkeit lässt sich für Produkte und Angebote separat verwalten. Das ist praktisch für Tagesangebote, saisonale Gerichte oder Artikel, die nur zu bestimmten Zeiten verfügbar sind.' ),
			array( 'Kann ich festlegen, wie weit im Voraus Gäste bestellen oder reservieren können?', 'Ja. Bestell- und Reservationsregeln können an Ihren Betrieb angepasst werden. Dazu gehört, wann Bestellungen oder Reservationen möglich sind und welche Zeitfenster verfügbar sind.' ),
			array( 'Kann das Reservationssystem die tatsächliche Tischkapazität berücksichtigen?', 'Ja. Reservationen können anhand der verfügbaren Kapazität verwaltet werden, statt jede Anfrage für eine bestimmte Zeit einfach anzunehmen. So werden keine Zeitfenster angeboten, die realistisch nicht möglich sind.' ),
			array( 'Können verschiedene Mitarbeitende unterschiedliche Zugriffsrechte erhalten?', 'Ja. Rollen und Berechtigungen lassen sich so einrichten, dass Teammitglieder nur die Bereiche sehen und verwalten, die für ihre Arbeit relevant sind.' ),
		),
		'fr' => array(
			array( 'Pouvez-vous migrer mon menu et mes paramètres GloriaFood existants ?', 'Oui. Nous pouvons vous aider à transférer votre structure de menu, vos produits, vos prix, vos catégories et les autres contenus pertinents. Avant la migration, nous examinons votre configuration actuelle et clarifions ce qui doit être transféré, modifié ou simplifié.' ),
			array( 'Y aura-t-il une interruption de service lors du passage depuis GloriaFood ?', 'En règle générale, le nouveau système peut être préparé et testé avant le changement. Votre solution actuelle reste ainsi en service jusqu’à ce que la nouvelle configuration soit prête.' ),
			array( 'AI Will Do peut-il être relié à d’autres systèmes que nous utilisons déjà ?', 'Oui, selon le système et le flux de travail requis. Nous examinons votre configuration actuelle et déterminons quelles connexions sont utiles et comment les mettre en place.' ),
			array( 'Comment mon équipe reçoit-elle et gère-t-elle les nouvelles commandes ?', 'Les nouvelles commandes sont transmises directement au flux opérationnel, où votre équipe peut les voir et les traiter. Une application dédiée aux commandes peut aussi être utilisée afin que le personnel responsable les voie immédiatement.' ),
			array( 'Les clients peuvent-ils payer en ligne lorsqu’ils passent une commande ?', 'Oui. Le paiement en ligne peut être intégré au processus de commande, selon la configuration de paiement souhaitée. Nous clarifions le flux de paiement requis et les options disponibles lors de la mise en œuvre.' ),
			array( 'Puis-je contrôler la disponibilité de certains plats ?', 'Oui. La disponibilité peut être gérée séparément pour les produits et les offres. C’est utile pour les suggestions du jour, les plats de saison ou les articles disponibles seulement à certains moments.' ),
			array( 'Puis-je définir jusqu’à quand les clients peuvent commander ou réserver à l’avance ?', 'Oui. Les règles de commande et de réservation peuvent être adaptées à votre activité. Cela inclut le moment où les commandes ou réservations sont possibles et les créneaux disponibles.' ),
			array( 'Le système de réservation peut-il tenir compte de la capacité réelle des tables ?', 'Oui. Les réservations peuvent être gérées selon la capacité disponible plutôt que d’accepter chaque demande pour une heure donnée. Cela évite de proposer des créneaux qui ne peuvent pas être réellement honorés.' ),
			array( 'Différents employés peuvent-ils avoir des droits d’accès différents ?', 'Oui. Les rôles et les autorisations peuvent être configurés afin que chaque membre de l’équipe ne voie et ne gère que les parties pertinentes pour son travail.' ),
		),
	);
	return $items[ $lang ] ?? $items['de'];
}

function awd_lm_render_prototype_body( $content, $lang, $landing_key = 'gloriafood' ) {
	$landing_key = sanitize_key( $landing_key );
	$html = file_get_contents( AWD_LM_DIR . 'templates/prototype-v7-body.php' );
	if ( false === $html ) { return ''; }
	/*
	 * Keep the prototype's old behaviour script out of the editable-text pass.
	 * The current script is loaded once by landing-gloriafood.php. Appending this
	 * legacy copy as well registered two click handlers, which opened and closed
	 * the mobile menu in the same click.
	 */
	$script_position = stripos( $html, '<script' );
	$script = false === $script_position ? '' : substr( $html, $script_position );
	$html = false === $script_position ? $html : substr( $html, 0, $script_position );
	$is_editor = ! empty( $GLOBALS['awd_lm_visual_editor'] );
	$seed_translations = awd_lm_prototype_seed_translations();
	$default_brand_name = 'second-landing' === $landing_key ? 'AI Will Do' : 'website-to-go';
	$brand_name = isset( $content['brand_name'] ) ? sanitize_text_field( $content['brand_name'] ) : $default_brand_name;
	if ( '' === $brand_name ) { $brand_name = $default_brand_name; }
	$brand_aliases = array_filter( array_unique( array_merge( array( 'website-to-go', 'website to go', 'Take Order' ), (array) ( $content['brand_history'] ?? array() ) ) ) );
	usort( $brand_aliases, function ( $a, $b ) { return strlen( $b ) <=> strlen( $a ); } );
	if ( 'second-landing' === $landing_key ) {
		$html = str_replace( 'aria-label="website-to-go Start"', 'aria-label="AI Will Do Start"', $html );
	}
	/* Protect the footer logo before the editable-text pass changes its wordmark.
	 * It is later replaced with the exact same uploaded logo that the header uses. */
	$footer_logo_token = '<!-- AWD_LM_FOOTER_LOGO -->';
	$html = str_replace( '<a class="wtg-logo" href="#start"><span class="wtg-logo-mark"></span><span>website-to-go</span></a>', $footer_logo_token, $html );
	/* Footer legal links are structural, not editable landing copy. Protecting them
	 * also repairs drafts whose old indexed text values drifted into this area. */
	$footer_links_token = '<!-- AWD_LM_FOOTER_LINKS -->';
	$html = preg_replace( '/<div class="wtg-footer-links">.*?<\/div>/su', $footer_links_token, $html, 1 );
	$index = 0;
	$html = preg_replace_callback( '/>([^<>]+)</u', function ( $match ) use ( &$index, $content, $lang, $is_editor, $seed_translations, $brand_name, $brand_aliases ) {
		$text = $match[1];
		$trimmed = trim( html_entity_decode( $text, ENT_QUOTES, 'UTF-8' ) );
		if ( '' === $trimmed || ! preg_match( '/[\p{L}\p{N}]/u', $trimmed ) ) { return $match[0]; }
		$index++;
		$key = awd_lm_prototype_text_key( $index );
		$value = isset( $content['prototype_text'][ $lang ][ $key ] ) ? $content['prototype_text'][ $lang ][ $key ] : ( $seed_translations[ $lang ][ $key ] ?? $trimmed );
		$prefix = substr( $text, 0, strlen( $text ) - strlen( ltrim( $text ) ) );
		$suffix = substr( $text, strlen( rtrim( $text ) ) );
		/* Render all previously used brand labels from one global variable. */
		$value = str_ireplace( $brand_aliases, $brand_name, (string) $value );
		$rendered = esc_html( $value );
		if ( $is_editor ) {
			$rendered = '<span class="awd-lm-inline" contenteditable="true" role="textbox" data-awd-text-key="' . esc_attr( $key ) . '">' . $rendered . '</span>';
		}
		return '>' . $prefix . $rendered . $suffix . '<';
	}, $html );
	/* Permanent-key copy is replaced only after the indexed pass. The original
	 * child text nodes still consume their historical indexes, so every later
	 * saved tN field remains mapped to exactly the same element. */
	$html = preg_replace_callback(
		'/<([a-z][a-z0-9]*)\b([^>]*\sdata-awd-stable-text-key="(t[1-9][0-9]*)"[^>]*)>(.*?)<\/\1>/su',
		function ( $match ) use ( $content, $lang, $is_editor, $seed_translations ) {
			$attributes = preg_replace( '/\sdata-awd-stable-text-key="[^"]*"/u', '', $match[2] );
			$default = $seed_translations[ $lang ][ $match[3] ] ?? trim( wp_strip_all_tags( $match[4] ) );
			$rendered = awd_lm_render_stable_prototype_text( $content, $lang, $match[3], $default, $is_editor );
			return '<' . $match[1] . $attributes . '>' . $rendered . '</' . $match[1] . '>';
		},
		$html
	);
	$faq_intro = array(
		'de' => 'In unseren FAQ finden Sie kompakte Antworten auf die wichtigsten Fragen zu unserer Zusammenarbeit und zum Ablauf. Wenn Ihr Anliegen darüber hinausgeht oder Sie etwas persönlich klären möchten, sind wir gerne direkt für Sie da.',
		'en' => 'In our FAQ, you will find concise answers to the most important questions about working with us and the process. If your concern goes beyond this or you would like to clarify something personally, we are happy to help you directly.',
		'fr' => 'Dans notre FAQ, vous trouverez des réponses concises aux principales questions concernant notre collaboration et son déroulement. Si votre demande va au-delà ou si vous souhaitez clarifier un point personnellement, nous sommes volontiers à votre disposition.',
	);
	$faq_intro_value = $faq_intro[ $lang ] ?? $faq_intro['de'];
	$html = str_replace( '<!-- AWD_LM_FAQ_INTRO -->', '<p class="wtg-copy wtg-faq-intro">' . awd_lm_render_stable_prototype_text( $content, $lang, 't9010', $faq_intro_value, $is_editor ) . '</p>', $html );
	/* Remove the stray allergy disclaimer in every language after the text-key pass.
	 * Doing this here keeps all later editable-text keys stable for existing drafts. */
	$html = preg_replace( '/\s*<p class="wtg-small" style="margin-top:23px">.*?<\\/p>/su', '', $html, 1 );
	/* Older drafts could assign a long footer slogan to the first footer link.
	 * This landing has no footer tagline, so remove that accidental link. */
	$html = preg_replace(
		'/(<div class="wtg-footer-links">)\s*<a[^>]*>[^<]{70,}<\\/a>\s*/su',
		'$1',
		$html,
		1
	);
	$faq_html = '<div class="wtg-faq">';
	foreach ( awd_lm_gloriafood_faq_items( $lang ) as $faq_index => $faq_item ) {
		if ( 'second-landing' === $landing_key && 1 === $faq_index ) { continue; }
		$question_key = 't' . ( 9020 + ( $faq_index * 2 ) );
		$answer_key = 't' . ( 9021 + ( $faq_index * 2 ) );
		$question = awd_lm_render_stable_prototype_text( $content, $lang, $question_key, $faq_item[0], $is_editor );
		$answer = awd_lm_render_stable_prototype_text( $content, $lang, $answer_key, $faq_item[1], $is_editor );
		$question_tag = $is_editor ? 'div' : 'button';
		$question_attributes = $is_editor ? ' role="group"' : ' type="button"';
		$faq_html .= '<div class="wtg-faq-item' . ( 0 === $faq_index ? ' is-open' : '' ) . '"><' . $question_tag . ' class="wtg-faq-q"' . $question_attributes . '>' . $question . '<span class="wtg-faq-plus">+</span></' . $question_tag . '><div class="wtg-faq-a"><p>' . $answer . '</p></div></div>';
	}
	$faq_html .= '</div></div></section>';
	$html = preg_replace( '/<div class="wtg-faq">.*?<\\/div>\s*<\\/div>\s*<\\/section>/su', $faq_html, $html, 1 );
	$link_index = 0;
	$html = preg_replace_callback( '/href="([^"]*)"/u', function ( $match ) use ( &$link_index, $content, $lang, $is_editor ) { $link_index++; $key = 'l' . $link_index; $value = isset( $content['prototype_links'][ $lang ][ $key ] ) ? $content['prototype_links'][ $lang ][ $key ] : $match[1]; return 'href="' . esc_url( $value ) . '"' . ( $is_editor ? ' data-awd-link-key="' . esc_attr( $key ) . '"' : '' ); }, $html );
	if ( 'second-landing' === $landing_key ) {
		$second_hero = awd_lm_render_second_landing_hero_slides( $content, $lang, $is_editor );
		$html = preg_replace(
			'/<div class="wtg-hero-copy">.*?<\/div>\s*(?=<!-- One permanent WordPress image slot)/su',
			$second_hero . "\n\n        ",
			$html,
			1
		);
		$html = preg_replace(
			'/<section class="wtg-section wtg-section--cream" id="problem">.*?<\/section>/su',
			awd_lm_render_second_landing_problem_section( $content, $lang, $is_editor ),
			$html,
			1
		);
		$html = preg_replace(
			'/(<section class="wtg-section wtg-section--cream" id="take-away">.*?<div class="wtg-detail)">\s*<div class="wtg-detail-copy".*?<div class="wtg-detail-visual"/su',
			'$1 wtg-ordering-detail">' . awd_lm_render_second_landing_ordering_copy( $content, $lang, $is_editor ) . '<div class="wtg-detail-visual"',
			$html,
			1
		);
		$html = preg_replace(
			'/(<section class="wtg-section wtg-section--cream" id="take-away">.*?<\/section>)/su',
			'$1' . awd_lm_render_second_landing_orders_app_section( $content, $lang, $is_editor ),
			$html,
			1
		);
		$html = preg_replace(
			'/(<section class="wtg-section" id="reservation">.*?<div class="wtg-container wtg-detail">)\s*<div class="wtg-detail-copy".*?<div class="wtg-detail-visual"/su',
			'$1' . awd_lm_render_second_landing_reservation_copy( $content, $lang, $is_editor ) . '<div class="wtg-detail-visual"',
			$html,
			1
		);
		$html = preg_replace(
			'/<section class="wtg-section wtg-section--cream" id="platform">.*?<\/section>/su',
			awd_lm_render_second_landing_workflow_section( $content, $lang, $is_editor ),
			$html,
			1
		);
		$html = preg_replace(
			'/<section class="wtg-section wtg-section--cream" id="menu-marketing">.*?<\/section>/su',
			awd_lm_render_second_landing_menu_performance_section( $content, $lang, $is_editor ) . awd_lm_render_second_landing_crm_newsletter_block( $content, $lang, $is_editor ),
			$html,
			1
		);
		$html = preg_replace(
			'/(<section class="wtg-section wtg-section--cream wtg-webdesign-story" id="webdesign">.*?<p class="wtg-lead"[^>]*>.*?<\/p>)/su',
			'$1' . awd_lm_render_second_landing_webdesign_options( $content, $lang, $is_editor ),
			$html,
			1
		);
		$solution_titles = array(
			'en' => 'One system. Five strong building blocks.',
			'de' => 'Ein System. Fünf starke Bausteine.',
			'fr' => 'Un système. Cinq modules puissants.',
		);
		$solution_title = awd_lm_render_stable_prototype_text( $content, $lang, 't9964', $solution_titles[ $lang ] ?? $solution_titles['de'], $is_editor );
		$html = preg_replace(
			'/(<section\b[^>]*\bid="loesungen"[^>]*>.*?<h2\b[^>]*>).*?(<\/h2>)/su',
			'$1' . $solution_title . '$2',
			$html,
			1
		);
		$html = preg_replace(
			'/(<div class="wtg-grid wtg-grid--2 wtg-products">.*<\/article>)(\s*<\/div>)/suU',
			'$1' . awd_lm_render_second_landing_crm_product_card( $content, $lang, $is_editor ) . '$2',
			$html,
			1
		);
		$product_link_targets = array( '#take-away', '#take-away', '#reservation', '#webdesign', '#ki' );
		$product_link_index = 0;
		$html = preg_replace_callback(
			'/<article class="wtg-card wtg-product-card[^"]*"[^>]*>.*?<\/article>/su',
			function ( $match ) use ( &$product_link_index, $product_link_targets ) {
				if ( ! isset( $product_link_targets[ $product_link_index ] ) ) { return $match[0]; }
				$target = $product_link_targets[ $product_link_index ];
				$product_link_index++;
				return preg_replace( '/(<a class="wtg-card-link" href=")[^"]+("[^>]*>)/u', '$1' . $target . '$2', $match[0], 1 );
			},
			$html
		);
		$html = preg_replace(
			'/(<section\b[^>]*\bid="loesungen"[^>]*>.*?(?:<article\b.*?<\/article>\s*){2}<article\b.*?<a class="wtg-card-link" href=")[^"]+("[^>]*>)/su',
			'$1#reservation$2',
			$html,
			1
		);
	}
	// The contact phone is a structural action, not an editable CTA. Keep its
	// telephone target stable even when older saved link indexes contain #start.
	$html = preg_replace(
		'/(<a\b[^>]*\bclass="[^"]*\bwtg-contact-phone\b[^"]*"[^>]*?)\shref="[^"]*"/u',
		'$1 href="tel:+41416301487"',
		$html
	);
	/* Replace after both generic passes: the legacy phone has already consumed
	 * its historical text and link indexes, so downstream fields stay stable. */
	$html = preg_replace(
		'/<div style="margin-top:33px;font-weight:850"><a class="wtg-contact-phone"[^>]*>.*?<\/a><\/div>/su',
		awd_lm_render_contact_phones( $content, $lang, $is_editor ),
		$html,
		1
	);
	$logo_image_id = isset( $content['prototype_images']['logo'] ) ? absint( $content['prototype_images']['logo'] ) : 0;
	$logo_url = '';
	$logo_attributes = ' class="wtg-logo-image" data-awd-image-slot="logo" data-awd-image-id="' . esc_attr( $logo_image_id ) . '" aria-label="' . esc_attr( $brand_name ) . ' logo"';
	$logo_inner = '';
	if ( $logo_image_id ) {
		/* A header wordmark is rendered at its native 360 × 80 px. Using WordPress'
		 * medium derivative (300 px wide) made the otherwise correct logo look soft. */
		$logo_url = wp_get_attachment_image_url( $logo_image_id, 'full' );
		if ( $logo_url ) { $logo_attributes = ' class="wtg-logo-image awd-lm-slot-image" data-awd-image-slot="logo" data-awd-image-id="' . esc_attr( $logo_image_id ) . '" aria-label="' . esc_attr( $brand_name ) . ' logo"'; $logo_inner = '<img class="wtg-logo-rendered" src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( $brand_name ) . ' logo">'; }
	}
	$html = str_replace( '<span class="wtg-logo-mark" aria-hidden="true"></span>', '<span' . $logo_attributes . '>' . $logo_inner . '</span>', $html );
	/* The footer uses the exact header wordmark container, but is intentionally
	 * not a second editable slot. This keeps its rendered logo identical. */
	$footer_logo = '<span class="wtg-logo-image wtg-footer-logo-image awd-lm-slot-image" aria-label="' . esc_attr( $brand_name ) . ' logo">' . ( $logo_url ? '<img class="wtg-logo-rendered" src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( $brand_name ) . ' logo">' : '' ) . '</span>';
	$html = str_replace( $footer_logo_token, '<a class="wtg-logo wtg-footer-logo" href="#start" aria-label="' . esc_attr( $brand_name ) . ' start">' . $footer_logo . '</a>', $html );
	$footer_labels = array(
		'de' => array( 'Datenschutz', 'Cookie-Richtlinie', 'AGB' ),
		'en' => array( 'Privacy policy', 'Cookie policy', 'Terms' ),
		'fr' => array( 'Confidentialité', 'Politique de cookies', 'Conditions' ),
	);
	$footer_label = $footer_labels[ $lang ] ?? $footer_labels['de'];
	$footer_defaults = array(
		'https://website-to-go.ch/datenschutz/',
		awd_lm_cookie_policy_url( $lang, $landing_key ),
		'https://website-to-go.ch/agb/',
	);
	$footer_links = '<div class="wtg-footer-links">';
	foreach ( $footer_defaults as $footer_index => $footer_default ) {
		$footer_key = 'l' . ( 901 + $footer_index );
		/* Cookie policy is generated and owned by the CMP registry. Unlike the
		 * privacy/terms links, an old saved draft must not point around it. */
		$footer_url = 1 === $footer_index ? awd_lm_cookie_policy_url( $lang, $landing_key ) : ( $content['prototype_links'][ $lang ][ $footer_key ] ?? $footer_default );
		$editable = $is_editor && 1 !== $footer_index;
		$footer_links .= '<a href="' . esc_url( $footer_url ) . '"' . ( $editable ? ' data-awd-link-key="' . esc_attr( $footer_key ) . '"' : '' ) . '>' . esc_html( $footer_label[ $footer_index ] ) . '</a>';
	}
	$footer_links .= '</div>';
	$cookie_settings_labels = array( 'de'=>'Cookie-Einstellungen', 'en'=>'Cookie settings', 'fr'=>'Paramètres des cookies' );
	$footer_links = str_replace( '</div>', '<a href="#" data-awd-cookie-settings>' . esc_html( $cookie_settings_labels[ $lang ] ?? $cookie_settings_labels['de'] ) . '</a></div>', $footer_links );
	$powered_defaults = array(
		'l906' => array( 'label' => 'AI Will Do', 'url' => 'https://aiwilldo.lu/' ),
	);
	$powered_links = array();
	foreach ( $powered_defaults as $powered_key => $powered_default ) {
		$powered_url = $content['prototype_links'][ $lang ][ $powered_key ] ?? $powered_default['url'];
		$powered_links[ $powered_key ] = '<a href="' . esc_url( $powered_url ) . '" target="_blank" rel="noopener"' . ( $is_editor ? ' data-awd-link-key="' . esc_attr( $powered_key ) . '"' : '' ) . '>' . esc_html( $powered_default['label'] ) . '</a>';
	}
	$footer_meta = '<div class="wtg-footer-meta"><div class="wtg-footer-powered">Powered by ' . $powered_links['l906'] . '</div>' . $footer_links . '</div>';
	if ( 'second-landing' === $landing_key ) {
		$contact_defaults = array(
			't9970' => '+352 661 554 736',
			't9971' => 'contact@viktal.eu',
			't9972' => '1 Rue Bender, Luxembourg',
		);
		$contact_values = array();
		foreach ( $contact_defaults as $contact_key => $contact_default ) {
			$contact_values[ $contact_key ] = isset( $content['prototype_text'][ $lang ][ $contact_key ] ) ? sanitize_text_field( $content['prototype_text'][ $lang ][ $contact_key ] ) : $contact_default;
		}
		$phone_href = preg_replace( '/[^0-9+]/', '', $contact_values['t9970'] );
		$email_href = sanitize_email( $contact_values['t9971'] );
		$footer_contact = '<div class="wtg-footer-contact" aria-label="Contact">';
		$footer_contact .= '<a href="tel:' . esc_attr( $phone_href ) . '">' . awd_lm_render_stable_prototype_text( $content, $lang, 't9970', $contact_defaults['t9970'], $is_editor ) . '</a>';
		$footer_contact .= '<a href="mailto:' . esc_attr( $email_href ) . '">' . awd_lm_render_stable_prototype_text( $content, $lang, 't9971', $contact_defaults['t9971'], $is_editor ) . '</a>';
		$footer_contact .= '<div>' . awd_lm_render_stable_prototype_text( $content, $lang, 't9972', $contact_defaults['t9972'], $is_editor ) . '</div></div>';
		$social_defaults = array(
			'l908' => array( 'label' => 'LinkedIn', 'url' => 'https://www.linkedin.com/' ),
			'l907' => array( 'label' => 'Facebook', 'url' => 'https://www.facebook.com/' ),
			'l909' => array( 'label' => 'Instagram', 'url' => 'https://www.instagram.com/' ),
		);
		$social_icons = array(
			'l908' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.45 20.45h-3.56v-5.57c0-1.33-.03-3.04-1.85-3.04-1.85 0-2.14 1.45-2.14 2.94v5.67H9.34V8.98h3.42v1.57h.05c.48-.9 1.64-1.85 3.37-1.85 3.61 0 4.27 2.37 4.27 5.46v6.29zM5.32 7.41a2.07 2.07 0 1 1 0-4.13 2.07 2.07 0 0 1 0 4.13zM7.1 20.45H3.54V8.98H7.1v11.47z"/></svg>',
			'l907' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M13.5 21v-8h2.75l.41-3.2H13.5V7.76c0-.93.26-1.56 1.59-1.56h1.69V3.34a22.8 22.8 0 0 0-2.47-.13c-2.44 0-4.11 1.49-4.11 4.23V9.8H7.44V13h2.76v8h3.3z"/></svg>',
			'l909' => '<svg viewBox="0 0 24 24" aria-hidden="true"><defs><linearGradient id="awd-instagram-gradient" x1="2" y1="22" x2="22" y2="2" gradientUnits="userSpaceOnUse"><stop stop-color="#feda75"/><stop offset=".28" stop-color="#fa7e1e"/><stop offset=".55" stop-color="#d62976"/><stop offset=".78" stop-color="#962fbf"/><stop offset="1" stop-color="#4f5bd5"/></linearGradient></defs><path fill="url(#awd-instagram-gradient)" d="M7.2 2h9.6A5.2 5.2 0 0 1 22 7.2v9.6a5.2 5.2 0 0 1-5.2 5.2H7.2A5.2 5.2 0 0 1 2 16.8V7.2A5.2 5.2 0 0 1 7.2 2zm-.18 2A3.02 3.02 0 0 0 4 7.02v9.96A3.02 3.02 0 0 0 7.02 20h9.96A3.02 3.02 0 0 0 20 16.98V7.02A3.02 3.02 0 0 0 16.98 4H7.02zM17.5 5.5a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5zM12 7.1a4.9 4.9 0 1 1 0 9.8 4.9 4.9 0 0 1 0-9.8zm0 2a2.9 2.9 0 1 0 0 5.8 2.9 2.9 0 0 0 0-5.8z"/></svg>',
		);
		$footer_social = '<div class="wtg-footer-social" aria-label="Social media">';
		foreach ( $social_defaults as $social_key => $social_default ) {
			$social_url = $content['prototype_links'][ $lang ][ $social_key ] ?? $social_default['url'];
			$footer_social .= '<a class="wtg-social--' . esc_attr( strtolower( $social_default['label'] ) ) . '" href="' . esc_url( $social_url ) . '" target="_blank" rel="noopener" aria-label="' . esc_attr( $social_default['label'] ) . '"' . ( $is_editor ? ' data-awd-link-key="' . esc_attr( $social_key ) . '"' : '' ) . '>' . $social_icons[ $social_key ] . '</a>';
		}
		$footer_social .= '</div>';
		$footer_meta = '<div class="wtg-footer-contact-stack">' . $footer_contact . $footer_social . '</div>' . $footer_meta;
	}
	$html = str_replace( $footer_links_token, $footer_meta, $html );
	$image_id = isset( $content['prototype_images']['hero'] ) ? absint( $content['prototype_images']['hero'] ) : 0;
	$hero_attributes = ' class="wtg-stage" data-awd-image-slot="hero" data-awd-image-id="' . esc_attr( $image_id ) . '"';
	if ( $image_id ) {
		$url = wp_get_attachment_image_url( $image_id, 'full' );
		if ( $url ) { $hero_attributes = ' class="wtg-stage awd-lm-hero-image" data-awd-image-slot="hero" data-awd-image-id="' . esc_attr( $image_id ) . '" style="background-image:url(' . esc_url( $url ) . ')"'; }
	}
	$html = preg_replace( '/<div class="wtg-stage"([^>]*)>/', '<div' . $hero_attributes . '$1>', $html, 1 );
	$mock_index = 0;
	$html = preg_replace_callback( '/<div class="wtg-mock([^\"]*)"([^>]*)>/u', function ( $match ) use ( &$mock_index, $content ) {
		$mock_index++;
		$key = 'mock' . $mock_index;
		$image_id = isset( $content['prototype_images'][ $key ] ) ? absint( $content['prototype_images'][ $key ] ) : 0;
		$attributes = $match[2] . ' data-awd-image-slot="' . esc_attr( $key ) . '" data-awd-image-id="' . esc_attr( $image_id ) . '"';
		if ( $image_id ) { $url = wp_get_attachment_image_url( $image_id, 'full' ); if ( $url ) { return '<div class="wtg-mock' . $match[1] . ' awd-lm-slot-image"' . $attributes . ' style="background-image:url(' . esc_url( $url ) . ')">'; } }
		return '<div class="wtg-mock' . $match[1] . '"' . $attributes . '>';
	}, $html );
	$product_index = 0;
	$html = preg_replace_callback( '/<div class="wtg-product-visual([^\"]*)"><\/div>/u', function ( $match ) use ( &$product_index, $content ) { $product_index++; $key = 'product' . $product_index; $image_id = isset( $content['prototype_images'][ $key ] ) ? absint( $content['prototype_images'][ $key ] ) : 0; $attributes = ' data-awd-image-slot="' . esc_attr( $key ) . '" data-awd-image-id="' . esc_attr( $image_id ) . '"'; if ( $image_id ) { $url = wp_get_attachment_image_url( $image_id, 'full' ); if ( $url ) { return '<div class="wtg-product-visual' . $match[1] . ' awd-lm-slot-image"' . $attributes . ' style="background-image:url(' . esc_url( $url ) . ')"></div>'; } } return '<div class="wtg-product-visual' . $match[1] . '"' . $attributes . '></div>'; }, $html );
	$panel_index = 0;
	$html = preg_replace_callback( '/<div class="wtg-visual-panel([^\"]*)"([^>]*)>/u', function ( $match ) use ( &$panel_index, $content ) { $panel_index++; $key = 'panel' . $panel_index; $image_id = isset( $content['prototype_images'][ $key ] ) ? absint( $content['prototype_images'][ $key ] ) : 0; $attributes = $match[2] . ' data-awd-image-slot="' . esc_attr( $key ) . '" data-awd-image-id="' . esc_attr( $image_id ) . '"'; $image = ''; if ( $image_id ) { $url = wp_get_attachment_image_url( $image_id, 'full' ); if ( $url ) { $image = '<img class="awd-lm-slot-img" src="' . esc_url( $url ) . '" alt="">'; } } return '<div class="wtg-visual-panel' . $match[1] . ( $image ? ' awd-lm-slot-image' : '' ) . '"' . $attributes . '>' . $image; }, $html );
	if ( 'second-landing' === $landing_key ) {
		$assistant_prompt_defaults = array(
			'de' => 'Möchten Sie mich in Aktion sehen?',
			'en' => 'Want to see me in action?',
			'fr' => 'Voulez-vous me voir en action ?',
		);
		$assistant_prompt = isset( $content['prototype_text'][ $lang ]['t278'] )
			? sanitize_text_field( $content['prototype_text'][ $lang ]['t278'] )
			: ( $assistant_prompt_defaults[ $lang ] ?? $assistant_prompt_defaults['en'] );
		$avatar_id = isset( $content['prototype_images']['chat-avatar'] ) ? absint( $content['prototype_images']['chat-avatar'] ) : 0;
		$avatar_url = $avatar_id ? wp_get_attachment_image_url( $avatar_id, 'thumbnail' ) : '';
		$avatar_class = 'wtg-assistant-avatar' . ( $avatar_url ? ' awd-lm-slot-image' : '' );
		$avatar_style = $avatar_url ? ' style="background-image:url(' . esc_url( $avatar_url ) . ')"' : '';
		$avatar_markup = '<div class="' . esc_attr( $avatar_class ) . '" data-awd-image-slot="chat-avatar" data-awd-image-id="' . esc_attr( $avatar_id ) . '" data-chat-prompt="' . esc_attr( $assistant_prompt ) . '" aria-label="Chat avatar"' . $avatar_style . '></div>';
		$html = str_replace( '<div class="wtg-assistant-avatar"></div>', $avatar_markup, $html );
		$image_metadata = awd_lm_second_landing_image_metadata( $lang );
		$html = preg_replace_callback( '/<([a-z][a-z0-9]*)\b([^>]*\bdata-awd-image-slot="([^"]+)"[^>]*)>/u', function ( $match ) use ( $image_metadata ) {
			$slot = sanitize_key( $match[3] );
			$meta = $image_metadata[ $slot ] ?? array( 'title'=>'', 'alt'=>'' );
			$attributes = preg_replace( '/\s(?:title|aria-label|aria-hidden|role|data-awd-image-title|data-awd-image-alt)="[^"]*"/u', '', $match[2] );
			$attributes .= ' data-awd-image-title="' . esc_attr( $meta['title'] ) . '" data-awd-image-alt="' . esc_attr( $meta['alt'] ) . '"';
			$has_image = preg_match( '/\bdata-awd-image-id="[1-9][0-9]*"/u', $attributes );
			if ( 'img' === strtolower( $match[1] ) ) {
				$attributes = preg_replace( '/\salt="[^"]*"/u', '', $attributes ) . ' alt="' . esc_attr( $meta['alt'] ) . '"';
			} elseif ( $has_image && '' !== $meta['alt'] ) {
				$attributes .= ' role="img" aria-label="' . esc_attr( $meta['alt'] ) . '" title="' . esc_attr( $meta['title'] ) . '"';
			} else {
				$attributes .= ' aria-hidden="true"';
			}
			return '<' . $match[1] . $attributes . '>';
		}, $html );
	}
	$section_index = 0;
	$html = preg_replace_callback( '/<section\b([^>]*)>(.*?)<\/section>/su', function ( $match ) use ( &$section_index, $content, $is_editor ) {
		$section_index++; $key = 'section' . $section_index; $settings = $content['prototype_sections'][ $key ] ?? array();
		$enabled = ! isset( $settings['enabled'] ) || ! empty( $settings['enabled'] );
		if ( ! $enabled && ! $is_editor ) { return ''; }
		$attributes = $match[1]; $anchor = isset( $settings['anchor'] ) ? sanitize_title( $settings['anchor'] ) : '';
		if ( $anchor ) { $attributes = preg_replace( '/\sid="[^"]*"/', '', $attributes ) . ' id="' . esc_attr( $anchor ) . '"'; }
		if ( ! $enabled ) {
			$attributes = preg_match( '/\sclass="/u', $attributes )
				? preg_replace( '/\sclass="([^"]*)"/u', ' class="$1 awd-lm-section-disabled"', $attributes, 1 )
				: $attributes . ' class="awd-lm-section-disabled"';
		}
		$name = sanitize_text_field( $settings['analytics_name'] ?? '' );
		if ( '' === $name && preg_match( '/<(?:h2|h3)[^>]*>(.*?)<\/(?:h2|h3)>/is', $match[2], $heading ) ) {
			$name = sanitize_text_field( wp_strip_all_tags( $heading[1] ) );
		}
		if ( '' === $name ) { $name = $anchor ?: $key; }
		return '<section' . $attributes . ' data-awd-section-key="' . esc_attr( $key ) . '" data-awd-section-anchor="' . esc_attr( $anchor ) . '" data-awd-section-enabled="' . ( $enabled ? '1' : '0' ) . '" data-analytics-name="' . esc_attr( $name ) . '">' . $match[2] . '</section>';
	}, $html );
	if ( 'second-landing' === $landing_key ) {
		/* Reorder only after stable section keys have been assigned. Moving the
		 * source nodes earlier would remap saved visibility, anchors and analytics. */
		if ( preg_match( '/<section\b[^>]*data-awd-section-key="section6"[^>]*>.*?<\/section>/su', $html, $solutions ) ) {
			$html = str_replace( $solutions[0], '', $html );
			$html = preg_replace(
				'/(?=<section\b[^>]*data-awd-section-key="section3"[^>]*>)/u',
				$solutions[0],
				$html,
				1
			);
		}

		/* The two locally disabled modules stay available to the visual editor,
		 * but live at the very end so they do not interrupt active editing work. */
		$deferred_sections = '';
		foreach ( array( 'section13', 'section14' ) as $deferred_key ) {
			if ( preg_match( '/<section\b[^>]*data-awd-section-key="' . preg_quote( $deferred_key, '/' ) . '"[^>]*>.*?<\/section>/su', $html, $deferred ) ) {
				$deferred_sections .= $deferred[0];
				$html = str_replace( $deferred[0], '', $html );
			}
		}
		if ( '' !== $deferred_sections ) {
			$html = preg_replace( '/<\/main>/u', $deferred_sections . '</main>', $html, 1 );
		}
	}
	$menu_index = 0;
	$html = preg_replace_callback( '/(<nav class="wtg-nav-links"[^>]*>)(.*?)(<\/nav>)/su', function ( $match ) use ( &$menu_index, $content, $lang, $is_editor, $landing_key ) {
		$items = preg_replace_callback( '/<a([^>]*)>(.*?)<\/a>/su', function ( $link ) use ( &$menu_index, $content, $lang ) {
			$menu_index++;
			$item = $content['prototype_menu']['existing' . $menu_index ] ?? array();
			if ( ! empty( $item['deleted'] ) ) { return ''; }
			$labels = isset( $item['labels'] ) && is_array( $item['labels'] ) ? $item['labels'] : array();
			/* Ignore the old one-language label field: it is the source of cross-language menu copy. */
			$label = ! empty( $labels[ $lang ] ) ? $labels[ $lang ] : trim( wp_strip_all_tags( $link[2] ) );
			$anchor = $item['anchor'] ?? '';
			if ( $anchor ) { $link[1] = preg_replace( '/\shref="[^"]*"/', '', $link[1] ) . ' href="#' . esc_attr( sanitize_title( $anchor ) ) . '"'; }
			$label_attributes = '';
			foreach ( awd_lm_languages() as $code => $language_name ) {
				$label_attributes .= ' data-awd-menu-label-' . esc_attr( $code ) . '="' . esc_attr( $labels[ $code ] ?? ( $code === $lang ? $label : '' ) ) . '"';
			}
			return '<a' . $link[1] . ' data-awd-menu-key="existing' . $menu_index . '"' . $label_attributes . '>' . esc_html( $label ) . '</a>';
		}, $match[2] );
		foreach ( (array) ( $content['prototype_menu']['added'] ?? array() ) as $added_index => $item ) {
			if ( ! empty( $item['deleted'] ) || empty( $item['anchor'] ) ) { continue; }
			$labels = isset( $item['labels'] ) && is_array( $item['labels'] ) ? $item['labels'] : array();
			$label = ! empty( $labels[ $lang ] ) ? $labels[ $lang ] : ( $item['label'] ?? '' );
			if ( '' === $label ) { continue; }
			$label_attributes = '';
			foreach ( awd_lm_languages() as $code => $language_name ) {
				$label_attributes .= ' data-awd-menu-label-' . esc_attr( $code ) . '="' . esc_attr( $labels[ $code ] ?? ( $code === $lang ? $label : '' ) ) . '"';
			}
			$items .= '<a href="#' . esc_attr( sanitize_title( $item['anchor'] ) ) . '" data-awd-menu-key="added[' . absint( $added_index ) . ']"' . $label_attributes . '>' . esc_html( $label ) . '</a>';
		}
		if ( ! $is_editor ) {
			$switcher = '<div class="wtg-language-switch" aria-label="Language">';
			foreach ( awd_lm_languages() as $code => $label ) {
				$switcher .= '<a href="' . esc_url( awd_lm_public_url( $landing_key, $code ) ) . '" hreflang="' . esc_attr( $code ) . '" lang="' . esc_attr( $code ) . '"' . ( $code === $lang ? ' class="is-current" aria-current="page"' : '' ) . '>' . esc_html( strtoupper( $code ) ) . '</a>';
			}
			$switcher .= '</div>';
			$items .= $switcher;
		}
		return $match[1] . $items . $match[3];
	}, $html );
	$system_fields = awd_lm_form_system_fields( $lang );
		$system_options = '';
		foreach ( $system_fields['options'] as $system_value => $system_label ) {
			$system_options .= '<option value="' . esc_attr( $system_value ) . '">' . esc_html( $system_label ) . '</option>';
		}
		$system_markup = '<div class="wtg-field"><label for="wtgSystem">' . esc_html( $system_fields['label'] ) . '</label><select id="wtgSystem" name="system" required>' . $system_options . '</select></div>';
		$system_markup .= '<div class="wtg-field wtg-system-other" id="wtgSystemOtherField" hidden><label for="wtgSystemOther">' . esc_html( $system_fields['other_label'] ) . '</label><input id="wtgSystemOther" name="system_other" type="text" placeholder="' . esc_attr( $system_fields['other_placeholder'] ) . '" disabled></div>';
		$html = preg_replace( '/<div class="wtg-field"><label for="wtgSystem">.*?<\/div>/su', $system_markup, $html, 1 );
		$interest_fields = awd_lm_form_interest_fields( $lang );
		$interest_markup = '<fieldset class="wtg-field wtg-field--full wtg-choice-field"><legend>' . esc_html( $interest_fields['label'] ) . '</legend><div class="wtg-choice-group">';
		foreach ( $interest_fields['options'] as $interest_value => $interest_label ) {
			$interest_markup .= '<label class="wtg-choice"><input type="checkbox" name="interest[]" value="' . esc_attr( $interest_value ) . '"><span>' . esc_html( $interest_label ) . '</span></label>';
		}
		$interest_markup .= '</div></fieldset>';
		$html = preg_replace( '/<div class="wtg-field"><label for="wtgInterest">.*?<\/div>/su', $interest_markup, $html, 1 );
		$name_copy = array(
			'de' => array( 'first' => 'Vorname', 'last' => 'Nachname', 'first_placeholder' => 'Ihr Vorname', 'last_placeholder' => 'Ihr Nachname' ),
			'en' => array( 'first' => 'First name', 'last' => 'Last name', 'first_placeholder' => 'Your first name', 'last_placeholder' => 'Your last name' ),
			'fr' => array( 'first' => 'Prénom', 'last' => 'Nom', 'first_placeholder' => 'Votre prénom', 'last_placeholder' => 'Votre nom' ),
		);
		$name_text = $name_copy[ $lang ];
		$name_markup = '<div class="wtg-field"><label for="wtgFirstName">' . esc_html( $name_text['first'] ) . '</label><input id="wtgFirstName" name="first_name" type="text" autocomplete="given-name" required placeholder="' . esc_attr( $name_text['first_placeholder'] ) . '"></div>';
		$name_markup .= '<div class="wtg-field"><label for="wtgLastName">' . esc_html( $name_text['last'] ) . '</label><input id="wtgLastName" name="last_name" type="text" autocomplete="family-name" required placeholder="' . esc_attr( $name_text['last_placeholder'] ) . '"></div>';
		$html = preg_replace( '/<div class="wtg-field"><label for="wtgName">.*?<\/div>/su', $name_markup, $html, 1 );
		$phone_copy = array(
			'de' => array( 'label' => 'Telefon', 'country' => 'Ländervorwahl', 'placeholder' => '79 123 45 67' ),
			'en' => array( 'label' => 'Phone', 'country' => 'Country code', 'placeholder' => '79 123 45 67' ),
			'fr' => array( 'label' => 'Téléphone', 'country' => 'Indicatif pays', 'placeholder' => '79 123 45 67' ),
		);
		$phone_text = $phone_copy[ $lang ];
		$phone_markup = '<div class="wtg-field wtg-phone-field"><label for="wtgPhoneNational">' . esc_html( $phone_text['label'] ) . '</label><div class="wtg-phone-control">';
		$phone_markup .= '<select id="wtgPhoneCountry" aria-label="' . esc_attr( $phone_text['country'] ) . '" autocomplete="tel-country-code"><option value="CH" selected>🇨🇭 +41</option><option value="LU">🇱🇺 +352</option><option value="DE">🇩🇪 +49</option><option value="FR">🇫🇷 +33</option><option value="AT">🇦🇹 +43</option><option value="IT">🇮🇹 +39</option></select>';
		$phone_markup .= '<input id="wtgPhoneNational" type="tel" inputmode="tel" autocomplete="tel-national" placeholder="' . esc_attr( $phone_text['placeholder'] ) . '" data-invalid-phone="' . esc_attr( awd_lm_form_messages( $lang )['phone'] ) . '"><input id="wtgPhone" name="phone" type="hidden"></div></div>';
		$html = preg_replace( '/<div class="wtg-field"><label for="wtgPhone">.*?<\/div>/su', $phone_markup, $html, 1 );
		$privacy_default = 'https://website-to-go.ch/datenschutz/';
		$privacy_url = $content['prototype_links'][ $lang ]['l904'] ?? $privacy_default;
		$privacy_copy = awd_lm_privacy_consent_copy( $lang );
		$privacy_label = esc_html( preg_replace( '/\s*(?:der Datenschutzerklärung|the privacy policy|la politique de confidentialité)\.?$/u', '', $privacy_copy ) );
		$privacy_label .= ' <a href="' . esc_url( $privacy_url ) . '" target="_blank" rel="privacy-policy noopener"' . ( $is_editor ? ' data-awd-link-key="l904"' : '' ) . '>' . esc_html( awd_lm_newsletter_privacy_label( $lang ) ) . '</a>.';
		$disabled = '';
		$consent_markup = '<div class="wtg-field wtg-field--full wtg-consents">';
		$consent_markup .= '<div class="wtg-newsletter wtg-privacy-consent"><label><input type="checkbox" name="privacy_consent" value="1" required' . $disabled . '><span>' . $privacy_label . ' <strong aria-hidden="true">*</strong></span></label></div>';
		$consent_markup .= '<div class="wtg-newsletter"><label><input type="checkbox" name="newsletter" value="1"' . $disabled . '><span>' . esc_html( awd_lm_newsletter_copy( $lang ) ) . '</span></label></div></div>';
		$html = str_replace( '<div class="wtg-form-foot">', $consent_markup . '<div class="wtg-form-foot">', $html );
	$form_messages = awd_lm_form_messages( $lang );
	$form_action = esc_url( admin_url( 'admin-post.php' ) );
	$form_open = '<form class="wtg-form" id="wtgDemoForm" data-wtg-reveal="delay1" method="post" action="' . $form_action . '"><input type="hidden" name="action" value="awd_lm_submit_demo"><input type="hidden" name="landing" value="' . esc_attr( $landing_key ) . '"><input type="hidden" name="lang" value="' . esc_attr( $lang ) . '"><div class="awd-lm-honeypot" aria-hidden="true"><label>Confirmation<input type="text" name="website_url_confirm" tabindex="-1" autocomplete="new-password" data-lpignore="true" data-1p-ignore="true"></label></div>';
	$html = str_replace( '<form class="wtg-form" id="wtgDemoForm" data-wtg-reveal="delay1">', $form_open, $html );
	if ( awd_lm_turnstile_enabled() ) {
		$turnstile = awd_lm_turnstile_settings();
		$turnstile_markup = '<div class="wtg-turnstile-wrap"><div class="cf-turnstile" data-sitekey="' . esc_attr( $turnstile['site_key'] ) . '" data-action="demo_form" data-theme="light"></div></div>';
		$html = str_replace( '<div class="wtg-form-foot">', $turnstile_markup . '<div class="wtg-form-foot">', $html );
	}
	$html = preg_replace( '/(<div class="wtg-form-foot">\s*)<small>.*?<\/small>/su', '$1<small>' . esc_html( $form_messages['privacy_note'] ) . '</small>', $html, 1 );
	$html = preg_replace( '/<div class="wtg-form-message" id="wtgFormMessage"[^>]*>.*?<\/div>/su', '<div class="wtg-form-message" id="wtgFormMessage" data-success="' . esc_attr( $form_messages['success'] ) . '" data-invalid="' . esc_attr( $form_messages['invalid'] ) . '" data-honeypot="' . esc_attr( $form_messages['honeypot'] ) . '" data-phone="' . esc_attr( $form_messages['phone'] ) . '" data-failed="' . esc_attr( $form_messages['failed'] ) . '" data-rate="' . esc_attr( $form_messages['rate'] ) . '" data-captcha="' . esc_attr( $form_messages['captcha'] ) . '" data-privacy="' . esc_attr( $form_messages['privacy'] ) . '" aria-live="polite"></div>', $html, 1 );
	/* Run this last: no saved copy/link value is allowed to repopulate the footer. */
	$html = preg_replace( '/<div class="wtg-footer-links">.*?<\/div>/su', $footer_links, $html, 1 );
	/* The Restaurants Ordering landing no longer has a Case Studies block.
	 * Remove it only after text/link/section indexing so existing saved legacy
	 * keys keep their historical mapping. Remove its navigation item as well. */
	if ( 'second-landing' === $landing_key && preg_match( '/<section\b[^>]*class="[^"]*\bwtg-case-studies-section\b[^"]*"[^>]*\bid="([^"]+)"[^>]*>.*?<\/section>/su', $html, $case_section ) ) {
		$case_anchor = sanitize_title( html_entity_decode( $case_section[1], ENT_QUOTES, 'UTF-8' ) );
		$html = str_replace( $case_section[0], '', $html );
		if ( '' !== $case_anchor ) {
			$html = preg_replace( '/<a\b[^>]*href="#' . preg_quote( $case_anchor, '/' ) . '"[^>]*>.*?<\/a>/su', '', $html, 1 );
		}
		$html = preg_replace( '/<a\b[^>]*data-awd-menu-key="existing7"[^>]*>.*?<\/a>/su', '', $html, 1 );
	}
	return $html;
}

function awd_lm_display_field( $path, $lang, $multiline = false ) {
	$value = awd_field( $path, $lang );
	if ( empty( $GLOBALS['awd_lm_visual_editor'] ) ) { return esc_html( $value ); }
	$parts = explode( '.', $path );
	$name = 'content[' . esc_attr( $parts[0] ) . '][' . esc_attr( $parts[1] ) . '][' . esc_attr( $lang ) . ']';
	$class = 'awd-lm-inline' . ( $multiline ? ' awd-lm-inline-multiline' : '' );
	return '<span class="' . esc_attr( $class ) . '" contenteditable="true" role="textbox" data-awd-input="' . esc_attr( $name ) . '">' . esc_html( $value ) . '</span><input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">';
}

function awd_lm_display_image( $section, $field, $attachment_id, $alt ) {
	if ( empty( $GLOBALS['awd_lm_visual_editor'] ) ) { return; }
	$url = $attachment_id ? wp_get_attachment_image_url( $attachment_id, 'full' ) : '';
	$name = 'content[' . esc_attr( $section ) . '][' . esc_attr( $field ) . ']';
	echo '<div class="awd-lm-image-editor"><input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $attachment_id ) . '">';
	if ( $url ) { echo '<img src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '">'; }
	echo '<button type="button" class="awd-lm-change-image">Change image</button></div>';
}

function awd_lm_render_text_section( $section, $lang, $style ) {
	$render_content = isset( $GLOBALS['awd_lm_render_content'] ) ? $GLOBALS['awd_lm_render_content'] : array();
	if ( empty( $render_content[ $section ]['enabled'] ) ) { return; }
	?>
	<section class="wtg-section wtg-<?php echo esc_attr( $style ); ?>" id="<?php echo esc_attr( $section ); ?>"><div class="wtg-container wtg-split"><div><span class="wtg-kicker"><?php echo awd_lm_display_field( $section . '.eyebrow', $lang ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><h2><?php echo awd_lm_display_field( $section . '.title', $lang ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h2></div><p class="wtg-lead"><?php echo awd_lm_display_field( $section . '.text', $lang, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p></div></section>
	<?php
}

function awd_lm_render_cards_section( $section, $lang, $style ) {
	$render_content = isset( $GLOBALS['awd_lm_render_content'] ) ? $GLOBALS['awd_lm_render_content'] : array();
	if ( empty( $render_content[ $section ]['enabled'] ) ) { return; }
	?>
	<section class="wtg-section wtg-<?php echo esc_attr( $style ); ?>" id="<?php echo esc_attr( $section ); ?>"><div class="wtg-container"><span class="wtg-kicker"><?php echo awd_lm_display_field( $section . '.eyebrow', $lang ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><h2><?php echo awd_lm_display_field( $section . '.title', $lang ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h2><p class="wtg-lead"><?php echo awd_lm_display_field( $section . '.text', $lang, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p><div class="wtg-cards"><?php foreach ( awd_lm_lines( awd_field( $section . '.items', $lang ) ) as $index => $item ) : ?><article><span>0<?php echo esc_html( $index + 1 ); ?></span><h3><?php echo esc_html( $item ); ?></h3></article><?php endforeach; ?></div><?php if ( ! empty( $editor ) ) : ?><div class="awd-lm-collection-editor"><label>Cards — one item per line</label><?php echo awd_lm_display_field( $section . '.items', $lang, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div><?php endif; ?></div></section>
	<?php
}
