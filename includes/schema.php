<?php
defined( 'ABSPATH' ) || exit;

function awd_lm_languages() {
	return array( 'de' => 'Deutsch', 'en' => 'English', 'fr' => 'Français' );
}

function awd_lm_definitions() {
	$definitions = array(
		'gloriafood' => array(
			'name' => 'GloriaFood Alternative',
			'option' => 'awd_landing_gloriafood',
			'template' => 'landing-gloriafood.php',
			'slug' => 'gloriafood-alternative',
			'sections' => array(
				'hero' => array( 'label' => 'Hero', 'fields' => array( 'eyebrow' => 'text', 'title' => 'text', 'text' => 'textarea', 'button_text' => 'text', 'button_url' => 'url', 'image_id' => 'image' ) ),
				'problem' => array( 'label' => 'Problem', 'fields' => array( 'eyebrow' => 'text', 'title' => 'text', 'text' => 'textarea' ) ),
				'solution' => array( 'label' => 'Solution', 'fields' => array( 'eyebrow' => 'text', 'title' => 'text', 'text' => 'textarea', 'items' => 'lines' ) ),
				'features' => array( 'label' => 'Features', 'fields' => array( 'eyebrow' => 'text', 'title' => 'text', 'text' => 'textarea', 'items' => 'lines' ) ),
				'migration' => array( 'label' => 'Migration', 'fields' => array( 'eyebrow' => 'text', 'title' => 'text', 'text' => 'textarea', 'button_text' => 'text', 'button_url' => 'url' ) ),
				'pricing' => array( 'label' => 'Pricing', 'fields' => array( 'eyebrow' => 'text', 'title' => 'text', 'text' => 'textarea', 'items' => 'lines' ) ),
				'faq' => array( 'label' => 'FAQ', 'fields' => array( 'eyebrow' => 'text', 'title' => 'text', 'items' => 'faq' ) ),
				'cta' => array( 'label' => 'Final CTA', 'fields' => array( 'eyebrow' => 'text', 'title' => 'text', 'text' => 'textarea', 'button_text' => 'text', 'button_url' => 'url' ) ),
				'seo' => array( 'label' => 'SEO', 'toggle' => false, 'fields' => array( 'title' => 'text', 'description' => 'textarea' ) ),
			),
		),
	);
	foreach ( awd_lm_landing_registry() as $key => $module ) {
		if ( isset( $definitions[ $key ] ) ) { continue; }
		if ( ! empty( $module['clone_of'] ) && isset( $definitions[ $module['clone_of'] ] ) ) {
			$definitions[ $key ] = array_merge( $definitions[ $module['clone_of'] ], array(
				'name'        => $module['name'],
				'option'      => $module['option'],
				'slug'        => $module['slug'],
				'clone_of'    => $module['clone_of'],
				'legacy_mode' => true,
			) );
			continue;
		}
		$schema = awd_lm_landing_module_data( $key, 'schema' );
		$definitions[ $key ] = array(
			'name'       => $schema['name'] ?? $module['name'],
			'option'     => $module['option'],
			'template'   => $module['template'],
			'slug'       => $module['slug'],
			'schema'     => $module['schema'],
			'module'     => true,
			'legacy_mode'=> false,
			'sections'   => $schema['sections'] ?? array(),
		);
	}
	return $definitions;
}

function awd_lm_default_content_for( $key ) {
	$key = sanitize_key( $key );
	if ( 'gloriafood' === $key ) { return awd_lm_default_content(); }
	$module = awd_lm_get_landing_module( $key );
	if ( ! empty( $module['clone_of'] ) && 'gloriafood' === $module['clone_of'] ) {
		$content = awd_lm_default_content();
		$defaults_file = $module['defaults'] ?? '';
		if ( $defaults_file && is_readable( $defaults_file ) ) {
			$defaults = json_decode( (string) file_get_contents( $defaults_file ), true );
			if ( ! empty( $defaults['prototype_text'] ) && is_array( $defaults['prototype_text'] ) ) { $content['prototype_text'] = $defaults['prototype_text']; }
		}
		return $content;
	}
	$schema = awd_lm_landing_module_data( $key, 'schema' );
	$translations = awd_lm_landing_module_data( $key, 'translations' );
	if ( ! $module || ! $schema ) { return array(); }
	$content = array();
	foreach ( (array) ( $schema['sections'] ?? array() ) as $section_key => $section ) {
		if ( false !== ( $section['toggle'] ?? true ) ) { $content[ $section_key ]['enabled'] = true; }
		foreach ( (array) ( $section['fields'] ?? array() ) as $field => $type ) {
			foreach ( awd_lm_languages() as $lang => $label ) {
				$value = $translations[ $lang ][ $section_key ][ $field ] ?? ( $translations[ $lang ][ $field ] ?? '' );
				if ( 'button_url' === $field && '' === $value ) { $value = '#contact'; }
				if ( 'seo' === $section_key && 'title' === $field && '' === $value ) { $value = $module['name']; }
				$content[ $section_key ][ $field ][ $lang ] = $value;
			}
		}
	}
	return $content;
}

