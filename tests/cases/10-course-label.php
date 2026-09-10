<?php
/**
 * The enrol button uses the course's short name, and still says something
 * when that field is empty.
 *
 * @package French_Path
 *
 * @var French_Path_Tests $t
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$t->group( 'Course label' );

$course = $t->course( 'label course' );
$title  = get_the_title( $course );

$t->same( $title, French_Path_Shortcodes::course_label( $course ), 'with no short name the full title is used' );

update_post_meta( $course, 'course_short_name', 'A1.1' );
$t->same( 'A1.1', French_Path_Shortcodes::course_label( $course ), 'the short name wins when it is set' );

update_post_meta( $course, 'course_short_name', '   B2.4   ' );
$t->same( 'B2.4', French_Path_Shortcodes::course_label( $course ), 'stray whitespace around the short name is trimmed' );

update_post_meta( $course, 'course_short_name', '   ' );
$t->same( $title, French_Path_Shortcodes::course_label( $course ), 'a short name of only spaces falls back to the title' );

update_post_meta( $course, 'course_short_name', '' );
$t->same( $title, French_Path_Shortcodes::course_label( $course ), 'an emptied short name falls back to the title' );

$t->same( '', French_Path_Shortcodes::course_label( 0 ), 'no course, no label' );

add_filter(
	'french_path_course_label',
	static function () {
		return 'Filtered label';
	}
);

$t->same( 'Filtered label', French_Path_Shortcodes::course_label( $course ), 'the label is filterable' );
remove_all_filters( 'french_path_course_label' );

$t->group( 'Enrol button' );

if ( post_type_exists( 'sfwd-courses' ) && post_type_exists( 'product' ) ) {
	$product = $t->product( 'label product' );
	update_post_meta( $product, '_price', '175000' );
	update_post_meta( $product, '_regular_price', '175000' );

	$t->package( 'label package', array( $course ), $product );
	update_post_meta( $course, 'course_short_name', 'A1.1' );

	wp_set_current_user( 0 );
	$out = do_shortcode( '[french_path_enrol course="' . $course . '"]' );

	$t->ok( false !== strpos( $out, 'Enrol in A1.1' ), 'the button uses the short name' );
	$t->ok( false === strpos( $out, 'Enrol in ' . $title ), 'the button does not use the full course title' );

	// The nine courses on this copy with an empty field must not read "Enrol in".
	update_post_meta( $course, 'course_short_name', '' );
	$fallback = do_shortcode( '[french_path_enrol course="' . $course . '"]' );

	$t->ok( false !== strpos( $fallback, 'Enrol in ' . $title ), 'an empty short name still names the course' );
	$t->ok( false === strpos( $fallback, 'Enrol in <' ), 'the button is never left with a bare label' );
}
