<?php
/**
 * The ways to buy a course, shown when the enrol button is pressed.
 *
 * Override by copying this file to yourtheme/french-path/enrol-packages.php
 *
 * @package French_Path
 *
 * @var int    $course_id Course being viewed.
 * @var array  $options   Ways to buy, cheapest first.
 * @var string $dialog_id Element id the enrol button targets.
 * @var array  $atts      Shortcode attributes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$fp_title_id = $dialog_id . '-title';
?>

<dialog class="french-path french-path-dialog" id="<?php echo esc_attr( $dialog_id ); ?>" aria-labelledby="<?php echo esc_attr( $fp_title_id ); ?>">

	<div class="french-path-dialog-head">
		<h2 class="french-path-dialog-title" id="<?php echo esc_attr( $fp_title_id ); ?>">
			<?php
			printf(
				/* translators: %s: course title. */
				esc_html__( 'Ways to get %s', 'french-path' ),
				esc_html( get_the_title( $course_id ) )
			);
			?>
		</h2>
		<p class="french-path-dialog-lede">
			<?php esc_html_e( 'Every option below includes this sublevel. You only ever pay once for a sublevel.', 'french-path' ); ?>
		</p>
	</div>

	<div class="french-path-options">
		<?php foreach ( $options as $option ) : ?>
			<div class="french-path-option <?php echo $option['guarantee'] ? 'is-guaranteed' : ''; ?>">

				<div class="french-path-option-name">
					<?php echo esc_html( $option['name'] ); ?>
					<?php if ( $option['guarantee'] ) : ?>
						<span class="french-path-option-badge"><?php esc_html_e( 'Exam guarantee', 'french-path' ); ?></span>
					<?php endif; ?>
				</div>

				<div class="french-path-option-meta">
					<?php
					printf(
						/* translators: %s: number of sublevels. */
						esc_html( _n( '%s sublevel', '%s sublevels', (int) $option['courses'], 'french-path' ) ),
						esc_html( number_format_i18n( (int) $option['courses'] ) )
					);
					?>
					<?php if ( (int) $option['courses'] > 1 ) : ?>
						&middot; <?php esc_html_e( 'released one at a time', 'french-path' ); ?>
					<?php endif; ?>
				</div>

				<div class="french-path-option-price"><?php echo wp_kses_post( $option['price_html'] ); ?></div>

				<a class="french-path-option-buy" href="<?php echo esc_url( $option['url'] ); ?>">
					<?php esc_html_e( 'Choose', 'french-path' ); ?>
				</a>

			</div>
		<?php endforeach; ?>
	</div>

	<div class="french-path-dialog-foot">
		<span>
			<?php
			echo esc_html(
				'' !== (string) $atts['note']
					? (string) $atts['note']
					: __( 'You choose your stream at checkout.', 'french-path' )
			);
			?>
		</span>
		<button type="button" class="french-path-dialog-close" data-french-path-close>
			<?php esc_html_e( 'Close', 'french-path' ); ?>
		</button>
	</div>

</dialog>
