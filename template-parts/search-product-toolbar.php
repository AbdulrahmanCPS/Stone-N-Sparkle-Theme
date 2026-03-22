<?php
/**
 * Toolbar: search field, availability filter, sort (product search only).
 */

defined('ABSPATH') || exit;

if (!function_exists('wc_get_product')) {
    return;
}

global $wp_query;

$found = isset($wp_query->found_posts) ? (int) $wp_query->found_posts : 0;
$cur_orderby = function_exists('ss_search_product_get_orderby') ? ss_search_product_get_orderby() : 'relevance';
$cur_stock   = function_exists('ss_search_product_get_stock') ? ss_search_product_get_stock() : 'all';
$search_q    = get_search_query();
$has_query   = is_string($search_q) && trim($search_q) !== '';
$clear_url   = function_exists('ss_search_product_clear_keyword_url') ? ss_search_product_clear_keyword_url() : home_url('/');

$form_action = home_url('/');
?>
<div class="ss-search-toolbar" role="region" aria-label="<?php esc_attr_e('Search tools', 'stone-sparkle'); ?>">
  <form class="ss-search-toolbar-form" method="get" action="<?php echo esc_url($form_action); ?>">
    <input type="hidden" name="post_type" value="product">

    <div class="ss-search-toolbar-searchrow">
      <div class="ss-search-toolbar-field ss-search-toolbar-field--search">
        <label class="ss-search-toolbar-label ss-sr-only" for="ss-search-toolbar-s"><?php esc_html_e('Search', 'stone-sparkle'); ?></label>
        <div class="ss-search-toolbar-inputrow">
          <input
            id="ss-search-toolbar-s"
            class="ss-search-toolbar-input"
            type="text"
            name="s"
            value="<?php echo esc_attr($search_q); ?>"
            autocomplete="off"
            inputmode="search"
            enterkeyhint="search"
            placeholder="<?php esc_attr_e('Search products…', 'stone-sparkle'); ?>"
          >
          <?php if ($has_query) : ?>
            <a
              class="ss-search-toolbar-clear"
              href="<?php echo esc_url($clear_url); ?>"
              aria-label="<?php esc_attr_e('Clear search', 'stone-sparkle'); ?>"
            ><span aria-hidden="true">&times;</span></a>
          <?php endif; ?>
          <button type="submit" class="ss-search-toolbar-submit-icon" aria-label="<?php esc_attr_e('Submit search', 'stone-sparkle'); ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <circle cx="11" cy="11" r="6.5" stroke="currentColor" stroke-width="1.6"/>
              <path d="M16 16 21 21" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
            </svg>
          </button>
        </div>
      </div>
    </div>

    <div class="ss-search-toolbar-util">
      <div class="ss-search-toolbar-util-left">
        <span class="ss-search-toolbar-util-prefix"><?php esc_html_e('Filter:', 'stone-sparkle'); ?></span>
        <label class="ss-search-toolbar-inline-label" for="ss-search-toolbar-stock"><?php esc_html_e('Availability', 'stone-sparkle'); ?></label>
        <select
          class="ss-search-toolbar-select"
          id="ss-search-toolbar-stock"
          name="stock"
          onchange="this.form.submit()"
        >
          <option value="all" <?php selected($cur_stock, 'all'); ?>><?php esc_html_e('All', 'stone-sparkle'); ?></option>
          <option value="instock" <?php selected($cur_stock, 'instock'); ?>><?php esc_html_e('In stock', 'stone-sparkle'); ?></option>
          <option value="outofstock" <?php selected($cur_stock, 'outofstock'); ?>><?php esc_html_e('Out of stock', 'stone-sparkle'); ?></option>
        </select>
      </div>
      <div class="ss-search-toolbar-util-right">
        <span class="ss-search-toolbar-util-prefix"><?php esc_html_e('Sort by:', 'stone-sparkle'); ?></span>
        <label class="ss-search-toolbar-inline-label ss-sr-only" for="ss-search-toolbar-orderby"><?php esc_html_e('Sort results', 'stone-sparkle'); ?></label>
        <select
          class="ss-search-toolbar-select"
          id="ss-search-toolbar-orderby"
          name="orderby"
          onchange="this.form.submit()"
        >
          <option value="relevance" <?php selected($cur_orderby, 'relevance'); ?>><?php esc_html_e('Relevance', 'stone-sparkle'); ?></option>
          <?php
          $catalog_opts = function_exists('wc_get_catalog_ordering_options') ? wc_get_catalog_ordering_options() : [];
          foreach ($catalog_opts as $id => $name) {
              if ($id === 'relevance' || $id === 'type') {
                  continue;
              }
              printf(
                  '<option value="%s" %s>%s</option>',
                  esc_attr($id),
                  selected($cur_orderby, $id, false),
                  esc_html($name)
              );
          }
          if (!isset($catalog_opts['type'])) {
              printf(
                  '<option value="type" %s>%s</option>',
                  selected($cur_orderby, 'type', false),
                  esc_html__('Product type (priority)', 'stone-sparkle')
              );
          }
          if (!isset($catalog_opts['price'])) {
              printf(
                  '<option value="price" %s>%s</option>',
                  selected($cur_orderby, 'price', false),
                  esc_html__('Price: low to high', 'stone-sparkle')
              );
          }
          if (!isset($catalog_opts['price-desc'])) {
              printf(
                  '<option value="price-desc" %s>%s</option>',
                  selected($cur_orderby, 'price-desc', false),
                  esc_html__('Price: high to low', 'stone-sparkle')
              );
          }
          ?>
        </select>
        <p class="ss-search-toolbar-count" role="status">
          <?php
          printf(
              /* translators: %d: number of results */
              esc_html(_n('%d result', '%d results', $found, 'stone-sparkle')),
              $found
          );
          ?>
        </p>
      </div>
    </div>
  </form>
</div>
