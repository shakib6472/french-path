<?php
/**
 * Admin screens for the package post type.
 *
 * @package French_Path
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Meta boxes, saving and list table columns.
 */
class French_Path_Admin {

	/**
	 * Nonce action for the package meta boxes.
	 */
	const NONCE_ACTION = 'french_path_nonce_package';

	/**
	 * Nonce field name.
	 */
	const NONCE_NAME = 'french_path_package_nonce';

	/**
	 * Hooks the admin behaviour.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_meta_boxes' ) );
		add_action( 'save_post_' . French_Path_Package::POST_TYPE, array( __CLASS__, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );

		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );

		add_filter( 'manage_' . French_Path_Package::POST_TYPE . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . French_Path_Package::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
	}

	/**
	 * Tells the client what is stopping a package from working.
	 *
	 * @return void
	 */
	public static function notices() {
		$screen = get_current_screen();

		if ( ! $screen || 'post' !== $screen->base || French_Path_Package::POST_TYPE !== $screen->post_type ) {
			return;
		}

		$post_id = (int) get_the_ID();

		if ( ! $post_id || 'auto-draft' === get_post_status( $post_id ) ) {
			return;
		}

		$issues   = French_Path_Package::get_issues( $post_id );
		$warnings = French_Path_Package::get_warnings( $post_id );

		if ( $issues ) {
			echo '<div class="notice notice-error"><p><strong>';
			esc_html_e( 'This package will not sell yet:', 'french-path' );
			echo '</strong></p><ul class="french-path-issues">';

			foreach ( $issues as $issue ) {
				printf( '<li>%s</li>', esc_html( $issue ) );
			}

			echo '</ul></div>';
		} else {
			printf(
				'<div class="notice notice-success"><p>%s</p></div>',
				esc_html__( 'This package is ready to sell.', 'french-path' )
			);
		}

		if ( ! $warnings ) {
			return;
		}

		echo '<div class="notice notice-warning"><p><strong>';
		esc_html_e( 'Worth checking:', 'french-path' );
		echo '</strong></p><ul class="french-path-issues">';

		foreach ( $warnings as $warning ) {
			printf( '<li>%s</li>', esc_html( $warning ) );
		}

		echo '</ul></div>';
	}

	/**
	 * Adds the package meta boxes.
	 *
	 * @return void
	 */
	public static function register_meta_boxes() {
		add_meta_box(
			'french-path-courses',
			__( 'Sublevels in this package', 'french-path' ),
			array( __CLASS__, 'render_courses' ),
			French_Path_Package::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'french-path-visibility',
			__( 'Where this package is offered', 'french-path' ),
			array( __CLASS__, 'render_visibility' ),
			French_Path_Package::POST_TYPE,
			'normal',
			'default'
		);

		add_meta_box(
			'french-path-purchase',
			__( 'Purchase', 'french-path' ),
			array( __CLASS__, 'render_purchase' ),
			French_Path_Package::POST_TYPE,
			'side',
			'default'
		);
	}

	/**
	 * Renders the ordered course list.
	 *
	 * @param WP_Post $post Package being edited.
	 * @return void
	 */
	public static function render_courses( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$selected = French_Path_Package::get_courses( $post->ID );
		$courses  = self::get_courses();

		require FRENCH_PATH_PATH . 'admin/views/metabox-courses.php';
	}

	/**
	 * Renders the product, kind and guarantee controls.
	 *
	 * @param WP_Post $post Package being edited.
	 * @return void
	 */
	public static function render_purchase( $post ) {
		$product   = French_Path_Package::get_product( $post->ID );
		$kind      = French_Path_Package::get_kind( $post->ID );
		$kinds     = French_Path_Package::get_kinds();
		$guarantee = French_Path_Package::has_guarantee( $post->ID );
		$products  = self::get_products();

		require FRENCH_PATH_PATH . 'admin/views/metabox-purchase.php';
	}

