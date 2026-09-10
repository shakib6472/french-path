<?php
/**
 * Ordered sublevel picker for a package.
 *
 * @package French_Path
 *
 * @var WP_Post $post     Package being edited.
 * @var int[]   $selected Course IDs already in the package, in order.
 * @var array   $courses  Course ID => title for every LearnDash course.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="french-path-box">

	<?php if ( ! $courses ) : ?>

		<p class="french-path-warn">
			<?php esc_html_e( 'No LearnDash courses were found. Activate LearnDash and create a course first.', 'french-path' ); ?>
		</p>

	<?php else : ?>

		<p class="french-path-help">
			<?php esc_html_e( 'The order is the ladder. A learner starts on the first sublevel; the rest stay paid for but closed until a coordinator releases them.', 'french-path' ); ?>
		</p>

		<ol class="french-path-list" id="french-path-course-list">
			<?php foreach ( $selected as $course_id ) : ?>
				<li class="french-path-item" data-course="<?php echo esc_attr( (string) $course_id ); ?>">
					<input type="hidden" name="french_path_courses[]" value="<?php echo esc_attr( (string) $course_id ); ?>">
					<span class="french-path-item-title">
						<?php echo esc_html( isset( $courses[ $course_id ] ) ? $courses[ $course_id ] : sprintf( /* translators: %d: course ID. */ __( 'Missing course #%d', 'french-path' ), $course_id ) ); ?>
					</span>
					<span class="french-path-item-actions">
						<button type="button" class="button-link french-path-up" aria-label="<?php esc_attr_e( 'Move up', 'french-path' ); ?>">&uarr;</button>
						<button type="button" class="button-link french-path-down" aria-label="<?php esc_attr_e( 'Move down', 'french-path' ); ?>">&darr;</button>
						<button type="button" class="button-link french-path-remove" aria-label="<?php esc_attr_e( 'Remove', 'french-path' ); ?>">&times;</button>
					</span>
				</li>
			<?php endforeach; ?>
		</ol>

		<p class="french-path-empty" id="french-path-course-empty" <?php echo $selected ? 'hidden' : ''; ?>>
			<?php esc_html_e( 'No sublevels yet. Add the first one below.', 'french-path' ); ?>
		</p>

		<p class="french-path-add">
			<label class="screen-reader-text" for="french-path-course-add">
				<?php esc_html_e( 'Sublevel to add', 'french-path' ); ?>
			</label>
			<select id="french-path-course-add">
				<?php foreach ( $courses as $course_id => $title ) : ?>
					<option value="<?php echo esc_attr( (string) $course_id ); ?>"><?php echo esc_html( $title ); ?></option>
				<?php endforeach; ?>
			</select>
			<button type="button" class="button" id="french-path-course-add-button">
				<?php esc_html_e( 'Add sublevel', 'french-path' ); ?>
			</button>
		</p>

	<?php endif; ?>

</div>
