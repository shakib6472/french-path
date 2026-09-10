<?php
/**
 * Test runner for French Path.
 *
 * Not PHPUnit. It boots the real site, makes real posts and rows, asserts
 * against them and puts everything back. Every fixture it creates is named
 * with the french_path_test_ prefix so an interrupted run can be recovered.
 *
 * @package French_Path
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Minimal assertion runner with fixture cleanup.
 */
class French_Path_Tests {

	/**
	 * Prefix every fixture carries.
	 */
	const PREFIX = 'french_path_test_';

	/**
	 * Current group name.
	 *
	 * @var string
	 */
	private $group = '';

	/**
	 * Assertions that passed.
	 *
	 * @var int
	 */
	private $passed = 0;

	/**
	 * Failure lines.
	 *
	 * @var array
	 */
	private $failures = array();

	/**
	 * Post IDs to delete at the end.
	 *
	 * @var array
	 */
	private $posts = array();

	/**
	 * User IDs to delete at the end.
	 *
	 * @var array
	 */
	private $users = array();

	/**
	 * PHP notices captured during the run.
	 *
	 * @var array
	 */
	private $notices = array();

	/**
	 * Fixtures found from a previous run that did not finish.
	 *
	 * @var array
	 */
	private $recovered = array();

	/**
	 * Captures notices and clears fixtures left by an interrupted run.
	 *
	 * @return void
	 */
	public function set_up() {
		set_error_handler( // phpcs:ignore WordPress.PHP.NoSilencedErrors
			function ( $number, $message, $file, $line ) {
				$this->notices[] = sprintf( '%s in %s:%d', $message, $file, $line );

				return true;
			}
		);

		foreach ( get_posts(
			array(
				'post_type'      => French_Path_Package::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				's'              => self::PREFIX,
				'fields'         => 'ids',
			)
		) as $stale ) {
			$this->recovered[] = 'package #' . $stale;
			wp_delete_post( (int) $stale, true );
		}

		foreach ( get_users( array( 'search' => self::PREFIX . '*' ) ) as $stale ) {
			$this->recovered[] = 'user ' . $stale->user_login;
			French_Path_Entitlement::delete_for_user( (int) $stale->ID );
			wp_delete_user( (int) $stale->ID );
		}
	}

	/**
	 * Fixtures cleared at set up.
	 *
	 * @return array
	 */
	public function recovered() {
		return $this->recovered;
	}

	/**
	 * Starts a named group.
	 *
	 * @param string $name Group name.
	 * @return void
	 */
	public function group( $name ) {
		$this->group = (string) $name;

		printf( PHP_EOL . '%s' . PHP_EOL, $this->group );
	}

	/**
	 * Asserts a condition is true.
	 *
	 * @param bool   $condition What was evaluated.
	 * @param string $label     What it means.
	 * @return bool
	 */
	public function ok( $condition, $label ) {
		if ( $condition ) {
			++$this->passed;

			printf( '  ok    %s' . PHP_EOL, $label );

			return true;
		}

		$this->failures[] = sprintf( '%s: %s', $this->group, $label );

		printf( '  FAIL  %s' . PHP_EOL, $label );

		return false;
	}

	/**
	 * Asserts two values match.
	 *
	 * @param mixed  $expected Expected value.
	 * @param mixed  $actual   Actual value.
	 * @param string $label    What it means.
	 * @return bool
	 */
	public function same( $expected, $actual, $label ) {
		$match = ( $expected === $actual );

		if ( ! $match ) {
			$label .= sprintf(
				' (expected %s, got %s)',
				wp_json_encode( $expected ),
				wp_json_encode( $actual )
			);
		}

		return $this->ok( $match, $label );
	}

