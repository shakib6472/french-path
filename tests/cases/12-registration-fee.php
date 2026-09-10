<?php
/**
 * The registration fee is read from its own product, so the card and the
 * checkout never show different figures.
 *
 * @package French_Path
 *
 * @var French_Path_Tests $t
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$t->group( 'Registration fee from a product' );

if ( ! function_exists( 'wc_get_product' ) ) {
	$t->ok( true, 'WooCommerce is absent, skipping' );
	return;
}

$fee = $t->product( 'reg fee' );

$blank = French_Path_Package::get_promotional_price( $fee );
$t->same( '', $blank['now'], 'a product with no price offers no figure' );
$t->same( '', $blank['was'], 'and nothing to strike out' );

$t->same( '', French_Path_Package::get_promotional_price( 0 )['now'], 'no product, no figure' );

// A fee with no promotion running.
update_post_meta( $fee, '_regular_price', '15000' );
update_post_meta( $fee, '_price', '15000' );

$plain = French_Path_Package::get_promotional_price( $fee );

$t->ok( false !== strpos( $plain['now'], '15,000' ), 'the fee is reported' );
$t->same( '', $plain['was'], 'with nothing struck out while no promotion runs' );

// The promotion the client is running: normal 15,000, currently 0.
update_post_meta( $fee, '_sale_price', '0' );
update_post_meta( $fee, '_price', '0' );

$promo = French_Path_Package::get_promotional_price( $fee );

$t->ok( false !== strpos( $promo['was'], '15,000' ), 'the normal fee is struck out' );
$t->ok( false !== strpos( $promo['now'], '0' ), 'the promotional figure is shown' );

$t->group( 'On the card' );

if ( post_type_exists( 'sfwd-courses' ) ) {
	$course  = $t->course( 'reg course' );
	$product = $t->product( 'reg course product' );

	update_post_meta( $product, '_regular_price', '175000' );
	update_post_meta( $product, '_price', '175000' );
	$t->package( 'reg package', array( $course ), $product );

	wp_set_current_user( 0 );

	$out = do_shortcode( '[french_path_enrol course="' . $course . '" reg_product="' . $fee . '"]' );

	$t->ok( false !== strpos( $out, 'Registration promotion' ), 'the promotion row is rendered from the product' );
	$t->ok( false !== strpos( $out, '15,000' ), 'the normal fee reaches the card' );
	$t->ok( false !== strpos( $out, '<s>' ), 'the normal fee is struck through' );

	// Ending the promotion on the product must end it on the card.
	delete_post_meta( $fee, '_sale_price' );
	update_post_meta( $fee, '_price', '15000' );

	$ended = do_shortcode( '[french_path_enrol course="' . $course . '" reg_product="' . $fee . '"]' );

	$t->ok( false !== strpos( $ended, 'Registration fee' ), 'with no sale it reads as a plain fee' );
	$t->ok( false === strpos( $ended, 'Registration promotion' ), 'and stops calling itself a promotion' );
	$t->ok( false === strpos( $ended, '<s>' ), 'with nothing struck through' );

	// The literal attributes still work, for anyone who wants to type them.
	$manual = do_shortcode( '[french_path_enrol course="' . $course . '" reg_was="X15,000" reg_now="X0"]' );
	$t->ok( false !== strpos( $manual, 'X15,000' ), 'the manual override still renders' );

	// No registration attributes at all: no row.
	$none = do_shortcode( '[french_path_enrol course="' . $course . '"]' );
	$t->ok( false === strpos( $none, 'Registration' ), 'nothing is shown when nothing is configured' );

	// A learner who has paid must never meet the fee again.
	$learner = $t->user();
	French_Path_Entitlement::grant(
		array(
			'user_id'   => $learner,
			'course_id' => $course,
			'order_id'  => 15001,
		)
	);

	wp_set_current_user( $learner );
	$paid = do_shortcode( '[french_path_enrol course="' . $course . '" reg_product="' . $fee . '"]' );

	$t->ok( false === strpos( $paid, 'Registration' ), 'a paying learner is never shown the registration fee' );

	wp_set_current_user( 0 );
}
