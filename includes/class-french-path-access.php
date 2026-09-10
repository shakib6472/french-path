<?php
/**
 * The bridge between an entitlement and real LearnDash access.
 *
 * The plugin runs its own enrolment rather than letting the LearnDash
 * WooCommerce integration do it, because that integration enrols a buyer in
 * every course attached to the product at once, which is the opposite of a
 * progressive pathway. A locked sublevel is therefore not enrolled at all:
 * LearnDash denies it natively, and nothing is left open if this plugin
 * stops running. See PLUGIN-SPEC.md section 7.
 *
 * @package French_Path
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Opens and closes courses for a learner.
 */
class French_Path_Access {

	/**
	 * Opens a course the learner is entitled to.
	 *
	 * Refuses to open a course with no live entitlement, so a coordinator
	 * cannot accidentally hand out a sublevel nobody paid for.
	 *
	 * @param int $user_id   Learner.
	 * @param int $course_id LearnDash course.
	 * @param int $actor_id  User performing the release.
	 * @return bool Whether the course is now open.
	 */
	public static function unlock( $user_id, $course_id, $actor_id = 0 ) {
		$user_id   = absint( $user_id );
		$course_id = absint( $course_id );

		if ( ! $user_id || ! $course_id ) {
			return false;
		}

		if ( ! French_Path_Entitlement::is_entitled( $user_id, $course_id ) ) {
			return false;
		}

		if ( ! $actor_id ) {
			$actor_id = get_current_user_id();
		}

		French_Path_Entitlement::set_unlocked( $user_id, $course_id, true, $actor_id );
		self::enroll( $user_id, $course_id );

		/**
		 * Fires when a sublevel is opened for a learner.
		 *
		 * @param int $user_id   Learner.
		 * @param int $course_id LearnDash course.
		 * @param int $actor_id  User who opened it, 0 when the system did.
		 */
		do_action( 'french_path_course_unlocked', $user_id, $course_id, $actor_id );

		return true;
	}

	/**
	 * Closes a course again.
	 *
	 * Progression never calls this: a completed sublevel stays open for the
	 * rest of the pathway. It exists for refunds and for correcting a release
	 * made in error.
	 *
	 * @param int $user_id   Learner.
	 * @param int $course_id LearnDash course.
	 * @param int $actor_id  User performing the change.
	 * @return bool
	 */
	public static function lock( $user_id, $course_id, $actor_id = 0 ) {
		$user_id   = absint( $user_id );
		$course_id = absint( $course_id );

		if ( ! $user_id || ! $course_id ) {
			return false;
		}

		if ( ! $actor_id ) {
			$actor_id = get_current_user_id();
		}

		French_Path_Entitlement::set_unlocked( $user_id, $course_id, false, $actor_id );
		self::unenroll( $user_id, $course_id );

		/**
		 * Fires when a sublevel is closed for a learner.
		 *
		 * @param int $user_id   Learner.
		 * @param int $course_id LearnDash course.
		 * @param int $actor_id  User who closed it.
		 */
		do_action( 'french_path_course_locked', $user_id, $course_id, $actor_id );

		return true;
	}

	/**
	 * Opens the sublevel a learner starts a package on.
	 *
	 * The entry sublevel is the first course in the package ladder that is
	 * not already open. A learner who already owns A1.1 and then buys the
	 * complete pathway therefore starts on A1.2 rather than being handed a
	 * sublevel they are already sitting in. Milestone 3 will override this
	 * with the stream chosen at checkout.
	 *
	 * @param int $user_id    Learner.
	 * @param int $package_id Package post ID.
	 * @return int Course opened, 0 when there was nothing left to open.
	 */
	public static function unlock_entry( $user_id, $package_id ) {
		$user_id = absint( $user_id );
		$courses = French_Path_Package::get_courses( $package_id );

		if ( ! $user_id || ! $courses ) {
			return 0;
		}

		/**
		 * Filters the sublevel a package opens on.
		 *
		 * Milestone 3 hooks this to honour the stream picked at checkout.
		 *
		 * @param int   $entry_course_id Chosen course, 0 to use the default rule.
		 * @param int   $user_id         Learner.
		 * @param int   $package_id      Package post ID.
		 * @param int[] $courses         The package ladder, in order.
		 */
		$entry = absint( apply_filters( 'french_path_entry_course', 0, $user_id, $package_id, $courses ) );

		if ( $entry && in_array( $entry, $courses, true ) ) {
			return self::unlock( $user_id, $entry ) ? $entry : 0;
		}

		foreach ( $courses as $course_id ) {
			if ( ! French_Path_Entitlement::is_unlocked( $user_id, $course_id ) ) {
				return self::unlock( $user_id, $course_id ) ? $course_id : 0;
			}
		}

		return 0;
	}

	/**
	 * Removes LearnDash access for courses the learner no longer holds.
	 *
	 * Called after a refund. A course is only closed when no other live
	 * entitlement still covers it, so refunding a single sublevel does not
	 * take away a sublevel the learner also bought inside a pathway.
	 *
	 * @param int   $user_id    Learner.
	 * @param int[] $course_ids Courses to reconsider.
	 * @return int[] Courses actually closed.
	 */
	public static function revoke_orphaned( $user_id, $course_ids ) {
		$user_id = absint( $user_id );
		$closed  = array();

		if ( ! $user_id ) {
			return $closed;
		}

		foreach ( (array) $course_ids as $course_id ) {
			$course_id = absint( $course_id );

			if ( ! $course_id || French_Path_Entitlement::is_entitled( $user_id, $course_id ) ) {
				continue;
			}

			self::unenroll( $user_id, $course_id );
			$closed[] = $course_id;
		}

		return $closed;
	}

	/**
	 * Gives the learner real LearnDash access to a course.
	 *
	 * @param int $user_id   Learner.
	 * @param int $course_id LearnDash course.
	 * @return bool
	 */
	public static function enroll( $user_id, $course_id ) {
		if ( ! French_Path::has_learndash() ) {
			return false;
		}

		return (bool) ld_update_course_access( absint( $user_id ), absint( $course_id ) );
	}

	/**
	 * Takes LearnDash access away again.
	 *
	 * @param int $user_id   Learner.
	 * @param int $course_id LearnDash course.
	 * @return bool
	 */
	public static function unenroll( $user_id, $course_id ) {
		if ( ! French_Path::has_learndash() ) {
			return false;
		}

		return (bool) ld_update_course_access( absint( $user_id ), absint( $course_id ), true );
	}
}
