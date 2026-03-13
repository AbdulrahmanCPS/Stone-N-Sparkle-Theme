<?php
/**
 * WooCommerce Product Archive (Shop)
 *
 * Layout: Top lookbook (images 1–2) → Products → Bottom lookbook (images 3–5)
 */

defined('ABSPATH') || exit;

get_header();

// ── Helper: get shop page ID ───────────────────────────────────────────────
$shop_page_id = function_exists('wc_get_page_id') ? (int) wc_get_page_id('shop') : 0;
if ($shop_page_id <= 0) {
    $shop_page_id = 8; // fallback to ACF field group location
}

$has_acf = function_exists('get_field');

/**
 * Render a single lookbook image slot.
 * Returns true if an image was rendered, false otherwise.
 */
function ss_render_lookbook_image($index, $shop_page_id, $has_acf) {
    if (!$has_acf) return false;

    $enable_key = ($index === 1) ? 'image_enable' : 'image_enable_' . $index;
    $enabled    = get_field($enable_key, $shop_page_id);

    // Disabled explicitly
    if ($enabled === false || (is_string($enabled) && $enabled === '0') || $enabled === 0) {
        return false;
    }

    $img     = get_field('image_' . $index, $shop_page_id);
    $img_url = is_array($img) && !empty($img['url']) ? $img['url'] : '';
    $img_alt = is_array($img) && !empty($img['alt']) ? $img['alt'] : 'Lookbook image ' . $index;

    if (empty($img_url)) return false;

    ?>
    <div class="ss-lookbook-item ss-lookbook-item--shop">
      <div class="ss-lookbook-card">
        <div class="ss-lookbook-media">
          <img
            src="<?php echo esc_url($img_url); ?>"
            alt="<?php echo esc_attr($img_alt); ?>"
            loading="lazy"
            decoding="async"
          >
        </div>
      </div>
    </div>
    <?php
    return true;
}
?>

<main id="primary" class="ss-main ss-shop" role="main">

  <?php
  // ── TOP LOOKBOOK: Images 1 & 2 ─────────────────────────────────────────
  $top_rendered = 0;
  ob_start();
  for ($i = 1; $i <= 2; $i++) {
      if (ss_render_lookbook_image($i, $shop_page_id, $has_acf)) {
          $top_rendered++;
      }
  }
  $top_html = ob_get_clean();

  if ($top_rendered > 0): ?>
  <section class="ss-shop-lookbook ss-shop-lookbook--top" aria-label="Lookbook Top">
    <div class="ss-container ss-lookbook-grid ss-lookbook-grid--<?php echo $top_rendered; ?>">
      <?php echo $top_html; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php
  // ── PRODUCTS GRID ───────────────────────────────────────────────────────
  ?>
  <section class="ss-shop-products" aria-label="Products">
    <div class="ss-container">
      <?php
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
      ?>
    </div>
  </section>

  <?php
  // ── BOTTOM LOOKBOOK: Images 3, 4 & 5 ───────────────────────────────────
  $bot_rendered = 0;
  ob_start();
  for ($i = 3; $i <= 5; $i++) {
      if (ss_render_lookbook_image($i, $shop_page_id, $has_acf)) {
          $bot_rendered++;
      }
  }
  $bot_html = ob_get_clean();

  if ($bot_rendered > 0): ?>
  <section class="ss-shop-lookbook ss-shop-lookbook--bottom" aria-label="Lookbook Bottom">
    <div class="ss-container ss-lookbook-grid ss-lookbook-grid--<?php echo $bot_rendered; ?>">
      <?php echo $bot_html; ?>
    </div>
  </section>
  <?php endif; ?>

</main>

<?php get_footer(); ?>
