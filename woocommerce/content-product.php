<?php
/**
 * Product content for loops
 */

defined('ABSPATH') || exit;

global $product;

if (empty($product) || !$product->is_visible()) {
  return;
}
?>

<li <?php wc_product_class('ss-product-card', $product); ?>>
  <a class="ss-product-link" href="<?php the_permalink(); ?>">
    <div class="ss-product-media">
      <?php
      /**
       * Hook: woocommerce_before_shop_loop_item_title.
       *
       * @hooked woocommerce_show_product_loop_sale_flash - 10
       * @hooked woocommerce_template_loop_product_thumbnail - 10
       */
      do_action('woocommerce_before_shop_loop_item_title');
      ?>
    </div>

    <div class="ss-product-meta">
      <h2 class="ss-product-title"><?php the_title(); ?></h2>
      <div class="ss-product-price"><?php echo $product->get_price_html(); ?></div>
    </div>
  </a>
</li>
