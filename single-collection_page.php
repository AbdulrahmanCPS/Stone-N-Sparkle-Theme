<?php
/**
 * Template for single collection page.
 *
 * Layout: hero (title, content, featured image) → lookbook before → product grid (by pa_collection) → pagination → lookbook after.
 */

defined('ABSPATH') || exit;

get_header();

while (have_posts()) {
    the_post();
    $post_id = get_the_ID();

    // Collection term: ACF field or fallback post meta.
    $term_id = null;
    if (function_exists('get_field')) {
        $term_val = get_field('collection_term', $post_id);
        if (is_object($term_val) && isset($term_val->term_id)) {
            $term_id = (int) $term_val->term_id;
        } elseif (is_numeric($term_val)) {
            $term_id = (int) $term_val;
        }
    }
    if ($term_id === null) {
        $term_id = (int) get_post_meta($post_id, 'collection_term_id', true);
    }

    // Products per page: ACF field or fallback post meta (default 12).
    $per_page = 12;
    if (function_exists('get_field')) {
        $pp = get_field('products_per_page', $post_id);
        if (is_numeric($pp) && (int) $pp >= 1) {
            $per_page = (int) $pp;
        }
    }
    if ($per_page < 1) {
        $from_meta = (int) get_post_meta($post_id, 'collection_products_per_page', true);
        $per_page = $from_meta >= 1 ? $from_meta : 12;
    }
    if ($per_page === 12) {
        $from_meta = (int) get_post_meta($post_id, 'collection_products_per_page', true);
        if ($from_meta >= 1) {
            $per_page = $from_meta;
        }
    }

    // Lookbook images from post meta (JSON).
    $lookbook_before = function_exists('ss_get_collection_lookbook') ? ss_get_collection_lookbook($post_id, 'ss_collection_lookbook_before') : [];
    $lookbook_after  = function_exists('ss_get_collection_lookbook') ? ss_get_collection_lookbook($post_id, 'ss_collection_lookbook_after') : [];
    if (!is_array($lookbook_before)) {
        $lookbook_before = [];
    }
    if (!is_array($lookbook_after)) {
        $lookbook_after = [];
    }
    $lookbook_before = array_values(array_filter($lookbook_before, function ($i) { return !empty($i['url']); }));
    $lookbook_after  = array_values(array_filter($lookbook_after, function ($i) { return !empty($i['url']); }));
?>
<main id="primary" class="ss-main ss-collection-page" role="main">

  <?php /* Hero: title, optional featured image, content/excerpt */ ?>
  <section class="ss-collection-hero ss-container" aria-label="<?php esc_attr_e('Collection', 'stone-sparkle'); ?>">
    <h1 class="ss-collection-title entry-title"><?php the_title(); ?></h1>
    <?php if (has_post_thumbnail()) : ?>
      <div class="ss-collection-featured">
        <?php the_post_thumbnail('large', ['loading' => 'eager']); ?>
      </div>
    <?php endif; ?>
    <?php if (has_excerpt()) : ?>
      <div class="ss-collection-excerpt entry-summary">
        <?php the_excerpt(); ?>
      </div>
    <?php endif; ?>
    <?php if (trim(get_the_content()) !== '') : ?>
      <div class="ss-collection-content entry-content">
        <?php the_content(); ?>
      </div>
    <?php endif; ?>
  </section>

  <?php /* Lookbook before products */ ?>
  <?php
  if (!empty($lookbook_before)) {
      $n = min(count($lookbook_before), 3);
      $grid_class = 'ss-lookbook-grid--' . $n;
      ?>
  <section class="ss-shop-lookbook ss-shop-lookbook--top" aria-label="<?php esc_attr_e('Lookbook', 'stone-sparkle'); ?>">
    <div class="ss-container ss-lookbook-grid <?php echo esc_attr($grid_class); ?>">
      <?php foreach ($lookbook_before as $item) :
          $url = isset($item['url']) ? $item['url'] : '';
          $alt = isset($item['alt']) && (string) $item['alt'] !== '' ? $item['alt'] : __('Lookbook image', 'stone-sparkle');
          if ($url === '') {
              continue;
          }
          ?>
      <div class="ss-lookbook-item ss-lookbook-item--shop">
        <div class="ss-lookbook-card">
          <div class="ss-lookbook-media">
            <img src="<?php echo esc_url($url); ?>" alt="<?php echo esc_attr($alt); ?>" loading="lazy" decoding="async">
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php } ?>

  <?php /* Product grid: WP_Query by pa_collection, with pagination */ ?>
  <section class="ss-shop-products" aria-label="<?php esc_attr_e('Products', 'stone-sparkle'); ?>">
    <div class="ss-container">
      <?php
      if ($term_id <= 0 || !taxonomy_exists('pa_collection')) {
          echo '<p class="ss-collection-empty">' . esc_html__('Please select a collection in the editor.', 'stone-sparkle') . '</p>';
      } else {
          $paged = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;

          // Honor WooCommerce catalog default ordering (Customizer → WooCommerce → Product Catalog)
          // and optional customer-facing override (?orderby=...).
          $orderby_value = '';
          if (isset($_GET['orderby'])) {
              $orderby_value = (string) wp_unslash($_GET['orderby']);
              if (function_exists('wc_clean')) {
                  $orderby_value = (string) wc_clean($orderby_value);
              } else {
                  $orderby_value = sanitize_text_field($orderby_value);
              }
          }

          $ordering_args = null;
          if (function_exists('wc_get_catalog_ordering_args')) {
              // If no explicit request, fall back to the WooCommerce global default.
              $default_orderby = (string) get_option('woocommerce_default_catalog_orderby', 'menu_order');
              $effective_orderby = $orderby_value !== '' ? $orderby_value : $default_orderby;
              $ordering_args = wc_get_catalog_ordering_args($effective_orderby);
          }

          $args = [
              'post_type'      => 'product',
              'post_status'    => 'publish',
              'posts_per_page' => $per_page,
              'paged'          => $paged,
              // Default fallback ordering. If WooCommerce ordering args are available, they'll override below.
              'orderby'        => 'menu_order title',
              'order'          => 'ASC',
              'tax_query'      => [
                  [
                      'taxonomy' => 'pa_collection',
                      'field'    => 'term_id',
                      'terms'    => $term_id,
                  ],
              ],
          ];

          if (is_array($ordering_args)) {
              if (isset($ordering_args['orderby'])) {
                  $args['orderby'] = $ordering_args['orderby'];
              }
              if (isset($ordering_args['order']) && is_string($ordering_args['order']) && $ordering_args['order'] !== '') {
                  $args['order'] = $ordering_args['order'];
              }
              if (isset($ordering_args['meta_key']) && is_string($ordering_args['meta_key']) && $ordering_args['meta_key'] !== '') {
                  $args['meta_key'] = $ordering_args['meta_key'];
              }
          }
          $query = new WP_Query($args);

          if ($query->have_posts()) {
              do_action('woocommerce_before_shop_loop');
              woocommerce_product_loop_start();
              while ($query->have_posts()) {
                  $query->the_post();
                  global $product;
                  $product = function_exists('wc_get_product') ? wc_get_product(get_the_ID()) : null;
                  if ($product && $product->is_visible()) {
                      wc_get_template_part('content', 'product');
                  }
              }
              woocommerce_product_loop_end();
              do_action('woocommerce_after_shop_loop');

              if ($query->max_num_pages > 1) {
                  echo '<nav class="ss-collection-pagination woocommerce-pagination" aria-label="' . esc_attr__('Products pagination', 'stone-sparkle') . '">';
                  echo paginate_links([
                      'total'   => $query->max_num_pages,
                      'current' => $paged,
                      'base'    => esc_url(get_permalink()) . '%_%',
                      'format'  => '?paged=%#%',
                      'add_args' => $orderby_value !== '' ? ['orderby' => $orderby_value] : false,
                  ]);
                  echo '</nav>';
              }
              wp_reset_postdata();
          } else {
              do_action('woocommerce_no_products_found');
          }
      }
      ?>
    </div>
  </section>

  <?php /* Lookbook after products */ ?>
  <?php
  if (!empty($lookbook_after)) {
      $n = min(count($lookbook_after), 3);
      $grid_class = 'ss-lookbook-grid--' . $n;
      ?>
  <section class="ss-shop-lookbook ss-shop-lookbook--bottom" aria-label="<?php esc_attr_e('Lookbook', 'stone-sparkle'); ?>">
    <div class="ss-container ss-lookbook-grid <?php echo esc_attr($grid_class); ?>">
      <?php foreach ($lookbook_after as $item) :
          $url = isset($item['url']) ? $item['url'] : '';
          $alt = isset($item['alt']) && (string) $item['alt'] !== '' ? $item['alt'] : __('Lookbook image', 'stone-sparkle');
          if ($url === '') {
              continue;
          }
          ?>
      <div class="ss-lookbook-item ss-lookbook-item--shop">
        <div class="ss-lookbook-card">
          <div class="ss-lookbook-media">
            <img src="<?php echo esc_url($url); ?>" alt="<?php echo esc_attr($alt); ?>" loading="lazy" decoding="async">
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php } ?>

</main>
<?php
}

get_footer();
