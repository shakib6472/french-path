<?php
/**
 * French Path test suite entry point.
 *
 * Usage: php wp-content/plugins/french-path/tests/run.php [case-filter]
 *
 * @package French_Path
 */

if ( 'cli' !== PHP_SAPI ) {
	header( 'HTTP/1.1 403 Forbidden' );
	exit( 'The French Path test suite is command line only.' );
}

// Walk up from this file until wp-load.php turns up.
$french_path_root = __DIR__;

while ( ! file_exists( $french_path_root . '/wp-load.php' ) ) {
	$parent = dirname( $french_path_root );

	if ( $parent === $french_path_root ) {
		fwrite( STDERR, 'Could not find wp-load.php above ' . __DIR__ . PHP_EOL );
		exit( 1 );
	}

	$french_path_root = $parent;
}

require_once $french_path_root . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';

if ( ! defined( 'FRENCH_PATH_PATH' ) ) {
	fwrite( STDERR, 'French Path is not active. Activate the plugin and run again.' . PHP_EOL );
	exit( 1 );
}

// Notices are recorded by the runner, not printed, so the report stays readable.
error_reporting( E_ALL ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.prevent_path_disclosure_error_reporting
ini_set( 'display_errors', '0' ); // phpcs:ignore WordPress.PHP.IniSet.display_errors_Disallowed

require_once __DIR__ . '/class-french-path-tests.php';

$t = new French_Path_Tests();
$t->set_up();

printf( 'French Path %s -- WordPress %s -- PHP %s' . PHP_EOL, FRENCH_PATH_VERSION, get_bloginfo( 'version' ), PHP_VERSION );
printf( 'Site: %s' . PHP_EOL, home_url( '/' ) );

// Never silent. A previous run left fixtures on this site and removing them is
// a change whoever is watching should see.
foreach ( $t->recovered() as $french_path_put_back ) {
	printf( 'Recovered from a run that did not finish: %s' . PHP_EOL, $french_path_put_back );
}

$cases = glob( __DIR__ . '/cases/*.php' );
sort( $cases );

// Run a subset when asked, so one rule can be checked without a full run.
if ( ! empty( $argv[1] ) ) {
	$only  = explode( ',', (string) $argv[1] );
	$cases = array_values(
		array_filter(
			$cases,
			static function ( $case ) use ( $only ) {
				foreach ( $only as $want ) {
					if ( false !== strpos( basename( $case ), trim( $want ) ) ) {
						return true;
					}
				}

				return false;
			}
		)
	);
}

foreach ( $cases as $case ) {
	if ( 'index.php' === basename( $case ) ) {
		continue;
	}

	require $case;

	$t->adopt_fixtures();
}

$exit = $t->report();

$t->tear_down();

exit( $exit );
