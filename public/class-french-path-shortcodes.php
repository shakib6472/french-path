<?php
/**
 * Front end shortcodes.
 *
 * @package French_Path
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and renders the shortcodes.
 */
class French_Path_Shortcodes {

	/**
	 * Registers every shortcode.
	 *
	 * @return void
	 */
	public static function init() {
		foreach ( self::get_shortcodes() as $tag => $callback ) {
			add_shortcode( $tag, array( __CLASS__, $callback ) );
		}
	}

	/**
	 * Shortcode tag to render method.
	 *
	 * @return array
	 */
	public static function get_shortcodes() {
		/**
		 * Filters the shortcodes the plugin registers.
		 *
		 * @param array $shortcodes Tag => method on this class.
		 */
		return apply_filters(
			'french_path_shortcodes',
			array(
				'french_path_my_courses' => 'render_my_courses',
				'french_path_enrol'      => 'render_enrol',
			)
		);
	}

	/**
	 * The enrol card on a single course page.
	 *
	 * One card, three states. A visitor who has not paid sees the price and
	 * the ways to buy. A learner who has paid never sees a price again:
	 * either the sublevel is open and they can carry on, or it is closed and
	 * the card says why.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function render_enrol( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'course'        => 0,
				'placement_url' => '',
				'pathway_url'   => '',
				'note'          => '',
				'reg_product'   => 0,
				'reg_was'       => '',
				'reg_now'       => '',
				'class'         => '',
			),
			(array) $atts,
			'french_path_enrol'
		);

		// The registration fee is its own product, not part of any package, so
		// the plugin has to be told which one. It is normally chosen once on
		// the settings screen; the attribute overrides that for a single card,
		// and the literal reg_was and reg_now override both.
		$fee_product = absint( $atts['reg_product'] );

		if ( ! $fee_product ) {
			$fee_product = French_Path_Settings::registration_product();
		}

		if ( $fee_product && '' === (string) $atts['reg_was'] && '' === (string) $atts['reg_now'] ) {
			$fee = French_Path_Package::get_promotional_price( $fee_product );

			if ( '' !== $fee['now'] ) {
				$atts['reg_was'] = $fee['was'];
				$atts['reg_now'] = $fee['now'];
			}
		}

		$course_id = absint( $atts['course'] );

		if ( ! $course_id ) {
			$course_id = (int) get_the_ID();
		}

		if ( ! $course_id || 'sfwd-courses' !== get_post_type( $course_id ) ) {
			return '';
		}

		$user_id = get_current_user_id();
		$pathway = French_Path_Pathway::for_course( $user_id, $course_id );

		French_Path_Public::enqueue_assets();

		$view = array(
			'course_id' => $course_id,
			'user_id'   => $user_id,
			'atts'      => $atts,
			'pathway'   => $pathway,
		);

		if ( null === $pathway ) {
			$view['options']    = French_Path_Pathway::buying_options( $course_id );
			$view['from_price'] = French_Path_Pathway::from_price_html( $course_id );
		} else {
			$view['resume_url'] = $pathway['unlocked']
				? French_Path_Pathway::resume_url( $user_id, $course_id )
				: '';
		}

		ob_start();

		French_Path_Templates::render( 'enrol-card', $view );

		return (string) ob_get_clean();
	}

	/**
	 * The learner's pathway: everything they paid for, open or closed.
	 *
	 * This is what makes a locked sublevel visible. A sublevel the learner
	 * has paid for but cannot enter yet is shown in place, marked as paid,
	 * with the reason it is closed.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function render_my_courses( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'title'      => '',
				'class'      => '',
				'show_empty' => 'yes',
			),
			(array) $atts,
			'french_path_my_courses'
		);

		if ( ! is_user_logged_in() ) {
			return self::message( __( 'Sign in to see the sublevels you have access to.', 'french-path' ), $atts['class'] );
		}

		$user_id  = get_current_user_id();
		$packages = French_Path_Entitlement::get_by_package( $user_id );

		if ( ! $packages ) {
			if ( 'yes' !== $atts['show_empty'] ) {
				return '';
			}

			return self::message( __( 'You have not bought a sublevel yet.', 'french-path' ), $atts['class'] );
		}

		French_Path_Public::enqueue_assets();

		ob_start();

		French_Path_Templates::render(
			'my-courses',
			array(
				'user_id'  => $user_id,
				'packages' => $packages,
				'atts'     => $atts,
			)
		);

		return (string) ob_get_clean();
	}

	/**
	 * A single line of front end text in the plugin's wrapper.
	 *
	 * @param string $text  Message.
	 * @param string $class Extra class from the shortcode.
	 * @return string
	 */
	private static function message( $text, $class = '' ) {
		French_Path_Public::enqueue_assets();

		return sprintf(
			'<div class="french-path %1$s"><p class="french-path-note">%2$s</p></div>',
			esc_attr( trim( (string) $class ) ),
			esc_html( $text )
		);
	}

	/**
	 * What to call a course in a button or a heading.
	 *
	 * Prefers the short name the site keeps in the course_short_name custom
	 * field, so a button reads "Enrol in A1.1" rather than the full course
	 * title. Falls back to the title, because the field is empty on most
	 * courses and an empty label would leave the button reading "Enrol in".
	 *
	 * @param int $course_id LearnDash course.
	 * @return string
	 */
	public static function course_label( $course_id ) {
		$course_id = absint( $course_id );
		$label     = trim( (string) get_post_meta( $course_id, 'course_short_name', true ) );

		if ( '' === $label ) {
			$label = (string) get_the_title( $course_id );
		}

		/**
		 * Filters the short label used for a course.
		 *
		 * @param string $label     What to call the course.
		 * @param int    $course_id LearnDash course.
		 */
		return (string) apply_filters( 'french_path_course_label', $label, $course_id );
	}

	/**
	 * Why a sublevel the learner has paid for is not open yet.
	 *
	 * @param int $course_id LearnDash course.
	 * @param int $user_id   Learner.
	 * @return string
	 */
	public static function locked_reason( $course_id = 0, $user_id = 0 ) {
		/**
		 * Filters the reason shown against a locked sublevel.
		 *
		 * @param string $reason    The sentence shown to the learner.
		 * @param int    $course_id LearnDash course.
		 * @param int    $user_id   Learner.
		 */
		return (string) apply_filters(
			'french_path_locked_reason',
			__( 'Paid for. It opens once your coordinator releases it.', 'french-path' ),
			(int) $course_id,
			(int) $user_id
		);
	}
}
