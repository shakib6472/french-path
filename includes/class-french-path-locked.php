<?php
/**
 * What happens when a learner meets a sublevel they have paid for but which
 * is not open yet.
 *
 * A locked sublevel is not enrolled, so LearnDash already refuses entry and
 * redirects a lesson, topic or quiz URL back to its course. All this class
 * adds is the reason: it marks that redirect so the course page can say the
 * sublevel is paid for and waiting on a coordinator, rather than leaving the
 * learner bounced with no explanation.
 *
 * @package French_Path
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Carries a reason through the LearnDash access redirect.
 */
class French_Path_Locked {

	/**
	 * Query argument added to the redirect.
	 */
	const QUERY_ARG = 'french-path';

	/**
	 * Value the query argument carries.
	 */
	const REASON_LOCKED = 'locked';

	/**
	 * Hooks the redirect.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'learndash_access_redirect', array( __CLASS__, 'filter_redirect' ), 10, 2 );
	}

	/**
	 * Marks the redirect when the learner has paid for the step's course.
	 *
	 * @param string $link    Where LearnDash is about to send the learner.
	 * @param int    $post_id The step the learner tried to open.
	 * @return string
	 */
	public static function filter_redirect( $link, $post_id ) {
		// An empty link cancels the redirect, which would expose the step.
		if ( ! $link ) {
			return $link;
		}

		$user_id   = get_current_user_id();
		$course_id = self::course_for_step( $post_id );

		if ( ! $user_id || ! $course_id ) {
			return $link;
		}

		if ( ! self::is_locked_for( $user_id, $course_id ) ) {
			return $link;
		}

		return add_query_arg( self::QUERY_ARG, self::REASON_LOCKED, $link );
	}

	/**
	 * Whether a learner has paid for a course that is not open to them.
	 *
	 * @param int $user_id   Learner.
	 * @param int $course_id LearnDash course.
	 * @return bool
	 */
	public static function is_locked_for( $user_id, $course_id ) {
		return French_Path_Entitlement::is_entitled( $user_id, $course_id )
			&& ! French_Path_Entitlement::is_unlocked( $user_id, $course_id );
	}

	/**
	 * The reason to show on the current request, if there is one.
	 *
	 * The query argument is only a hint. Entitlement is read from the
	 * database every time, so a visitor cannot produce this message by
	 * typing the argument themselves.
	 *
	 * @return string Empty when there is nothing to say.
	 */
	public static function current_reason() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$flagged = isset( $_GET[ self::QUERY_ARG ] ) && self::REASON_LOCKED === sanitize_key( wp_unslash( $_GET[ self::QUERY_ARG ] ) );

		if ( ! $flagged ) {
			return '';
		}

		$user_id   = get_current_user_id();
		$course_id = self::current_course();

		if ( ! $user_id || ! $course_id || ! self::is_locked_for( $user_id, $course_id ) ) {
			return '';
		}

		return French_Path_Shortcodes::locked_reason( $course_id, $user_id );
	}

	/**
	 * The course a step belongs to.
	 *
	 * Uses learndash_get_courses_for_step() rather than
	 * learndash_get_course_id(), because with shared course steps enabled -
	 * as they are on this site - the latter reads $_GET['course_id'] and
	 * would let a visitor choose which course a step appears to belong to.
	 *
	 * @param int $post_id Step post ID.
	 * @return int Course ID, 0 when it cannot be resolved safely.
	 */
	public static function course_for_step( $post_id ) {
		$post_id = absint( $post_id );

		if ( ! $post_id ) {
			return 0;
		}

		if ( function_exists( 'learndash_get_courses_for_step' ) ) {
			$courses = (array) learndash_get_courses_for_step( $post_id, true );
			$ids     = array_map( 'absint', array_keys( $courses ) );
			$ids     = array_filter( $ids );

			// A step shared by several courses is ambiguous from the URL
			// alone, so no reason is claimed for it.
			if ( 1 === count( $ids ) ) {
				return (int) reset( $ids );
			}

			return 0;
		}

		return 0;
	}

	/**
	 * The course being viewed on the current request.
	 *
	 * @return int Course ID, 0 when the request is not a course.
	 */
	public static function current_course() {
		$post_id = get_queried_object_id();

		if ( ! $post_id || 'sfwd-courses' !== get_post_type( $post_id ) ) {
			return 0;
		}

		return (int) $post_id;
	}
}
