<?php
/**
 * The learner and release screen.
 *
 * This is where a coordinator opens the next sublevel after assessment. It is
 * gated on French_Path_Roles::CAP_MANAGE_LEARNERS rather than on
 * manage_options, so the Milestone 3 coordinator role can be given this screen
 * without also being given the payment settings.
 *
 * @package French_Path
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lists learners and releases their sublevels.
 */
class French_Path_Learners {

	/**
	 * Menu slug.
	 */
	const SLUG = 'french-path-learners';

	/**
	 * Nonce action for release and lock.
	 */
	const NONCE_ACTION = 'french_path_nonce_learner';

	/**
	 * admin-post.php action.
	 */
	const POST_ACTION = 'french_path_learner_action';

	/**
	 * Learners shown per page.
	 */
	const PER_PAGE = 25;

	/**
	 * Hooks the screen.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_post_' . self::POST_ACTION, array( __CLASS__, 'handle_action' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Adds the submenu under the French Path menu.
	 *
	 * @return void
	 */
	public static function register_menu() {
		add_submenu_page(
			'edit.php?post_type=' . French_Path_Package::POST_TYPE,
			__( 'Learners', 'french-path' ),
			__( 'Learners', 'french-path' ),
			French_Path_Roles::CAP_MANAGE_LEARNERS,
			self::SLUG,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Loads the admin stylesheet on this screen.
	 *
	 * @param string $hook Current admin page.
	 * @return void
	 */
	public static function enqueue( $hook ) {
		if ( false === strpos( (string) $hook, self::SLUG ) ) {
			return;
		}

		wp_enqueue_style(
			'french-path-admin',
			FRENCH_PATH_URL . 'admin/assets/css/admin.css',
			array(),
			FRENCH_PATH_VERSION
		);
	}

	/**
	 * The screen URL.
	 *
	 * @param array $args Extra query arguments.
	 * @return string
	 */
	public static function url( $args = array() ) {
		return add_query_arg(
			array_merge(
				array(
					'post_type' => French_Path_Package::POST_TYPE,
					'page'      => self::SLUG,
				),
				$args
			),
			admin_url( 'edit.php' )
		);
	}

	/**
	 * Routes to the list or to one learner.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! French_Path_Roles::can_manage_learners() ) {
			wp_die( esc_html__( 'You are not allowed to manage learners.', 'french-path' ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$learner = isset( $_GET['learner'] ) ? absint( wp_unslash( $_GET['learner'] ) ) : 0;
		$search  = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$paged   = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
		$notice  = isset( $_GET['fp_notice'] ) ? sanitize_key( wp_unslash( $_GET['fp_notice'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$message = self::notice_text( $notice );

		if ( $learner ) {
			self::render_detail( $learner, $message );
			return;
		}

		self::render_list( $search, $paged, $message );
	}

	/**
	 * The learner list.
	 *
	 * @param string $search  Search term.
	 * @param int    $paged   Page number.
	 * @param string $message Notice to show.
	 * @return void
	 */
	private static function render_list( $search, $paged, $message ) {
		$total    = French_Path_Entitlement::count_learners();
		$pages    = max( 1, (int) ceil( $total / self::PER_PAGE ) );
		$paged    = min( $paged, $pages );
		$learners = French_Path_Entitlement::get_learner_ids(
			array(
				'search' => $search,
				'limit'  => self::PER_PAGE,
				'offset' => ( $paged - 1 ) * self::PER_PAGE,
			)
		);

		require FRENCH_PATH_PATH . 'admin/views/learners-list.php';
	}

	/**
	 * One learner, their packages and the release controls.
	 *
	 * @param int    $user_id Learner.
	 * @param string $message Notice to show.
	 * @return void
	 */
	private static function render_detail( $user_id, $message ) {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			wp_die( esc_html__( 'That learner does not exist.', 'french-path' ) );
		}

		$packages = French_Path_Entitlement::get_by_package( $user_id );

		require FRENCH_PATH_PATH . 'admin/views/learners-detail.php';
	}

	/**
	 * Summary counts for one learner.
	 *
	 * @param int $user_id Learner.
	 * @return array entitled and unlocked counts, and unique course totals.
	 */
	public static function summary( $user_id ) {
		$rows     = French_Path_Entitlement::get_for_user( $user_id );
		$entitled = array();
		$unlocked = array();

		foreach ( $rows as $row ) {
			$course_id              = (int) $row->course_id;
			$entitled[ $course_id ] = true;

			if ( (int) $row->unlocked ) {
				$unlocked[ $course_id ] = true;
			}
		}

		return array(
			'entitled' => count( $entitled ),
			'unlocked' => count( $unlocked ),
		);
	}

	/**
	 * Handles release, lock and release-next.
	 *
	 * @return void
	 */
	public static function handle_action() {
		if ( ! French_Path_Roles::can_manage_learners() ) {
			wp_die( esc_html__( 'You are not allowed to manage learners.', 'french-path' ) );
		}

		check_admin_referer( self::NONCE_ACTION );

		$user_id    = isset( $_POST['learner'] ) ? absint( wp_unslash( $_POST['learner'] ) ) : 0;
		$course_id  = isset( $_POST['course'] ) ? absint( wp_unslash( $_POST['course'] ) ) : 0;
		$package_id = isset( $_POST['package'] ) ? absint( wp_unslash( $_POST['package'] ) ) : 0;
		$do         = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';

		$notice = 'nothing';

		if ( $user_id ) {
			switch ( $do ) {
				case 'unlock':
					$notice = French_Path_Access::unlock( $user_id, $course_id ) ? 'released' : 'not_entitled';
					break;

				case 'lock':
					$notice = French_Path_Access::lock( $user_id, $course_id ) ? 'locked' : 'nothing';
					break;

				case 'release_next':
					$notice = French_Path_Access::unlock_entry( $user_id, $package_id ) ? 'released' : 'all_open';
					break;
			}
		}

		wp_safe_redirect(
			self::url(
				array(
					'learner'   => $user_id,
					'fp_notice' => $notice,
				)
			)
		);
		exit;
	}

	/**
	 * Translates a notice key.
	 *
	 * @param string $notice Notice key.
	 * @return string Empty when there is nothing to say.
	 */
	private static function notice_text( $notice ) {
		switch ( $notice ) {
			case 'released':
				return __( 'Sublevel released. The learner can enter it now.', 'french-path' );

			case 'locked':
				return __( 'Sublevel closed. The learner keeps the entitlement.', 'french-path' );

			case 'all_open':
				return __( 'Every sublevel in that package is already open.', 'french-path' );

			case 'not_entitled':
				return __( 'That sublevel was not released: the learner has not paid for it.', 'french-path' );

			default:
				return '';
		}
	}
}
