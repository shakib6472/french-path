<?php
/**
 * Learner list.
 *
 * @package French_Path
 *
 * @var int[]  $learners User IDs on this page.
 * @var int    $total    Learners holding a live entitlement.
 * @var int    $paged    Current page.
 * @var int    $pages    Total pages.
 * @var string $search   Current search term.
 * @var string $message  Notice to show.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap french-path-screen">

	<h1><?php esc_html_e( 'Learners', 'french-path' ); ?></h1>

	<?php if ( $message ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
	<?php endif; ?>

	<p class="french-path-help">
		<?php esc_html_e( 'Everyone who has paid for at least one sublevel. Open a learner to release their next sublevel after assessment.', 'french-path' ); ?>
	</p>

	<form method="get" class="french-path-search">
		<input type="hidden" name="post_type" value="<?php echo esc_attr( French_Path_Package::POST_TYPE ); ?>">
		<input type="hidden" name="page" value="<?php echo esc_attr( French_Path_Learners::SLUG ); ?>">
		<label class="screen-reader-text" for="french-path-search"><?php esc_html_e( 'Search learners', 'french-path' ); ?></label>
		<input type="search" id="french-path-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Name or email', 'french-path' ); ?>">
		<button type="submit" class="button"><?php esc_html_e( 'Search', 'french-path' ); ?></button>
	</form>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Learner', 'french-path' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Email', 'french-path' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Paid for', 'french-path' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Open', 'french-path' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Guarantee', 'french-path' ); ?></th>
			</tr>
		</thead>
		<tbody>

			<?php if ( ! $learners ) : ?>
				<tr>
					<td colspan="5">
						<?php
						echo $search
							? esc_html__( 'No learner matches that search.', 'french-path' )
							: esc_html__( 'Nobody has bought a package yet.', 'french-path' );
						?>
					</td>
				</tr>
			<?php endif; ?>

			<?php foreach ( $learners as $learner_id ) : ?>
				<?php
				$learner_user = get_userdata( $learner_id );

				if ( ! $learner_user ) {
					continue;
				}

				$counts = French_Path_Learners::summary( $learner_id );
				?>
				<tr>
					<td>
						<strong>
							<a href="<?php echo esc_url( French_Path_Learners::url( array( 'learner' => $learner_id ) ) ); ?>">
								<?php echo esc_html( $learner_user->display_name ); ?>
							</a>
						</strong>
					</td>
					<td><?php echo esc_html( $learner_user->user_email ); ?></td>
					<td><?php echo esc_html( (string) $counts['entitled'] ); ?></td>
					<td><?php echo esc_html( (string) $counts['unlocked'] ); ?></td>
					<td>
						<?php
						echo French_Path_Entitlement::has_guarantee( $learner_id )
							? esc_html__( 'Yes', 'french-path' )
							: '&mdash;';
						?>
					</td>
				</tr>
			<?php endforeach; ?>

		</tbody>
	</table>

	<?php if ( $pages > 1 ) : ?>
		<div class="tablenav">
			<div class="tablenav-pages">
				<?php
				echo wp_kses_post(
					paginate_links(
						array(
							'base'      => French_Path_Learners::url(
								array(
									's'     => $search,
									'paged' => '%#%',
								)
							),
							'format'    => '',
							'current'   => $paged,
							'total'     => $pages,
							'prev_text' => '&laquo;',
							'next_text' => '&raquo;',
						)
					)
				);
				?>
			</div>
		</div>
	<?php endif; ?>

	<p class="french-path-help">
		<?php
		printf(
			/* translators: %d: number of learners. */
			esc_html( _n( '%d learner holds a live entitlement.', '%d learners hold a live entitlement.', $total, 'french-path' ) ),
			(int) $total
		);
		?>
	</p>

</div>
