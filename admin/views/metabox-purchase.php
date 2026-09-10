<?php
/**
 * Product, kind and guarantee controls for a package.
 *
 * @package French_Path
 *
 * @var WP_Post $post      Package being edited.
 * @var int     $product   Currently linked product ID.
 * @var string  $kind      Current package kind.
 * @var array   $kinds     Kind key => label.
 * @var bool    $guarantee Whether the guarantee applies.
 * @var array   $products  Product ID => title.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="french-path-box">

	<p>
		<label for="french-path-product"><strong><?php esc_html_e( 'Product', 'french-path' ); ?></strong></label><br>

		<?php if ( ! $products ) : ?>
			<span class="french-path-warn"><?php esc_html_e( 'No WooCommerce products found.', 'french-path' ); ?></span>
		<?php else : ?>
			<select name="french_path_product" id="french-path-product" class="widefat">
				<option value="0"><?php esc_html_e( '— Not for sale —', 'french-path' ); ?></option>
				<?php foreach ( $products as $product_id => $title ) : ?>
					<option value="<?php echo esc_attr( (string) $product_id ); ?>" <?php selected( $product, $product_id ); ?>>
						<?php echo esc_html( $title ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		<?php endif; ?>

		<span class="french-path-help">
			<?php esc_html_e( 'Buying this product grants every sublevel above. Leave the product\'s own LearnDash course field empty, or the learner will be enrolled in all of them at once.', 'french-path' ); ?>
		</span>
	</p>

	<p>
		<label for="french-path-kind"><strong><?php esc_html_e( 'Kind', 'french-path' ); ?></strong></label><br>
		<select name="french_path_kind" id="french-path-kind" class="widefat">
			<?php foreach ( $kinds as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $kind, $key ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<span class="french-path-help">
			<?php esc_html_e( 'Used to group the buying options shown on a course page.', 'french-path' ); ?>
		</span>
	</p>

	<p>
		<label for="french-path-guarantee">
			<input type="checkbox" name="french_path_guarantee" id="french-path-guarantee" value="1" <?php checked( $guarantee ); ?>>
			<strong><?php esc_html_e( 'Carries the examination fee guarantee', 'french-path' ); ?></strong>
		</label>
		<span class="french-path-help">
			<?php esc_html_e( 'Tick only for the complete pathway. Every entitlement this package writes is stamped with it, which is what proves eligibility when a claim is reviewed.', 'french-path' ); ?>
		</span>
	</p>

</div>
