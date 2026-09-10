<?php
/**
 * What a learner's journey looks like from one course.
 *
 * Answers the question the enrol card asks: for this learner, on this course,
 * which ladder are they climbing, how far along are they, and what comes next.
 *
 * @package French_Path
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves a learner's pathway around a course.
 */
class French_Path_Pathway {

	/**
	 * The learner's position on the ladder that contains a course.
	 *
	 * @param int $user_id   Learner.
	 * @param int $course_id LearnDash course being viewed.
	 * @return array|null null when the learner has not paid for the course.
	 */
	public static function for_course( $user_id, $course_id ) {
		$user_id   = absint( $user_id );
		$course_id = absint( $course_id );

		if ( ! $user_id || ! $course_id ) {
			return null;
		}

		if ( ! French_Path_Entitlement::is_entitled( $user_id, $course_id ) ) {
			return null;
		}

		$package_id = self::best_package( $user_id, $course_id );
		$ladder     = $package_id ? French_Path_Package::get_courses( $package_id ) : array( $course_id );

		if ( ! in_array( $course_id, $ladder, true ) ) {
			$ladder = array( $course_id );
		}

		$index = (int) array_search( $course_id, $ladder, true );
		$next  = isset( $ladder[ $index + 1 ] ) ? (int) $ladder[ $index + 1 ] : 0;

		return array(
			'package_id' => $package_id,
			'ladder'     => $ladder,
			'step'       => $index + 1,
			'total'      => count( $ladder ),
			'next_id'    => $next,
			'window'     => self::window( $user_id, $ladder, $index ),
			'unlocked'   => French_Path_Entitlement::is_unlocked( $user_id, $course_id ),
			'guarantee'  => $package_id ? French_Path_Package::has_guarantee( $package_id ) : false,
		);
	}

	/**
	 * The rung before, the rung they are on, and the rung after.
	 *
	 * A twelve sublevel ladder will not fit on a card, and a learner mostly
	 * wants to know where they are and what is coming. The window slides and
	 * clamps at both ends, so it holds three rungs wherever they stand,
	 * unless the ladder itself is shorter than that.
	 *
	 * @param int   $user_id Learner.
	 * @param int[] $ladder  Every course in the package, in order.
	 * @param int   $index   Zero based position of the current course.
	 * @param int   $size    How many rungs to show.
	 * @return array One entry per rung: id, step, current, unlocked.
	 */
	public static function window( $user_id, $ladder, $index, $size = 3 ) {
		$ladder = array_values( (array) $ladder );
		$count  = count( $ladder );

		if ( ! $count ) {
			return array();
		}

		$size  = max( 1, (int) $size );
		$start = max( 0, min( (int) $index - (int) floor( ( $size - 1 ) / 2 ), $count - $size ) );

		$rungs = array();

		foreach ( array_slice( $ladder, $start, $size, true ) as $at => $course_id ) {
			$course_id = (int) $course_id;

			$rungs[] = array(
				'id'       => $course_id,
				'step'     => $at + 1,
				'current'  => ( (int) $at === (int) $index ),
				'unlocked' => French_Path_Entitlement::is_unlocked( $user_id, $course_id ),
			);
		}

		return $rungs;
	}

	/**
	 * Which of a learner's packages best describes their journey here.
	 *
	 * The longest ladder wins: someone who bought A1.1 alone and later the
	 * complete pathway is on the pathway, and telling them "step 1 of 12"
	 * is more use than "step 1 of 1". Ties go to the most recent purchase.
	 *
	 * @param int $user_id   Learner.
	 * @param int $course_id LearnDash course.
	 * @return int Package post ID, 0 when none granted this course.
	 */
	public static function best_package( $user_id, $course_id ) {
		$course_id = absint( $course_id );
		$best      = 0;
		$best_size = -1;

		foreach ( French_Path_Entitlement::get_by_package( $user_id ) as $package_id => $rows ) {
			$package_id = (int) $package_id;

			if ( ! $package_id ) {
				continue;
			}

			$covers = false;

			foreach ( $rows as $row ) {
				if ( (int) $row->course_id === $course_id ) {
					$covers = true;
					break;
				}
			}

			if ( ! $covers ) {
				continue;
			}

			$size = count( French_Path_Package::get_courses( $package_id ) );

			// get_by_package() returns packages in grant order, so a later
			// package of equal length replaces an earlier one.
			if ( $size >= $best_size ) {
				$best      = $package_id;
				$best_size = $size;
			}
		}

		return $best;
	}

	/**
	 * Where the resume button should send a learner.
	 *
	 * The first step they have not finished, falling back to the course
	 * itself when LearnDash cannot say.
	 *
	 * @param int $user_id   Learner.
	 * @param int $course_id LearnDash course.
	 * @return string
	 */
	public static function resume_url( $user_id, $course_id ) {
		$course_id = absint( $course_id );
		$course_url = (string) get_permalink( $course_id );

		if ( ! function_exists( 'learndash_user_progress_get_first_incomplete_step' ) ) {
			return $course_url;
		}

		$step_id = (int) learndash_user_progress_get_first_incomplete_step( absint( $user_id ), $course_id );

		if ( ! $step_id ) {
			return $course_url;
		}

		$step_url = get_permalink( $step_id );

		return $step_url ? (string) $step_url : $course_url;
	}

	/**
	 * Every way of buying a course, cheapest first.
	 *
	 * @param int $course_id LearnDash course.
	 * @return array One entry per package, each with id, name, kind, price,
	 *               price_html, courses, guarantee and url.
	 */
	public static function buying_options( $course_id ) {
		$options = array();

		foreach ( French_Path_Package::find_shown_on( $course_id ) as $package_id ) {
			$product_id = French_Path_Package::get_product( $package_id );

			if ( ! $product_id ) {
				continue;
			}

			$price = French_Path_Package::get_price( $package_id );

			if ( null === $price ) {
				continue;
			}

			$options[] = array(
				'id'         => $package_id,
				'name'       => get_the_title( $package_id ),
				'kind'       => French_Path_Package::get_kind( $package_id ),
				'price'      => $price,
				'price_html' => French_Path_Package::get_price_html( $package_id ),
				'courses'    => count( French_Path_Package::get_courses( $package_id ) ),
				'guarantee'  => French_Path_Package::has_guarantee( $package_id ),
				'url'        => French_Path_Package::get_add_to_cart_url( $package_id ),
			);
		}

		usort(
			$options,
			static function ( $a, $b ) {
				return $a['price'] <=> $b['price'];
			}
		);

		/**
		 * Filters the buying options offered for a course.
		 *
		 * @param array $options   One entry per package, cheapest first.
		 * @param int   $course_id LearnDash course.
		 */
		return apply_filters( 'french_path_buying_options', $options, (int) $course_id );
	}

	/**
	 * The lowest price a course can be bought at.
	 *
	 * @param int $course_id LearnDash course.
	 * @return string Formatted price, empty when nothing sells it.
	 */
	public static function from_price_html( $course_id ) {
		$options = self::buying_options( $course_id );

		return $options ? (string) $options[0]['price_html'] : '';
	}
}
