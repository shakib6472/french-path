<?php
/**
 * The enrol card on a single course page.
 *
 * Override by copying this file to yourtheme/french-path/enrol-card.php
 *
 * @package French_Path
 *
 * @var int        $course_id  Course being viewed.
 * @var int        $user_id    Current user, 0 when logged out.
 * @var array      $atts       Shortcode attributes.
 * @var array|null $pathway    Ladder position, or null when not paid for.
 * @var array      $options    Ways to buy. Only set when $pathway is null.
 * @var string     $from_price Lowest price. Only set when $pathway is null.
 * @var string     $resume_url Where to carry on. Only set when unlocked.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$fp_classes = trim( 'french-path french-path-enrol ' . (string) $atts['class'] );
$fp_dialog  = 'french-path-packages-' . (int) $course_id;
?>

<div class="<?php echo esc_attr( $fp_classes ); ?>">

	<?php if ( null === $pathway ) : ?>

		<?php /* State 1: nobody has paid for this sublevel yet. */ ?>

		<?php if ( $from_price ) : ?>
			<div class="french-path-enrol-block">
				<span class="french-path-label"><?php esc_html_e( 'From', 'french-path' ); ?></span>
				<div class="french-path-price"><?php echo wp_kses_post( $from_price ); ?></div>
			</div>
		<?php endif; ?>

		<?php if ( '' !== (string) $atts['reg_was'] || '' !== (string) $atts['reg_now'] ) : ?>
			<div class="french-path-enrol-block">
				<span class="french-path-label">
					<?php
					echo '' !== (string) $atts['reg_was']
						? esc_html__( 'Registration promotion', 'french-path' )
						: esc_html__( 'Registration fee', 'french-path' );
					?>
				</span>
				<div class="french-path-promo">
					<?php if ( '' !== (string) $atts['reg_was'] ) : ?>
						<s><?php echo wp_kses_post( (string) $atts['reg_was'] ); ?></s>
					<?php endif; ?>
					<?php if ( '' !== (string) $atts['reg_now'] ) : ?>
						<b><?php echo wp_kses_post( (string) $atts['reg_now'] ); ?></b>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>

		<div class="french-path-actions">
			<?php if ( $options ) : ?>
				<button
					type="button"
					class="french-path-btn french-path-btn-primary"
					data-french-path-open="<?php echo esc_attr( $fp_dialog ); ?>"
				>
					<?php
					printf(
						/* translators: %s: short name of the course, e.g. A1.1. */
						esc_html__( 'Enrol in %s', 'french-path' ),
						esc_html( French_Path_Shortcodes::course_label( $course_id ) )
					);
					?>
				</button>
			<?php else : ?>
				<span class="french-path-btn" aria-disabled="true">
					<?php esc_html_e( 'Not on sale yet', 'french-path' ); ?>
				</span>
			<?php endif; ?>

			<?php if ( '' !== (string) $atts['placement_url'] ) : ?>
				<a class="french-path-btn french-path-btn-ghost" href="<?php echo esc_url( (string) $atts['placement_url'] ); ?>">
					<?php esc_html_e( 'Take the placement test', 'french-path' ); ?>
				</a>
			<?php endif; ?>
		</div>

		<p class="french-path-note">
			<?php
			echo esc_html(
				'' !== (string) $atts['note']
					? (string) $atts['note']
					: __( 'You choose your stream at checkout.', 'french-path' )
			);
			?>
		</p>

	<?php else : ?>

		<?php /* States 2 and 3: the learner has paid. No price is ever shown again. */ ?>

		<div class="french-path-head">
			<span class="french-path-label"><?php esc_html_e( 'Your pathway', 'french-path' ); ?></span>

			<?php if ( $pathway['total'] > 1 ) : ?>
				<span class="french-path-count">
					<?php
					printf(
						/* translators: 1: current step number, 2: total steps. */
						esc_html__( 'Step %1$s of %2$s', 'french-path' ),
						esc_html( number_format_i18n( $pathway['step'] ) ),
						esc_html( number_format_i18n( $pathway['total'] ) )
					);
					?>
				</span>
			<?php endif; ?>
		</div>

		<?php if ( $pathway['package_id'] ) : ?>
			<p class="french-path-package-name"><?php echo esc_html( get_the_title( $pathway['package_id'] ) ); ?></p>
		<?php endif; ?>

		<?php if ( $pathway['total'] > 1 ) : ?>

			<ol class="french-path-window">
				<?php foreach ( $pathway['window'] as $fp_rung ) : ?>
					<li class="french-path-rung <?php echo $fp_rung['current'] ? 'is-current ' : ''; ?><?php echo $fp_rung['unlocked'] ? 'is-open' : 'is-locked'; ?>">
						<span class="french-path-rung-node" aria-hidden="true"></span>
						<span class="french-path-rung-t"><?php echo esc_html( get_the_title( $fp_rung['id'] ) ); ?></span>
						<span class="french-path-rung-s">
							<?php
							if ( $fp_rung['current'] ) {
								esc_html_e( 'You are here', 'french-path' );
							} elseif ( $fp_rung['unlocked'] ) {
								esc_html_e( 'Open', 'french-path' );
							} else {
								esc_html_e( 'Locked', 'french-path' );
							}
							?>
						</span>
					</li>
				<?php endforeach; ?>
			</ol>

		<?php endif; ?>

		<?php if ( $pathway['unlocked'] ) : ?>

			<div class="french-path-actions">
				<a class="french-path-btn french-path-btn-go" href="<?php echo esc_url( $resume_url ); ?>">
					<?php esc_html_e( 'Start learning', 'french-path' ); ?>
				</a>
			</div>

			<p class="french-path-note"><?php esc_html_e( 'Continue where you left off.', 'french-path' ); ?></p>

		<?php else : ?>

			<div class="french-path-gate">
				<p class="french-path-gate-title">
					<span class="french-path-gate-icon" aria-hidden="true"></span>
					<?php esc_html_e( 'Waiting on your coordinator', 'french-path' ); ?>
				</p>
				<p class="french-path-gate-body">
					<?php echo esc_html( French_Path_Shortcodes::locked_reason( $course_id, $user_id ) ); ?>
				</p>
			</div>

		<?php endif; ?>

		<?php if ( '' !== (string) $atts['pathway_url'] && $pathway['total'] > 1 ) : ?>
			<p class="french-path-note french-path-pathway-link">
				<a href="<?php echo esc_url( (string) $atts['pathway_url'] ); ?>">
					<?php esc_html_e( 'See your full pathway', 'french-path' ); ?>
				</a>
			</p>
		<?php endif; ?>

	<?php endif; ?>

</div>

<?php if ( null === $pathway && ! empty( $options ) ) : ?>
	<?php
	French_Path_Templates::render(
		'enrol-packages',
		array(
			'course_id' => $course_id,
			'options'   => $options,
			'dialog_id' => $fp_dialog,
			'atts'      => $atts,
		)
	);
	?>
<?php endif; ?>
