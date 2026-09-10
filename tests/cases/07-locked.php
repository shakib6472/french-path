<?php
/**
 * A learner bounced off a locked sublevel is told why, and only when it is
 * actually true of them.
 *
 * @package French_Path
 *
 * @var French_Path_Tests $t
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$t->group( 'Locked reason' );

French_Path_Locked::init();

$learner = $t->user();
$open    = $t->course( 'lock open' );
$locked  = $t->course( 'lock locked' );
$unpaid  = $t->course( 'lock unpaid' );
$band    = $t->package( 'lock band', array( $open, $locked ) );

foreach ( array( $open, $locked ) as $position => $course_id ) {
	French_Path_Entitlement::grant(
		array(
			'user_id'    => $learner,
			'course_id'  => $course_id,
			'package_id' => $band,
			'order_id'   => 9001,
			'position'   => $position,
		)
	);
}

French_Path_Access::unlock( $learner, $open );

wp_set_current_user( $learner );

$t->ok( French_Path_Locked::is_locked_for( $learner, $locked ), 'a paid but unreleased sublevel reads as locked' );
$t->ok( ! French_Path_Locked::is_locked_for( $learner, $open ), 'a released sublevel does not read as locked' );
$t->ok( ! French_Path_Locked::is_locked_for( $learner, $unpaid ), 'a sublevel nobody paid for does not read as locked' );

$t->group( 'Redirect marking' );

$destination = 'http://example.invalid/courses/whatever/';

// LearnDash passes the STEP id. Without a resolvable step the link is left alone.
$t->same(
	$destination,
	French_Path_Locked::filter_redirect( $destination, 0 ),
	'an unresolvable step leaves the redirect untouched'
);

$t->same( '', French_Path_Locked::filter_redirect( '', 123 ), 'an empty link stays empty, so the redirect is never cancelled' );

wp_set_current_user( 0 );

$t->same(
	$destination,
	French_Path_Locked::filter_redirect( $destination, 0 ),
	'a logged out visitor is never marked'
);

$t->group( 'Step to course mapping' );

// The mapping must not read $_GET, because shared course steps make
// learndash_get_course_id() trust it.
$_GET['course_id'] = (string) $open;

$t->same( 0, French_Path_Locked::course_for_step( 0 ), 'an empty step maps to no course' );
$t->same( 0, French_Path_Locked::course_for_step( 999999 ), 'a step that belongs to no course maps to no course' );

unset( $_GET['course_id'] );

$t->group( 'Reason is re-checked, never trusted' );

wp_set_current_user( $learner );

// The query argument alone must never produce a message.
$_GET[ French_Path_Locked::QUERY_ARG ] = French_Path_Locked::REASON_LOCKED;

$t->same(
	'',
	French_Path_Locked::current_reason(),
	'the query argument alone says nothing when the request is not a course'
);

unset( $_GET[ French_Path_Locked::QUERY_ARG ] );

$t->same( '', French_Path_Locked::current_reason(), 'no query argument, no message' );
$t->same( 0, French_Path_Locked::current_course(), 'a request that is not a course resolves to no course' );

wp_set_current_user( 0 );
