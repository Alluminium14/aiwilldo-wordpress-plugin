<?php
return array(
	'name' => 'Second Landing',
	'sections' => array(
		'hero' => array(
			'label' => 'Hero',
			'fields' => array(
				'eyebrow'    => 'text',
				'title'      => 'text',
				'text'       => 'textarea',
				'button_text'=> 'text',
				'button_url' => 'url',
			),
		),
		'seo' => array(
			'label'  => 'SEO',
			'toggle' => false,
			'fields' => array( 'title' => 'text', 'description' => 'textarea' ),
		),
	),
);

