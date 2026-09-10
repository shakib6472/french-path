<?php
/**
 * The learner facing pathway: locked sublevels are visible, marked paid, and
 * are not links.
 *
 * @package French_Path
 *
 * @var French_Path_Tests $t
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$t->group( 'Shortcode registration' );

French_Path_Shortcodes::init();

$t->ok( shortcode_exists( 'french_path_my_courses' ), 'the pathway shortcode is registered' );

$t->group( 'Template loading' );

$t->ok( '' !== French_Path_Templates::locate( 'my-courses' ), 'the pathway template is found' );
$t->same( '', French_Path_Templates::locate( 'no-such-template' ), 'a missing template resolves to nothing' );
$t->same( '', French_Path_Templates::locate( '' ), 'an empty template name resolves to nothing' );

$t->group( 'Logged out' );

wp_set_current_user( 0 );

$out = do_shortcode( '[french_path_my_courses]' );

$t->ok( false !== strpos( $out, 'Sign in' ), 'a logged out visitor is asked to sign in' );
$t->ok( false === strpos( $out, 'french-path-step' ), 'a logged out visitor sees no ladder' );

$t->group( 'Locked but visible' );

$learner = $t->user();
$a11     = $t->course( 'sc A1.1' );
$a12     = $t->course( 'sc A1.2' );
$band    = $t->package( 'sc band', array( $a11, $a12 ) );

wp_set_current_user( $learner );

$empty = do_shortcode( '[french_path_my_courses]' );
$t->ok( false !== strpos( $empty, 'not bought' ), 'a learner with nothing bought is told so' );
$t->same( '', do_shortcode( '[french_path_my_courses show_empty="no"]' ), 'show_empty="no" renders nothing' );

foreach ( array( $a11, $a12 ) as $position => $course_id ) {
	French_Path_Entitlement::grant(
		array(
			'user_id'    => $learner,
			'course_id'  => $course_id,
			'package_id' => $band,
			'order_id'   => 8001,
			'position'   => $position,
		)
	);
}

French_Path_Access::unlock_entry( $learner, $band );

$out = do_shortcode( '[french_path_my_courses]' );

$open_title   = get_the_title( $a11 );
$locked_title = get_the_title( $a12 );

$t->ok( false !== strpos( $out, $open_title ), 'the open sublevel is listed' );
$t->ok( false !== strpos( $out, $locked_title ), 'the locked sublevel is listed too, not hidden' );

$t->same( 1, substr_count( $out, 'french-path-step is-open' ), 'exactly one sublevel reads as open' );
$t->same( 1, substr_count( $out, 'french-path-step is-locked' ), 'exactly one sublevel reads as locked' );

$t->ok(
	false !== strpos( $out, 'It opens once your coordinator releases it' ),
	'the locked sublevel says why it is closed'
);

$t->ok(
	false !== strpos( $out, 'href="' . esc_url( get_permalink( $a11 ) ) . '"' ),
	'the open sublevel links to the course'
);

$t->ok(
	false === strpos( $out, 'href="' . esc_url( get_permalink( $a12 ) ) . '"' ),
	'the locked sublevel is not a link'
);

$t->ok( false !== strpos( $out, get_the_title( $band ) ), 'the package name heads the ladder' );

$t->group( 'Shortcode attributes' );

$titled = do_shortcode( '[french_path_my_courses title="My pathway" class="custom-class"]' );

$t->ok( false !== strpos( $titled, 'My pathway' ), 'the title attribute is rendered' );
$t->ok( false !== strpos( $titled, 'custom-class' ), 'the class attribute reaches the wrapper' );

// Attributes are escaped, not trusted.
$nasty = do_shortcode( '[french_path_my_courses title="<script>alert(1)</script>"]' );
$t->ok( false === strpos( $nasty, '<script>' ), 'the title attribute is escaped' );

$t->group( 'Reason filter' );

add_filter(
	'french_path_locked_reason',
	static function () {
		return 'Replaced reason for the test.';
	}
);

$filtered = do_shortcode( '[french_path_my_courses]' );
$t->ok( false !== strpos( $filtered, 'Replaced reason for the test.' ), 'the reason is filterable' );

remove_all_filters( 'french_path_locked_reason' );

wp_set_current_user( 0 );
