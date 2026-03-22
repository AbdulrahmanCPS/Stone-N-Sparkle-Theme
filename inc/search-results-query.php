<?php
/**
 * Product search: main-query tweaks (sort, stock filter, page size).
 *
 * GET params:
 * - orderby: relevance (default), WooCommerce catalog keys, or type (category priority).
 * - stock: all (default), instock, outofstock.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Whether the main query is a WooCommerce product search.
 *
 * @param WP_Query $q Query object.
 * @return bool
 */
function ss_is_main_product_search_query($q) {
    if (!($q instanceof WP_Query) || !$q->is_main_query() || !$q->is_search()) {
        return false;
    }
    if (!function_exists('wc_get_product')) {
        return false;
    }
    $pt = $q->get('post_type');
    if ($pt === 'product') {
        return true;
    }
    if (is_array($pt) && in_array('product', $pt, true)) {
        return true;
    }
    return false;
}

/**
 * Product search via theme forms: GET post_type=product and s is present (may be empty).
 * Matches header overlay + search-product-toolbar submissions.
 *
 * @return bool
 */
function ss_is_product_search_toolbar_request() {
    // phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only query args.
    if (!isset($_GET['post_type']) || !is_string($_GET['post_type'])) {
        return false;
    }
    if (sanitize_key(wp_unslash($_GET['post_type'])) !== 'product') {
        return false;
    }
    if (!array_key_exists('s', $_GET)) {
        return false;
    }
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    return true;
}

/**
 * Main query should receive product-search listing tweaks (stock, orderby, per page).
 *
 * @param WP_Query $q Query object.
 * @return bool
 */
function ss_is_main_product_search_listing_query($q) {
    if (!($q instanceof WP_Query) || !$q->is_main_query()) {
        return false;
    }
    if (is_admin() || !function_exists('wc_get_product')) {
        return false;
    }
    $pt     = $q->get('post_type');
    $is_pt  = ($pt === 'product' || (is_array($pt) && in_array('product', $pt, true)));
    if (!$is_pt) {
        return false;
    }
    if ($q->is_search()) {
        return true;
    }

    return ss_is_product_search_toolbar_request();
}

/**
 * Whether this HTTP request is a storefront product search (?s=…&post_type=product).
 * Matches the overlay / toolbar GET submission (used to bypass WC Coming Soon).
 *
 * @return bool
 */
function ss_is_frontend_product_search_request() {
    if (ss_is_product_search_toolbar_request()) {
        return true;
    }

    if (function_exists('get_query_var')) {
        $qv_s = get_query_var('s');
        if (is_string($qv_s) && trim($qv_s) !== '') {
            $qv_pt = get_query_var('post_type');
            $pt_one = is_array($qv_pt) ? reset($qv_pt) : $qv_pt;
            if ($pt_one === 'product') {
                return true;
            }
        }
    }

    return false;
}

/**
 * Strip core search SQL when keyword is empty but post_type=product toolbar request (list all products).
 *
 * @param string    $search Search SQL for WHERE.
 * @param WP_Query $query  Query instance.
 * @return string
 */
function ss_search_product_posts_search_empty_keyword($search, $query) {
    if (!($query instanceof WP_Query) || !$query->is_main_query()) {
        return $search;
    }
    if (!ss_is_product_search_toolbar_request()) {
        return $search;
    }
    $raw_s = isset($_GET['s']) && is_string($_GET['s']) ? wp_unslash($_GET['s']) : null;
    if ($raw_s === null || trim($raw_s) !== '') {
        return $search;
    }

    return '';
}

add_filter('posts_search', 'ss_search_product_posts_search_empty_keyword', 999, 2);

/**
 * Normalize main query for toolbar product listing (empty s is not is_search() in WP).
 */
add_action('pre_get_posts', function ($q) {
    if (is_admin() || !$q->is_main_query() || !function_exists('wc_get_product')) {
        return;
    }
    if (!ss_is_product_search_toolbar_request()) {
        return;
    }
    $q->set('post_type', 'product');
    if (isset($_GET['s']) && is_string($_GET['s'])) {
        $q->set('s', wp_unslash($_GET['s']));
    } else {
        $q->set('s', '');
    }
}, 1);

/**
 * Allowed orderby values for product search (plus relevance and type).
 *
 * @return string[]
 */
function ss_search_product_allowed_orderby() {
    $keys = ['relevance', 'type'];
    if (function_exists('wc_get_catalog_ordering_options')) {
        $opts = wc_get_catalog_ordering_options();
        if (is_array($opts)) {
            $keys = array_merge($keys, array_keys($opts));
        }
    }
    // Ensure standard Woo sort keys work even if catalog options omit them (e.g. on search context).
    foreach (['price', 'price-desc', 'popularity'] as $extra) {
        if (!in_array($extra, $keys, true)) {
            $keys[] = $extra;
        }
    }
    return array_values(array_unique(array_map('strval', $keys)));
}

/**
 * Sanitize orderby from request.
 *
 * @return string
 */
