<?php
/**
 * Plugin settings.
 *
 * One option holding a small array. Everything the client sets once and then
 * forgets lives here, so it is not retyped into every shortcode.
 *
 * @package French_Path
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and writes the settings option.
 */
class French_Path_Settings {

	/**
	 * Option holding every setting.
	 */
	const OPTION = 'french_path_settings';

	/**
	 * Runtime copy, so a page with several cards reads the option once.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Every setting and its default.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'registration_product' => 0,
		);
	}

	/**
	 * Every setting, defaults filled in.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null === self::$cache ) {
			$stored = get_option( self::OPTION, array() );

			self::$cache = wp_parse_args( is_array( $stored ) ? $stored : array(), self::defaults() );
		}

		return self::$cache;
	}

	/**
	 * One setting.
	 *
	 * @param string $key     Setting name.
	 * @param mixed  $default Returned when the setting is unknown.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$all = self::all();

		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	/**
	 * Saves settings, keeping anything not being changed.
	 *
	 * Unknown keys are dropped rather than stored, so the option cannot grow
	 * fields the spec does not describe.
	 *
	 * @param array $values Setting name => value.
	 * @return bool
	 */
	public static function save( $values ) {
		$clean = self::all();

		foreach ( (array) $values as $key => $value ) {
			if ( ! array_key_exists( $key, self::defaults() ) ) {
				continue;
			}

			$clean[ $key ] = ( 'registration_product' === $key ) ? absint( $value ) : $value;
		}

		self::$cache = $clean;

		return update_option( self::OPTION, $clean );
	}

	/**
	 * The product the site charges as a registration fee.
	 *
	 * @return int Product ID, 0 when none is chosen.
	 */
	public static function registration_product() {
		return absint( self::get( 'registration_product', 0 ) );
	}

	/**
	 * Clears the runtime copy. Only needed by the test suite.
	 *
	 * @return void
	 */
	public static function flush() {
		self::$cache = null;
	}
}