function awd_lm_default_content() {
	$tr = function ( $de, $en, $fr ) { return array( 'de' => $de, 'en' => $en, 'fr' => $fr ); };
	return array(
		/* One global brand label, shared by every language and reusable in the prototype copy. */
		'brand_name' => 'website-to-go',
		'hero' => array( 'enabled' => true, 'eyebrow' => $tr( 'GloriaFood Alternative', 'GloriaFood alternative', 'Alternative à GloriaFood' ), 'title' => $tr( 'GloriaFood wird eingestellt. Ihr Onlineverkauf muss es nicht.', 'GloriaFood is shutting down. Your online sales do not have to.', 'GloriaFood s’arrête. Pas vos ventes en ligne.' ), 'text' => $tr( 'Wechseln Sie zu einer modernen Lösung für Bestellungen, Tischreservation und Ihre Restaurant-Website.', 'Move to one modern solution for ordering, reservations and your restaurant website.', 'Passez à une solution moderne pour les commandes, les réservations et le site de votre restaurant.' ), 'button_text' => $tr( 'Kostenlose Demo', 'Free demo', 'Démo gratuite' ), 'button_url' => $tr( '#demo', '#demo', '#demo' ), 'image_id' => 0 ),
		'problem' => array( 'enabled' => true, 'eyebrow' => $tr( 'Der Wechsel', 'The change', 'Le changement' ), 'title' => $tr( 'Ein notwendiger Wechsel darf nicht zum nächsten IT-Projekt werden.', 'A necessary switch should not become another IT project.', 'Un changement nécessaire ne doit pas devenir un nouveau projet informatique.' ), 'text' => $tr( 'Produkte, Öffnungszeiten und Kundendaten sollen sauber übernommen werden – ohne Verkaufsunterbruch.', 'Products, opening hours and customer data should move cleanly, without interrupting sales.', 'Produits, horaires et données clients doivent migrer proprement, sans interrompre les ventes.' ) ),
		'solution' => array( 'enabled' => true, 'eyebrow' => $tr( 'Eine Plattform', 'One platform', 'Une plateforme' ), 'title' => $tr( 'Weniger verwalten. Einfacher verkaufen.', 'Manage less. Sell more easily.', 'Moins de gestion. Plus de ventes.' ), 'text' => $tr( 'Alles, was Ihr Restaurant digital braucht, arbeitet zusammen.', 'Everything your restaurant needs digitally works together.', 'Tout ce dont votre restaurant a besoin en ligne fonctionne ensemble.' ), 'items' => $tr( "Take Away & Delivery\nTischreservation\nRestaurant-Website\nKI Chat Assistent", "Takeaway & delivery\nTable reservations\nRestaurant website\nAI chat assistant", "Vente à emporter & livraison\nRéservation de table\nSite du restaurant\nAssistant IA" ) ),
		'features' => array( 'enabled' => true, 'eyebrow' => $tr( 'Ihre Vorteile', 'Your benefits', 'Vos avantages' ), 'title' => $tr( 'Ein Verkaufsprozess statt einzelner Tools.', 'One sales process instead of disconnected tools.', 'Un processus de vente au lieu d’outils isolés.' ), 'text' => $tr( 'Einmal pflegen, überall aktuell. Für Ihr Team einfach und für Ihre Gäste schnell.', 'Update once, stay current everywhere. Easy for your team and fast for your guests.', 'Une seule mise à jour, partout à jour. Simple pour votre équipe et rapide pour vos clients.' ), 'items' => $tr( "Keine Provision pro Bestellung\nDirekter Kundenkontakt\nMobil optimiert\nPersönlicher Support", "No commission per order\nDirect customer relationship\nMobile optimized\nPersonal support", "Aucune commission par commande\nRelation client directe\nOptimisé mobile\nSupport personnalisé" ) ),
		'migration' => array( 'enabled' => true, 'eyebrow' => $tr( 'Sicher migrieren', 'Migrate safely', 'Migration sécurisée' ), 'title' => $tr( 'Wir begleiten Ihren Wechsel von GloriaFood.', 'We guide your move from GloriaFood.', 'Nous accompagnons votre migration depuis GloriaFood.' ), 'text' => $tr( 'Von der Vorbereitung bis zum Go-live erhalten Sie einen klaren Ablauf und einen persönlichen Ansprechpartner.', 'From preparation to go-live, you get a clear process and a personal contact.', 'De la préparation à la mise en ligne, profitez d’un processus clair et d’un interlocuteur dédié.' ), 'button_text' => $tr( 'Wechsel besprechen', 'Discuss your migration', 'Parler de la migration' ), 'button_url' => $tr( '#demo', '#demo', '#demo' ) ),
		'pricing' => array( 'enabled' => true, 'eyebrow' => $tr( 'Transparent', 'Transparent', 'Transparent' ), 'title' => $tr( 'Module, die zu Ihrem Restaurant passen.', 'Modules that fit your restaurant.', 'Des modules adaptés à votre restaurant.' ), 'text' => $tr( 'Starten Sie mit dem, was Sie heute brauchen, und erweitern Sie später.', 'Start with what you need today and expand later.', 'Commencez avec vos besoins actuels et évoluez ensuite.' ), 'items' => $tr( "Keine versteckten Transaktionsgebühren\nFlexible Module\nEin persönlicher Ansprechpartner", "No hidden transaction fees\nFlexible modules\nOne personal contact", "Aucun frais de transaction caché\nModules flexibles\nUn interlocuteur dédié" ) ),
		'faq' => array( 'enabled' => true, 'eyebrow' => $tr( 'FAQ', 'FAQ', 'FAQ' ), 'title' => $tr( 'Die wichtigsten Fragen vor dem Wechsel.', 'Key questions before switching.', 'Les questions essentielles avant de changer.' ), 'items' => $tr( "Was passiert mit meinen bestehenden Daten?|Wir planen die Übernahme gemeinsam und prüfen alle Inhalte vor dem Go-live.\nKann ich nur einzelne Module nutzen?|Ja. Sie starten mit den Modulen, die zu Ihrem Betrieb passen.\nFunktioniert die Lösung auf dem Smartphone?|Ja. Bestellung, Reservation und Website sind für mobile Geräte optimiert.", "What happens to my existing data?|We plan the transfer together and verify all content before launch.\nCan I use individual modules only?|Yes. Start with the modules that fit your operation.\nDoes it work on smartphones?|Yes. Ordering, reservations and the website are mobile optimized.", "Que deviennent mes données existantes ?|Nous planifions la migration ensemble et vérifions tout avant la mise en ligne.\nPuis-je utiliser seulement certains modules ?|Oui. Commencez avec les modules adaptés à votre activité.\nLa solution fonctionne-t-elle sur mobile ?|Oui. Commandes, réservations et site sont optimisés pour mobile." ) ),
		'cta' => array( 'enabled' => true, 'eyebrow' => $tr( 'Persönliche Demo', 'Personal demo', 'Démo personnalisée' ), 'title' => $tr( 'Passt die Lösung zu Ihrem Restaurant? Schauen wir es gemeinsam an.', 'Is this right for your restaurant? Let’s look at it together.', 'Cette solution convient-elle à votre restaurant ? Regardons ensemble.' ), 'text' => $tr( 'In 30 Minuten klären wir Ihre Ausgangslage, den Wechsel und die passenden Module.', 'In 30 minutes, we cover your situation, migration and the right modules.', 'En 30 minutes, faisons le point sur votre situation, la migration et les bons modules.' ), 'button_text' => $tr( 'Demo vereinbaren', 'Book a demo', 'Réserver une démo' ), 'button_url' => $tr( 'mailto:hello@example.com', 'mailto:hello@example.com', 'mailto:hello@example.com' ) ),
		'seo' => array( 'title' => $tr( 'GloriaFood Alternative | website-to-go', 'GloriaFood Alternative | website-to-go', 'Alternative à GloriaFood | website-to-go' ), 'description' => $tr( 'Die moderne GloriaFood Alternative für Bestellungen, Reservation und Restaurant-Websites.', 'A modern GloriaFood alternative for ordering, reservations and restaurant websites.', 'Une alternative moderne à GloriaFood pour les commandes, réservations et sites de restaurants.' ) ),
	);
}
