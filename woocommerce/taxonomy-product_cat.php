<?php
/**
 * Product Category Archive Template
 *
 * Editorial layout: 2 top images → category header → products (max 20 first batch).
 * If >20 products: first 20 → 3 images → remaining products → 3 trailing images (8 images total).
 * If ≤20 products: stack images 3–8 vertically, then all products.
 */

defined('ABSPATH') || exit;

get_header();

$current_cat = get_queried_object();
if (!($current_cat instanceof WP_Term) || $current_cat->taxonomy !== 'product_cat') {
    // Fallback: let WooCommerce handle it
    do_action('woocommerce_before_main_content');
    if (woocommerce_product_loop()) {
        do_action('woocommerce_before_shop_loop');
        woocommerce_product_loop_start();
        if (wc_get_loop_prop('total')) {
            while (have_posts()) {
                the_post();
                wc_get_template_part('content', 'product');
            }
        }
        woocommerce_product_loop_end();
        do_action('woocommerce_after_shop_loop');
    } else {
        do_action('woocommerce_no_products_found');
    }
    do_action('woocommerce_after_main_content');
    get_footer();
    return;
}

$cat_id   = $current_cat->term_id;
$images   = ss_get_category_archive_images($cat_id);
$total    = ss_get_category_product_count($cat_id);
?>
<main id="primary" class="ss-main ss-category-archive" role="main">
  <?php do_action('woocommerce_before_main_content'); ?>

  <?php ss_render_top_images($images, $current_cat->name); ?>

  <?php
  if ($total > 20) {
      // >20: first 20 → 3 mid images → remaining products → 3 trailing images (hooks once per archive)
      do_action('woocommerce_before_shop_loop');
      ?>
  <section class="ss-category-products" aria-label="Products">
    <div class="ss-container">
      <?php ss_render_first_product_batch($cat_id, 20); ?>
    </div>
  </section>
  <?php ss_render_mid_images($images, $current_cat->name); ?>
  <section class="ss-category-products" aria-label="Products">
    <div class="ss-container">
      <?php ss_render_remaining_products($cat_id, 20); ?>
    </div>
  </section>
  <?php
      do_action('woocommerce_after_shop_loop');
      ss_render_trailing_images($images, $current_cat->name);
  } else {
      // ≤20: stack images 3–8, then all products
      ss_render_category_image_stack($images, 2, 7, $current_cat->name);
      do_action('woocommerce_before_shop_loop');
      ?>
  <section class="ss-category-products" aria-label="Products">
    <div class="ss-container">
      <?php
      if ($total > 0) {
          ss_render_first_product_batch($cat_id, 999);
      } else {
          do_action('woocommerce_no_products_found');
      }
      ?>
    </div>
  </section>
  <?php
      do_action('woocommerce_after_shop_loop');
  }
  ?>

  <?php do_action('woocommerce_after_main_content'); ?>
</main>
<?php
get_footer();
