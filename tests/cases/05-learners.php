<?php
/**
 * The coordinator release screen and the capability that gates it.
 *
 * @package French_Path
 *
 * @var French_Path_Tests $t
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$t->group( 'Capability' );

French_Path_Roles::grant();

$admin = get_role( 'administrator' );

$t->ok( $admin && $admin->has_cap( French_Path_Roles::CAP_MANAGE_LEARNERS ), 'administrators may manage learners' );

$subscriber = get_role( 'subscriber' );

$t->ok(
	$subscriber && ! $subscriber->has_cap( French_Path_Roles::CAP_MANAGE_LEARNERS ),
	'subscribers may not manage learners'
);

$t->ok(
	'manage_options' !== French_Path_Roles::CAP_MANAGE_LEARNERS && 'edit_users' !== French_Path_Roles::CAP_MANAGE_LEARNERS,
	'the screen is not gated on a capability that would also hand over settings or the user table'
);

$t->group( 'Release screen' );

$learner = $t->user();
$a11     = $t->course( 'rel A1.1' );
$a12     = $t->course( 'rel A1.2' );
$b11     = $t->course( 'rel B1.1' );

$band    = $t->package( 'rel band', array( $a11, $a12 ), 0, false );
$pathway = $t->package( 'rel pathway', array( $a11, $a12, $b11 ), 0, true );

foreach ( array( $a11, $a12 ) as $position => $course_id ) {
	French_Path_Entitlement::grant(
		array(
			'user_id'    => $learner,
			'course_id'  => $course_id,
			'package_id' => $band,
			'order_id'   => 7001,
			'position'   => $position,
		)
	);
}

$counts = French_Path_Learners::summary( $learner );

$t->same( 2, $counts['entitled'], 'the summary counts what was paid for' );
$t->same( 0, $counts['unlocked'], 'nothing is open before a release' );

$grouped = French_Path_Entitlement::get_by_package( $learner );

$t->ok( isset( $grouped[ $band ] ), 'entitlements are grouped by the package that granted them' );
$t->same( 2, count( $grouped[ $band ] ), 'the band contributes two rungs' );
$t->same( $a11, (int) $grouped[ $band ][0]->course_id, 'the ladder is returned in order' );
$t->same( $a12, (int) $grouped[ $band ][1]->course_id, 'the second rung follows the first' );

// The gesture the coordinator actually makes.
$t->same( $a11, French_Path_Access::unlock_entry( $learner, $band ), 'releasing opens the first closed rung' );

$counts = French_Path_Learners::summary( $learner );
$t->same( 1, $counts['unlocked'], 'one sublevel is now open' );

$t->same( $a12, French_Path_Access::unlock_entry( $learner, $band ), 'releasing again opens the next rung' );
$t->same( 0, French_Path_Access::unlock_entry( $learner, $band ), 'a fully open package releases nothing more' );

$t->group( 'Learner list' );

$t->ok( French_Path_Entitlement::count_learners() >= 1, 'the learner appears in the count' );

$ids = French_Path_Entitlement::get_learner_ids( array( 'limit' => 100 ) );
$t->ok( in_array( $learner, $ids, true ), 'the learner appears in the list' );

$user  = get_userdata( $learner );
$found = French_Path_Entitlement::get_learner_ids(
	array(
		'search' => $user->user_email,
		'limit'  => 10,
	)
);

$t->same( array( $learner ), $found, 'searching by email finds exactly that learner' );

$t->same(
	array(),
	French_Path_Entitlement::get_learner_ids( array( 'search' => 'no_such_learner_anywhere' ) ),
	'a search that matches nobody returns nothing'
);

// A learner whose entitlements were all refunded drops off the list.
French_Path_Entitlement::revoke_order( 7001 );
$t->ok(
	! in_array( $learner, French_Path_Entitlement::get_learner_ids( array( 'limit' => 100 ) ), true ),
	'a fully refunded learner leaves the list'
);

$t->group( 'Screen URL' );

$url = French_Path_Learners::url( array( 'learner' => $learner ) );

$t->ok( false !== strpos( $url, 'page=' . French_Path_Learners::SLUG ), 'the screen URL carries the page slug' );
$t->ok( false !== strpos( $url, 'learner=' . $learner ), 'the screen URL carries the learner' );

// A guarantee bearing package must still read as such through the screen.
French_Path_Entitlement::grant(
	array(
		'user_id'    => $learner,
		'course_id'  => $b11,
		'package_id' => $pathway,
		'order_id'   => 7002,
		'position'   => 2,
		'guarantee'  => true,
	)
);

$t->ok( French_Path_Entitlement::has_guarantee( $learner ), 'the screen can tell the guarantee is held' );
