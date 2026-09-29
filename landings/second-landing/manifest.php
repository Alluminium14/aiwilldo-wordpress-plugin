<?php
return array(
	'id'           => 'second-landing',
	'name'         => 'Restaurants Ordering System',
	'version'      => '1.0.0',
	'option'       => 'awd_landing_second_landing',
	'slug'         => 'restaurants-ordering-system',
	'languages'    => array( 'de', 'en', 'fr' ),
	'root_class'   => 'awd-landing--second-landing',
	'legacy_mode'  => true,
	'clone_of'     => 'gloriafood',
	'template'     => __DIR__ . '/template.php',
	'style'        => __DIR__ . '/style.css',
	'script'       => __DIR__ . '/script.js',
	'translations' => __DIR__ . '/translations.json',
	'defaults'     => __DIR__ . '/default-content.json',
	'schema'       => __DIR__ . '/schema.php',
);
