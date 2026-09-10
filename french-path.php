<?php
/**
 * Plugin Name:       French Path - Course Packages and Progressive Unlock
 * Plugin URI:        https://github.com/shakib6472/french-path
 * Description:       Sells LearnDash courses as packages, records what a learner has paid for, and opens one sublevel at a time under coordinator control.
 * Version:           1.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Shakib Shown
 * Author URI:        https://github.com/shakib6472
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       french-path
 * Domain Path:       /languages
 *
 * @package French_Path
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FRENCH_PATH_VERSION', '1.1.0' );
define( 'FRENCH_PATH_FILE', __FILE__ );
define( 'FRENCH_PATH_PATH', plugin_dir_path( __FILE__ ) );
define( 'FRENCH_PATH_URL', plugin_dir_url( __FILE__ ) );
define( 'FRENCH_PATH_BASENAME', plugin_basename( __FILE__ ) );
define( 'FRENCH_PATH_SLUG', 'french-path' );

require_once FRENCH_PATH_PATH . 'includes/class-french-path.php';

// Registered here, not in the core class constructor, so autoloading is
// available during activation, before plugins_loaded has fired.
spl_autoload_register( array( 'French_Path', 'autoload' ) );

register_activation_hook( FRENCH_PATH_FILE, array( 'French_Path_Activator', 'activate' ) );
register_deactivation_hook( FRENCH_PATH_FILE, array( 'French_Path_Deactivator', 'deactivate' ) );

/**
 * Bootstraps the plugin.
 *
 * @return French_Path
 */
function french_path() {
	return French_Path::instance();
}
add_action( 'plugins_loaded', 'french_path' );
