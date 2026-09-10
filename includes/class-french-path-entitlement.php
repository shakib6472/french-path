<?php
/**
 * The entitlement record.
 *
 * Entitlement and access are deliberately two different facts. Entitlement
 * means the learner has paid for a sublevel. Unlocked means the learner may
 * enter it today. Buying a twelve sublevel pathway writes twelve
 * entitlements and unlocks one. See PLUGIN-SPEC.md section 6.
 *
 * @package French_Path
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and writes the entitlements table.
 */
class French_Path_Entitlement {

	/**
	 * Paid for and live.
	 */
	const STATUS_ENTITLED = 'entitled';

	/**
	 * Withdrawn after a refund or cancellation.
	 */
	const STATUS_REVOKED = 'revoked';

	/**
	 * Records that a learner has paid for a course.
	 *
	 * Idempotent. The unique key on (user_id, course_id, order_id) means a
	 * repeated order transition updates the existing row instead of adding a
	 * second one, and a row revoked by a refund is restored if the same order
	 * is later paid again.
	 *
	 * @param array $args user_id, course_id, package_id, order_id, position, guarantee.
	 * @return int Row ID, or 0 on failure.
	 */
	public static function grant( $args ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'user_id'    => 0,
				'course_id'  => 0,
				'package_id' => 0,
				'order_id'   => 0,
				'position'   => 0,
				'guarantee'  => false,
			)
		);

		$user_id   = absint( $args['user_id'] );
		$course_id = absint( $args['course_id'] );

		if ( ! $user_id || ! $course_id ) {
			return 0;
		}

		$table    = French_Path_Install::table();
		$order_id = absint( $args['order_id'] );

		$existing = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d AND course_id = %d AND order_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id,
				$course_id,
				$order_id
			)
		);

		$data = array(
			'user_id'    => $user_id,
			'course_id'  => $course_id,
			'package_id' => absint( $args['package_id'] ),
			'order_id'   => $order_id,
			'position'   => absint( $args['position'] ),
			'status'     => self::STATUS_ENTITLED,
			'guarantee'  => $args['guarantee'] ? 1 : 0,
		);

		if ( $existing ) {
			// A row coming back from a refund starts locked again, so the
			// order re-opens an entry sublevel rather than silently restoring
			// whatever was open when the money went back.
			if ( self::STATUS_REVOKED === (string) $existing->status ) {
				$data['unlocked']    = 0;
				$data['unlocked_at'] = null;
				$data['unlocked_by'] = 0;
			}

			$wpdb->update( $table, $data, array( 'id' => (int) $existing->id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

			$row_id = (int) $existing->id;
		} else {
			$data['granted_at'] = current_time( 'mysql' );

			$wpdb->insert( $table, $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

			$row_id = (int) $wpdb->insert_id;
		}

		if ( ! $row_id ) {
			return 0;
		}

		/**
		 * Fires after an entitlement is recorded.
		 *
		 * @param int   $user_id   Learner.
		 * @param int   $course_id LearnDash course.
		 * @param array $data      The row as written.
		 */
		do_action( 'french_path_entitlement_granted', $user_id, $course_id, $data );

		return $row_id;
	}

	/**
	 * Withdraws every entitlement written by one order.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return int[] Course IDs whose rows were revoked.
	 */
	public static function revoke_order( $order_id ) {
		global $wpdb;

		$order_id = absint( $order_id );

		if ( ! $order_id ) {
			return array();
		}

		$table = French_Path_Install::table();

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->prepare(
				"SELECT id, user_id, course_id FROM {$table} WHERE order_id = %d AND status = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$order_id,
				self::STATUS_ENTITLED
			)
		);

		if ( ! $rows ) {
			return array();
		}

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array( 'status' => self::STATUS_REVOKED ),
			array(
				'order_id' => $order_id,
				'status'   => self::STATUS_ENTITLED,
			)
		);

		$courses = array();

		foreach ( $rows as $row ) {
			$courses[] = (int) $row->course_id;

			/**
			 * Fires after an entitlement is withdrawn.
			 *
			 * @param int $user_id   Learner.
			 * @param int $course_id LearnDash course.
			 * @param int $order_id  Order that granted it.
			 */
			do_action( 'french_path_entitlement_revoked', (int) $row->user_id, (int) $row->course_id, $order_id );
		}

		return array_values( array_unique( $courses ) );
	}

	/**
	 * Live entitlement rows for a learner.
	 *
	 * @param int $user_id Learner.
	 * @return array Row objects, ordered by package then ladder position.
	 */
	public static function get_for_user( $user_id ) {
		global $wpdb;

		$user_id = absint( $user_id );

		if ( ! $user_id ) {
			return array();
		}

		$table = French_Path_Install::table();

		return (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d AND status = %s ORDER BY package_id ASC, position ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id,
				self::STATUS_ENTITLED
			)
		);
	}

	/**
	 * Whether the learner has paid for a course.
	 *
	 * @param int $user_id   Learner.
	 * @param int $course_id LearnDash course.
	 * @return bool
	 */
	public static function is_entitled( $user_id, $course_id ) {
		global $wpdb;

		$user_id   = absint( $user_id );
		$course_id = absint( $course_id );

		if ( ! $user_id || ! $course_id ) {
			return false;
		}

		$table = French_Path_Install::table();

		return (bool) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE user_id = %d AND course_id = %d AND status = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id,
				$course_id,
				self::STATUS_ENTITLED
			)
		);
	}

	/**
	 * Whether the learner may enter a course today.
	 *
	 * @param int $user_id   Learner.
	 * @param int $course_id LearnDash course.
	 * @return bool
	 */
	public static function is_unlocked( $user_id, $course_id ) {
		global $wpdb;

		$user_id   = absint( $user_id );
		$course_id = absint( $course_id );

		if ( ! $user_id || ! $course_id ) {
			return false;
		}

		$table = French_Path_Install::table();

		return (bool) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE user_id = %d AND course_id = %d AND status = %s AND unlocked = 1 LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id,
				$course_id,
				self::STATUS_ENTITLED
			)
		);
	}

	/**
	 * Flips the unlocked flag on every row for one learner and course.
	 *
	 * A course can be granted by more than one package, so the flag is kept
	 * consistent across all of them rather than on whichever row happened to
	 * be found first.
	 *
	 * @param int  $user_id    Learner.
	 * @param int  $course_id  LearnDash course.
	 * @param bool $unlocked   Target state.
	 * @param int  $actor_id   User who made the change.
	 * @return bool Whether any row changed.
	 */
	public static function set_unlocked( $user_id, $course_id, $unlocked, $actor_id = 0 ) {
		global $wpdb;

		$user_id   = absint( $user_id );
		$course_id = absint( $course_id );

		if ( ! $user_id || ! $course_id ) {
			return false;
		}

		$table = French_Path_Install::table();

		$data = array(
			'unlocked'    => $unlocked ? 1 : 0,
			'unlocked_at' => $unlocked ? current_time( 'mysql' ) : null,
			'unlocked_by' => $unlocked ? absint( $actor_id ) : 0,
		);

		$updated = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			$data,
			array(
				'user_id'   => $user_id,
				'course_id' => $course_id,
				'status'    => self::STATUS_ENTITLED,
			)
		);

		return (bool) $updated;
	}

	/**
	 * Course IDs the learner has paid for.
	 *
	 * @param int $user_id Learner.
	 * @return int[]
	 */
	public static function get_course_ids( $user_id ) {
		$courses = array();

		foreach ( self::get_for_user( $user_id ) as $row ) {
			$course_id = (int) $row->course_id;

			if ( ! in_array( $course_id, $courses, true ) ) {
				$courses[] = $course_id;
			}
		}

		return $courses;
	}

	/**
	 * Whether the learner holds a live entitlement from a guarantee bearing
	 * package.
	 *
	 * The examination fee guarantee requires the complete pathway to have
	 * been bought and paid. This is the record that proves it later.
	 *
	 * @param int $user_id Learner.
	 * @return bool
	 */
	public static function has_guarantee( $user_id ) {
		global $wpdb;

		$user_id = absint( $user_id );

		if ( ! $user_id ) {
			return false;
		}

		$table = French_Path_Install::table();

		return (bool) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE user_id = %d AND status = %s AND guarantee = 1 LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id,
				self::STATUS_ENTITLED
			)
		);
	}

	/**
	 * Learners holding at least one live entitlement.
	 *
	 * Ordered by most recently granted, so the people who just bought
	 * something are the first thing a coordinator sees.
	 *
	 * @param array $args search, limit, offset.
	 * @return int[] User IDs.
	 */
	public static function get_learner_ids( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'search' => '',
				'limit'  => 25,
				'offset' => 0,
			)
		);

		$table  = French_Path_Install::table();
		$users  = $wpdb->users;
		$search = trim( (string) $args['search'] );

		if ( '' !== $search ) {
			$like = '%' . $wpdb->esc_like( $search ) . '%';

			$sql = $wpdb->prepare(
				"SELECT e.user_id FROM {$table} e INNER JOIN {$users} u ON u.ID = e.user_id
				WHERE e.status = %s AND ( u.user_login LIKE %s OR u.user_email LIKE %s OR u.display_name LIKE %s )
				GROUP BY e.user_id ORDER BY MAX(e.granted_at) DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				self::STATUS_ENTITLED,
				$like,
				$like,
				$like,
				absint( $args['limit'] ),
				absint( $args['offset'] )
			);
		} else {
			$sql = $wpdb->prepare(
				"SELECT user_id FROM {$table} WHERE status = %s
				GROUP BY user_id ORDER BY MAX(granted_at) DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				self::STATUS_ENTITLED,
				absint( $args['limit'] ),
				absint( $args['offset'] )
			);
		}

		return array_map( 'absint', (array) $wpdb->get_col( $sql ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * How many learners hold at least one live entitlement.
	 *
	 * @return int
	 */
	public static function count_learners() {
		global $wpdb;

		$table = French_Path_Install::table();

		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT user_id) FROM {$table} WHERE status = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				self::STATUS_ENTITLED
			)
		);
	}

	/**
	 * Live entitlement rows for one learner grouped by the package that
	 * granted them, each package's ladder in order.
	 *
	 * @param int $user_id Learner.
	 * @return array Package ID => array of rows.
	 */
	public static function get_by_package( $user_id ) {
		$grouped = array();

		foreach ( self::get_for_user( $user_id ) as $row ) {
			$grouped[ (int) $row->package_id ][] = $row;
		}

		// The package post is the authority on ladder order, not the stored
		// position: an admin who reorders a package after people bought it
		// would otherwise leave every existing learner reading the old order.
		foreach ( $grouped as $package_id => $rows ) {
			if ( ! $package_id ) {
				continue;
			}

			$ladder = French_Path_Package::get_courses( $package_id );

			if ( ! $ladder ) {
				continue;
			}

			usort(
				$rows,
				static function ( $a, $b ) use ( $ladder ) {
					$a_at = array_search( (int) $a->course_id, $ladder, true );
					$b_at = array_search( (int) $b->course_id, $ladder, true );

					// A course no longer in the package sinks to the bottom.
					$a_at = ( false === $a_at ) ? PHP_INT_MAX : $a_at;
					$b_at = ( false === $b_at ) ? PHP_INT_MAX : $b_at;

					return $a_at <=> $b_at;
				}
			);

			$grouped[ $package_id ] = $rows;
		}

		return $grouped;
	}

	/**
	 * Rewrites the stored position of every row a package granted.
	 *
	 * Called when a package is saved. Display never depends on this - it
	 * reads the package itself - but the column is reported in the admin and
	 * should not drift away from the truth.
	 *
	 * @param int $package_id Package post ID.
	 * @return int Rows updated.
	 */
	public static function sync_positions( $package_id ) {
		global $wpdb;

		$package_id = absint( $package_id );

		if ( ! $package_id ) {
			return 0;
		}

		$ladder = French_Path_Package::get_courses( $package_id );

		if ( ! $ladder ) {
			return 0;
		}

		$table   = French_Path_Install::table();
		$updated = 0;

		foreach ( $ladder as $position => $course_id ) {
			$updated += (int) $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$table,
				array( 'position' => (int) $position ),
				array(
					'package_id' => $package_id,
					'course_id'  => (int) $course_id,
				)
			);
		}

		return $updated;
	}

	/**
	 * Deletes every row for one learner.
	 *
	 * Only used when a user is deleted, and by the test suite.
	 *
	 * @param int $user_id Learner.
	 * @return int Rows removed.
	 */
	public static function delete_for_user( $user_id ) {
		global $wpdb;

		$user_id = absint( $user_id );

		if ( ! $user_id ) {
			return 0;
		}

		return (int) $wpdb->delete( French_Path_Install::table(), array( 'user_id' => $user_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
}