	/**
	 * Creates a learner fixture.
	 *
	 * @return int User ID.
	 */
	public function user() {
		$login = self::PREFIX . wp_generate_password( 8, false );

		$user_id = wp_insert_user(
			array(
				'user_login' => $login,
				'user_email' => $login . '@example.invalid',
				'user_pass'  => wp_generate_password( 20 ),
				'role'       => 'subscriber',
			)
		);

		if ( is_wp_error( $user_id ) ) {
			return 0;
		}

		$this->users[] = (int) $user_id;

		return (int) $user_id;
	}

	/**
	 * Creates a course fixture.
	 *
	 * Uses the LearnDash post type when it is registered, so the package
	 * pickers see the same rows the site would.
	 *
	 * @param string $title Course title.
	 * @return int Post ID.
	 */
	public function course( $title ) {
		$post_id = wp_insert_post(
			array(
				'post_type'   => post_type_exists( 'sfwd-courses' ) ? 'sfwd-courses' : 'post',
				'post_title'  => self::PREFIX . $title,
				'post_status' => 'publish',
			)
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return 0;
		}

		$this->posts[] = (int) $post_id;

		return (int) $post_id;
	}

	/**
	 * Creates a package fixture.
	 *
	 * @param string $title      Package title.
	 * @param int[]  $courses    Ordered course IDs.
	 * @param int    $product_id Linked product, 0 for none.
	 * @param bool   $guarantee  Whether it carries the guarantee.
	 * @return int Post ID.
	 */
	public function package( $title, $courses, $product_id = 0, $guarantee = false ) {
		$post_id = wp_insert_post(
			array(
				'post_type'   => French_Path_Package::POST_TYPE,
				'post_title'  => self::PREFIX . $title,
				'post_status' => 'publish',
			)
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return 0;
		}

		$this->posts[] = (int) $post_id;

		update_post_meta( $post_id, French_Path_Package::META_COURSES, array_map( 'absint', $courses ) );
		update_post_meta( $post_id, French_Path_Package::META_PRODUCT, absint( $product_id ) );
		update_post_meta( $post_id, French_Path_Package::META_GUARANTEE, $guarantee ? '1' : '' );

		French_Path_Package::flush_memo();

		return (int) $post_id;
	}

	/**
	 * Creates a stand-in product fixture.
	 *
	 * @param string $title Product title.
	 * @return int Post ID.
	 */
	public function product( $title ) {
		$post_id = wp_insert_post(
			array(
				'post_type'   => post_type_exists( 'product' ) ? 'product' : 'post',
				'post_title'  => self::PREFIX . $title,
				'post_status' => 'publish',
			)
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return 0;
		}

		$this->posts[] = (int) $post_id;

		return (int) $post_id;
	}

	/**
	 * Adopts anything the plugin created on a case's behalf.
	 *
	 * @return void
	 */
	public function adopt_fixtures() {
		French_Path_Package::flush_memo();
	}

	/**
	 * Prints the summary and returns the exit code.
	 *
	 * @return int 0 when everything passed.
	 */
	public function report() {
		printf( PHP_EOL . '%d passed, %d failed' . PHP_EOL, $this->passed, count( $this->failures ) );

		foreach ( $this->failures as $failure ) {
			printf( '  FAIL  %s' . PHP_EOL, $failure );
		}

		if ( $this->notices ) {
			printf( PHP_EOL . '%d PHP notices:' . PHP_EOL, count( $this->notices ) );

			foreach ( array_unique( $this->notices ) as $notice ) {
				printf( '  %s' . PHP_EOL, $notice );
			}
		}

		return ( $this->failures || $this->notices ) ? 1 : 0;
	}

	/**
	 * Removes every fixture.
	 *
	 * @return void
	 */
	public function tear_down() {
		foreach ( $this->users as $user_id ) {
			French_Path_Entitlement::delete_for_user( $user_id );
			wp_delete_user( $user_id );
		}

		foreach ( $this->posts as $post_id ) {
			wp_delete_post( $post_id, true );
		}

		restore_error_handler();
	}
}
