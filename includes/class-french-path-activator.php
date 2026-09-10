<?php
/**
 * Activation routine.
 *
 * @package French_Path
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs once when the plugin is activated.
 */
class French_Path_Activator {

	/**
	 * Option holding the plugin version last activated.
	 */
	const VERSION_OPTION = 'french_path_version';

	/**
	 * Creates the schema and records the version.
	 *
	 * @return void
	 */
	public static function activate() {
		French_Path_Install::install();
		French_Path_Roles::grant();

		update_option( self::VERSION_OPTION, FRENCH_PATH_VERSION );
	}
}
