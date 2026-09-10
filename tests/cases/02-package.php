<?php
/**
 * Packages resolve to ordered courses and to their product.
 *
 * @package French_Path
 *
 * @var French_Path_Tests $t
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$t->group( 'Package' );

$t->ok( post_type_exists( French_Path_Package::POST_TYPE ), 'the package post type is registered' );

$a11 = $t->course( 'A1.1' );
$a12 = $t->course( 'A1.2' );
$b11 = $t->course( 'B1.1' );

$single_product = $t->product( 'A1.1 product' );
$band_product   = $t->product( 'A1 band product' );

$single = $t->package( 'A1.1 single', array( $a11 ), $single_product );
$band   = $t->package( 'A1 Foundation', array( $a11, $a12 ), $band_product );
$full   = $t->package( 'Complete pathway', array( $a11, $a12, $b11 ), 0, true );

$t->same( array( $a11, $a12 ), French_Path_Package::get_courses( $band ), 'the ladder keeps the order it was saved in' );
$t->same( $band_product, French_Path_Package::get_product( $band ), 'the linked product is returned' );
$t->same( 0, French_Path_Package::get_product( $full ), 'a package with no product returns 0' );

$t->ok( French_Path_Package::has_guarantee( $full ), 'the pathway carries the guarantee' );
$t->ok( ! French_Path_Package::has_guarantee( $band ), 'the band does not carry the guarantee' );

$t->same( 'single', French_Path_Package::get_kind( $band ), 'an unset kind falls back to single' );

// Duplicates inside one package are dropped, so a ladder never repeats a step.
update_post_meta( $band, French_Path_Package::META_COURSES, array( $a11, $a12, $a11, 0 ) );
$t->same( array( $a11, $a12 ), French_Path_Package::get_courses( $band ), 'duplicate and empty course IDs are dropped' );
update_post_meta( $band, French_Path_Package::META_COURSES, array( $a11, $a12 ) );

French_Path_Package::flush_memo();

$t->same( array( $single ), French_Path_Package::find_by_product( $single_product ), 'a product resolves to its package' );
$t->same( array(), French_Path_Package::find_by_product( 0 ), 'an empty product resolves to nothing' );

$in_a11 = French_Path_Package::find_by_course( $a11 );

$t->ok( in_array( $single, $in_a11, true ), 'A1.1 is offered by the single package' );
$t->ok( in_array( $band, $in_a11, true ), 'A1.1 is also offered inside the band' );
$t->ok( in_array( $full, $in_a11, true ), 'A1.1 is also offered inside the pathway' );

$t->same( array(), French_Path_Package::find_by_course( 0 ), 'an empty course resolves to nothing' );
