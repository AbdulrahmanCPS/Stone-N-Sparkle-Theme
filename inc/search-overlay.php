<?php
/**
 * Header search overlay: AJAX product search + script localization.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('SS_SEARCH_MIN_CHARS')) {
    define('SS_SEARCH_MIN_CHARS', 2);
}
if (!defined('SS_SEARCH_PRODUCT_LIMIT')) {
    define('SS_SEARCH_PRODUCT_LIMIT', 8);
}
if (!defined('SS_SEARCH_TERM_LIMIT')) {
    define('SS_SEARCH_TERM_LIMIT', 5);
}

/**
 * Build the front-end URL for full search results (progressive enhancement / CTA).
 *
 * @param string $term Raw search string.
 * @return string
 */
function ss_search_view_url($term) {
    $term = sanitize_text_field($term);
    $args = ['s' => $term];
    if (function_exists('wc_get_product')) {
        $args['post_type'] = 'product';
    }
    return add_query_arg($args, home_url('/'));
}

/**
 * AJAX: return suggestions (categories/tags) and matching products as JSON.
 */
function ss_ajax_product_search() {
    if (!check_ajax_referer('ss_product_search', 'nonce', false)) {
        wp_send_json_error(['message' => 'bad_nonce'], 403);
    }

    $term = isset($_POST['term']) ? sanitize_text_field(wp_unslash($_POST['term'])) : '';
    $term = trim($term);

    if (strlen($term) < SS_SEARCH_MIN_CHARS) {
        wp_send_json_success([
            'suggestions' => [],
            'products'    => [],
            'view_url'    => ss_search_view_url($term),
        ]);
    }

    $products    = [];
    $suggestions = [];

    if (function_exists('wc_get_product')) {
        $q = new WP_Query([
            'post_type'              => 'product',
            'post_status'            => 'publish',
            's'                      => $term,
            'posts_per_page'         => SS_SEARCH_PRODUCT_LIMIT,
            'ignore_sticky_posts'    => true,
            'no_found_rows'          => true,
            'update_post_term_cache' => false,
        ]);

        $thumb_size = 'woocommerce_gallery_thumbnail';

        while ($q->have_posts()) {
            $q->the_post();
            $pid     = (int) get_the_ID();
            $product = wc_get_product($pid);
            if (!$product || !$product->is_visible()) {
                continue;
            }

            $thumb = get_the_post_thumbnail_url($pid, $thumb_size);
            if (!$thumb) {
                $thumb = get_the_post_thumbnail_url($pid, 'thumbnail') ?: '';
            }

            $products[] = [
                'id'         => $pid,
                'title'      => html_entity_decode(get_the_title(), ENT_QUOTES, 'UTF-8'),
                'url'        => get_permalink($pid),
                'thumb'      => $thumb,
                'price_html' => $product->get_price_html() ?: '',
            ];
        }
        wp_reset_postdata();

        $terms = get_terms([
            'taxonomy'   => ['product_cat', 'product_tag'],
            'hide_empty' => true,
            'number'     => 8,
            'search'     => $term,
        ]);

        if (!is_wp_error($terms) && !empty($terms)) {
            $seen = [];
            foreach ($terms as $t) {
                if (count($suggestions) >= SS_SEARCH_TERM_LIMIT) {
                    break;
                }
                $key = strtolower($t->name);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $link       = get_term_link($t);
                if (is_wp_error($link)) {
                    continue;
                }
                $suggestions[] = [
                    'label' => $t->name,
                    'url'   => $link,
                ];
            }
        }
    } else {
        $q = new WP_Query([
            'post_type'              => 'post',
            'post_status'            => 'publish',
            's'                      => $term,
            'posts_per_page'         => SS_SEARCH_PRODUCT_LIMIT,
            'ignore_sticky_posts'    => true,
            'no_found_rows'          => true,
            'update_post_term_cache' => false,
        ]);

        while ($q->have_posts()) {
            $q->the_post();
            $pid = (int) get_the_ID();
            $products[] = [
                'id'         => $pid,
                'title'      => html_entity_decode(get_the_title(), ENT_QUOTES, 'UTF-8'),
                'url'        => get_permalink($pid),
                'thumb'      => get_the_post_thumbnail_url($pid, 'thumbnail') ?: '',
                'price_html' => '',
            ];
        }
        wp_reset_postdata();
    }

    wp_send_json_success([
        'suggestions' => $suggestions,
        'products'    => $products,
        'view_url'    => ss_search_view_url($term),
    ]);
}

add_action('wp_ajax_ss_product_search', 'ss_ajax_product_search');
add_action('wp_ajax_nopriv_ss_product_search', 'ss_ajax_product_search');

add_action('wp_enqueue_scripts', function () {
    wp_localize_script('stone-sparkle-main', 'SS_SEARCH', [
        'ajaxUrl'  => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('ss_product_search'),
        'action'   => 'ss_product_search',
        'minChars' => SS_SEARCH_MIN_CHARS,
        'strings'  => [
            'ctaPrefix'   => __('Search for', 'stone-sparkle'),
            'emptyHint'   => __('Type at least two characters to search products.', 'stone-sparkle'),
            'noResults'   => __('No matches yet. Try another term.', 'stone-sparkle'),
            'loading'     => __('Searching…', 'stone-sparkle'),
            'wrapQuotes'  => '"%s"',
        ],
    ]);
}, 20);
