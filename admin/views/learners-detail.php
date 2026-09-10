<?php
/**
 * One learner: their packages, the ladder, and the release controls.
 *
 * @package French_Path
 *
 * @var WP_User $user     The learner.
 * @var int     $user_id  Learner ID.
 * @var array   $packages Package ID => entitlement rows, ladder in order.
 * @var string  $message  Notice to show.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap french-path-screen">

	<h1>
		<?php echo esc_html( $user->display_name ); ?>
		<a href="<?php echo esc_url( French_Path_Learners::url() ); ?>" class="page-title-action">
			<?php esc_html_e( 'All learners', 'french-path' ); ?>
		</a>
	</h1>

	<?php if ( $message ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
	<?php endif; ?>

	<p class="french-path-help">
		<?php echo esc_html( $user->user_email ); ?>
		<?php if ( French_Path_Entitlement::has_guarantee( $user_id ) ) : ?>
			&middot; <strong><?php esc_html_e( 'Holds the examination fee guarantee', 'french-path' ); ?></strong>
		<?php endif; ?>
	</p>

	<?php if ( ! $packages ) : ?>

		<p><?php esc_html_e( 'This learner has no live entitlement.', 'french-path' ); ?></p>

	<?php endif; ?>

	<?php foreach ( $packages as $package_id => $rows ) : ?>
		<?php
		$package_title = $package_id ? get_the_title( $package_id ) : __( 'Granted outside a package', 'french-path' );
		$has_locked    = false;

		foreach ( $rows as $row ) {
			if ( ! (int) $row->unlocked ) {
				$has_locked = true;
				break;
			}
		}
		?>

		<div class="french-path-package">

			<h2 class="french-path-package-title">
				<?php if ( $package_id ) : ?>
					<a href="<?php echo esc_url( (string) get_edit_post_link( $package_id ) ); ?>">
						<?php echo esc_html( $package_title ); ?>
					</a>
				<?php else : ?>
					<?php echo esc_html( $package_title ); ?>
				<?php endif; ?>

				<?php if ( $package_id && French_Path_Package::has_guarantee( $package_id ) ) : ?>
					<span class="french-path-badge"><?php esc_html_e( 'Guarantee', 'french-path' ); ?></span>
				<?php endif; ?>
			</h2>

			<table class="wp-list-table widefat striped">
				<thead>
					<tr>
						<th scope="col" class="french-path-col-step">#</th>
						<th scope="col"><?php esc_html_e( 'Sublevel', 'french-path' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'french-path' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Released', 'french-path' ); ?></th>
						<th scope="col" class="french-path-col-action"><?php esc_html_e( 'Action', 'french-path' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $index => $row ) : ?>
						<?php
						$course_id  = (int) $row->course_id;
						$is_open    = (bool) (int) $row->unlocked;
						$released   = $row->unlocked_at ? mysql2date( get_option( 'date_format' ), $row->unlocked_at ) : '';
						$actor      = (int) $row->unlocked_by ? get_userdata( (int) $row->unlocked_by ) : null;
						$next_do    = $is_open ? 'lock' : 'unlock';
						$next_label = $is_open ? __( 'Close', 'french-path' ) : __( 'Release', 'french-path' );
						?>
						<tr>
							<td><?php echo esc_html( (string) ( $index + 1 ) ); ?></td>
							<td>
								<strong><?php echo esc_html( get_the_title( $course_id ) ); ?></strong>
							</td>
							<td>
								<?php if ( $is_open ) : ?>
									<span class="french-path-open"><?php esc_html_e( 'Open', 'french-path' ); ?></span>
								<?php else : ?>
									<span class="french-path-locked"><?php esc_html_e( 'Locked', 'french-path' ); ?></span>
									<span class="french-path-help"><?php esc_html_e( 'paid for', 'french-path' ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( $released ) : ?>
									<?php echo esc_html( $released ); ?>
									<?php if ( $actor ) : ?>
										<span class="french-path-help"><?php echo esc_html( $actor->display_name ); ?></span>
									<?php endif; ?>
								<?php else : ?>
									&mdash;
								<?php endif; ?>
							</td>
							<td>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<?php wp_nonce_field( French_Path_Learners::NONCE_ACTION ); ?>
									<input type="hidden" name="action" value="<?php echo esc_attr( French_Path_Learners::POST_ACTION ); ?>">
									<input type="hidden" name="do" value="<?php echo esc_attr( $next_do ); ?>">
									<input type="hidden" name="learner" value="<?php echo esc_attr( (string) $user_id ); ?>">
									<input type="hidden" name="course" value="<?php echo esc_attr( (string) $course_id ); ?>">
									<button type="submit" class="button button-small">
										<?php echo esc_html( $next_label ); ?>
									</button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ( $package_id && $has_locked ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="french-path-release-next">
					<?php wp_nonce_field( French_Path_Learners::NONCE_ACTION ); ?>
					<input type="hidden" name="action" value="<?php echo esc_attr( French_Path_Learners::POST_ACTION ); ?>">
					<input type="hidden" name="do" value="release_next">
					<input type="hidden" name="learner" value="<?php echo esc_attr( (string) $user_id ); ?>">
					<input type="hidden" name="package" value="<?php echo esc_attr( (string) $package_id ); ?>">
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Release next sublevel', 'french-path' ); ?>
					</button>
					<span class="french-path-help">
						<?php esc_html_e( 'Opens the first sublevel in this ladder that is still closed.', 'french-path' ); ?>
					</span>
				</form>
			<?php endif; ?>

		</div>
	<?php endforeach; ?>

</div>
