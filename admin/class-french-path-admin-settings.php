<?php
/**
 * The settings screen.
 *
 * @package French_Path
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders and saves the settings.
 */
class French_Path_Admin_Settings {

	/**
	 * Menu slug.
	 */
	const SLUG = 'french-path-settings';

	/**
	 * Nonce action.
	 */
	const NONCE_ACTION = 'french_path_nonce_settings';

	/**
	 * admin-post.php action.
	 */
	const POST_ACTION = 'french_path_save_settings';

	/**
	 * Hooks the screen.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_post_' . self::POST_ACTION, array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Adds the submenu.
	 *
	 * @return void
	 */
	public static function register_menu() {
		add_submenu_page(
			'edit.php?post_type=' . French_Path_Package::POST_TYPE,
			__( 'Settings', 'french-path' ),
			__( 'Settings', 'french-path' ),
			French_Path_Roles::CAP_MANAGE_PACKAGES,
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
	 * Renders the form.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! French_Path_Roles::can_manage_packages() ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'french-path' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$saved = isset( $_GET['fp_saved'] ) && '1' === sanitize_key( wp_unslash( $_GET['fp_saved'] ) );

		$registration = French_Path_Settings::registration_product();
		$products     = French_Path_Admin::get_products();
		$fee          = $registration ? French_Path_Package::get_promotional_price( $registration ) : array(
			'was' => '',
			'now' => '',
		);

		require FRENCH_PATH_PATH . 'admin/views/settings.php';
	}

	/**
	 * Saves the form.
	 *
	 * @return void
	 */
	public static function handle_save() {
		if ( ! French_Path_Roles::can_manage_packages() ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'french-path' ) );
		}

		check_admin_referer( self::NONCE_ACTION );

		French_Path_Settings::save(
			array(
				'registration_product' => isset( $_POST['registration_product'] )
					? absint( wp_unslash( $_POST['registration_product'] ) )
					: 0,
			)
		);

		wp_safe_redirect( self::url( array( 'fp_saved' => '1' ) ) );
		exit;
	}
}
