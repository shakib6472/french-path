<?php
/**
 * Database schema installation and upgrades.
 *
 * @package French_Path
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Owns the custom table and the stored schema version.
 */
class French_Path_Install {

	/**
	 * Option holding the schema version the database is currently at.
	 */
	const VERSION_OPTION = 'french_path_db_version';

	/**
	 * Schema version. Raise this whenever the table definition changes.
	 */
	const DB_VERSION = '1.0.0';

	/**
	 * Hooks the upgrade check.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'plugins_loaded', array( __CLASS__, 'maybe_upgrade' ), 20 );
	}

	/**
	 * Fully qualified entitlements table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;

		return $wpdb->prefix . 'french_path_entitlements';
	}

	/**
	 * Creates or updates the table.
	 *
	 * Safe to call repeatedly: dbDelta only applies differences.
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$collate = $wpdb->get_charset_collate();

		// One row per (learner, course, order). The unique key is what makes
		// purchase processing idempotent: WooCommerce fires both the
		// processing and the completed transition for most gateways.
		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			course_id bigint(20) unsigned NOT NULL DEFAULT 0,
			package_id bigint(20) unsigned NOT NULL DEFAULT 0,
			order_id bigint(20) unsigned NOT NULL DEFAULT 0,
			position smallint(5) unsigned NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'entitled',
			guarantee tinyint(1) NOT NULL DEFAULT 0,
			unlocked tinyint(1) NOT NULL DEFAULT 0,
			unlocked_at datetime DEFAULT NULL,
			unlocked_by bigint(20) unsigned NOT NULL DEFAULT 0,
			granted_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY user_course_order (user_id,course_id,order_id),
			KEY user_status (user_id,status),
			KEY user_course (user_id,course_id),
			KEY package_id (package_id),
			KEY order_id (order_id)
		) {$collate};";

		dbDelta( $sql );

		update_option( self::VERSION_OPTION, self::DB_VERSION );
	}

	/**
	 * Runs install() when the stored schema version is behind the code.
	 *
	 * Covers the case where the plugin files are replaced without the
	 * activation hook firing, which is what happens on most host updaters.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( get_option( self::VERSION_OPTION ) === self::DB_VERSION ) {
			return;
		}

		self::install();
	}

	/**
	 * Whether the entitlements table exists.
	 *
	 * @return bool
	 */
	public static function table_exists() {
		global $wpdb;

		$table = self::table();

		return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
}
