<?php
/**
 * Uninstall routine.
 *
 * @package French_Path
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/*
 * The entitlements table and the package records are deliberately left in
 * place. They are the evidence that learners paid, and a plugin deletion is
 * not a decision to destroy financial records. Removing them is a manual
 * database step, documented in readme.txt.
 */

delete_option( 'french_path_version' );
delete_option( 'french_path_db_version' );
