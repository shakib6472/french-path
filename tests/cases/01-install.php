<?php
/**
 * The schema is installed and matches the spec.
 *
 * @package French_Path
 *
 * @var French_Path_Tests $t
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

$t->group( 'Install' );

$t->ok( French_Path_Install::table_exists(), 'the entitlements table exists' );

$t->same(
	$wpdb->prefix . 'french_path_entitlements',
	French_Path_Install::table(),
	'the table is named as the spec says'
);

$columns = $wpdb->get_col( 'DESC ' . French_Path_Install::table(), 0 ); // phpcs:ignore WordPress.DB

foreach ( array(
	'id',
	'user_id',
	'course_id',
	'package_id',
	'order_id',
	'position',
	'status',
	'guarantee',
	'unlocked',
	'unlocked_at',
	'unlocked_by',
	'granted_at',
) as $column ) {
	$t->ok( in_array( $column, (array) $columns, true ), 'column ' . $column . ' is present' );
}

$indexes = $wpdb->get_results( 'SHOW INDEX FROM ' . French_Path_Install::table() ); // phpcs:ignore WordPress.DB
$unique  = array();

foreach ( $indexes as $index ) {
	if ( 'user_course_order' === $index->Key_name ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName
		$unique[] = $index->Column_name; // phpcs:ignore WordPress.NamingConventions.ValidVariableName
	}
}

$t->same(
	array( 'user_id', 'course_id', 'order_id' ),
	$unique,
	'the unique key that makes purchases idempotent is present'
);

$t->same(
	French_Path_Install::DB_VERSION,
	get_option( French_Path_Install::VERSION_OPTION ),
	'the stored schema version matches the code'
);
