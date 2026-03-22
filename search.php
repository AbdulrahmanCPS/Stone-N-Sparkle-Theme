<?php
/**
 * Search results (supports product-only search via ?post_type=product)
 */

defined('ABSPATH') || exit;

get_header();

$query   = get_search_query();
$pt      = get_query_var('post_type');
$pt_one  = is_array($pt) ? reset($pt) : $pt;
$is_prod = function_exists('wc_get_product') && $pt_one === 'product';
?>

<main id="primary" class="ss-main ss-search-results-page" role="main">
  <div class="ss-search-results-inner ss-container">
    <header class="ss-search-results-hero">
      <?php if ($is_prod) : ?>
        <h1 class="ss-search-results-title">
          <?php esc_html_e('Search results', 'stone-sparkle'); ?>
        </h1>
        <?php if ($query !== '') : ?>
          <p class="ss-sr-only">
            <?php
            printf(
              /* translators: %s: search query */
              esc_html__('Current search: %s', 'stone-sparkle'),
              esc_html($query)
            );
            ?>
          </p>
        <?php endif; ?>
      <?php else : ?>
        <h1 class="ss-search-results-title ss-search-results-title--mixed">
          <?php
          if ($query !== '') {
              printf(
                  /* translators: %s: search query */
                  esc_html__('Search results for "%s"', 'stone-sparkle'),
                  esc_html($query)
              );
          } else {
              esc_html_e('Search', 'stone-sparkle');
          }
          ?>
        </h1>
      <?php endif; ?>
    </header>

    <?php if ($is_prod) : ?>
      <?php get_template_part('template-parts/search', 'product-toolbar'); ?>
    <?php endif; ?>

    <?php if (have_posts()) : ?>
      <?php if ($is_prod) : ?>
        <div class="woocommerce ss-search-results-woo">
          <?php
          woocommerce_product_loop_start();
          while (have_posts()) {
              the_post();
              wc_get_template_part('content', 'product');
          }
          woocommerce_product_loop_end();
          ?>
        </div>
      <?php else : ?>
        <div class="ss-search-results-list">
          <?php
          while (have_posts()) {
              the_post();
              get_template_part('template-parts/content', get_post_type());
          }
          ?>
        </div>
      <?php endif; ?>

      <?php
      $pag_args = [
          'mid_size'           => 2,
          'prev_text'          => '<span class="ss-search-pagination-icon" aria-hidden="true">‹</span><span class="ss-sr-only">' . esc_html__('Previous', 'stone-sparkle') . '</span>',
          'next_text'          => '<span class="ss-search-pagination-icon" aria-hidden="true">›</span><span class="ss-sr-only">' . esc_html__('Next', 'stone-sparkle') . '</span>',
          'screen_reader_text' => __('Posts navigation', 'stone-sparkle'),
      ];
      if ($is_prod && function_exists('ss_search_product_pagination_add_args')) {
          $pag_args['add_args'] = ss_search_product_pagination_add_args();
      }
      ?>
      <div class="ss-search-pagination-wrap">
        <?php the_posts_pagination($pag_args); ?>
      </div>
    <?php else : ?>
      <p class="ss-search-none">
        <?php esc_html_e('No results found.', 'stone-sparkle'); ?>
      </p>
    <?php endif; ?>
  </div>
</main>

<?php
get_footer();
