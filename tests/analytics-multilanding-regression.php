<?php
/** Static regression checks for multi-landing analytics persistence and filtering. */
$root = dirname( __DIR__ );
$analytics = file_get_contents( $root . '/includes/analytics.php' );
$dashboard = file_get_contents( $root . '/includes/analytics-dashboard.php' );

$checks = array(
	'analytics DB version is upgraded' => "awd_lm_analytics_db_version', '1.3'",
	'sessions store landing identity' => 'landing_id varchar(50)',
	'events store landing identity' => 'landing_event_time',
	'old sessions are migrated' => "SET landing_id=IF(landing<>'',landing,'gloriafood')",
	'old events inherit session landing' => 'SET e.landing_id=s.landing_id',
	'payload landing identity is validated' => "context['landing_id']",
	'lead events retain landing identity' => "'landing_id' => \$landing_id",
);
foreach ( $checks as $message => $needle ) {
	if ( false === strpos( $analytics, $needle ) ) {
		fwrite( STDERR, "FAIL: $message\n" );
		exit( 1 );
	}
}

foreach ( array( 'All landings', '$session_filter', '$event_filter', "'landing_id' => \$landing_query_value" ) as $needle ) {
	if ( false === strpos( $dashboard, $needle ) ) {
		fwrite( STDERR, "FAIL: analytics dashboard filter missing: $needle\n" );
		exit( 1 );
	}
}

echo "Analytics multi-landing regression checks passed.\n";
