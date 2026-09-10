<?php
/**
 * Which course pages a package is offered on.
 *
 * @package French_Path
 *
 * @var WP_Post $post    Package being edited.
 * @var string  $mode    Current mode.
 * @var int[]   $show_on Currently ticked course IDs.
 * @var int[]   $ladder  The package's own sublevels, in order.
 * @var array   $courses Course ID => title for every LearnDash course.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$fp_first = $ladder ? (int) $ladder[0] : 0;
?>

<div class="french-path-box">

	<?php if ( ! $courses ) : ?>

		<p class="french-path-warn">
			<?php esc_html_e( 'No LearnDash courses were found.', 'french-path' ); ?>
		</p>

	<?php else : ?>

		<p class="french-path-help">
			<?php esc_html_e( 'Somebody opening a course page came to start there. A package that would put them somewhere earlier is not an answer to what they asked for, so a package is normally offered only on the sublevel it opens on.', 'french-path' ); ?>
		</p>

		<p class="french-path-choice">
			<label>
				<input type="radio" name="french_path_show_on_mode" value="<?php echo esc_attr( French_Path_Package::SHOW_ON_FIRST ); ?>" <?php checked( $mode, French_Path_Package::SHOW_ON_FIRST ); ?>>
				<strong><?php esc_html_e( 'Only the sublevel it starts on', 'french-path' ); ?></strong>
				<?php if ( $fp_first ) : ?>
					<span class="french-path-help">
						<?php
						printf(
							/* translators: %s: course title. */
							esc_html__( 'That is %s.', 'french-path' ),
							esc_html( get_the_title( $fp_first ) )
						);
						?>
					</span>
				<?php else : ?>
					<span class="french-path-help">
						<?php esc_html_e( 'Add sublevels above and this will follow the first one automatically.', 'french-path' ); ?>
					</span>
				<?php endif; ?>
			</label>
		</p>

		<p class="french-path-choice">
			<label>
				<input type="radio" name="french_path_show_on_mode" value="<?php echo esc_attr( French_Path_Package::SHOW_ON_CHOSEN ); ?>" <?php checked( $mode, French_Path_Package::SHOW_ON_CHOSEN ); ?>>
				<strong><?php esc_html_e( 'These course pages', 'french-path' ); ?></strong>
				<span class="french-path-help">
					<?php esc_html_e( 'Tick every page that should offer this package. Use it to advertise a package ahead of time, on a page it does not start from.', 'french-path' ); ?>
				</span>
			</label>
		</p>

		<div class="french-path-pages">
			<?php foreach ( $courses as $course_id => $title ) : ?>
				<?php $fp_in = in_array( (int) $course_id, $ladder, true ); ?>
				<label class="french-path-page <?php echo $fp_in ? 'is-inside' : ''; ?>">
					<input
						type="checkbox"
						name="french_path_show_on[]"
						value="<?php echo esc_attr( (string) $course_id ); ?>"
						<?php checked( in_array( (int) $course_id, $show_on, true ) ); ?>
					>
					<span class="french-path-page-title"><?php echo esc_html( $title ); ?></span>
					<?php if ( (int) $course_id === $fp_first ) : ?>
						<span class="french-path-page-tag"><?php esc_html_e( 'starts here', 'french-path' ); ?></span>
					<?php elseif ( $fp_in ) : ?>
						<span class="french-path-page-tag is-quiet"><?php esc_html_e( 'in package', 'french-path' ); ?></span>
					<?php endif; ?>
				</label>
			<?php endforeach; ?>
		</div>

	<?php endif; ?>

</div>
