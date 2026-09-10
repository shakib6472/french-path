<?php
/**
 * Core plugin class.
 *
 * @package French_Path
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * French Path core singleton.
 */
final class French_Path {

	/**
	 * Single instance of the class.
	 *
	 * @var French_Path|null
	 */
	private static $instance = null;

	/**
	 * Returns the single instance of the class.
	 *
	 * @return French_Path
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		// WordPress 6.7+ triggers a notice if translations load before init.
		add_action( 'init', array( $this, 'load_textdomain' ) );

		$this->init();
	}

	/**
	 * Autoloads French_Path_ prefixed classes.
	 *
	 * Registered in french-path.php when the plugin file loads, so it is
	 * available during activation. See PLUGIN-SPEC.md section 4.
	 *
	 * French_Path_Entitlement -> includes/class-french-path-entitlement.php
	 * French_Path_Admin_Menu  -> admin/class-french-path-admin-menu.php
	 *
	 * @param string $class_name Class name being loaded.
	 * @return void
	 */
	public static function autoload( $class_name ) {
		if ( 0 !== strpos( $class_name, 'French_Path_' ) ) {
			return;
		}

		$file = 'class-' . strtolower( str_replace( '_', '-', $class_name ) ) . '.php';

		foreach ( array( 'includes', 'admin', 'public' ) as $dir ) {
			$path = FRENCH_PATH_PATH . $dir . '/' . $file;

			if ( file_exists( $path ) ) {
				require_once $path;
				return;
			}
		}
	}

	/**
	 * Loads the translation files.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'french-path',
			false,
			dirname( FRENCH_PATH_BASENAME ) . '/languages'
		);
	}

	/**
	 * Boots every service class.
	 *
	 * Each service is a class of static methods with its own init(). Only the
	 * core class is a singleton. See PLUGIN-SPEC.md section 4.
	 *
	 * @return void
	 */
	private function init() {
		French_Path_Install::init();
		French_Path_Package::init();
		French_Path_Purchase::init();
		French_Path_Locked::init();
		French_Path_Public::init();
		French_Path_Shortcodes::init();

		if ( is_admin() ) {
			French_Path_Admin::init();
			French_Path_Admin_Settings::init();
			French_Path_Learners::init();
		}

		/**
		 * Fires once every French Path service has been booted.
		 */
		do_action( 'french_path_loaded' );
	}

	/**
	 * Whether LearnDash is available.
	 *
	 * Every enrolment call is guarded on this, so the plugin degrades to
	 * recording entitlements only when LearnDash is absent.
	 *
	 * @return bool
	 */
	public static function has_learndash() {
		return function_exists( 'ld_update_course_access' );
	}

	/**
	 * Whether WooCommerce is available.
	 *
	 * @return bool
	 */
	public static function has_woocommerce() {
		return function_exists( 'wc_get_order' );
	}
}
