<?php
/**
 * The three rung window on the enrol card.
 *
 * @package French_Path
 *
 * @var French_Path_Tests $t
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$t->group( 'Window slides and clamps' );

$ladder = array( 101, 102, 103, 104, 105, 106 );

function french_path_test_steps( $window ) {
	return array_map(
		static function ( $rung ) {
			return (int) $rung['step'];
		},
		$window
	);
}

$t->same( array( 1, 2, 3 ), french_path_test_steps( French_Path_Pathway::window( 0, $ladder, 0 ) ), 'at the first rung it looks forward' );
$t->same( array( 1, 2, 3 ), french_path_test_steps( French_Path_Pathway::window( 0, $ladder, 1 ) ), 'at the second rung the window has not moved yet' );
$t->same( array( 2, 3, 4 ), french_path_test_steps( French_Path_Pathway::window( 0, $ladder, 2 ) ), 'in the middle it centres on the current rung' );
$t->same( array( 4, 5, 6 ), french_path_test_steps( French_Path_Pathway::window( 0, $ladder, 5 ) ), 'at the last rung it clamps rather than running off the end' );

$middle = French_Path_Pathway::window( 0, $ladder, 3 );

$t->same( array( 3, 4, 5 ), french_path_test_steps( $middle ), 'the window holds the rung before and after' );
$t->ok( ! $middle[0]['current'], 'the rung before is not the current one' );
$t->ok( $middle[1]['current'], 'the middle rung is the current one' );
$t->ok( ! $middle[2]['current'], 'the rung after is not the current one' );
$t->same( 104, (int) $middle[1]['id'], 'the current rung carries its course id' );

$t->same( array( 1, 2 ), french_path_test_steps( French_Path_Pathway::window( 0, array( 201, 202 ), 0 ) ), 'a two rung ladder shows both' );
$t->same( array( 1 ), french_path_test_steps( French_Path_Pathway::window( 0, array( 201 ), 0 ) ), 'a one rung ladder shows one' );
$t->same( array(), French_Path_Pathway::window( 0, array(), 0 ), 'an empty ladder has no window' );

$t->group( 'Window on a real learner' );

$learner = $t->user();
$one     = $t->course( 'win one' );
$two     = $t->course( 'win two' );
$three   = $t->course( 'win three' );
$band    = $t->package( 'win band', array( $one, $two, $three ) );

foreach ( array( $one, $two, $three ) as $position => $course_id ) {
	French_Path_Entitlement::grant(
		array(
			'user_id'    => $learner,
			'course_id'  => $course_id,
			'package_id' => $band,
			'order_id'   => 14001,
			'position'   => $position,
		)
	);
}

French_Path_Access::unlock( $learner, $one );

$view = French_Path_Pathway::for_course( $learner, $two );

$t->same( 3, count( $view['window'] ), 'the pathway carries a three rung window' );
$t->ok( $view['window'][0]['unlocked'], 'the released rung behind reads as open' );
$t->ok( ! $view['window'][1]['unlocked'], 'the current, unreleased rung reads as closed' );
$t->ok( $view['window'][1]['current'], 'the current rung is marked' );

$t->group( 'Card rendering' );

if ( post_type_exists( 'sfwd-courses' ) ) {
	wp_set_current_user( $learner );

	$out = do_shortcode( '[french_path_enrol course="' . $two . '"]' );

	$t->ok( false !== strpos( $out, 'Step 2 of 3' ), 'the card still names the rung and the length' );
	$t->ok( false !== strpos( $out, 'french-path-window' ), 'the window is rendered' );
	$t->same( 3, substr_count( $out, 'french-path-rung ' ), 'all three rungs are drawn' );
	$t->ok( false !== strpos( $out, 'You are here' ), 'the current rung says so' );
	$t->ok( false !== strpos( $out, 'french-path-rung-node' ), 'each rung draws its node on the spine' );
	$t->ok( false !== strpos( $out, get_the_title( $one ) ), 'the rung before is named' );
	$t->ok( false !== strpos( $out, get_the_title( $three ) ), 'the rung after is named' );
	$t->ok( false === strpos( $out, 'Next:' ), 'the old single line next label is gone' );

	// A single sublevel purchase has no ladder worth drawing.
	$solo_course = $t->course( 'win solo' );
	$solo        = $t->package( 'win solo package', array( $solo_course ) );

	French_Path_Entitlement::grant(
		array(
			'user_id'    => $learner,
			'course_id'  => $solo_course,
			'package_id' => $solo,
			'order_id'   => 14002,
		)
	);

	$solo_out = do_shortcode( '[french_path_enrol course="' . $solo_course . '"]' );

	$t->ok( false === strpos( $solo_out, 'Step 1 of 1' ), 'a one sublevel purchase does not say step 1 of 1' );
	$t->ok( false === strpos( $solo_out, 'french-path-window' ), 'and draws no ladder' );
	$t->ok( false !== strpos( $solo_out, 'Waiting on your coordinator' ), 'but still explains why it is closed' );

	wp_set_current_user( 0 );
}
