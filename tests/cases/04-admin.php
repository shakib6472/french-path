<?php
/**
 * The admin screen pieces load and render without touching the site.
 *
 * The admin class is only booted behind is_admin(), which is false on the
 * command line, so these call it directly.
 *
 * @package French_Path
 *
 * @var French_Path_Tests $t
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$t->group( 'Admin' );

$t->ok( class_exists( 'French_Path_Admin' ), 'the admin class autoloads' );

$columns = French_Path_Admin::columns(
	array(
		'cb'    => '',
		'title' => 'Title',
		'date'  => 'Date',
	)
);

foreach ( array( 'french_path_kind', 'french_path_courses', 'french_path_product', 'french_path_guarantee' ) as $column ) {
	$t->ok( isset( $columns[ $column ] ), 'the list table offers the ' . $column . ' column' );
}

$keys = array_keys( $columns );
$t->same( 'date', end( $keys ), 'the date column stays last' );

$courses  = French_Path_Admin::get_courses();
$products = French_Path_Admin::get_products();

$t->ok( is_array( $courses ), 'the course picker returns an array' );
$t->ok( is_array( $products ), 'the product picker returns an array' );

$fixture = $t->course( 'picker A1.1' );
$t->ok( isset( French_Path_Admin::get_courses()[ $fixture ] ), 'a new course appears in the picker' );

// Rendering must not emit anything unescaped or fatal.
$package = $t->package( 'admin render', array( $fixture ), 0, true );

ob_start();
French_Path_Admin::column( 'french_path_courses', $package );
French_Path_Admin::column( 'french_path_guarantee', $package );
French_Path_Admin::column( 'french_path_kind', $package );
French_Path_Admin::column( 'french_path_product', $package );
$rendered = (string) ob_get_clean();

$t->ok( false !== strpos( $rendered, 'picker A1.1' ), 'the courses column names the sublevel' );
$t->ok( false !== strpos( $rendered, 'Yes' ), 'the guarantee column reports the flag' );
$t->ok( false !== strpos( $rendered, 'None set' ), 'a package with no product says so' );

// A package pointing at a deleted course must not fatal the list table.
update_post_meta( $package, French_Path_Package::META_COURSES, array( 999999 ) );
French_Path_Package::flush_memo();

ob_start();
French_Path_Admin::column( 'french_path_courses', $package );
$missing = (string) ob_get_clean();

$t->ok( is_string( $missing ), 'a missing course renders without fatal' );

$t->group( 'Save guard' );

// Saving without a nonce must be a no-op, not a write.
$before = French_Path_Package::get_courses( $package );
French_Path_Admin::save( $package, get_post( $package ) );
$t->same( $before, French_Path_Package::get_courses( $package ), 'save without a valid nonce changes nothing' );
