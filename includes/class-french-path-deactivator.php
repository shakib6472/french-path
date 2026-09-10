<?php
/**
 * Deactivation routine.
 *
 * @package French_Path
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs once when the plugin is deactivated.
 */
class French_Path_Deactivator {

	/**
	 * Leaves every record in place.
	 *
	 * Entitlements are the evidence that a learner paid, so deactivation
	 * never touches them. Locked sublevels stay locked while the plugin is
	 * off, because a locked sublevel is simply one the learner was never
	 * enrolled in.
	 *
	 * @return void
	 */
	public static function deactivate() {
		wp_cache_flush();
	}
}
