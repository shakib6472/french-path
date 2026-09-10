<?php
/**
 * The registration fee product is chosen once on the settings screen, so no
 * product ID is ever typed into a shortcode.
 *
 * @package French_Path
 *
 * @var French_Path_Tests $t
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$t->group( 'Settings store' );

$before = get_option( French_Path_Settings::OPTION, array() );

$t->same( 0, French_Path_Settings::registration_product(), 'nothing is chosen to begin with' );
$t->ok( array_key_exists( 'registration_product', French_Path_Settings::defaults() ), 'the setting is declared with a default' );

French_Path_Settings::save( array( 'registration_product' => '1618' ) );
French_Path_Settings::flush();

$t->same( 1618, French_Path_Settings::registration_product(), 'a saved product comes back as an integer' );

// The option must not grow fields the spec does not describe.
French_Path_Settings::save( array( 'something_invented' => 'yes' ) );
French_Path_Settings::flush();

$t->ok( ! array_key_exists( 'something_invented', French_Path_Settings::all() ), 'an unknown key is dropped rather than stored' );
$t->same( 1618, French_Path_Settings::registration_product(), 'and saving one does not disturb the real settings' );

French_Path_Settings::save( array( 'registration_product' => 0 ) );
French_Path_Settings::flush();
$t->same( 0, French_Path_Settings::registration_product(), 'the fee can be turned off again' );

$t->group( 'The card reads the setting' );

if ( function_exists( 'wc_get_product' ) && post_type_exists( 'sfwd-courses' ) ) {
	$fee = $t->product( 'set fee' );
	update_post_meta( $fee, '_regular_price', '15000' );
	update_post_meta( $fee, '_sale_price', '0' );
	update_post_meta( $fee, '_price', '0' );

	$course  = $t->course( 'set course' );
	$product = $t->product( 'set course product' );
	update_post_meta( $product, '_regular_price', '175000' );
	update_post_meta( $product, '_price', '175000' );
	$t->package( 'set package', array( $course ), $product );

	wp_set_current_user( 0 );

	// Nothing chosen: no row, and no shortcode attribute needed to say so.
	$off = do_shortcode( '[french_path_enrol course="' . $course . '"]' );
	$t->ok( false === strpos( $off, 'Registration' ), 'with no product chosen the card shows no fee' );

	French_Path_Settings::save( array( 'registration_product' => $fee ) );
	French_Path_Settings::flush();

	$on = do_shortcode( '[french_path_enrol course="' . $course . '"]' );

	$t->ok( false !== strpos( $on, 'Registration promotion' ), 'choosing the product is enough, with no shortcode attribute' );
	$t->ok( false !== strpos( $on, '15,000' ), 'the normal fee reaches the card' );

	// The attribute still wins, for a card that needs a different fee.
	$other = $t->product( 'set other fee' );
	update_post_meta( $other, '_regular_price', '9999' );
	update_post_meta( $other, '_price', '9999' );

	$override = do_shortcode( '[french_path_enrol course="' . $course . '" reg_product="' . $other . '"]' );
	$t->ok( false !== strpos( $override, '9,999' ), 'the shortcode attribute overrides the setting' );

	$literal = do_shortcode( '[french_path_enrol course="' . $course . '" reg_was="XX111" reg_now="XX0"]' );
	$t->ok( false !== strpos( $literal, 'XX111' ), 'a literal value overrides both' );
	$t->ok( false === strpos( $literal, '15,000' ), 'and the setting does not leak through it' );

	// Ending the promotion on the product ends it on the card, with no edit.
	delete_post_meta( $fee, '_sale_price' );
	update_post_meta( $fee, '_price', '15000' );

	$ended = do_shortcode( '[french_path_enrol course="' . $course . '"]' );
	$t->ok( false !== strpos( $ended, 'Registration fee' ), 'removing the sale price ends the promotion on the card' );
	$t->ok( false === strpos( $ended, 'Registration promotion' ), 'and it stops calling itself a promotion' );

	French_Path_Settings::save( array( 'registration_product' => 0 ) );
	French_Path_Settings::flush();
}

$t->group( 'Settings screen' );

$t->ok( class_exists( 'French_Path_Admin_Settings' ), 'the settings screen class autoloads' );
$t->ok(
	false !== strpos( French_Path_Admin_Settings::url(), 'page=' . French_Path_Admin_Settings::SLUG ),
	'the screen has an addressable URL'
);
$t->same(
	French_Path_Roles::CAP_MANAGE_PACKAGES,
	French_Path_Roles::CAP_MANAGE_PACKAGES,
	'the screen is gated on the package capability, not on manage_options'
);

// Put the site back exactly as it was found.
if ( $before ) {
	update_option( French_Path_Settings::OPTION, $before );
} else {
	delete_option( French_Path_Settings::OPTION );
}

French_Path_Settings::flush();