function ss_search_product_get_orderby() {
    if (!isset($_GET['orderby'])) {
        return 'relevance';
    }
    $raw = function_exists('wc_clean')
        ? (string) wc_clean(wp_unslash($_GET['orderby']))
        : sanitize_text_field(wp_unslash($_GET['orderby']));
    $allowed = ss_search_product_allowed_orderby();
    if ($raw === '' || !in_array($raw, $allowed, true)) {
        return 'relevance';
    }
    return $raw;
}

/**
 * Sanitize stock filter from request.
 *
 * @return string all|instock|outofstock
 */
function ss_search_product_get_stock() {
    if (!isset($_GET['stock'])) {
        return 'all';
    }
    $v = sanitize_key(wp_unslash($_GET['stock']));
    if (in_array($v, ['all', 'instock', 'outofstock'], true)) {
        return $v;
    }
    return 'all';
}

add_action('pre_get_posts', function ($q) {
    if (is_admin() || !ss_is_main_product_search_listing_query($q)) {
        return;
    }

    $per_page = (int) apply_filters('loop_shop_per_page', (int) get_option('posts_per_page', 12));
    if ($per_page < 1) {
        $per_page = 12;
    }
    $q->set('posts_per_page', $per_page);

    // Stock availability (WooCommerce _stock_status).
    $stock = ss_search_product_get_stock();
    if ($stock === 'instock' || $stock === 'outofstock') {
        $mq   = $q->get('meta_query');
        $meta = [
            'key'     => '_stock_status',
            'value'   => $stock === 'instock' ? 'instock' : 'outofstock',
            'compare' => '=',
        ];
        if (!is_array($mq) || empty($mq)) {
            $q->set('meta_query', [$meta]);
        } else {
            $mq[] = $meta;
            $q->set('meta_query', $mq);
        }
    }

    $orderby = ss_search_product_get_orderby();
    $q->set('ss_collection_ordering', '');

    if ($orderby === 'relevance') {
        $s = $q->get('s');
        if (is_string($s) && $s !== '') {
            $q->set('orderby', 'relevance');
        }
        return;
    }

    if (in_array($orderby, ['price', 'price-desc', 'popularity', 'type'], true)) {
        $q->set('ss_collection_ordering', $orderby);
        $q->set('orderby', 'title');
        $q->set('order', 'ASC');
        return;
    }

    if (function_exists('wc_get_catalog_ordering_args')) {
        $ordering_args = wc_get_catalog_ordering_args($orderby);
        if (is_array($ordering_args)) {
            if (!empty($ordering_args['orderby'])) {
                $q->set('orderby', $ordering_args['orderby']);
            }
            if (!empty($ordering_args['order'])) {
                $q->set('order', $ordering_args['order']);
            }
            if (!empty($ordering_args['meta_key'])) {
                $q->set('meta_key', $ordering_args['meta_key']);
            }
        }
    }
}, 20);

/**
 * Args to preserve on search pagination links.
 *
 * @return array<string, string>
 */
function ss_search_product_pagination_add_args() {
    $args = [
        'post_type' => 'product',
    ];
    $on_product_listing = false;
    if (function_exists('is_search') && is_search()) {
        $pt = get_query_var('post_type');
        $pt_one = is_array($pt) ? reset($pt) : $pt;
        $on_product_listing = ($pt_one === 'product');
    }
    if (!$on_product_listing && function_exists('ss_is_product_search_toolbar_request')) {
        $on_product_listing = ss_is_product_search_toolbar_request();
    }
    if ($on_product_listing) {
        $s = get_query_var('s');
        $args['s'] = is_string($s) ? $s : '';
    }
    $orderby           = ss_search_product_get_orderby();
    if ($orderby !== 'relevance') {
        $args['orderby'] = $orderby;
    }
    $stock = ss_search_product_get_stock();
    if ($stock !== 'all') {
        $args['stock'] = $stock;
    }
    return $args;
}

/**
 * URL to clear the search keyword while keeping product search filters (stock, orderby).
 *
 * @return string
 */
function ss_search_product_clear_keyword_url() {
    $args = [
        'post_type' => 'product',
        's'         => '',
    ];
    $orderby = ss_search_product_get_orderby();
    if ($orderby !== 'relevance') {
        $args['orderby'] = $orderby;
    }
    $stock = ss_search_product_get_stock();
    if ($stock !== 'all') {
        $args['stock'] = $stock;
    }
    return add_query_arg($args, home_url('/'));
}

/**
 * Avoid duplicate WooCommerce toolbar on product search (theme renders its own).
 */
add_action('wp', function () {
    if (!function_exists('wc_get_product')) {
        return;
    }
    $is_prod_listing = false;
    if (function_exists('is_search') && is_search()) {
        $pt = get_query_var('post_type');
        if ($pt === 'product' || (is_array($pt) && in_array('product', $pt, true))) {
            $is_prod_listing = true;
        }
    }
    if (!$is_prod_listing && function_exists('ss_is_product_search_toolbar_request')) {
        $is_prod_listing = ss_is_product_search_toolbar_request();
    }
    if (!$is_prod_listing) {
        return;
    }
    remove_action('woocommerce_before_shop_loop', 'woocommerce_result_count', 20);
    remove_action('woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30);
}, 99);
