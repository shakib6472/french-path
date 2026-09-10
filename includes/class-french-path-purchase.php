<?php
/**
 * Turns a paid WooCommerce order into entitlements.
 *
 * @package French_Path
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Listens to order transitions and records what was bought.
 */
class French_Path_Purchase {

	/**
	 * Order meta recording which package opened which sublevel.
	 *
	 * Its presence is what stops a second order transition from opening a
	 * second sublevel: most gateways fire both processing and completed.
	 */
	const ENTRY_META = '_french_path_entry';

	/**
	 * Hooks the order transitions.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'handle_paid' ), 10, 1 );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'handle_paid' ), 10, 1 );
		add_action( 'woocommerce_payment_complete', array( __CLASS__, 'handle_paid' ), 10, 1 );

		add_action( 'woocommerce_order_status_refunded', array( __CLASS__, 'handle_reversed' ), 10, 1 );
		add_action( 'woocommerce_order_status_cancelled', array( __CLASS__, 'handle_reversed' ), 10, 1 );
		add_action( 'woocommerce_order_status_failed', array( __CLASS__, 'handle_reversed' ), 10, 1 );
		add_action( 'woocommerce_order_refunded', array( __CLASS__, 'handle_reversed' ), 10, 1 );
	}

	/**
	 * Records entitlements and opens the entry sublevel of each package.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return void
	 */
	public static function handle_paid( $order_id ) {
		$order = self::get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$user_id = absint( $order->get_user_id() );

		if ( ! $user_id ) {
			return;
		}

		$entry = $order->get_meta( self::ENTRY_META );
		$entry = is_array( $entry ) ? $entry : array();
		$dirty = false;

		foreach ( self::get_packages( $order ) as $package_id ) {
			$courses   = French_Path_Package::get_courses( $package_id );
			$guarantee = French_Path_Package::has_guarantee( $package_id );

			if ( ! $courses ) {
				continue;
			}

			foreach ( $courses as $position => $course_id ) {
				French_Path_Entitlement::grant(
					array(
						'user_id'    => $user_id,
						'course_id'  => $course_id,
						'package_id' => $package_id,
						'order_id'   => $order->get_id(),
						'position'   => $position,
						'guarantee'  => $guarantee,
					)
				);
			}

			if ( isset( $entry[ $package_id ] ) ) {
				continue;
			}

			$opened = French_Path_Access::unlock_entry( $user_id, $package_id );

			if ( $opened ) {
				$entry[ $package_id ] = $opened;
				$dirty                = true;
			}
		}

		if ( $dirty ) {
			$order->update_meta_data( self::ENTRY_META, $entry );
			$order->save();
		}
	}

	/**
	 * Withdraws everything an order granted.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return void
	 */
	public static function handle_reversed( $order_id ) {
		$order = self::get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$courses = French_Path_Entitlement::revoke_order( $order->get_id() );

		if ( $courses ) {
			French_Path_Access::revoke_orphaned( absint( $order->get_user_id() ), $courses );
		}

		// Cleared so that re-paying the order opens an entry sublevel again.
		$order->delete_meta_data( self::ENTRY_META );
		$order->save();
	}

	/**
	 * Package IDs bought in an order.
	 *
	 * A variation is checked before its parent, so a variable product can
	 * point each variation at a different package.
	 *
	 * @param WC_Order $order Order.
	 * @return int[] Unique package post IDs.
	 */
	public static function get_packages( $order ) {
		$packages = array();

		foreach ( $order->get_items() as $item ) {
			if ( ! is_callable( array( $item, 'get_product_id' ) ) ) {
				continue;
			}

			$variation_id = is_callable( array( $item, 'get_variation_id' ) ) ? absint( $item->get_variation_id() ) : 0;
			$product_id   = $variation_id ? $variation_id : absint( $item->get_product_id() );

			$found = French_Path_Package::find_by_product( $product_id );

			if ( ! $found && $variation_id ) {
				$found = French_Path_Package::find_by_product( absint( $item->get_product_id() ) );
			}

			foreach ( $found as $package_id ) {
				if ( ! in_array( $package_id, $packages, true ) ) {
					$packages[] = $package_id;
				}
			}
		}

		return $packages;
	}

	/**
	 * Loads an order, or false when the environment is not ready.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return WC_Order|false
	 */
	private static function get_order( $order_id ) {
		if ( ! French_Path::has_woocommerce() || ! French_Path_Install::table_exists() ) {
			return false;
		}

		$order = wc_get_order( absint( $order_id ) );

		if ( ! $order || ! is_callable( array( $order, 'get_items' ) ) ) {
			return false;
		}

		return $order;
	}
}
