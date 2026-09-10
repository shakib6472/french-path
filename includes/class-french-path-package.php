<?php
/**
 * The Package post type and everything that reads it.
 *
 * A package is the only thing the plugin sells. A single sublevel is a
 * package holding one course, so there is no separate code path for
 * individual purchases. See PLUGIN-SPEC.md section 5.
 *
 * @package French_Path
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the package post type and resolves packages to courses.
 */
class French_Path_Package {

	/**
	 * Post type key.
	 */
	const POST_TYPE = 'fp_package';

	/**
	 * Ordered list of LearnDash course IDs inside the package.
	 */
	const META_COURSES = '_french_path_courses';

	/**
	 * WooCommerce product whose purchase grants this package.
	 */
	const META_PRODUCT = '_french_path_product';

	/**
	 * Whether buying this package carries the examination fee guarantee.
	 */
	const META_GUARANTEE = '_french_path_guarantee';

	/**
	 * Presentation grouping: single, band or pathway.
	 */
	const META_KIND = '_french_path_kind';

	/**
	 * How the package decides which course pages it appears on.
	 */
	const META_SHOW_ON_MODE = '_french_path_show_on_mode';

	/**
	 * The course pages it appears on, when that is chosen by hand.
	 */
	const META_SHOW_ON = '_french_path_show_on';

	/**
	 * Offered only on the first sublevel of its own ladder.
	 */
	const SHOW_ON_FIRST = 'first';

	/**
	 * Offered on a hand picked list of course pages.
	 */
	const SHOW_ON_CHOSEN = 'chosen';

	/**
	 * Runtime memo for product and course lookups.
	 *
	 * @var array
	 */
	private static $memo = array();

