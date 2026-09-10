<?php
/**
 * The learner's pathway.
 *
 * Override by copying this file to yourtheme/french-path/my-courses.php
 *
 * @package French_Path
 *
 * @var int   $user_id  Learner.
 * @var array $packages Package ID => entitlement rows, ladder in order.
 * @var array $atts     Shortcode attributes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="french-path french-path-pathway <?php echo esc_attr( trim( (string) $atts['class'] ) ); ?>">

	<?php if ( ! empty( $atts['title'] ) ) : ?>
		<h2 class="french-path-pathway-title"><?php echo esc_html( $atts['title'] ); ?></h2>
	<?php endif; ?>

	<?php foreach ( $packages as $package_id => $rows ) : ?>
		<section class="french-path-group">

			<?php if ( $package_id ) : ?>
				<h3 class="french-path-group-title"><?php echo esc_html( get_the_title( $package_id ) ); ?></h3>
			<?php endif; ?>

			<ol class="french-path-steps">
				<?php foreach ( $rows as $row ) : ?>
					<?php
					$course_id = (int) $row->course_id;
					$title     = get_the_title( $course_id );

					if ( '' === $title ) {
						continue;
					}

					$is_open = (bool) (int) $row->unlocked;
					?>
					<li class="french-path-step <?php echo $is_open ? 'is-open' : 'is-locked'; ?>">

						<span class="french-path-step-main">
							<?php if ( $is_open ) : ?>
								<a class="french-path-step-title" href="<?php echo esc_url( (string) get_permalink( $course_id ) ); ?>">
									<?php echo esc_html( $title ); ?>
								</a>
							<?php else : ?>
								<span class="french-path-step-title" aria-disabled="true"><?php echo esc_html( $title ); ?></span>
							<?php endif; ?>

							<span class="french-path-step-status">
								<?php
								echo $is_open
									? esc_html__( 'Open', 'french-path' )
									: esc_html__( 'Locked', 'french-path' );
								?>
							</span>
						</span>

						<?php if ( ! $is_open ) : ?>
							<span class="french-path-step-reason">
								<?php echo esc_html( French_Path_Shortcodes::locked_reason( $course_id, $user_id ) ); ?>
							</span>
						<?php endif; ?>

					</li>
				<?php endforeach; ?>
			</ol>

		</section>
	<?php endforeach; ?>

</div>
