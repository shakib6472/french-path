<?php
/**
 * Entitlement is recorded, is idempotent, and is not the same thing as access.
 *
 * @package French_Path
 *
 * @var French_Path_Tests $t
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$t->group( 'Entitlement' );

$learner = $t->user();
$a11     = $t->course( 'ent A1.1' );
$a12     = $t->course( 'ent A1.2' );

$t->ok( $learner > 0, 'a learner fixture was created' );

$row = French_Path_Entitlement::grant(
	array(
		'user_id'   => $learner,
		'course_id' => $a11,
		'order_id'  => 4242,
		'position'  => 0,
	)
);

$t->ok( $row > 0, 'granting writes a row' );
$t->ok( French_Path_Entitlement::is_entitled( $learner, $a11 ), 'the learner is entitled to the course' );
$t->ok( ! French_Path_Entitlement::is_unlocked( $learner, $a11 ), 'entitlement alone does not open the course' );

// Most gateways fire both processing and completed for the same order.
$again = French_Path_Entitlement::grant(
	array(
		'user_id'   => $learner,
		'course_id' => $a11,
		'order_id'  => 4242,
		'position'  => 0,
	)
);

$t->same( $row, $again, 'granting the same order twice updates one row rather than adding a second' );
$t->same( 1, count( French_Path_Entitlement::get_for_user( $learner ) ), 'the learner still has exactly one row' );

$t->same( array( $a11 ), French_Path_Entitlement::get_course_ids( $learner ), 'the paid course list is returned' );
$t->ok( ! French_Path_Entitlement::has_guarantee( $learner ), 'no guarantee without a guarantee bearing package' );

$t->group( 'Unlocking' );

$t->ok( ! French_Path_Access::unlock( $learner, $a12 ), 'a course with no entitlement cannot be opened' );

$t->ok( French_Path_Access::unlock( $learner, $a11 ), 'an entitled course can be opened' );
$t->ok( French_Path_Entitlement::is_unlocked( $learner, $a11 ), 'the course reads as open' );

if ( French_Path::has_learndash() ) {
	$t->ok( sfwd_lms_has_access( $a11, $learner ), 'LearnDash agrees the learner has access' );
}

French_Path_Access::lock( $learner, $a11 );
$t->ok( ! French_Path_Entitlement::is_unlocked( $learner, $a11 ), 'locking closes the course again' );
$t->ok( French_Path_Entitlement::is_entitled( $learner, $a11 ), 'locking does not withdraw the entitlement' );

$t->group( 'Entry sublevel' );

$buyer   = $t->user();
$b_a11   = $t->course( 'entry A1.1' );
$b_a12   = $t->course( 'entry A1.2' );
$pathway = $t->package( 'entry pathway', array( $b_a11, $b_a12 ), 0, true );

foreach ( array( $b_a11, $b_a12 ) as $position => $course_id ) {
	French_Path_Entitlement::grant(
		array(
			'user_id'    => $buyer,
			'course_id'  => $course_id,
			'package_id' => $pathway,
			'order_id'   => 5150,
			'position'   => $position,
			'guarantee'  => true,
		)
	);
}

$t->same( 2, count( French_Path_Entitlement::get_for_user( $buyer ) ), 'a two sublevel package writes two entitlements' );
$t->ok( French_Path_Entitlement::has_guarantee( $buyer ), 'the guarantee is stamped on the entitlement' );

$opened = French_Path_Access::unlock_entry( $buyer, $pathway );

$t->same( $b_a11, $opened, 'the package opens on the first rung of the ladder' );
$t->ok( ! French_Path_Entitlement::is_unlocked( $buyer, $b_a12 ), 'the second sublevel stays paid for but closed' );

// Buying a pathway that overlaps something already open starts on the next rung.
$next = French_Path_Access::unlock_entry( $buyer, $pathway );
$t->same( $b_a12, $next, 'the entry rule skips a sublevel that is already open' );

$t->group( 'Refund' );

$refunded = French_Path_Entitlement::revoke_order( 5150 );

$t->same( 2, count( $refunded ), 'revoking an order reports every course it covered' );
$t->ok( ! French_Path_Entitlement::is_entitled( $buyer, $b_a11 ), 'the entitlement is withdrawn' );
$t->ok( ! French_Path_Entitlement::is_unlocked( $buyer, $b_a11 ), 'a withdrawn entitlement reads as closed' );
$t->ok( ! French_Path_Entitlement::has_guarantee( $buyer ), 'the guarantee goes with the refund' );

$closed = French_Path_Access::revoke_orphaned( $buyer, $refunded );
$t->same( 2, count( $closed ), 'LearnDash access is removed for every orphaned course' );

// A course still covered by another live order must survive the refund.
$holder = $t->user();
$shared = $t->course( 'shared A1.1' );

French_Path_Entitlement::grant(
	array(
		'user_id'   => $holder,
		'course_id' => $shared,
		'order_id'  => 6001,
	)
);
French_Path_Entitlement::grant(
	array(
		'user_id'   => $holder,
		'course_id' => $shared,
		'order_id'  => 6002,
	)
);

French_Path_Entitlement::revoke_order( 6001 );

$t->ok( French_Path_Entitlement::is_entitled( $holder, $shared ), 'a second live order keeps the course entitled' );
$t->same( array(), French_Path_Access::revoke_orphaned( $holder, array( $shared ) ), 'a still entitled course is not closed' );

// Re-paying a refunded order starts it locked again.
French_Path_Entitlement::grant(
	array(
		'user_id'   => $buyer,
		'course_id' => $b_a11,
		'order_id'  => 5150,
	)
);

$t->ok( French_Path_Entitlement::is_entitled( $buyer, $b_a11 ), 're-paying restores the entitlement' );
$t->ok( ! French_Path_Entitlement::is_unlocked( $buyer, $b_a11 ), 're-paying does not silently restore what was open' );