	/**
	 * Registers the post type.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
	}

	/**
	 * Registers fp_package.
	 *
	 * Not public: a package has no front end of its own. It is an admin
	 * record that links a product to an ordered list of courses.
	 *
	 * @return void
	 */
	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'               => __( 'Packages', 'french-path' ),
					'singular_name'      => __( 'Package', 'french-path' ),
					'add_new'            => __( 'Add Package', 'french-path' ),
					'add_new_item'       => __( 'Add Package', 'french-path' ),
					'edit_item'          => __( 'Edit Package', 'french-path' ),
					'new_item'           => __( 'New Package', 'french-path' ),
					'view_item'          => __( 'View Package', 'french-path' ),
					'search_items'       => __( 'Search Packages', 'french-path' ),
					'not_found'          => __( 'No packages yet.', 'french-path' ),
					'not_found_in_trash' => __( 'No packages in the bin.', 'french-path' ),
					'all_items'          => __( 'Packages', 'french-path' ),
					'menu_name'          => __( 'French Path', 'french-path' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'menu_position'       => 56,
				'show_in_nav_menus'   => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'hierarchical'        => false,
				'supports'            => array( 'title' ),
				// Every capability maps to one plugin capability, so a
				// coordinator can be given the learner screen without also
				// being handed the pricing decisions a package carries.
				'capability_type'     => 'post',
				'map_meta_cap'        => false,
				'capabilities'        => array(
					'edit_post'          => French_Path_Roles::CAP_MANAGE_PACKAGES,
					'read_post'          => French_Path_Roles::CAP_MANAGE_PACKAGES,
					'delete_post'        => French_Path_Roles::CAP_MANAGE_PACKAGES,
					'edit_posts'         => French_Path_Roles::CAP_MANAGE_PACKAGES,
					'edit_others_posts'  => French_Path_Roles::CAP_MANAGE_PACKAGES,
					'delete_posts'       => French_Path_Roles::CAP_MANAGE_PACKAGES,
					'delete_others_posts' => French_Path_Roles::CAP_MANAGE_PACKAGES,
					'publish_posts'      => French_Path_Roles::CAP_MANAGE_PACKAGES,
					'read_private_posts' => French_Path_Roles::CAP_MANAGE_PACKAGES,
					'create_posts'       => French_Path_Roles::CAP_MANAGE_PACKAGES,
				),
				'menu_icon'           => 'dashicons-portfolio',
			)
		);
	}

	/**
	 * Ordered course IDs in a package.
	 *
	 * The order is the ladder: index 0 is the sublevel a learner starts on.
	 *
	 * @param int $package_id Package post ID.
	 * @return int[] Course IDs, in the order the admin arranged them.
	 */
	public static function get_courses( $package_id ) {
		$stored = get_post_meta( (int) $package_id, self::META_COURSES, true );

		if ( ! is_array( $stored ) ) {
			return array();
		}

		$courses = array();

		foreach ( $stored as $course_id ) {
			$course_id = absint( $course_id );

			if ( $course_id && ! in_array( $course_id, $courses, true ) ) {
				$courses[] = $course_id;
			}
		}

		return $courses;
	}

	/**
	 * The product that grants this package.
	 *
	 * @param int $package_id Package post ID.
	 * @return int Product ID, 0 when unset.
	 */
	public static function get_product( $package_id ) {
		return absint( get_post_meta( (int) $package_id, self::META_PRODUCT, true ) );
	}

	/**
	 * Whether the package carries the examination fee guarantee.
	 *
	 * @param int $package_id Package post ID.
	 * @return bool
	 */
	public static function has_guarantee( $package_id ) {
		return '1' === (string) get_post_meta( (int) $package_id, self::META_GUARANTEE, true );
	}

	/**
	 * Presentation grouping for the package.
	 *
	 * @param int $package_id Package post ID.
	 * @return string One of single, band, pathway.
	 */
	public static function get_kind( $package_id ) {
		$kind = (string) get_post_meta( (int) $package_id, self::META_KIND, true );

		return array_key_exists( $kind, self::get_kinds() ) ? $kind : 'single';
	}

	/**
	 * The available package kinds and their labels.
	 *
	 * @return array
	 */
	public static function get_kinds() {
		/**
		 * Filters the package kinds offered in the admin.
		 *
		 * @param array $kinds Kind key => label.
		 */
		return apply_filters(
			'french_path_package_kinds',
			array(
				'single'  => __( 'Single sublevel', 'french-path' ),
				'band'    => __( 'Band package', 'french-path' ),
				'pathway' => __( 'Complete pathway', 'french-path' ),
			)
		);
	}

	/**
	 * Every published package, newest first.
	 *
	 * @return int[] Package post IDs.
	 */
	public static function get_all() {
		if ( isset( self::$memo['all'] ) ) {
			return self::$memo['all'];
		}

		$ids = get_posts(
			array(
				'post_type'              => self::POST_TYPE,
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'orderby'                => 'menu_order title',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
			)
		);

		self::$memo['all'] = array_map( 'absint', (array) $ids );

		return self::$memo['all'];
	}

	/**
	 * Packages granted by a given product.
	 *
	 * More than one package may point at the same product; every match is
	 * granted, so the caller never has to guess which one was intended.
	 *
	 * @param int $product_id WooCommerce product ID.
	 * @return int[] Package post IDs.
	 */
	public static function find_by_product( $product_id ) {
		$product_id = absint( $product_id );

		if ( ! $product_id ) {
			return array();
		}

		$found = array();

		foreach ( self::get_all() as $package_id ) {
			if ( self::get_product( $package_id ) === $product_id ) {
				$found[] = $package_id;
			}
		}

		return $found;
	}

	/**
	 * How this package decides where it is offered.
	 *
	 * @param int $package_id Package post ID.
	 * @return string SHOW_ON_FIRST or SHOW_ON_CHOSEN.
	 */
	public static function get_show_on_mode( $package_id ) {
		$mode = (string) get_post_meta( (int) $package_id, self::META_SHOW_ON_MODE, true );

		return ( self::SHOW_ON_CHOSEN === $mode ) ? self::SHOW_ON_CHOSEN : self::SHOW_ON_FIRST;
	}

	/**
	 * The course pages this package is offered on.
	 *
	 * By default a package is offered only on the first rung of its own
	 * ladder. A visitor who opens B1.2 came to start at B1.2, and a package
	 * that would drop them at A1.1 instead is not an answer to what they
	 * asked for - it is also how someone could believe they were buying the
	 * examination guarantee when they were not.
	 *
	 * @param int $package_id Package post ID.
	 * @return int[] Course IDs.
	 */
	public static function get_show_on( $package_id ) {
		$package_id = (int) $package_id;

		if ( self::SHOW_ON_CHOSEN === self::get_show_on_mode( $package_id ) ) {
			$stored = get_post_meta( $package_id, self::META_SHOW_ON, true );
			$chosen = array();

			foreach ( (array) $stored as $course_id ) {
				$course_id = absint( $course_id );

				if ( $course_id && ! in_array( $course_id, $chosen, true ) ) {
					$chosen[] = $course_id;
				}
			}

			return $chosen;
		}

		$courses = self::get_courses( $package_id );

		return $courses ? array( (int) $courses[0] ) : array();
	}

	/**
	 * Packages offered on a given course page.
	 *
	 * This is what the single course page uses. Unlike find_by_course() it
	 * answers "what can somebody starting here buy", not "what contains this
	 * course".
	 *
	 * @param int $course_id LearnDash course ID.
	 * @return int[] Package post IDs.
	 */
	public static function find_shown_on( $course_id ) {
		$course_id = absint( $course_id );

		if ( ! $course_id ) {
			return array();
		}

		$found = array();

		foreach ( self::get_all() as $package_id ) {
			if ( in_array( $course_id, self::get_show_on( $package_id ), true ) ) {
				$found[] = $package_id;
			}
		}

		return $found;
	}

	/**
	 * Packages that contain a given course.
	 *
	 * Containment, not visibility. Use find_shown_on() for what a course page
	 * should offer.
	 *
	 * @param int $course_id LearnDash course ID.
	 * @return int[] Package post IDs.
	 */
	public static function find_by_course( $course_id ) {
		$course_id = absint( $course_id );

		if ( ! $course_id ) {
			return array();
		}

		$found = array();

		foreach ( self::get_all() as $package_id ) {
			if ( in_array( $course_id, self::get_courses( $package_id ), true ) ) {
				$found[] = $package_id;
			}
		}

		return $found;
	}

	/**
	 * What stops a package from working, in plain sentences.
	 *
	 * The client builds packages without a developer, so a package that
	 * silently does nothing has to say why. An empty result means it is
	 * ready to sell.
	 *
	 * @param int $package_id Package post ID.
	 * @return string[]
	 */
	public static function get_issues( $package_id ) {
		$issues  = array();
		$courses = self::get_courses( $package_id );

		if ( ! $courses ) {
			$issues[] = __( 'No sublevels yet. Nobody can be enrolled in an empty package.', 'french-path' );
		} else {
			$missing = 0;

			foreach ( $courses as $course_id ) {
				if ( 'publish' !== get_post_status( $course_id ) ) {
					++$missing;
				}
			}

			if ( $missing ) {
				$issues[] = sprintf(
					/* translators: %s: number of sublevels. */
					_n(
						'%s sublevel in the ladder is not published, so buyers will not reach it.',
						'%s sublevels in the ladder are not published, so buyers will not reach them.',
						$missing,
						'french-path'
					),
					number_format_i18n( $missing )
				);
			}
		}

		$product_id = self::get_product( $package_id );

		if ( ! $product_id ) {
			$issues[] = __( 'No product. Nothing grants this package until you choose one.', 'french-path' );
		} else {
			if ( 'publish' !== get_post_status( $product_id ) ) {
				$issues[] = __( 'The product is not published, so it cannot be bought.', 'french-path' );
			}

			if ( null === self::get_price( $package_id ) ) {
				$issues[] = __( 'The product has no price, so the package is hidden from the buying options.', 'french-path' );
			}

			$related = get_post_meta( $product_id, '_related_course', true );

			if ( ! empty( $related ) ) {
				$issues[] = __( 'The product still has a LearnDash course attached. Clear that field, or buyers are enrolled in every sublevel at once.', 'french-path' );
			}
		}

		/**
		 * Filters the problems reported against a package.
		 *
		 * @param string[] $issues     Sentences shown in the admin.
		 * @param int      $package_id Package post ID.
		 */
		return (array) apply_filters( 'french_path_package_issues', $issues, (int) $package_id );
	}

	/**
	 * Whether the package is ready to sell.
	 *
	 * @param int $package_id Package post ID.
	 * @return bool
	 */
	public static function is_ready( $package_id ) {
		return ! self::get_issues( $package_id );
	}

	/**
	 * Things worth knowing about a package that still works.
	 *
	 * Kept apart from get_issues(), which means broken. A warning never
	 * stops a package selling, so it must not make the list read as broken.
	 *
	 * @param int $package_id Package post ID.
	 * @return string[]
	 */
	public static function get_warnings( $package_id ) {
		$package_id = (int) $package_id;
		$warnings   = array();
		$show_on    = self::get_show_on( $package_id );

		if ( self::SHOW_ON_CHOSEN === self::get_show_on_mode( $package_id ) ) {
			$courses = self::get_courses( $package_id );
			$first   = $courses ? (int) $courses[0] : 0;

			if ( ! $show_on ) {
				$warnings[] = __( 'No course pages are ticked, so nobody will ever be offered this package.', 'french-path' );
			} elseif ( $first && self::has_guarantee( $package_id ) && array( $first ) !== $show_on ) {
				$warnings[] = sprintf(
					/* translators: %s: title of the package's first sublevel. */
					__( 'This package carries the examination guarantee, and it starts at %s. Offering it on other course pages can leave a buyer believing they will start where they are reading, and that they qualify for the guarantee when they do not.', 'french-path' ),
					get_the_title( $first )
				);
			} elseif ( $first && ! in_array( $first, $show_on, true ) ) {
				$warnings[] = sprintf(
					/* translators: %s: title of the package's first sublevel. */
					__( 'This package starts at %s, but that page is not ticked, so the sublevel it opens on does not offer it.', 'french-path' ),
					get_the_title( $first )
				);
			}
		}

		/**
		 * Filters the advisory notes shown against a package.
		 *
		 * @param string[] $warnings   Sentences shown in the admin.
		 * @param int      $package_id Package post ID.
		 */
		return (array) apply_filters( 'french_path_package_warnings', $warnings, $package_id );
	}

	/**
	 * The package's price, from its WooCommerce product.
	 *
	 * @param int $package_id Package post ID.
	 * @return float|null null when there is no product or no price on it.
	 */
	public static function get_price( $package_id ) {
		$product = self::get_wc_product( $package_id );

		if ( ! $product ) {
			return null;
		}

		$price = $product->get_price();

		return ( '' === $price || null === $price ) ? null : (float) $price;
	}

	/**
	 * The package's price as WooCommerce would render it.
	 *
	 * @param int $package_id Package post ID.
	 * @return string Empty when there is no price.
	 */
	public static function get_price_html( $package_id ) {
		$product = self::get_wc_product( $package_id );

		if ( ! $product || null === self::get_price( $package_id ) ) {
			return '';
		}

		return (string) $product->get_price_html();
	}

	/**
	 * A product's normal and promotional price, formatted.
	 *
	 * Used for the registration fee, which is its own product rather than
	 * part of any package, so the card cannot find it on its own. Reading it
	 * from the product means the client changes the fee in one place and the
	 * card follows, instead of the two drifting apart.
	 *
	 * @param int $product_id WooCommerce product ID.
	 * @return array was and now, both formatted. Empty strings when unknown.
	 */
	public static function get_promotional_price( $product_id ) {
		$empty = array(
			'was' => '',
			'now' => '',
		);

		$product_id = absint( $product_id );

		if ( ! $product_id || ! function_exists( 'wc_get_product' ) || ! function_exists( 'wc_price' ) ) {
			return $empty;
		}

		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			return $empty;
		}

		$regular = $product->get_regular_price();
		$sale    = $product->get_sale_price();

		if ( '' === $regular || null === $regular ) {
			return $empty;
		}

		// No sale running: the fee is simply the fee, with nothing struck out.
		if ( '' === $sale || null === $sale ) {
			return array(
				'was' => '',
				'now' => wc_price( $regular ),
			);
		}

		return array(
			'was' => wc_price( $regular ),
			'now' => wc_price( $sale ),
		);
	}

	/**
	 * Where a buyer goes to purchase the package.
	 *
	 * Straight to checkout, matching how the site already links to its one
	 * existing course product.
	 *
	 * @param int $package_id Package post ID.
	 * @return string Empty when the package is not for sale.
	 */
	public static function get_add_to_cart_url( $package_id ) {
		$product_id = self::get_product( $package_id );

		if ( ! $product_id || ! function_exists( 'wc_get_checkout_url' ) ) {
			return '';
		}

		/**
		 * Filters where a package's buy button sends the visitor.
		 *
		 * @param string $url        Checkout URL carrying add-to-cart.
		 * @param int    $package_id Package post ID.
		 * @param int    $product_id WooCommerce product ID.
		 */
		return (string) apply_filters(
			'french_path_add_to_cart_url',
			add_query_arg( 'add-to-cart', $product_id, wc_get_checkout_url() ),
			(int) $package_id,
			$product_id
		);
	}

	/**
	 * The WooCommerce product behind a package.
	 *
	 * @param int $package_id Package post ID.
	 * @return WC_Product|null
	 */
	private static function get_wc_product( $package_id ) {
		$product_id = self::get_product( $package_id );

		if ( ! $product_id || ! function_exists( 'wc_get_product' ) ) {
			return null;
		}

		$product = wc_get_product( $product_id );

		return $product ? $product : null;
	}

	/**
	 * Clears the runtime memo.
	 *
	 * Only needed by the test suite and by save_post, since a package edited
	 * mid-request would otherwise serve stale course lists.
	 *
	 * @return void
	 */
	public static function flush_memo() {
		self::$memo = array();
	}
}
