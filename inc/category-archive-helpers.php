<?php
/**
 * Product category archive layout helpers
 * Used by taxonomy-product_cat.php for editorial image + product flow.
 *
 * @package Stone_Sparkle
 */

defined('ABSPATH') || exit;

/**
 * Get up to 8 lookbook image URLs for a product category term.
 *
 * @param int $term_id Product category term ID.
 * @return array List of image URLs (indices 0–7 = lookbook_image_1 … 8).
 */
function ss_get_category_archive_images($term_id) {
    $urls = array();
    for ($i = 1; $i <= 8; $i++) {
        $url = get_term_meta($term_id, 'lookbook_image_' . $i, true);
        if (!empty($url) && is_string($url)) {
            $urls[] = $url;
        }
    }
    return $urls;
}

/**
 * Get product count for a product category (including children if desired).
 * Uses a lightweight query; does not affect main query.
 *
 * @param int $term_id Product category term ID.
 * @return int
 */
function ss_get_category_product_count($term_id) {
    if (!function_exists('WC') || !taxonomy_exists('product_cat')) {
        return 0;
    }
    $q = new WP_Query(array(
        'post_type'              => 'product',
        'post_status'             => 'publish',
        'fields'                  => 'ids',
        'posts_per_page'          => 1,
        'no_found_rows'           => true,
        'update_post_meta_cache'   => false,
        'update_post_term_cache'  => false,
        'tax_query'               => array(array(
            'taxonomy' => 'product_cat',
            'field'    => 'term_id',
            'terms'    => $term_id,
        )),
    ));
    return (int) $q->found_posts;
}

/**
 * Render a single category archive image row (one slot, full-width row).
 *
 * @param string $url      Image URL.
 * @param string $alt      Alt text.
 * @param string $section_class Optional section wrapper class.
 */
function ss_render_category_archive_image_row($url, $alt, $section_class = '') {
    if (empty($url)) {
        return;
    }
    $class = trim('ss-category-archive-image-row ' . $section_class);
    ?>
    <div class="<?php echo esc_attr($class); ?>">
        <div class="ss-category-archive-image">
            <img src="<?php echo esc_url($url); ?>" alt="<?php echo esc_attr($alt); ?>" loading="lazy" decoding="async">
        </div>
    </div>
    <?php
}

/**
 * Top section: render at most 2 images (first two slots).
 */
function ss_render_top_images($image_urls, $cat_name) {
    $slice = array_slice($image_urls, 0, 2);
    if (empty($slice)) {
        return;
    }
    echo '<section class="ss-category-archive-top" aria-label="' . esc_attr($cat_name) . ' Lookbook">';
    echo '<div class="ss-category-archive-image-stack">';
    foreach ($slice as $url) {
        ss_render_category_archive_image_row($url, $cat_name, 'ss-category-archive-top-item');
    }
    echo '</div></section>';
}

/**
 * Mid block: render exactly 3 images (indices 2,3,4 = images 3,4,5). Only output available.
 */
function ss_render_mid_images($image_urls, $cat_name) {
    $slice = array_slice($image_urls, 2, 3);
    if (empty($slice)) {
        return;
    }
    echo '<section class="ss-category-archive-mid" aria-label="' . esc_attr($cat_name) . ' Lookbook Mid">';
    echo '<div class="ss-category-archive-image-stack">';
    foreach ($slice as $url) {
        ss_render_category_archive_image_row($url, $cat_name, 'ss-category-archive-mid-item');
    }
    echo '</div></section>';
}

/**
 * Trailing block: render next 3 images (indices 5,6,7 = images 6,7,8). Only output available.
 */
function ss_render_trailing_images($image_urls, $cat_name) {
    $slice = array_slice($image_urls, 5, 3);
    if (empty($slice)) {
        return;
    }
    echo '<section class="ss-category-archive-trailing" aria-label="' . esc_attr($cat_name) . ' Lookbook Trailing">';
    echo '<div class="ss-category-archive-image-stack">';
    foreach ($slice as $url) {
        ss_render_category_archive_image_row($url, $cat_name, 'ss-category-archive-trailing-item');
    }
    echo '</div></section>';
}

/**
 * Stack a range of images vertically (for ≤20 products: images 3–8 in one block).
 * $from_index and $to_index are 0-based; both inclusive.
 */
function ss_render_category_image_stack($image_urls, $from_index, $to_index, $cat_name) {
    $len = count($image_urls);
    if ($from_index >= $len || $from_index > $to_index) {
        return;
    }
    $slice = array_slice($image_urls, $from_index, $to_index - $from_index + 1);
    if (empty($slice)) {
        return;
    }
    echo '<section class="ss-category-archive-stack" aria-label="' . esc_attr($cat_name) . ' Lookbook">';
    echo '<div class="ss-category-archive-image-stack">';
    foreach ($slice as $url) {
        ss_render_category_archive_image_row($url, $cat_name, 'ss-category-archive-stack-item');
    }
    echo '</div></section>';
}

/**
 * Run a product query for the category and render products using content-product template.
 *
 * @param int $term_id Product category term ID.
 * @param int $limit   Max number of products (-1 for all).
 * @param int $offset Offset for pagination.
 */
function ss_render_category_products($term_id, $limit = 20, $offset = 0) {
    if (!function_exists('wc_get_product') || !taxonomy_exists('product_cat')) {
        return;
    }
    $args = array(
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => $limit < 1 ? 999 : $limit,
        'offset'         => $offset,
        'orderby'       => 'menu_order title',
        'order'         => 'ASC',
        'tax_query'     => array(array(
            'taxonomy' => 'product_cat',
            'field'    => 'term_id',
            'terms'    => $term_id,
        )),
    );
    $query = new WP_Query($args);
    if (!$query->have_posts()) {
        wp_reset_postdata();
        return;
    }
    // Loop hooks are fired once per archive by the template to avoid double execution.
    echo '<ul class="products columns-4 ss-products">';
    while ($query->have_posts()) {
        $query->the_post();
        global $product;
        $product = wc_get_product(get_the_ID());
        if ($product && $product->is_visible()) {
            wc_get_template_part('content', 'product');
        }
    }
    echo '</ul>';
    wp_reset_postdata();
}

/**
 * Render first product batch (max 20).
 */
function ss_render_first_product_batch($term_id, $limit = 20) {
    ss_render_category_products($term_id, $limit, 0);
}

/**
 * Render remaining products after the first batch (offset 20).
 */
function ss_render_remaining_products($term_id, $offset = 20) {
    ss_render_category_products($term_id, 999, $offset);
}