	/**
	 * Renders the course pages this package is offered on.
	 *
	 * @param WP_Post $post Package being edited.
	 * @return void
	 */
	public static function render_visibility( $post ) {
		$mode    = French_Path_Package::get_show_on_mode( $post->ID );
		$show_on = French_Path_Package::get_show_on( $post->ID );
		$ladder  = French_Path_Package::get_courses( $post->ID );
		$courses = self::get_courses();

		require FRENCH_PATH_PATH . 'admin/views/metabox-visibility.php';
	}

	/**
	 * Saves the package meta.
	 *
	 * @param int     $post_id Package post ID.
	 * @param WP_Post $post    Package post.
	 * @return void
	 */
	public static function save( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		$nonce = isset( $_POST[ self::NONCE_NAME ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ) : '';

		if ( ! $nonce || ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$courses = array();

		if ( isset( $_POST['french_path_courses'] ) && is_array( $_POST['french_path_courses'] ) ) {
			foreach ( wp_unslash( $_POST['french_path_courses'] ) as $course_id ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$course_id = absint( $course_id );

				if ( $course_id && ! in_array( $course_id, $courses, true ) ) {
					$courses[] = $course_id;
				}
			}
		}

		update_post_meta( $post_id, French_Path_Package::META_COURSES, $courses );

		$product = isset( $_POST['french_path_product'] ) ? absint( wp_unslash( $_POST['french_path_product'] ) ) : 0;
		update_post_meta( $post_id, French_Path_Package::META_PRODUCT, $product );

		$kind  = isset( $_POST['french_path_kind'] ) ? sanitize_key( wp_unslash( $_POST['french_path_kind'] ) ) : 'single';
		$kinds = French_Path_Package::get_kinds();
		update_post_meta( $post_id, French_Path_Package::META_KIND, isset( $kinds[ $kind ] ) ? $kind : 'single' );

		$guarantee = isset( $_POST['french_path_guarantee'] ) ? '1' : '';
		update_post_meta( $post_id, French_Path_Package::META_GUARANTEE, $guarantee );

		$mode = isset( $_POST['french_path_show_on_mode'] )
			? sanitize_key( wp_unslash( $_POST['french_path_show_on_mode'] ) )
			: French_Path_Package::SHOW_ON_FIRST;

		update_post_meta(
			$post_id,
			French_Path_Package::META_SHOW_ON_MODE,
			French_Path_Package::SHOW_ON_CHOSEN === $mode
				? French_Path_Package::SHOW_ON_CHOSEN
				: French_Path_Package::SHOW_ON_FIRST
		);

		$pages = array();

		if ( isset( $_POST['french_path_show_on'] ) && is_array( $_POST['french_path_show_on'] ) ) {
			foreach ( wp_unslash( $_POST['french_path_show_on'] ) as $course_id ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$course_id = absint( $course_id );

				if ( $course_id && ! in_array( $course_id, $pages, true ) ) {
					$pages[] = $course_id;
				}
			}
		}

		update_post_meta( $post_id, French_Path_Package::META_SHOW_ON, $pages );

		French_Path_Package::flush_memo();

		// Keeps the stored ladder index on existing entitlements true after a
		// package is reordered.
		French_Path_Entitlement::sync_positions( $post_id );
	}

	/**
	 * Loads the admin stylesheet and script on the package screens only.
	 *
	 * @param string $hook Current admin page.
	 * @return void
	 */
	public static function enqueue( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();

		if ( ! $screen || French_Path_Package::POST_TYPE !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style(
			'french-path-admin',
			FRENCH_PATH_URL . 'admin/assets/css/admin.css',
			array(),
			FRENCH_PATH_VERSION
		);

		wp_enqueue_script(
			'french-path-admin',
			FRENCH_PATH_URL . 'admin/assets/js/admin.js',
			array(),
			FRENCH_PATH_VERSION,
			true
		);

		wp_localize_script(
			'french-path-admin',
			'frenchPathData',
			array(
				'alreadyAdded' => __( 'That sublevel is already in this package.', 'french-path' ),
				'remove'       => __( 'Remove', 'french-path' ),
				'moveUp'       => __( 'Move up', 'french-path' ),
				'moveDown'     => __( 'Move down', 'french-path' ),
			)
		);
	}

	/**
	 * Adds the package list table columns.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public static function columns( $columns ) {
		$date = isset( $columns['date'] ) ? $columns['date'] : '';
		unset( $columns['date'] );

		$columns['french_path_status']    = __( 'Status', 'french-path' );
		$columns['french_path_kind']      = __( 'Kind', 'french-path' );
		$columns['french_path_courses']   = __( 'Sublevels', 'french-path' );
		$columns['french_path_product']   = __( 'Product', 'french-path' );
		$columns['french_path_guarantee'] = __( 'Guarantee', 'french-path' );

		if ( $date ) {
			$columns['date'] = $date;
		}

		return $columns;
	}

	/**
	 * Renders one custom column.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Package post ID.
	 * @return void
	 */
	public static function column( $column, $post_id ) {
		switch ( $column ) {
			case 'french_path_status':
				$issues = French_Path_Package::get_issues( $post_id );

				if ( ! $issues ) {
					echo '<span class="french-path-ready">' . esc_html__( 'Ready', 'french-path' ) . '</span>';
					break;
				}

				printf(
					'<span class="french-path-warn">%s</span><span class="french-path-help">%s</span>',
					esc_html(
						sprintf(
							/* translators: %s: number of problems. */
							_n( '%s problem', '%s problems', count( $issues ), 'french-path' ),
							number_format_i18n( count( $issues ) )
						)
					),
					esc_html( reset( $issues ) )
				);
				break;

			case 'french_path_kind':
				$kinds = French_Path_Package::get_kinds();
				$kind  = French_Path_Package::get_kind( $post_id );

				echo esc_html( isset( $kinds[ $kind ] ) ? $kinds[ $kind ] : $kind );
				break;

			case 'french_path_courses':
				$courses = French_Path_Package::get_courses( $post_id );

				if ( ! $courses ) {
					echo '<span class="french-path-warn">' . esc_html__( 'None set', 'french-path' ) . '</span>';
					break;
				}

				$titles = array();

				foreach ( $courses as $course_id ) {
					$titles[] = get_the_title( $course_id );
				}

				echo esc_html( implode( ' → ', $titles ) );
				break;

			case 'french_path_product':
				$product = French_Path_Package::get_product( $post_id );

				if ( ! $product ) {
					echo '<span class="french-path-warn">' . esc_html__( 'None set', 'french-path' ) . '</span>';
					break;
				}

				printf(
					'<a href="%1$s">%2$s</a>',
					esc_url( (string) get_edit_post_link( $product ) ),
					esc_html( get_the_title( $product ) )
				);
				break;

			case 'french_path_guarantee':
				echo French_Path_Package::has_guarantee( $post_id )
					? esc_html__( 'Yes', 'french-path' )
					: esc_html__( 'No', 'french-path' );
				break;
		}
	}

	/**
	 * Every LearnDash course, for the picker.
	 *
	 * @return array Course ID => title.
	 */
	public static function get_courses() {
		$choices = array();

		if ( ! post_type_exists( 'sfwd-courses' ) ) {
			return $choices;
		}

		$posts = get_posts(
			array(
				'post_type'      => 'sfwd-courses',
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);

		foreach ( $posts as $course ) {
			$choices[ (int) $course->ID ] = $course->post_title;
		}

		return $choices;
	}

	/**
	 * Every WooCommerce product, for the picker.
	 *
	 * @return array Product ID => title.
	 */
	public static function get_products() {
		$choices = array();

		if ( ! post_type_exists( 'product' ) ) {
			return $choices;
		}

		$posts = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);

		foreach ( $posts as $product ) {
			$choices[ (int) $product->ID ] = $product->post_title;
		}

		return $choices;
	}
}
