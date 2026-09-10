<?php
/**
 * Capabilities.
 *
 * The coordinator role itself belongs to Milestone 3. What exists here is the
 * capability that role will be given, so the release screen is already gated
 * on the right thing rather than on manage_options or edit_users - both of
 * which would hand a coordinator the payment settings and the user table the
 * client explicitly wants kept away from them.
 *
 * @package French_Path
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Defines and grants the plugin's capabilities.
 */
class French_Path_Roles {

	/**
	 * Lets a user see learners and release their sublevels.
	 */
	const CAP_MANAGE_LEARNERS = 'french_path_manage_learners';

	/**
	 * Lets a user create and edit packages.
	 *
	 * Deliberately separate from and stricter than managing learners. A
	 * package decides which product grants which sublevels, which is a
	 * pricing decision; the client wants coordinators kept away from those.
	 */
	const CAP_MANAGE_PACKAGES = 'french_path_manage_packages';

	/**
	 * Every capability the plugin defines, and the roles that get it.
	 *
	 * @return array Capability => role slugs.
	 */
	public static function get_capabilities() {
		/**
		 * Filters which roles receive each French Path capability.
		 *
		 * Milestone 3 adds the coordinator role here with
		 * french_path_manage_learners and deliberately without
		 * french_path_manage_packages.
		 *
		 * @param array $capabilities Capability => role slugs.
		 */
		return (array) apply_filters(
			'french_path_capability_roles',
			array(
				self::CAP_MANAGE_LEARNERS => array( 'administrator' ),
				self::CAP_MANAGE_PACKAGES => array( 'administrator' ),
			)
		);
	}

	/**
	 * Grants every capability to its default roles.
	 *
	 * @return void
	 */
	public static function grant() {
		foreach ( self::get_capabilities() as $cap => $roles ) {
			foreach ( (array) $roles as $role_name ) {
				$role = get_role( $role_name );

				if ( $role && ! $role->has_cap( $cap ) ) {
					$role->add_cap( $cap );
				}
			}
		}
	}

	/**
	 * Whether the current user may create and edit packages.
	 *
	 * @return bool
	 */
	public static function can_manage_packages() {
		return current_user_can( self::CAP_MANAGE_PACKAGES );
	}

	/**
	 * Whether the current user may manage learners.
	 *
	 * @return bool
	 */
	public static function can_manage_learners() {
		return current_user_can( self::CAP_MANAGE_LEARNERS );
	}
}
