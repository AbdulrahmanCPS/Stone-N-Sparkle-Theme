<?php
/**
 * Product Category Archive Template
 * 
 * Shows lookbook images for the category followed by products in a grid
 */

defined('ABSPATH') || exit;

get_header();

// Get current category
$current_cat = get_queried_object();
$cat_id = $current_cat->term_id;
?>

<main id="primary" class="ss-main ss-category-archive" role="main">
  
  <?php
  // Check if this category has lookbook images defined in ACF
  $lookbook_images = [];
  
  // Try to get ACF lookbook images for this category (you'll need to set these up in ACF)
  // For now, we'll create a fallback that checks for custom fields
  for ($i = 1; $i <= 6; $i++) {
    $img_url = get_term_meta($cat_id, 'lookbook_image_' . $i, true);
    if ($img_url) {
      $lookbook_images[] = $img_url;
    }
  }
  
  // If we have lookbook images, display them
  if (!empty($lookbook_images)) :
  ?>
    <section class="ss-category-lookbook" aria-label="<?php echo esc_attr($current_cat->name); ?> Lookbook">
      <div class="ss-category-lookbook-grid">
        <?php foreach ($lookbook_images as $img_url) : ?>
          <div class="ss-category-lookbook-item">
            <div class="ss-category-lookbook-media">
              <img src="<?php echo esc_url($img_url); ?>" alt="<?php echo esc_attr($current_cat->name); ?>" loading="lazy">
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <!-- Category Title -->
  <section class="ss-category-header">
    <div class="ss-container">
      <h1 class="ss-category-title"><?php echo esc_html($current_cat->name); ?></h1>
      <?php if ($current_cat->description) : ?>
        <div class="ss-category-description">
          <?php echo wpautop(wp_kses_post($current_cat->description)); ?>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- Products Grid -->
  <section class="ss-category-products" aria-label="Products">
    <div class="ss-container">
      <?php
      /**
       * Hook: woocommerce_before_main_content.
       */
      do_action('woocommerce_before_main_content');

      if (woocommerce_product_loop()) {
        /**
         * Hook: woocommerce_before_shop_loop.
         */
        do_action('woocommerce_before_shop_loop');

        woocommerce_product_loop_start();

        if (wc_get_loop_prop('total')) {
          while (have_posts()) {
            the_post();
            wc_get_template_part('content', 'product');
          }
        }

        woocommerce_product_loop_end();

        /**
         * Hook: woocommerce_after_shop_loop.
         */
        do_action('woocommerce_after_shop_loop');
      } else {
        /**
         * Hook: woocommerce_no_products_found.
         */
        do_action('woocommerce_no_products_found');
      }

      /**
       * Hook: woocommerce_after_main_content.
       */
      do_action('woocommerce_after_main_content');
      ?>
    </div>
  </section>
</main>

<?php
get_footer();
