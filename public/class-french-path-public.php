<?php
/**
 * Front end assets.
 *
 * @package French_Path
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and enqueues the front end stylesheet and script.
 */
class French_Path_Public {

	/**
	 * Stylesheet handle.
	 */
	const STYLE = 'french-path';

	/**
	 * Script handle.
	 */
	const SCRIPT = 'french-path';

	/**
	 * Registers the assets.
	 *
	 * They are registered on wp_enqueue_scripts but only enqueued when
	 * something actually renders, so a page with no French Path output
	 * carries neither file.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	/**
	 * Registers without enqueuing.
	 *
	 * @return void
	 */
	public static function register_assets() {
		wp_register_style(
			self::STYLE,
			FRENCH_PATH_URL . 'public/assets/css/french-path.css',
			array(),
			FRENCH_PATH_VERSION
		);

		wp_register_script(
			self::SCRIPT,
			FRENCH_PATH_URL . 'public/assets/js/french-path.js',
			array(),
			FRENCH_PATH_VERSION,
			true
		);
	}

	/**
	 * Enqueues both assets, registering them first when a shortcode runs
	 * before wp_enqueue_scripts has fired.
	 *
	 * @return void
	 */
	public static function enqueue_assets() {
		if ( ! wp_style_is( self::STYLE, 'registered' ) ) {
			self::register_assets();
		}

		wp_enqueue_style( self::STYLE );
		wp_enqueue_script( self::SCRIPT );
	}
}
