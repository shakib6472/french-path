<?php
/**
 * Settings screen.
 *
 * @package French_Path
 *
 * @var bool  $saved        Whether the form was just saved.
 * @var int   $registration Currently chosen registration fee product.
 * @var array $products     Product ID => title.
 * @var array $fee          was and now, formatted, for the chosen product.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap french-path-screen">

	<h1><?php esc_html_e( 'French Path settings', 'french-path' ); ?></h1>

	<?php if ( $saved ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'french-path' ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( French_Path_Admin_Settings::NONCE_ACTION ); ?>
		<input type="hidden" name="action" value="<?php echo esc_attr( French_Path_Admin_Settings::POST_ACTION ); ?>">

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="french-path-registration-product"><?php esc_html_e( 'Registration fee product', 'french-path' ); ?></label>
				</th>
				<td>
					<?php if ( ! $products ) : ?>

						<p class="french-path-warn"><?php esc_html_e( 'No WooCommerce products were found.', 'french-path' ); ?></p>

					<?php else : ?>

						<select name="registration_product" id="french-path-registration-product" class="regular-text">
							<option value="0"><?php esc_html_e( '— Do not show a registration fee —', 'french-path' ); ?></option>
							<?php foreach ( $products as $product_id => $title ) : ?>
								<option value="<?php echo esc_attr( (string) $product_id ); ?>" <?php selected( $registration, $product_id ); ?>>
									<?php echo esc_html( $title ); ?>
								</option>
							<?php endforeach; ?>
						</select>

						<p class="description">
							<?php esc_html_e( 'The one-off fee shown on the enrol card, above the buttons. It is a product of its own, not part of any package, which is why it has to be named here.', 'french-path' ); ?>
						</p>

						<?php if ( $registration ) : ?>
							<p class="french-path-fee-state">
								<?php if ( '' !== $fee['was'] ) : ?>
									<strong><?php esc_html_e( 'A promotion is running.', 'french-path' ); ?></strong>
									<?php
									printf(
										/* translators: 1: normal fee, 2: promotional fee. */
										esc_html__( 'The card will show %1$s struck through beside %2$s.', 'french-path' ),
										wp_kses_post( $fee['was'] ),
										wp_kses_post( $fee['now'] )
									);
									?>
								<?php elseif ( '' !== $fee['now'] ) : ?>
									<strong><?php esc_html_e( 'No promotion is running.', 'french-path' ); ?></strong>
									<?php
									printf(
										/* translators: %s: the fee. */
										esc_html__( 'The card will show %s on its own. Put a sale price on the product to start a promotion.', 'french-path' ),
										wp_kses_post( $fee['now'] )
									);
									?>
								<?php else : ?>
									<span class="french-path-warn">
										<?php esc_html_e( 'That product has no price, so nothing will be shown on the card.', 'french-path' ); ?>
									</span>
								<?php endif; ?>
							</p>
						<?php endif; ?>

					<?php endif; ?>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Save settings', 'french-path' ) ); ?>
	</form>

	<h2><?php esc_html_e( 'How the fee is edited', 'french-path' ); ?></h2>

	<p class="french-path-help">
		<?php esc_html_e( 'Change the amount on the product itself, not here. The regular price is the normal fee. Add a sale price to run a promotion, and remove it to end one; the enrol card follows either way, with no shortcode to edit.', 'french-path' ); ?>
	</p>

</div>
