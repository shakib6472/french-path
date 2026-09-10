<?php
/**
 * A course page offers only what somebody starting there can buy.
 *
 * @package French_Path
 *
 * @var French_Path_Tests $t
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$t->group( 'Default: the sublevel it starts on' );

$b11 = $t->course( 'vis B1.1' );
$b12 = $t->course( 'vis B1.2' );
$b13 = $t->course( 'vis B1.3' );

$band = $t->package( 'vis B1 band', array( $b11, $b12, $b13 ) );
$solo = $t->package( 'vis B1.2 alone', array( $b12 ) );
$rest = $t->package( 'vis B1.2 onwards', array( $b12, $b13 ) );

$t->same( French_Path_Package::SHOW_ON_FIRST, French_Path_Package::get_show_on_mode( $band ), 'a new package starts on the default' );
$t->same( array( $b11 ), French_Path_Package::get_show_on( $band ), 'the band is offered on its first rung only' );
$t->same( array( $b12 ), French_Path_Package::get_show_on( $solo ), 'a single sublevel is offered on itself' );
$t->same( array( $b12 ), French_Path_Package::get_show_on( $rest ), 'a package starting mid ladder is offered where it starts' );

$empty = $t->package( 'vis empty', array() );
$t->same( array(), French_Path_Package::get_show_on( $empty ), 'a package with no sublevels is offered nowhere' );

French_Path_Package::flush_memo();

$t->group( 'The client bug is fixed' );

$on_b12 = French_Path_Package::find_shown_on( $b12 );

$t->ok( in_array( $solo, $on_b12, true ), 'B1.2 offers B1.2 alone' );
$t->ok( in_array( $rest, $on_b12, true ), 'B1.2 offers B1.2 onwards' );
$t->ok( ! in_array( $band, $on_b12, true ), 'B1.2 does NOT offer a package that would start the buyer at B1.1' );

// Containment is still available, it is just not what a course page asks.
$t->ok( in_array( $band, French_Path_Package::find_by_course( $b12 ), true ), 'the band still contains B1.2' );

$t->same( array( $band ), French_Path_Package::find_shown_on( $b11 ), 'B1.1 offers the band, because that is where it starts' );
$t->same( array(), French_Path_Package::find_shown_on( 0 ), 'no course, nothing offered' );

$t->group( 'Chosen pages override the default' );

update_post_meta( $band, French_Path_Package::META_SHOW_ON_MODE, French_Path_Package::SHOW_ON_CHOSEN );
update_post_meta( $band, French_Path_Package::META_SHOW_ON, array( $b11, $b12, 0, $b12 ) );
French_Path_Package::flush_memo();

$t->same( array( $b11, $b12 ), French_Path_Package::get_show_on( $band ), 'duplicates and empty values are dropped' );
$t->ok( in_array( $band, French_Path_Package::find_shown_on( $b12 ), true ), 'the band now appears on B1.2 as well' );

update_post_meta( $band, French_Path_Package::META_SHOW_ON_MODE, French_Path_Package::SHOW_ON_FIRST );
French_Path_Package::flush_memo();
$t->same( array( $b11 ), French_Path_Package::get_show_on( $band ), 'switching back to the default ignores the stored list' );

$t->group( 'Warnings, not blockers' );

$product = $t->product( 'vis product' );
update_post_meta( $product, '_regular_price', '500000' );
update_post_meta( $product, '_price', '500000' );

$guaranteed = $t->package( 'vis pathway', array( $b11, $b12, $b13 ), $product, true );

$t->same( array(), French_Path_Package::get_warnings( $guaranteed ), 'the default raises no warning' );
$t->ok( French_Path_Package::is_ready( $guaranteed ), 'and the package is ready' );

// Ticking nothing means it is offered nowhere, which is worth saying.
update_post_meta( $guaranteed, French_Path_Package::META_SHOW_ON_MODE, French_Path_Package::SHOW_ON_CHOSEN );
update_post_meta( $guaranteed, French_Path_Package::META_SHOW_ON, array() );
French_Path_Package::flush_memo();

$warnings = French_Path_Package::get_warnings( $guaranteed );
$t->ok( false !== strpos( implode( ' ', $warnings ), 'nobody will ever be offered' ), 'a package ticked nowhere says so' );
$t->ok( French_Path_Package::is_ready( $guaranteed ), 'but it is still not broken' );

// A guarantee package advertised away from its start is the risky case.
update_post_meta( $guaranteed, French_Path_Package::META_SHOW_ON, array( $b11, $b13 ) );
French_Path_Package::flush_memo();

$warnings = French_Path_Package::get_warnings( $guaranteed );
$t->ok( false !== strpos( implode( ' ', $warnings ), 'examination guarantee' ), 'the guarantee risk is called out' );

// Ticked only where it starts is exactly the default, so nothing to say.
update_post_meta( $guaranteed, French_Path_Package::META_SHOW_ON, array( $b11 ) );
French_Path_Package::flush_memo();
$t->same( array(), French_Path_Package::get_warnings( $guaranteed ), 'ticking only its own start raises nothing' );

// Leaving its own start out is a different mistake.
update_post_meta( $guaranteed, French_Path_Package::META_SHOW_ON_MODE, French_Path_Package::SHOW_ON_CHOSEN );
update_post_meta( $guaranteed, French_Path_Package::META_GUARANTEE, '' );
update_post_meta( $guaranteed, French_Path_Package::META_SHOW_ON, array( $b13 ) );
French_Path_Package::flush_memo();

$warnings = French_Path_Package::get_warnings( $guaranteed );
$t->ok( false !== strpos( implode( ' ', $warnings ), 'not ticked' ), 'a package that omits its own start is flagged' );

$t->group( 'The card offers the right thing' );

if ( post_type_exists( 'sfwd-courses' ) && function_exists( 'wc_get_product' ) ) {
	$p_solo = $t->product( 'vis solo product' );
	update_post_meta( $p_solo, '_regular_price', '125000' );
	update_post_meta( $p_solo, '_price', '125000' );
	update_post_meta( $solo, French_Path_Package::META_PRODUCT, $p_solo );

	$p_band = $t->product( 'vis band product' );
	update_post_meta( $p_band, '_regular_price', '500000' );
	update_post_meta( $p_band, '_price', '500000' );
	update_post_meta( $band, French_Path_Package::META_PRODUCT, $p_band );

	French_Path_Package::flush_memo();
	wp_set_current_user( 0 );

	$names = array_map(
		static function ( $option ) {
			return $option['name'];
		},
		French_Path_Pathway::buying_options( $b12 )
	);

	$t->ok( in_array( get_the_title( $solo ), $names, true ), 'B1.2 is offered the package that starts there' );
	$t->ok( ! in_array( get_the_title( $band ), $names, true ), 'and not the one that starts earlier' );

	$out = do_shortcode( '[french_path_enrol course="' . $b12 . '"]' );

	$t->ok( false !== strpos( $out, get_the_title( $solo ) ), 'the popup lists it' );
	$t->ok( false === strpos( $out, get_the_title( $band ) ), 'and leaves the earlier one out' );
}
