<?php
/**
 * The enrol card, the pathway it reads, and ladder order after a package is
 * edited.
 *
 * @package French_Path
 *
 * @var French_Path_Tests $t
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$t->group( 'Ladder order survives a package edit' );

$learner = $t->user();
$one     = $t->course( 'ord one' );
$two     = $t->course( 'ord two' );
$three   = $t->course( 'ord three' );

$package = $t->package( 'ord package', array( $one, $two ) );

foreach ( array( $one, $two ) as $position => $course_id ) {
	French_Path_Entitlement::grant(
		array(
			'user_id'    => $learner,
			'course_id'  => $course_id,
			'package_id' => $package,
			'order_id'   => 11001,
			'position'   => $position,
		)
	);
}

// The admin now inserts a sublevel at the front of the ladder.
update_post_meta( $package, French_Path_Package::META_COURSES, array( $three, $one, $two ) );
French_Path_Package::flush_memo();

French_Path_Entitlement::grant(
	array(
		'user_id'    => $learner,
		'course_id'  => $three,
		'package_id' => $package,
		'order_id'   => 11001,
		'position'   => 0,
	)
);

$grouped = French_Path_Entitlement::get_by_package( $learner );
$order   = array_map(
	static function ( $row ) {
		return (int) $row->course_id;
	},
	$grouped[ $package ]
);

$t->same(
	array( $three, $one, $two ),
	$order,
	'the ladder follows the package as it is now, not the position stored at purchase'
);

$t->ok( French_Path_Entitlement::sync_positions( $package ) > 0, 'syncing rewrites the stored positions' );

global $wpdb;
$stored = (int) $wpdb->get_var(
	$wpdb->prepare(
		'SELECT position FROM ' . French_Path_Install::table() . ' WHERE user_id = %d AND course_id = %d',
		$learner,
		$two
	)
);

$t->same( 2, $stored, 'the stored position now matches the package' );
$t->same( 0, French_Path_Entitlement::sync_positions( 0 ), 'syncing an empty package does nothing' );

$t->group( 'Pathway' );

$t->same( null, French_Path_Pathway::for_course( $learner, $t->course( 'ord unpaid' ) ), 'an unpaid course has no pathway' );
$t->same( null, French_Path_Pathway::for_course( 0, $one ), 'a logged out visitor has no pathway' );

$view = French_Path_Pathway::for_course( $learner, $one );

$t->same( 2, $view['step'], 'the current course knows its rung' );
$t->same( 3, $view['total'], 'the ladder knows its length' );
$t->same( $two, $view['next_id'], 'the next rung is the one after it' );
$t->ok( ! $view['unlocked'], 'nothing is open until it is released' );

$last = French_Path_Pathway::for_course( $learner, $two );
$t->same( 0, $last['next_id'], 'the final rung has nothing after it' );

$t->group( 'Longest ladder wins' );

$buyer  = $t->user();
$solo   = $t->course( 'best solo' );
$second = $t->course( 'best second' );

$single  = $t->package( 'best single', array( $solo ) );
$pathway = $t->package( 'best pathway', array( $solo, $second ), 0, true );

French_Path_Entitlement::grant(
	array(
		'user_id'    => $buyer,
		'course_id'  => $solo,
		'package_id' => $single,
		'order_id'   => 12001,
	)
);

$t->same( $single, French_Path_Pathway::best_package( $buyer, $solo ), 'one package is the best package' );

foreach ( array( $solo, $second ) as $position => $course_id ) {
	French_Path_Entitlement::grant(
		array(
			'user_id'    => $buyer,
			'course_id'  => $course_id,
			'package_id' => $pathway,
			'order_id'   => 12002,
			'position'   => $position,
			'guarantee'  => true,
		)
	);
}

$t->same( $pathway, French_Path_Pathway::best_package( $buyer, $solo ), 'buying the longer pathway takes over the view' );
$t->same( 2, French_Path_Pathway::for_course( $buyer, $solo )['total'], 'the learner is now on a two rung ladder' );
$t->ok( French_Path_Pathway::for_course( $buyer, $solo )['guarantee'], 'the guarantee comes through the pathway' );

$t->group( 'Enrol card' );

French_Path_Shortcodes::init();

$t->ok( shortcode_exists( 'french_path_enrol' ), 'the enrol shortcode is registered' );
$t->same( '', do_shortcode( '[french_path_enrol course="0"]' ), 'a card outside a course renders nothing' );

if ( post_type_exists( 'sfwd-courses' ) ) {
	wp_set_current_user( $buyer );

	// State 2: paid, not released.
	$locked_out = do_shortcode( '[french_path_enrol course="' . $solo . '" reg_was="₦15,000" reg_now="₦0"]' );

	$t->ok( false !== strpos( $locked_out, 'Waiting on your coordinator' ), 'a paid but closed sublevel says what it is waiting on' );
	$t->ok( false !== strpos( $locked_out, 'Step 1 of 2' ), 'the card shows the rung and the length' );
	$t->ok( false === strpos( $locked_out, 'french-path-btn-go' ), 'a locked sublevel offers no way in' );
	$t->ok( false !== strpos( $locked_out, 'coordinator releases it' ), 'the card says why it is closed' );
	$t->ok( false === strpos( $locked_out, 'Registration promotion' ), 'no promotion is shown to someone who paid' );
	$t->ok( false === strpos( $locked_out, '15,000' ), 'no price reaches a learner who already paid' );
	$t->ok( false === strpos( $locked_out, 'french-path-dialog' ), 'no buying options are offered' );

	// State 3: released.
	French_Path_Access::unlock( $buyer, $solo );
	$open_out = do_shortcode( '[french_path_enrol course="' . $solo . '"]' );

	$t->ok( false !== strpos( $open_out, 'Start learning' ), 'a released sublevel offers a way in' );
	$t->ok( false !== strpos( $open_out, 'Continue where you left off' ), 'the note changes once it is open' );
	$t->ok( false === strpos( $open_out, 'french-path-gate' ), 'and no longer shows the locked panel' );
	$t->ok( false === strpos( $open_out, '15,000' ), 'still no price for a learner who paid' );

	// State 1: never bought.
	$stranger = $t->user();
	wp_set_current_user( $stranger );

	$buy_out = do_shortcode( '[french_path_enrol course="' . $solo . '" reg_was="₦15,000" reg_now="₦0" placement_url="/placement/"]' );

	$t->ok( false !== strpos( $buy_out, 'Registration promotion' ), 'a new visitor sees the promotion' );
	$t->ok( false !== strpos( $buy_out, '15,000' ), 'a new visitor sees the registration figure' );
	$t->ok( false !== strpos( $buy_out, '/placement/' ), 'the placement link is rendered when given' );
	$t->ok( false === strpos( $buy_out, 'Step 1 of' ), 'no ladder is shown to someone who has not bought' );

	$no_placement = do_shortcode( '[french_path_enrol course="' . $solo . '"]' );
	$t->ok( false === strpos( $no_placement, 'placement test' ), 'the placement button is hidden when no url is given' );

	wp_set_current_user( 0 );
	$out_logged_out = do_shortcode( '[french_path_enrol course="' . $solo . '"]' );
	$t->ok( false === strpos( $out_logged_out, 'Step 1 of' ), 'a logged out visitor gets the buying state' );
}

wp_set_current_user( 0 );
