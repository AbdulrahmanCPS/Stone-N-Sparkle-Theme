<?php
/**
 * Stone Sparkle theme functions
 */

if (!defined('ABSPATH')) { exit; }

define('SS_THEME_VERSION', '0.2.1');

require_once get_template_directory() . '/inc/search-overlay.php';
require_once get_template_directory() . '/inc/search-results-query.php';
require_once get_template_directory() . '/inc/you-may-also-like.php';

/**
 * WooCommerce: Header cart link (with live-updating count badge)
 */
function ss_cart_count() {
    if (!function_exists('WC')) {
        return 0;
    }
    // Ensure the cart is loaded before we read counts (some hosts/themes defer cart init).
    if (function_exists('wc_load_cart') && (!isset(WC()->cart) || !WC()->cart)) {
        wc_load_cart();
    }
    if (!isset(WC()->cart) || !WC()->cart) {
        return 0;
    }
    return (int) WC()->cart->get_cart_contents_count();
}

function ss_render_cart_link() {
    $cart_url = function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/cart/');
    $count = ss_cart_count();

    ob_start();
    ?>
    <a class="ss-icon-btn ss-cart-link" href="<?php echo esc_url($cart_url); ?>" aria-label="<?php echo esc_attr__('Cart', 'stone-sparkle'); ?>">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <path d="M6 6h15l-1.5 9h-12L6 6Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
        <path d="M6 6 5 3H2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
        <path d="M9 21a1 1 0 1 0 0-2 1 1 0 0 0 0 2ZM18 21a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" fill="currentColor"/>
      </svg>
      <span
        class="ss-cart-count<?php echo ($count > 0) ? ' is-active' : ''; ?>"
        data-count="<?php echo (int) $count; ?>"
        aria-hidden="true"
      ><?php echo (int) $count; ?></span>
    </a>
    <?php
    return trim(ob_get_clean());
}

add_action('after_setup_theme', function () {
    load_theme_textdomain('stone-sparkle', get_template_directory() . '/languages');

    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('responsive-embeds');
    add_theme_support('editor-styles');
    add_editor_style('assets/css/main.css');
    add_theme_support('automatic-feed-links');
    add_theme_support('html5', ['search-form','comment-form','comment-list','gallery','caption','style','script']);
    add_theme_support('custom-logo', [
        'height'      => 80,
        'width'       => 280,
        'flex-height' => true,
        'flex-width'  => true,
    ]);

    // WooCommerce
    add_theme_support('woocommerce');

    register_nav_menus([
        'primary' => __('Primary Menu', 'stone-sparkle'),
        'footer'  => __('Footer Menu', 'stone-sparkle'),

        // Footer section menus (ACF Free compatible: unlimited sub-items via WP menus)
        'footer_product_type_menu' => __('Footer: Product Type', 'stone-sparkle'),
        'footer_about_menu'        => __('Footer: About', 'stone-sparkle'),
        'footer_support_menu'      => __('Footer: Support', 'stone-sparkle'),
        'footer_contact_menu'      => __('Footer: Contact', 'stone-sparkle'),
    ]);
});

/**
 * Front page: show only the site name in the document title (e.g. "Stone and Sparkle").
 * Search: "You searched for {query} – {site name}" for clearer browser tabs.
 * Other pages keep the default title (page/post title + site name, SEO plugins, etc.).
 */
add_filter('pre_get_document_title', function ($title) {
    if (is_front_page()) {
        return get_bloginfo('name');
    }
    if (is_search()) {
        $q = get_search_query();
        if ($q !== '') {
            return sprintf(
                /* translators: 1: search keywords, 2: site name */
                __('You searched for %1$s – %2$s', 'stone-sparkle'),
                $q,
                get_bloginfo('name')
            );
        }
    }
    return $title;
});

/**
 * WooCommerce Coming Soon: allow real templates for guests on key storefront views.
 * WC hides the store from non-admins when "Coming soon" is on; without exclusions,
 * single product URLs show the placeholder even though admins (manage_woocommerce) see PDPs.
 *
 * - Single product pages (incognito / logged-out shoppers).
 * - Product search (?s=&post_type=product), same as before.
 *
 * To show the entire site to everyone, use WooCommerce → Settings → Site visibility → Live.
 *
 * @link https://developer.woocommerce.com/docs/integrating-with-coming-soon-mode/
 */
add_filter('woocommerce_coming_soon_exclude', function ($excluded) {
    if ($excluded) {
        return true;
    }
    if (function_exists('is_product') && is_product()) {
        return true;
    }
    return function_exists('ss_is_frontend_product_search_request') && ss_is_frontend_product_search_request();
}, 10);

/**
 * Product search results: body class for brand search layout (toolbar + spacing; scoped CSS).
 */
add_filter('body_class', function ($classes) {
    if (!function_exists('wc_get_product')) {
        return $classes;
    }
    $product_search = false;
    if (is_search()) {
        $pt = get_query_var('post_type');
        $pt_one = is_array($pt) ? reset($pt) : $pt;
        $product_search = ($pt_one === 'product');
    }
    if (!$product_search && function_exists('ss_is_product_search_toolbar_request')) {
        $product_search = ss_is_product_search_toolbar_request();
    }
    if ($product_search) {
        $classes[] = 'ss-search-page--products';
    }
    return $classes;
});

/**
 * Product search: always use theme search.php (dedicated listing), not shop archive with lookbook.
 */
add_filter('template_include', function ($template) {
    if (!function_exists('wc_get_product')) {
        return $template;
    }
    $product_search = false;
    if (is_search()) {
        $pt = get_query_var('post_type');
        $pt_one = is_array($pt) ? reset($pt) : $pt;
        $product_search = ($pt_one === 'product');
    }
    if (!$product_search && function_exists('ss_is_product_search_toolbar_request')) {
        $product_search = ss_is_product_search_toolbar_request();
    }
    if (!$product_search) {
        return $template;
    }
    $search_template = locate_template('search.php');
    if ($search_template === '') {
        return $template;
    }
    return $search_template;
}, 99);

/**
 * WooCommerce: archive UI cleanup
 * - The shop layout is custom (lookbook + grid), so we hide default breadcrumb,
 *   result count, and ordering controls.
 */
add_action('wp', function () {
    if (!function_exists('is_woocommerce')) {
        return;
    }

    if (is_shop() || is_product_taxonomy()) {
        remove_action('woocommerce_before_main_content', 'woocommerce_breadcrumb', 20);
        remove_action('woocommerce_before_shop_loop', 'woocommerce_result_count', 20);
        remove_action('woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30);
    }
});

/**
 * WooCommerce single product: remove breadcrumb and product meta (category/SKU/tags).
 */
add_action('wp', function () {
    if (!function_exists('is_product') || !is_product()) {
        return;
    }
    remove_action('woocommerce_before_main_content', 'woocommerce_breadcrumb', 20);
    remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40);
}, 20);

// Remove related products from single product pages
remove_action('woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20);

/**
 * WooCommerce My Account: simplify menu (remove Dashboard and Downloads).
 * Redirect dashboard to Orders so users land on a useful endpoint.
 */
add_filter('woocommerce_account_menu_items', function ($items) {
    unset($items['dashboard']);
    unset($items['downloads']);
    return $items;
}, 20);

add_filter('woocommerce_login_redirect', function ($redirect, $user) {
    $orders_url = wc_get_account_endpoint_url('orders', '', wc_get_page_permalink('myaccount'));
    // Respect redirect from login form (e.g. wishlist return URL) when valid and same site.
    if (!empty($redirect)) {
        $allowed = wp_validate_redirect($redirect, $orders_url ?: home_url('/'));
        if ($allowed) {
            return $allowed;
        }
    }
    return $orders_url ? $orders_url : $redirect;
}, 10, 2);

/**
 * WooCommerce: show "AED" instead of Arabic "د.إ" for UAE Dirham.
 * Store currency must be set to AED in WooCommerce → Settings → General.
 */
add_filter('woocommerce_currency_symbol', function ($symbol, $currency) {
    if ($currency === 'AED') {
        return 'AED';
    }
    return $symbol;
}, 10, 2);

/**
 * Classic checkout: wrap order-review table for two-column + sticky summary (block-style layout).
 * Opens before @see woocommerce_order_review (10), closes before @see woocommerce_checkout_payment (20).
 */
add_action('woocommerce_checkout_order_review', function () {
    if (!function_exists('is_checkout') || !is_checkout() || (function_exists('is_order_received_page') && is_order_received_page())) {
        return;
    }
    echo '<div class="ss-checkout-sidebar-inner">';
    echo '<p class="ss-checkout-order-summary-title">' . esc_html__('Order summary', 'stone-sparkle') . '</p>';
}, 5);

add_action('woocommerce_checkout_order_review', function () {
    if (!function_exists('is_checkout') || !is_checkout() || (function_exists('is_order_received_page') && is_order_received_page())) {
        return;
    }
    echo '</div>';
}, 15);

/**
 * Classic checkout: line-item thumbnail + qty badge (reference layout); hide default × qty text.
 */
add_filter('woocommerce_cart_item_name', function ($product_name, $cart_item, $cart_item_key) {
    if (!function_exists('is_checkout') || !is_checkout() || (function_exists('is_order_received_page') && is_order_received_page())) {
        return $product_name;
    }
    if (empty($cart_item['data']) || !is_object($cart_item['data'])) {
        return $product_name;
    }
    $product     = $cart_item['data'];
    $qty         = isset($cart_item['quantity']) ? (int) $cart_item['quantity'] : 0;
    $thumb_html  = $product->get_image('woocommerce_gallery_thumbnail', ['class' => 'ss-checkout-line__img']);
    $badge       = '<span class="ss-checkout-line__qty" aria-hidden="true">' . esc_html((string) $qty) . '</span>';
    $thumb_wrap  = '<span class="ss-checkout-line__thumb">' . $thumb_html . $badge . '</span>';
    return '<span class="ss-checkout-line">' . $thumb_wrap . '<span class="ss-checkout-line__meta">' . $product_name . '</span></span>';
}, 20, 3);

add_filter('woocommerce_checkout_cart_item_quantity', function ($html, $cart_item, $cart_item_key) {
    if (!function_exists('is_checkout') || !is_checkout()) {
        return $html;
    }
    return '';
}, 10, 3);

add_action('woocommerce_review_order_before_submit', function () {
    if (!function_exists('wc_get_cart_url')) {
        return;
    }
    echo '<a class="ss-checkout-return__link" href="' . esc_url(wc_get_cart_url()) . '">' . esc_html__('← Return to cart', 'stone-sparkle') . '</a>';
}, 5);

add_action('template_redirect', function () {
    if (!function_exists('is_account_page') || !is_account_page()) {
        return;
    }
    $wc = function_exists('WC') ? WC() : null;
    if (!$wc || !isset($wc->query)) {
        return;
    }
    if (empty($wc->query->get_current_endpoint())) {
        // Logged-in users: send to orders. Guests with return URL (e.g. wishlist): keep on login form.
        if (!is_user_logged_in() && !empty($_GET['redirect']) && is_string($_GET['redirect'])) {
            $allowed = wp_validate_redirect(wp_unslash($_GET['redirect']), false);
            if ($allowed) {
                return;
            }
        }
        $orders_url = wc_get_account_endpoint_url('orders', '', wc_get_page_permalink('myaccount'));
        if ($orders_url) {
            wp_safe_redirect($orders_url);
            exit;
        }
    }
});

/**
 * Pass redirect query param into login form so guests sent from wishlist (or other links) return after login.
 */
add_action('woocommerce_login_form_end', function () {
    if (empty($_GET['redirect']) || !is_string($_GET['redirect'])) {
        return;
    }
    $redirect_url = wp_validate_redirect(wp_unslash($_GET['redirect']), home_url('/'));
    if ($redirect_url) {
        echo '<input type="hidden" name="redirect" value="' . esc_attr($redirect_url) . '" />';
    }
});

/**
 * Popup Settings context for ACF Free (page-bound) or ACF Pro (options).
 *
 * - With ACF Pro Options Pages: values live in 'option'.
 * - With ACF Free: field group is typically attached to a specific page (often the Front Page).
 */
function ss_popup_settings_context() {
    // Prefer ACF Options if available and the options page exists.
    if (function_exists('acf_get_options_page')) {
        // If an options page with this slug exists, use 'option' context.
        $opts = acf_get_options_page('popup-settings');
        if (!empty($opts)) {
            return 'option';
        }
    }

    // ACF Free fallback: use the WordPress Front Page (static homepage) if set.
    $front_id = (int) get_option('page_on_front');
    if ($front_id > 0) {
        return $front_id;
    }

    // Final fallback: a known page ID (can be overridden by filter).
    $fallback_id = (int) apply_filters('ss_popup_settings_fallback_post_id', 22);
    return $fallback_id > 0 ? $fallback_id : 0;
}

/** Read a popup setting with safe default. */
function ss_popup_get($field_name, $default = null) {
    if (!function_exists('get_field')) {
        return $default;
    }

    $ctx = ss_popup_settings_context();

    // If ctx is 0, avoid accidental "current post" lookups.
    if ($ctx === 0) {
        return $default;
    }

    $val = get_field($field_name, $ctx);

    if ($val === null || $val === '') {
        return $default;
    }
    return $val;
}


add_action('wp_enqueue_scripts', function () {
    $main_deps = [];
    if (function_exists('is_product') && is_product()) {
        $main_deps[] = 'woocommerce-general';
    }
    wp_enqueue_style(
        'stone-sparkle-main',
        get_template_directory_uri() . '/assets/css/main.css',
        $main_deps,
        SS_THEME_VERSION
    );

    wp_enqueue_script(
        'stone-sparkle-main',
        get_template_directory_uri() . '/assets/js/main.js',
        [],
        SS_THEME_VERSION,
        true
    );

    // Expose Popup Settings (ACF Options) to frontend JS.
    // Safe fallbacks are handled in JS as well.
    if (function_exists('get_field')) {
        $payload = [
            'enabled'        => (bool) ss_popup_get('popup_enabled'),
            'trigger'        => (string) (ss_popup_get('popup_trigger') ?: 'on_load'),
            'delaySeconds'   => (int) (ss_popup_get('popup_delay_seconds') ?? 0),
            'frequencyDays'  => (int) (ss_popup_get('popup_frequency_days') ?? 0),
        ];
    } else {
        $payload = [
            'enabled'        => false,
            'trigger'        => 'on_load',
            'delaySeconds'   => 0,
            'frequencyDays'  => 0,
        ];
    }

    wp_localize_script('stone-sparkle-main', 'SS_POPUP', $payload);

    // Product gallery enhancements (single product only).
    if (function_exists('is_product') && is_product()) {
        wp_enqueue_style(
            'stone-sparkle-product-gallery',
            get_template_directory_uri() . '/assets/css/product-gallery.css',
            ['stone-sparkle-main'],
            SS_THEME_VERSION
        );

        wp_enqueue_script(
            'stone-sparkle-product-gallery',
            get_template_directory_uri() . '/assets/js/product-gallery.js',
            [],
            SS_THEME_VERSION,
            true
        );

        wp_enqueue_script(
            'stone-sparkle-quantity-stepper',
            get_template_directory_uri() . '/assets/js/quantity-stepper.js',
            [],
            SS_THEME_VERSION,
            true
        );

        wp_enqueue_script(
            'stone-sparkle-variation-select',
            get_template_directory_uri() . '/assets/js/variation-custom-select.js',
            [ 'jquery', 'wc-add-to-cart-variation' ],
            SS_THEME_VERSION,
            true
        );
    }

    // Contact Us (unified) – load only on the Contact Us page (slug: contact-us).
    if (function_exists('is_page') && is_page('contact-us')) {
        $ss_cu_css = get_template_directory() . '/assets/css/contact-us.css';
        $ss_cu_js  = get_template_directory() . '/assets/js/contact-us.js';
        $ss_cu_css_ver = file_exists($ss_cu_css) ? (string) filemtime($ss_cu_css) : SS_THEME_VERSION;
        $ss_cu_js_ver  = file_exists($ss_cu_js)  ? (string) filemtime($ss_cu_js)  : SS_THEME_VERSION;

        wp_enqueue_style(
            'stone-sparkle-contact-us',
            get_template_directory_uri() . '/assets/css/contact-us.css',
            ['stone-sparkle-main'],
            $ss_cu_css_ver
        );

        wp_enqueue_script(
            'stone-sparkle-contact-us',
            get_template_directory_uri() . '/assets/js/contact-us.js',
            [],
            $ss_cu_js_ver,
            true
        );

        // ACF-driven config for the phone country dropdown.
        $pid = (int) get_queried_object_id();
        $cfg = [
            'countryEnabled'  => function_exists('get_field') ? (bool) get_field('cu_phone_country_enabled', $pid) : true,
            'countryLabel'    => function_exists('get_field') ? (string) (get_field('cu_phone_country_label', $pid) ?: 'Country') : 'Country',
            'countryRequired' => function_exists('get_field') ? (bool) get_field('cu_phone_country_required', $pid) : true,
        ];
        wp_localize_script('stone-sparkle-contact-us', 'SS_CONTACT_US', $cfg);
    }
});

/**
 * Contact Us (Unified) field group – ACF Free compatible.
 *
 * Location rule requirement: Page == "Contact Us".
 * Page IDs differ per environment, so we resolve by title at runtime.
 * If the page isn't created yet, we attach by template as a safe fallback.
 */
add_action('acf/include_fields', function() {
    if (!function_exists('acf_add_local_field_group')) { return; }

    $contact_page_id = 0;
    $p = get_page_by_title('Contact Us');
    if ($p && !is_wp_error($p)) {
        $contact_page_id = (int) $p->ID;
    }

    $location = [];
    if ($contact_page_id > 0) {
        $location = [
            [
                [
                    'param' => 'page',
                    'operator' => '==',
                    'value' => (string) $contact_page_id,
                ],
            ],
        ];
    } else {
        // Fallback only until the page exists.
        $location = [
            [
                [
                    'param' => 'page_template',
                    'operator' => '==',
                    'value' => 'page-contact-us.php',
                ],
            ],
        ];
    }

    acf_add_local_field_group([
        'key' => 'group_gby_contact_us_unified',
        'title' => 'GBY – Contact Us Unified',
        'fields' => [
            // GLOBAL
            [
                'key' => 'field_cu_page_enabled',
                'label' => 'cu_page_enabled',
                'name' => 'cu_page_enabled',
                'type' => 'true_false',
                'ui' => 1,
                'default_value' => 1,
            ],

            // HERO
            [
                'key' => 'field_cu_hero_enabled',
                'label' => 'cu_hero_enabled',
                'name' => 'cu_hero_enabled',
                'type' => 'true_false',
                'ui' => 1,
                'default_value' => 1,
            ],
            [
                'key' => 'field_cu_hero_image',
                'label' => 'cu_hero_image',
                'name' => 'cu_hero_image',
                'type' => 'image',
                'return_format' => 'array',
                'preview_size' => 'medium',
                'library' => 'all',
            ],
            [
                'key' => 'field_cu_hero_title',
                'label' => 'cu_hero_title',
                'name' => 'cu_hero_title',
                'type' => 'text',
                'default_value' => '',
            ],
            [
                'key' => 'field_cu_hero_subtitle',
                'label' => 'cu_hero_subtitle',
                'name' => 'cu_hero_subtitle',
                'type' => 'textarea',
                'new_lines' => 'br',
                'default_value' => '',
            ],

            // CONTACT INFO
            [
                'key' => 'field_cu_info_enabled',
                'label' => 'cu_info_enabled',
                'name' => 'cu_info_enabled',
                'type' => 'true_false',
                'ui' => 1,
                'default_value' => 1,
            ],
            [
                'key' => 'field_cu_info_title',
                'label' => 'cu_info_title',
                'name' => 'cu_info_title',
                'type' => 'text',
                'default_value' => '',
            ],
            [
                'key' => 'field_cu_info_subtitle',
                'label' => 'cu_info_subtitle',
                'name' => 'cu_info_subtitle',
                'type' => 'textarea',
                'new_lines' => 'br',
                'default_value' => '',
            ],

            // Card 1
            [
                'key' => 'field_cu_card1_enabled',
                'label' => 'cu_card1_enabled',
                'name' => 'cu_card1_enabled',
                'type' => 'true_false',
                'ui' => 1,
                'default_value' => 1,
            ],
            [
                'key' => 'field_cu_card1_icon',
                'label' => 'cu_card1_icon',
                'name' => 'cu_card1_icon',
                'type' => 'image',
                'return_format' => 'array',
                'preview_size' => 'thumbnail',
                'library' => 'all',
            ],
            [
                'key' => 'field_cu_card1_title',
                'label' => 'cu_card1_title',
                'name' => 'cu_card1_title',
                'type' => 'text',
                'default_value' => '',
            ],
            [
                'key' => 'field_cu_card1_text',
                'label' => 'cu_card1_text',
                'name' => 'cu_card1_text',
                'type' => 'textarea',
                'new_lines' => 'br',
                'default_value' => '',
            ],

            // Card 2
            [
                'key' => 'field_cu_card2_enabled',
                'label' => 'cu_card2_enabled',
                'name' => 'cu_card2_enabled',
                'type' => 'true_false',
                'ui' => 1,
                'default_value' => 1,
            ],
            [
                'key' => 'field_cu_card2_icon',
                'label' => 'cu_card2_icon',
                'name' => 'cu_card2_icon',
                'type' => 'image',
                'return_format' => 'array',
                'preview_size' => 'thumbnail',
                'library' => 'all',
            ],
            [
                'key' => 'field_cu_card2_title',
                'label' => 'cu_card2_title',
                'name' => 'cu_card2_title',
                'type' => 'text',
                'default_value' => '',
            ],
            [
                'key' => 'field_cu_card2_text',
                'label' => 'cu_card2_text',
                'name' => 'cu_card2_text',
                'type' => 'textarea',
                'new_lines' => 'br',
                'default_value' => '',
            ],

            // Card 3
            [
                'key' => 'field_cu_card3_enabled',
                'label' => 'cu_card3_enabled',
                'name' => 'cu_card3_enabled',
                'type' => 'true_false',
                'ui' => 1,
                'default_value' => 1,
            ],
            [
                'key' => 'field_cu_card3_icon',
                'label' => 'cu_card3_icon',
                'name' => 'cu_card3_icon',
                'type' => 'image',
                'return_format' => 'array',
                'preview_size' => 'thumbnail',
                'library' => 'all',
            ],
            [
                'key' => 'field_cu_card3_title',
                'label' => 'cu_card3_title',
                'name' => 'cu_card3_title',
                'type' => 'text',
                'default_value' => '',
            ],
            [
                'key' => 'field_cu_card3_text',
                'label' => 'cu_card3_text',
                'name' => 'cu_card3_text',
                'type' => 'textarea',
                'new_lines' => 'br',
                'default_value' => '',
            ],

            // Card 4
            [
                'key' => 'field_cu_card4_enabled',
                'label' => 'cu_card4_enabled',
                'name' => 'cu_card4_enabled',
                'type' => 'true_false',
                'ui' => 1,
                'default_value' => 1,
            ],
            [
                'key' => 'field_cu_card4_icon',
                'label' => 'cu_card4_icon',
                'name' => 'cu_card4_icon',
                'type' => 'image',
                'return_format' => 'array',
                'preview_size' => 'thumbnail',
                'library' => 'all',
            ],
            [
                'key' => 'field_cu_card4_title',
                'label' => 'cu_card4_title',
                'name' => 'cu_card4_title',
                'type' => 'text',
                'default_value' => '',
            ],
            [
                'key' => 'field_cu_card4_text',
                'label' => 'cu_card4_text',
                'name' => 'cu_card4_text',
                'type' => 'textarea',
                'new_lines' => 'br',
                'default_value' => '',
            ],

            // GET IN TOUCH
            [
                'key' => 'field_cu_form_enabled',
                'label' => 'cu_form_enabled',
                'name' => 'cu_form_enabled',
                'type' => 'true_false',
                'ui' => 1,
                'default_value' => 1,
            ],
            [
                'key' => 'field_cu_form_title',
                'label' => 'cu_form_title',
                'name' => 'cu_form_title',
                'type' => 'text',
                'default_value' => '',
            ],
            [
                'key' => 'field_cu_form_subtitle',
                'label' => 'cu_form_subtitle',
                'name' => 'cu_form_subtitle',
                'type' => 'textarea',
                'new_lines' => 'br',
                'default_value' => '',
            ],

            // FLUENT FORMS
            [
                'key' => 'field_cu_use_fluent_form',
                'label' => 'cu_use_fluent_form',
                'name' => 'cu_use_fluent_form',
                'type' => 'true_false',
                'ui' => 1,
                'default_value' => 1,
            ],
            [
                'key' => 'field_cu_fluent_form_id',
                'label' => 'cu_fluent_form_id',
                'name' => 'cu_fluent_form_id',
                'type' => 'number',
                'default_value' => '',
            ],
            [
                'key' => 'field_cu_form_shortcode_fallback',
                'label' => 'cu_form_shortcode_fallback',
                'name' => 'cu_form_shortcode_fallback',
                'type' => 'text',
                'default_value' => '',
            ],

            // PHONE COUNTRY
            [
                'key' => 'field_cu_phone_country_enabled',
                'label' => 'cu_phone_country_enabled',
                'name' => 'cu_phone_country_enabled',
                'type' => 'true_false',
                'ui' => 1,
                'default_value' => 1,
            ],
            [
                'key' => 'field_cu_phone_country_label',
                'label' => 'cu_phone_country_label',
                'name' => 'cu_phone_country_label',
                'type' => 'text',
                'default_value' => 'Country',
            ],
            [
                'key' => 'field_cu_phone_country_required',
                'label' => 'cu_phone_country_required',
                'name' => 'cu_phone_country_required',
                'type' => 'true_false',
                'ui' => 1,
                'default_value' => 1,
            ],

            // STYLING HELPERS
            [
                'key' => 'field_cu_section_bg_color',
                'label' => 'cu_section_bg_color',
                'name' => 'cu_section_bg_color',
                'type' => 'text',
                'default_value' => '',
            ],
            [
                'key' => 'field_cu_form_bg_color',
                'label' => 'cu_form_bg_color',
                'name' => 'cu_form_bg_color',
                'type' => 'text',
                'default_value' => '',
            ],
        ],
        'location' => $location,
        'menu_order' => 0,
        'position' => 'normal',
        'style' => 'default',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
        'active' => true,
        'show_in_rest' => 0,
    ]);
});

/**
 * WooCommerce: Custom single-product image gallery
 * - Fixed 576x576 stage (desktop)
 * - Left thumbnails (desktop); row below stage on narrow viewports (max-width: 767.98px)
 * - Prev/Next controls (desktop); hidden on narrow viewports — thumbnails only
 * - Desktop: hover zoom + pointer pan (JS). Mobile/narrow: press-and-hold touch zoom (ends on release)
 */
add_action('wp', function () {
    if (!function_exists('is_product') || !is_product()) {
        return;
    }
    // Replace default WooCommerce gallery output.
    remove_action('woocommerce_before_single_product_summary', 'woocommerce_show_product_images', 20);
    add_action('woocommerce_before_single_product_summary', 'ss_render_product_gallery', 20);
}, 20);

function ss_render_product_gallery() {
    if (!function_exists('wc_get_product')) {
        return;
    }

    global $product;
    if (!$product || !is_a($product, 'WC_Product')) {
        $product = wc_get_product(get_the_ID());
    }
    if (!$product) {
        return;
    }

    $ids = [];
    $featured_id = (int) $product->get_image_id();
    if ($featured_id) {
        $ids[] = $featured_id;
    }
    $gallery_ids = $product->get_gallery_image_ids();
    if (is_array($gallery_ids)) {
        foreach ($gallery_ids as $gid) {
            $gid = (int) $gid;
            if ($gid && !in_array($gid, $ids, true)) {
                $ids[] = $gid;
            }
        }
    }

    // Fallback: if no images, use placeholder.
    if (empty($ids)) {
        $placeholder = function_exists('wc_placeholder_img_src') ? wc_placeholder_img_src('woocommerce_single') : '';
        $images = [[
            'full'  => $placeholder,
            'thumb' => $placeholder,
            'alt'   => esc_attr__('Product image', 'stone-sparkle'),
        ]];
    } else {
        $images = [];
        foreach ($ids as $id) {
            // Use the full/original asset for the zoom source so that
            // desktop zoom at 2.5x on a 576px viewport remains crisp.
            $full  = wp_get_attachment_image_url($id, 'full');
            $thumb = wp_get_attachment_image_url($id, 'woocommerce_thumbnail');
            if (!$full) {
                continue;
            }
            if (!$thumb) {
                $thumb = $full;
            }
            $alt = get_post_meta($id, '_wp_attachment_image_alt', true);
            if ($alt === '') {
                $alt = get_the_title($id);
            }
            $images[] = [
                'full'  => $full,
                'thumb' => $thumb,
                'alt'   => $alt,
            ];
        }
        if (empty($images)) {
            $placeholder = function_exists('wc_placeholder_img_src') ? wc_placeholder_img_src('woocommerce_single') : '';
            $images = [[
                'full'  => $placeholder,
                'thumb' => $placeholder,
                'alt'   => esc_attr__('Product image', 'stone-sparkle'),
            ]];
        }
    }

    $json = wp_json_encode($images);
    if (!$json) {
        $json = '[]';
    }

    echo '<div class="ss-product-gallery" data-images="' . esc_attr($json) . '">';
    echo '  <div class="ss-product-thumbs" aria-label="' . esc_attr__('Product thumbnails', 'stone-sparkle') . '"></div>';
    echo '  <div class="ss-product-stage">';
    echo '    <div class="ss-stage-viewport" aria-label="' . esc_attr__('Product image', 'stone-sparkle') . '">';
    echo '      <img class="ss-stage-image" src="' . esc_url($images[0]['full']) . '" alt="' . esc_attr($images[0]['alt']) . '" loading="eager" decoding="async" />';
    echo '      <button type="button" class="ss-gallery-nav ss-gallery-prev" aria-label="' . esc_attr__('Previous image', 'stone-sparkle') . '">&#8249;</button>';
    echo '      <button type="button" class="ss-gallery-nav ss-gallery-next" aria-label="' . esc_attr__('Next image', 'stone-sparkle') . '">&#8250;</button>';
    echo '    </div>';
    echo '  </div>';
    echo '</div>';
}

/**
 * ACF: Popup Settings (Options Page + Field Group)
 * - No repeaters
 * - Field names match the required keys exactly
 */
add_action('acf/init', function () {
    if (!function_exists('acf_add_options_page') || !function_exists('acf_add_local_field_group')) {
        return;
    }

    acf_add_options_page([
        'page_title' => __('Popup Settings', 'stone-sparkle'),
        'menu_title' => __('Popup Settings', 'stone-sparkle'),
        'menu_slug'  => 'ss-popup-settings',
        'capability' => 'manage_options',
        'redirect'   => false,
        'position'   => 58,
    ]);

    acf_add_local_field_group([
        'key'    => 'group_ss_popup_settings',
        'title'  => 'Popup Settings',
        'fields' => [
            // POPUP CORE
            [
                'key'   => 'field_ss_popup_enabled',
                'label' => 'popup_enabled',
                'name'  => 'popup_enabled',
                'type'  => 'true_false',
                'ui'    => 1,
                'default_value' => 0,
            ],
            [
                'key'   => 'field_ss_popup_title',
                'label' => 'popup_title',
                'name'  => 'popup_title',
                'type'  => 'text',
            ],
            [
                'key'   => 'field_ss_popup_body',
                'label' => 'popup_body',
                'name'  => 'popup_body',
                'type'  => 'wysiwyg',
                'tabs'  => 'all',
                'toolbar' => 'basic',
                'media_upload' => 0,
            ],
            [
                'key'     => 'field_ss_popup_trigger',
                'label'   => 'popup_trigger',
                'name'    => 'popup_trigger',
                'type'    => 'select',
                'choices' => [
                    'on_load'   => 'on_load',
                    'on_scroll' => 'on_scroll',
                ],
                'default_value' => 'on_load',
                'ui' => 1,
            ],
            [
                'key'   => 'field_ss_popup_delay_seconds',
                'label' => 'popup_delay_seconds',
                'name'  => 'popup_delay_seconds',
                'type'  => 'number',
                'min'   => 0,
                'step'  => 1,
                'default_value' => 0,
            ],
            [
                'key'   => 'field_ss_popup_frequency_days',
                'label' => 'popup_frequency_days',
                'name'  => 'popup_frequency_days',
                'type'  => 'number',
                'min'   => 0,
                'step'  => 1,
                'default_value' => 0,
            ],

            // FORM FIELDS (COMMON)
            // Email
            [
                'key'   => 'field_ss_email_enabled',
                'label' => 'email_enabled',
                'name'  => 'email_enabled',
                'type'  => 'true_false',
                'ui'    => 1,
                'default_value' => 1,
            ],
            [
                'key'   => 'field_ss_email_label',
                'label' => 'email_label',
                'name'  => 'email_label',
                'type'  => 'text',
                'default_value' => 'Email',
            ],
            [
                'key'   => 'field_ss_email_placeholder',
                'label' => 'email_placeholder',
                'name'  => 'email_placeholder',
                'type'  => 'text',
                'default_value' => 'Enter your email',
            ],
            [
                'key'   => 'field_ss_email_required',
                'label' => 'email_required',
                'name'  => 'email_required',
                'type'  => 'true_false',
                'ui'    => 1,
                'default_value' => 1,
            ],

            // First name
            [
                'key'   => 'field_ss_first_name_enabled',
                'label' => 'first_name_enabled',
                'name'  => 'first_name_enabled',
                'type'  => 'true_false',
                'ui'    => 1,
                'default_value' => 1,
            ],
            [
                'key'   => 'field_ss_first_name_label',
                'label' => 'first_name_label',
                'name'  => 'first_name_label',
                'type'  => 'text',
                'default_value' => 'First name',
            ],
            [
                'key'   => 'field_ss_first_name_placeholder',
                'label' => 'first_name_placeholder',
                'name'  => 'first_name_placeholder',
                'type'  => 'text',
                'default_value' => 'Enter your first name',
            ],
            [
                'key'   => 'field_ss_first_name_required',
                'label' => 'first_name_required',
                'name'  => 'first_name_required',
                'type'  => 'true_false',
                'ui'    => 1,
                'default_value' => 0,
            ],

            // Last name
            [
                'key'   => 'field_ss_last_name_enabled',
                'label' => 'last_name_enabled',
                'name'  => 'last_name_enabled',
                'type'  => 'true_false',
                'ui'    => 1,
                'default_value' => 0,
            ],
            [
                'key'   => 'field_ss_last_name_label',
                'label' => 'last_name_label',
                'name'  => 'last_name_label',
                'type'  => 'text',
                'default_value' => 'Last name',
            ],
            [
                'key'   => 'field_ss_last_name_placeholder',
                'label' => 'last_name_placeholder',
                'name'  => 'last_name_placeholder',
                'type'  => 'text',
                'default_value' => 'Enter your last name',
            ],
            [
                'key'   => 'field_ss_last_name_required',
                'label' => 'last_name_required',
                'name'  => 'last_name_required',
                'type'  => 'true_false',
                'ui'    => 1,
                'default_value' => 0,
            ],

            // Phone
            [
                'key'   => 'field_ss_phone_enabled',
                'label' => 'phone_enabled',
                'name'  => 'phone_enabled',
                'type'  => 'true_false',
                'ui'    => 1,
                'default_value' => 0,
            ],
            [
                'key'   => 'field_ss_phone_label',
                'label' => 'phone_label',
                'name'  => 'phone_label',
                'type'  => 'text',
                'default_value' => 'Phone',
            ],
            [
                'key'   => 'field_ss_phone_placeholder',
                'label' => 'phone_placeholder',
                'name'  => 'phone_placeholder',
                'type'  => 'text',
                'default_value' => '05x xxx xxxx',
            ],
            [
                'key'   => 'field_ss_phone_required',
                'label' => 'phone_required',
                'name'  => 'phone_required',
                'type'  => 'true_false',
                'ui'    => 1,
                'default_value' => 0,
            ],

            // Extra (interest dropdown)
            [
                'key'   => 'field_ss_extra_enabled',
                'label' => 'extra_enabled',
                'name'  => 'extra_enabled',
                'type'  => 'true_false',
                'ui'    => 1,
                'default_value' => 0,
            ],
            [
                'key'   => 'field_ss_extra_label',
                'label' => 'extra_label',
                'name'  => 'extra_label',
                'type'  => 'text',
                'default_value' => "I’m interested in",
            ],
            [
                'key'   => 'field_ss_extra_required',
                'label' => 'extra_required',
                'name'  => 'extra_required',
                'type'  => 'true_false',
                'ui'    => 1,
                'default_value' => 0,
            ],
            [
                'key'   => 'field_ss_extra_option_rings_enabled',
                'label' => 'extra_option_rings_enabled',
                'name'  => 'extra_option_rings_enabled',
                'type'  => 'true_false',
                'ui'    => 1,
                'default_value' => 1,
            ],
            [
                'key'   => 'field_ss_extra_option_earrings_enabled',
                'label' => 'extra_option_earrings_enabled',
                'name'  => 'extra_option_earrings_enabled',
                'type'  => 'true_false',
                'ui'    => 1,
                'default_value' => 1,
            ],
            [
                'key'   => 'field_ss_extra_option_necklaces_enabled',
                'label' => 'extra_option_necklaces_enabled',
                'name'  => 'extra_option_necklaces_enabled',
                'type'  => 'true_false',
                'ui'    => 1,
                'default_value' => 1,
            ],
            [
                'key'   => 'field_ss_extra_option_bracelets_enabled',
                'label' => 'extra_option_bracelets_enabled',
                'name'  => 'extra_option_bracelets_enabled',
                'type'  => 'true_false',
                'ui'    => 1,
                'default_value' => 1,
            ],
        ],
        'location' => [
            [
                [
                    'param'    => 'options_page',
                    'operator' => '==',
                    'value'    => 'ss-popup-settings',
                ],
            ],
        ],
        'position' => 'normal',
        'style'    => 'default',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
        'hide_on_screen' => '',
        'active'   => true,
        'description' => '',
    ]);
});

// Optional: keep ACF JSON synced with the theme.
add_filter('acf/settings/save_json', function ($path) {
    return get_template_directory() . '/acf-json';
});

add_filter('acf/settings/load_json', function ($paths) {
    $paths[] = get_template_directory() . '/acf-json';
    return $paths;
});

/**
 * Frontend: render the popup markup (only when enabled).
 * Markup is intentionally self-contained and only reads from ACF Options.
 */
add_action('wp_footer', function () {
    if (!function_exists('get_field')) {
        return;
    }

    $enabled = (bool) ss_popup_get('popup_enabled');
    if (!$enabled) {
        return;
    }

    $title = (string) (ss_popup_get('popup_title') ?: 'Join our newsletter');
    $body  = ss_popup_get('popup_body');

    // Form toggles + content.
    // Integration note:
    // This popup submits to *The Newsletter Plugin* (by Stefano Lissa).
    // That plugin expects specific field names on a custom HTML form:
    // - ne: subscriber email (required)
    // - nn: subscriber name / first name
    // - ns: subscriber surname / last name
    // - npX: subscriber profile fields (optional)
    // - action URL must point to: https://www.domain.tld/?na=s
    // Reference: https://www.thenewsletterplugin.com/documentation/subscription/newsletter-forms/
    $fields = [
        'email' => [
            'enabled'     => (bool) (ss_popup_get('email_enabled') ?? true),
            'label'       => (string) (ss_popup_get('email_label') ?: 'Email'),
            'placeholder' => (string) (ss_popup_get('email_placeholder') ?: 'Enter your email'),
            'required'    => (bool) (ss_popup_get('email_required') ?? true),
            'type'        => 'email',
            'name'        => 'ne',
            'autocomplete'=> 'email',
        ],
        'first_name' => [
            'enabled'     => (bool) (ss_popup_get('first_name_enabled') ?? true),
            'label'       => (string) (ss_popup_get('first_name_label') ?: 'First name'),
            'placeholder' => (string) (ss_popup_get('first_name_placeholder') ?: 'Enter your first name'),
            'required'    => (bool) (ss_popup_get('first_name_required') ?? false),
            'type'        => 'text',
            'name'        => 'nn',
            'autocomplete'=> 'given-name',
        ],
        'last_name' => [
            'enabled'     => (bool) (ss_popup_get('last_name_enabled') ?? false),
            'label'       => (string) (ss_popup_get('last_name_label') ?: 'Last name'),
            'placeholder' => (string) (ss_popup_get('last_name_placeholder') ?: 'Enter your last name'),
            'required'    => (bool) (ss_popup_get('last_name_required') ?? false),
            'type'        => 'text',
            'name'        => 'ns',
            'autocomplete'=> 'family-name',
        ],
        'phone' => [
            'enabled'     => (bool) (ss_popup_get('phone_enabled') ?? false),
            'label'       => (string) (ss_popup_get('phone_label') ?: 'Phone'),
            'placeholder' => (string) (ss_popup_get('phone_placeholder') ?: '05x xxx xxxx'),
            'required'    => (bool) (ss_popup_get('phone_required') ?? false),
            'type'        => 'tel',
            // Map phone to Newsletter profile field #2 (np2).
            // If you want to store it, configure profile field #2 in Newsletter (Subscription/Profile Fields).
            'name'        => 'np2',
            'autocomplete'=> 'tel',
        ],
        'extra' => [
            'enabled'     => (bool) (ss_popup_get('extra_enabled') ?? false),
            'label'       => (string) (ss_popup_get('extra_label') ?: "I’m interested in"),
            'required'    => (bool) (ss_popup_get('extra_required') ?? false),
            // Map the interest dropdown to Newsletter profile field #1 (np1).
            // Configure profile field #1 in Newsletter (Subscription/Profile Fields) as a dropdown if desired.
            'name'        => 'np1',
        ],
    ];

    $extra_options = [];
    if ($fields['extra']['enabled']) {
        if ((bool) (ss_popup_get('extra_option_rings_enabled') ?? true)) {
            $extra_options[] = ['value' => 'Rings', 'label' => 'Rings'];
        }
        if ((bool) (ss_popup_get('extra_option_earrings_enabled') ?? true)) {
            $extra_options[] = ['value' => 'Earrings', 'label' => 'Earrings'];
        }
        if ((bool) (ss_popup_get('extra_option_necklaces_enabled') ?? true)) {
            $extra_options[] = ['value' => 'Necklaces', 'label' => 'Necklaces'];
        }
        if ((bool) (ss_popup_get('extra_option_bracelets_enabled') ?? true)) {
            $extra_options[] = ['value' => 'Bracelets', 'label' => 'Bracelets'];
        }
    }

    // Render.
    ?>
    <div class="ss-popup" id="ssNewsletterPopup" aria-hidden="true">
        <div class="ss-popup__backdrop" data-ss-popup-close tabindex="-1"></div>
        <div class="ss-popup__dialog" role="dialog" aria-modal="true" aria-labelledby="ssPopupTitle">
            <button class="ss-popup__close" type="button" aria-label="<?php echo esc_attr__('Close', 'stone-sparkle'); ?>" data-ss-popup-close>
                <span aria-hidden="true">&times;</span>
            </button>

            <div class="ss-popup__content">
                <h2 class="ss-popup__title" id="ssPopupTitle"><?php echo esc_html($title); ?></h2>
                <?php if (!empty($body)) : ?>
                    <div class="ss-popup__body"><?php echo wp_kses_post($body); ?></div>
                <?php endif; ?>

                <form class="ss-popup__form" method="post" action="<?php echo esc_url(home_url('/?na=s')); ?>" novalidate>
                    <input type="hidden" name="nr" value="acf-popup" />
                    <div class="ss-popup__fields">
                        <?php foreach (['first_name','last_name','email','phone'] as $k) :
                            $f = $fields[$k];
                            if (!$f['enabled']) continue;
                            $id = 'ss_popup_' . $k;
                        ?>
                            <div class="ss-popup__field">
                                <label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($f['label']); ?></label>
                                <input
                                    id="<?php echo esc_attr($id); ?>"
                                    name="<?php echo esc_attr($f['name']); ?>"
                                    type="<?php echo esc_attr($f['type']); ?>"
                                    placeholder="<?php echo esc_attr($f['placeholder']); ?>"
                                    autocomplete="<?php echo esc_attr($f['autocomplete']); ?>"
                                    <?php echo $f['required'] ? 'required' : ''; ?>
                                />
                            </div>
                        <?php endforeach; ?>

                        <?php if ($fields['extra']['enabled']) :
                            $id = 'ss_popup_extra';
                            $has_options = !empty($extra_options);
                        ?>
                            <div class="ss-popup__field">
                                <label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($fields['extra']['label']); ?></label>
                                <select id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($fields['extra']['name']); ?>" <?php echo $fields['extra']['required'] ? 'required' : ''; ?>>
                                    <option value="" disabled selected><?php echo esc_html__('Select…', 'stone-sparkle'); ?></option>
                                    <?php if ($has_options) : ?>
                                        <?php foreach ($extra_options as $opt) : ?>
                                            <option value="<?php echo esc_attr($opt['value']); ?>"><?php echo esc_html($opt['label']); ?></option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="ss-popup__actions">
                        <button class="ss-popup__submit" type="submit"><?php echo esc_html__('Join', 'stone-sparkle'); ?></button>
                        <p class="ss-popup__fineprint"><?php echo esc_html__('No spam — only curated drops.', 'stone-sparkle'); ?></p>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php
});

/**
 * If the site front page is not set, but a page with slug "home-page" exists,
 * redirect / to /home-page/ so the client always lands on the intended homepage.
 */
add_action('template_redirect', function () {
    if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
        return;
    }

    if (is_front_page() && !is_page('home-page')) {
        $home_page = get_page_by_path('home-page');
        if ($home_page) {
            wp_safe_redirect(get_permalink($home_page), 301);
            exit;
        }
    }
});

/**
 * Send visitors from the WooCommerce shop archive (typically /shop/) to the site home.
 * WooCommerce still needs a Shop page assigned in settings; this only affects the public URL.
 */
add_action('template_redirect', function () {
    if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
        return;
    }
    if (function_exists('is_customize_preview') && is_customize_preview()) {
        return;
    }
    if (is_feed()) {
        return;
    }
    if (!function_exists('is_shop') || !is_shop() || is_search()) {
        return;
    }
    wp_safe_redirect(home_url('/'), 301);
    exit;
}, 5);

/**
 * Register a simple block pattern for the homepage lookbook layout.
 * The design is handled by CSS classes (ss-lookbook, ss-look, ss-look-card).
 */
add_action('init', function () {
    if (!function_exists('register_block_pattern')) {
        return;
    }

    register_block_pattern_category('stone-sparkle', [
        'label' => __('Stone Sparkle', 'stone-sparkle'),
    ]);

    $pattern = <<<HTML
<!-- wp:group {"className":"ss-lookbook","layout":{"type":"constrained"}} -->
<div class="wp-block-group ss-lookbook">
  <!-- wp:group {"className":"ss-look","layout":{"type":"constrained"}} -->
  <div class="wp-block-group ss-look">
    <!-- wp:group {"className":"ss-look-card"} -->
    <div class="wp-block-group ss-look-card">
      <!-- wp:cover {"dimRatio":0,"className":"ss-ph"} -->
      <div class="wp-block-cover ss-ph"><span aria-hidden="true" class="wp-block-cover__background has-background-dim-0 has-background-dim"></span><div class="wp-block-cover__inner-container">
        <!-- wp:group {"className":"ss-ph-caption","layout":{"type":"constrained"}} -->
        <div class="wp-block-group ss-ph-caption">
          <!-- wp:paragraph {"className":"kicker"} --><p class="kicker">NEW DROP</p><!-- /wp:paragraph -->
          <!-- wp:heading {"level":3,"className":"title"} --><h3 class="title">Earrings</h3><!-- /wp:heading -->
        </div>
        <!-- /wp:group -->
      </div></div>
      <!-- /wp:cover -->
    </div>
    <!-- /wp:group -->
    <!-- wp:buttons -->
    <div class="wp-block-buttons">
      <!-- wp:button {"className":"ss-btn"} --><div class="wp-block-button ss-btn"><a class="wp-block-button__link wp-element-button" href="/shop/">Shop</a></div><!-- /wp:button -->
    </div>
    <!-- /wp:buttons -->
  </div>
  <!-- /wp:group -->
</div>
<!-- /wp:group -->
HTML;

    register_block_pattern('stone-sparkle/home-lookbook', [
        'title'       => __('Homepage Lookbook (Starter)', 'stone-sparkle'),
        'description' => __('A stacked lookbook section matching the reference layout. Replace images and links.', 'stone-sparkle'),
        'categories'  => ['stone-sparkle'],
        'content'     => $pattern,
    ]);
});

/**
 * WooCommerce tweaks
 */
add_filter('loop_shop_columns', function () {
    return 4;
});

// Reduce Woo default wrappers spacing (CSS handles layout).
add_filter('woocommerce_product_loop_start', function ($html) {
    return '<ul class="products columns-4 ss-products">';
});

/**
 * Collection pages: custom ordering helpers.
 *
 * Implemented as scoped SQL adjustments for the collection page product query only.
 * Supported modes via `ss_collection_ordering` query var:
 * - type: product category priority list (filterable via `ss_collection_type_sort_priority`)
 * - price / price-desc: order by lookup table min_price (WooCommerce)
 * - popularity: order by lookup table total_sales (WooCommerce)
 */
add_filter('posts_clauses', function ($clauses, $query) {
    if (!($query instanceof WP_Query)) {
        return $clauses;
    }
    if (is_admin() || !$query->is_main_query() && !$query->get('ss_collection_ordering')) {
        // Not our query (we only set ss_collection_ordering on the collection page product query).
        return $clauses;
    }
    $mode = (string) $query->get('ss_collection_ordering');
    if ($mode === '') {
        return $clauses;
    }

    global $wpdb;

    // ---------------------------------------------------------------------
    // price / price-desc / popularity: use WooCommerce lookup table ordering
    // ---------------------------------------------------------------------
    if (in_array($mode, ['price', 'price-desc', 'popularity'], true)) {
        static $lookup_table_exists = null;
        if ($lookup_table_exists === null) {
            $table = $wpdb->prefix . 'wc_product_meta_lookup';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $lookup_table_exists = (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        }

        if ($lookup_table_exists) {
            $lookup_table = $wpdb->prefix . 'wc_product_meta_lookup';
            $clauses['join'] .= " \nLEFT JOIN {$lookup_table} AS ss_wcl ON (ss_wcl.product_id = {$wpdb->posts}.ID)";

            $order_by = '';
            if ($mode === 'popularity') {
                $order_by = "COALESCE(ss_wcl.total_sales, 0) DESC, {$wpdb->posts}.post_title ASC";
            } elseif ($mode === 'price-desc') {
                $order_by = "COALESCE(ss_wcl.min_price, 0) DESC, {$wpdb->posts}.post_title ASC";
            } else {
                $order_by = "COALESCE(ss_wcl.min_price, 0) ASC, {$wpdb->posts}.post_title ASC";
            }

            $clauses['orderby'] = $order_by;
            return $clauses;
        }

        // Fallback: if lookup table missing, keep default clauses (WP_Query meta ordering may apply elsewhere).
        return $clauses;
    }

    // ---------------------------------------------------------------------
    // type: product category priority list
    // ---------------------------------------------------------------------
    if ($mode !== 'type') {
        return $clauses;
    }

    $default_priority = ['rings', 'necklaces', 'bangles', 'bracelets', 'earrings'];
    $priority_slugs = apply_filters('ss_collection_type_sort_priority', $default_priority);
    if (!is_array($priority_slugs)) {
        $priority_slugs = $default_priority;
    }
    $priority_slugs = array_values(array_filter(array_map('sanitize_title', $priority_slugs)));
    if (empty($priority_slugs)) {
        $priority_slugs = $default_priority;
    }

    $when = [];
    $i = 1;
    foreach ($priority_slugs as $slug) {
        $when[] = "WHEN t.slug = '" . esc_sql($slug) . "' THEN " . (int) $i;
        $i++;
    }
    $case = 'CASE ' . implode(' ', $when) . ' ELSE 999 END';

    $clauses['join'] .= " \nLEFT JOIN {$wpdb->term_relationships} AS ss_tr ON ({$wpdb->posts}.ID = ss_tr.object_id)";
    $clauses['join'] .= " \nLEFT JOIN {$wpdb->term_taxonomy} AS ss_tt ON (ss_tr.term_taxonomy_id = ss_tt.term_taxonomy_id AND ss_tt.taxonomy = 'product_cat')";
    $clauses['join'] .= " \nLEFT JOIN {$wpdb->terms} AS t ON (ss_tt.term_id = t.term_id)";

    $clauses['fields'] .= ", MIN({$case}) AS ss_type_priority";

    $groupby = isset($clauses['groupby']) ? trim((string) $clauses['groupby']) : '';
    if ($groupby === '') {
        $clauses['groupby'] = "{$wpdb->posts}.ID";
    } elseif (stripos($groupby, "{$wpdb->posts}.ID") === false && stripos($groupby, "{$wpdb->posts}.id") === false) {
        $clauses['groupby'] .= ", {$wpdb->posts}.ID";
    }

    $clauses['orderby'] = "ss_type_priority ASC, {$wpdb->posts}.post_title ASC";

    return $clauses;
}, 20, 2);

/**
 * Category Lookbook Image Fields
 * Enables adding lookbook images to product categories
 */
require_once get_template_directory() . '/category-lookbook-fields.php';

/**
 * Dynamic Homepage Sections (meta box, migration from legacy ACF section_1…7)
 */
require_once get_template_directory() . '/inc/home-sections.php';

/**
 * Collection Pages CPT and fields (term, lookbook before/after)
 */
require_once get_template_directory() . '/inc/collection-page-cpt.php';
require_once get_template_directory() . '/inc/collection-page-fields.php';

/**
 * Single product: title block wrapper — wishlist renders on the line below the title (see .ss-pdp-title-row CSS).
 */
add_action('woocommerce_single_product_summary', function () {
    if (!function_exists('is_product') || !is_product()) {
        return;
    }
    echo '<div class="ss-pdp-title-row">';
}, 4);
add_action('woocommerce_single_product_summary', function () {
    echo '</div>';
}, 7);

/**
 * Whether a wishlist plugin is active (YITH, TI, etc.). When true, the theme outputs the plugin button in the title row.
 * Other plugins can declare themselves via the filter.
 */
function ss_wishlist_plugin_active() {
    if (class_exists('YITH_WCWL', false) || class_exists('TInvWL', false)) {
        return true;
    }
    return (bool) apply_filters('ss_wishlist_plugin_active', false);
}

/**
 * Remove wishlist plugin default single-product hooks so we can place the button in the title row only.
 */
add_action('init', function () {
    if (!class_exists('TInvWL', false)) {
        return;
    }
    $positions = array(
        array('tinvwl_before_add_to_cart_button', 'tinvwl_view_addto_html', 10),
        array('tinvwl_single_product_summary', 'tinvwl_view_addto_htmlout', 10),
        array('woocommerce_before_add_to_cart_button', 'tinvwl_view_addto_html', 9),
        array('woocommerce_single_product_summary', 'tinvwl_view_addto_htmlout', 29),
        array('catalog_visibility_before_alternate_add_to_cart_button', 'tinvwl_view_addto_html', 10),
        array('tinvwl_after_add_to_cart_button', 'tinvwl_view_addto_html', 10),
        array('woocommerce_after_add_to_cart_button', 'tinvwl_view_addto_html', 20),
        array('woocommerce_single_product_summary', 'tinvwl_view_addto_htmlout', 31),
        array('catalog_visibility_after_alternate_add_to_cart_button', 'tinvwl_view_addto_html', 10),
        array('tinvwl_after_thumbnails', 'tinvwl_view_addto_html', 10),
        array('woocommerce_product_thumbnails', 'tinvwl_view_addto_html', 21),
        array('tinvwl_after_summary', 'tinvwl_view_addto_html', 10),
        array('woocommerce_after_single_product_summary', 'tinvwl_view_addto_html', 11),
    );
    foreach ($positions as $p) {
        remove_action($p[0], $p[1], isset($p[2]) ? $p[2] : 10);
    }
}, 999);

/**
 * Single product: wishlist below product title (inside .ss-pdp-title-row).
 * - If a wishlist plugin is active: output the plugin button here (after removing its default placement).
 * - If no plugin: output theme fallback link (login redirect for guests).
 */
add_action('woocommerce_single_product_summary', function () {
    if (!function_exists('is_product') || !is_product()) {
        return;
    }
    if (ss_wishlist_plugin_active()) {
        if (class_exists('TInvWL', false)) {
            echo do_shortcode('[ti_wishlists_addtowishlist]');
        } elseif (class_exists('YITH_WCWL', false)) {
            echo do_shortcode('[yith_wcwl_add_to_wishlist]');
        } else {
            $html = apply_filters('ss_wishlist_button_html', '');
            if ($html !== '') {
                echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
        }
        return;
    }
    $pid = get_the_ID();
    if (!$pid) {
        return;
    }
    $wishlist_url = add_query_arg(['product_id' => $pid], home_url('/wishlist/'));

    if (is_user_logged_in()) {
        $href = $wishlist_url;
    } else {
        $myaccount_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : home_url('/my-account/');
        $href = add_query_arg('redirect', urlencode($wishlist_url), $myaccount_url);
    }
    ?>
    <a class="ss-pdp-wishlist-inline" href="<?php echo esc_url($href); ?>" aria-label="<?php esc_attr_e('Add to wishlist', 'woocommerce'); ?>">
        <span class="ss-pdp-wishlist-inline__icon" aria-hidden="true"></span>
        <?php esc_html_e('Add to wishlist', 'woocommerce'); ?>
    </a>
    <?php
}, 6);

/**
 * Category archive layout helpers (editorial image + product flow)
 */
require_once get_template_directory() . '/inc/category-archive-helpers.php';

/**
 * Private View request modal + storage workflow.
 */
require_once get_template_directory() . '/inc/private-view-request.php';

/**
 * Variable product: output "Clear" beside the variation price row.
 *
 * @return void
 */
function ss_output_variations_reset_link() {
    if (!function_exists('is_product') || !is_product()) {
        return;
    }
    echo '<div class="ss-pdp-variation-actions">';
    echo wp_kses_post(
        apply_filters(
            'woocommerce_reset_variations_link',
            '<a class="reset_variations" href="#" aria-label="' . esc_attr__('Clear options', 'woocommerce') . '">' . esc_html__('Clear', 'woocommerce') . '</a>'
        )
    );
    echo '</div>';
}
add_action('woocommerce_single_variation', 'ss_output_variations_reset_link', 15);

/**
 * WooCommerce: Remove product tabs (Description / Reviews / Additional info)
 * and the shop sidebar on single product pages.
 */
add_action('wp', function () {
    if (!function_exists('is_product') || !is_product()) {
        return;
    }

    // Remove the whole tabs section.
    remove_action('woocommerce_after_single_product_summary', 'woocommerce_output_product_data_tabs', 10);

    // Disable reviews (prevents review form markup).
    add_filter('woocommerce_reviews_enabled', '__return_false', 100);

    // Prevent WooCommerce from outputting the shop sidebar (often shows widgets like Pages/Archives/Categories).
    remove_action('woocommerce_sidebar', 'woocommerce_get_sidebar', 10);
}, 20);

/**
 * WooCommerce: Disable the default add-to-cart success notice.
 * We show a small toast via JS instead.
 */
add_filter('wc_add_to_cart_message_html', function ($message, $products) {
    return '';
}, 10, 2);

/**
 * Checkout: hide Cash on delivery description only (title/method remain).
 */
add_filter('woocommerce_gateway_description', function ($description, $gateway_id) {
    if ($gateway_id === 'cod') {
        return '';
    }
    return $description;
}, 10, 2);

/**
 * Product admin: default text color in Classic TinyMCE (short description / product editor fields).
 */
add_filter('tiny_mce_before_init', function ($init) {
    if (!is_admin() || !function_exists('get_current_screen')) {
        return $init;
    }
    $screen = get_current_screen();
    if (!$screen || $screen->base !== 'post' || $screen->post_type !== 'product') {
        return $init;
    }
    $existing = isset($init['content_style']) ? (string) $init['content_style'] : '';
    $init['content_style'] = trim($existing . ' body,body#tinymce,body#tinymce.wp-editor{color:#65343C!important;}');
    return $init;
}, 20);

/**
 * WooCommerce: Update header cart count via AJAX fragments.
 */
add_filter('woocommerce_add_to_cart_fragments', function ($fragments) {
    if (!function_exists('ss_render_cart_link')) {
        return $fragments;
    }
    if (function_exists('wc_load_cart') && function_exists('WC') && (!isset(WC()->cart) || !WC()->cart)) {
        wc_load_cart();
    }
    $fragments['a.ss-cart-link'] = ss_render_cart_link();
    return $fragments;
});

/**
 * WooCommerce: Variation dropdown placeholder — "Select {Attribute Name}".
 * Uses the attribute's customer-facing label (global and custom attributes).
 */
add_filter('woocommerce_dropdown_variation_attribute_options_args', function ($args) {
    if (!function_exists('wc_attribute_label') || empty($args['attribute'])) {
        return $args;
    }
    $product = isset($args['product']) ? $args['product'] : null;
    $label   = wc_attribute_label($args['attribute'], $product);
    $args['show_option_none'] = $label !== ''
        ? sprintf(__('Select %s', 'stone-sparkle'), $label)
        : __('Choose an option', 'woocommerce');
    return $args;
}, 10, 1);

/* =========================================================
 * Footer Settings (ACF Free)
 * - Fields are registered in-code (no JSON required)
 * - Settings are stored on a dedicated WP Page (ID = 402 by default),
 *   matching your current field group "location" rule.
 *
 * IMPORTANT:
 * - The footer renderer will read from this page ID first.
 * - If you later move the group to an ACF Options Page, the renderer
 *   also supports reading from 'option' automatically.
 * ========================================================= */

/**
 * Resolve the Footer Settings page ID (where ACF footer fields are edited).
 * Result is memoized for the request to avoid repeated DB lookups.
 * Resolution order: slug "footer-settings" (stable), then page ID 402 if published, then title match (published only).
 * You can override via the filter 'ss_footer_settings_page_id'.
 */
function ss_footer_settings_page_id() {
    static $resolved_id = null;
    if ($resolved_id !== null) {
        return $resolved_id;
    }

    $default_id = 402;

    // Prefer stable identifiers first (slug is unique; ID is explicit).
    $page = get_page_by_path('footer-settings', OBJECT, 'page');
    if ($page && $page->ID && get_post_status($page) === 'publish') {
        $resolved_id = (int) apply_filters('ss_footer_settings_page_id', (int) $page->ID);
        return $resolved_id;
    }

    $page_402 = get_post($default_id);
    if ($page_402 && $page_402->post_type === 'page' && get_post_status($page_402) === 'publish') {
        $resolved_id = (int) apply_filters('ss_footer_settings_page_id', $default_id);
        return $resolved_id;
    }

    // Fallback: title match (published only; avoid drafts/trash).
    foreach (array('Footer settings', 'Footer Settings') as $title) {
        $page = get_page_by_title($title, OBJECT, 'page');
        if ($page && $page->ID && get_post_status($page) === 'publish') {
            $resolved_id = (int) apply_filters('ss_footer_settings_page_id', (int) $page->ID);
            return $resolved_id;
        }
    }

    $resolved_id = (int) apply_filters('ss_footer_settings_page_id', $default_id);
    return $resolved_id;
}

/**
 * Resolve ACF context for footer settings:
 * 1) If the ACF group is attached to a page (current setup), use that page ID.
 * 2) If an ACF options page is used in the future, 'option' will work too.
 */
function ss_footer_settings_context() {
    // Preferred: ACF Options (site-wide)
    return 'option';
}

/**
 * Safe getter for footer ACF fields.
 * Reads from ACF Options first (site-wide). Falls back to the legacy "Footer Settings" page (ID via filter)
 * to preserve older data if it already exists.
 */
function ss_footer_get_field($key, $default = '') {
    if (!function_exists('get_field')) {
        return $default;
    }

    // 1) Site-wide options
    $val = get_field($key, 'option');

    // 2) Legacy page fallback
    if ($val === null || $val === '' || $val === false) {
        $page_id = ss_footer_settings_page_id();
        if ($page_id > 0 && get_post($page_id)) {
            $val = get_field($key, $page_id);
        }
    }

    if ($val === null || $val === '' || $val === false) {
        return $default;
    }

    return $val;
}

/**
 * Register Footer Settings field group in-code (ACF Free compatible).
 * This is the exact structure you posted, including the location rule to page ID 402.
 */
/**
 * Register Footer Settings options page (site-wide), when ACF supports it.
 * Keeps compatibility with ACF Free by also allowing the legacy page-based editor (page ID 402).
 *
 * Admin menu: Appearance → Footer Settings
 */
add_action('acf/init', function() {
    if (function_exists('acf_add_options_page')) {
        acf_add_options_page(array(
            'page_title'  => 'Footer Settings',
            'menu_title'  => 'Footer Settings',
            'menu_slug'   => 'ss-footer-settings',
            'parent_slug' => 'themes.php',
            'capability'  => 'edit_theme_options',
            'redirect'    => false,
            'position'    => 61,
        ));
    }
});

/**
 * Register Footer Settings field group in-code (ACF Free compatible).
 *
 * Data source preference:
 * - Site-wide options (option)
 * - Fallback: legacy page (ID 402)
 */
add_action('acf/include_fields', function() {
    if (!function_exists('acf_add_local_field_group')) return;

    $fields = array();

    $fields[] = array(
        'key' => 'field_ss_footer_enabled',
        'label' => 'footer_enabled',
        'name' => 'footer_enabled',
        'type' => 'true_false',
        'ui' => 1,
        'default_value' => 1,
    );

    // Stone & Sparkle (brand column)
    $fields[] = array(
        'key' => 'field_ss_footer_brand_accordion',
        'label' => 'Stone & Sparkle Section',
        'name' => '',
        'type' => 'accordion',
        'open' => 0,
        'multi_expand' => 0,
        'endpoint' => 0,
    );
    $fields[] = array(
        'key' => 'field_ss_footer_brand_enabled',
        'label' => 'footer_brand_enabled',
        'name' => 'footer_brand_enabled',
        'type' => 'true_false',
        'ui' => 1,
        'default_value' => 1,
    );
    $fields[] = array(
        'key' => 'field_ss_footer_brand_title',
        'label' => 'footer_brand_title',
        'name' => 'footer_brand_title',
        'type' => 'text',
        'default_value' => 'STONE AND SPARKLE',
    );
    $fields[] = array(
        'key' => 'field_ss_footer_brand_description',
        'label' => 'footer_brand_description',
        'name' => 'footer_brand_description',
        'type' => 'text',
        'default_value' => 'Luxury jewelry, curated drops, and timeless pieces.',
    );
    // Brand manual links (ACF Free compatible): 8 link slots (label + url)
$fields[] = array(
    'key' => 'field_ss_footer_brand_links_message',
    'label' => 'Brand Links',
    'name' => '',
    'type' => 'message',
    'message' => 'Fill label + URL. Leave empty to hide that row.',
);
for ($i = 1; $i <= 8; $i++) {
    $fields[] = array(
        'key' => 'field_ss_footer_brand_link_' . $i . '_label',
        'label' => 'brand_link_' . $i . '_label',
        'name' => 'brand_link_' . $i . '_label',
        'type' => 'text',
        'default_value' => '',
    );
    $fields[] = array(
        'key' => 'field_ss_footer_brand_link_' . $i . '_url',
        'label' => 'brand_link_' . $i . '_url',
        'name' => 'brand_link_' . $i . '_url',
        'type' => 'url',
        'default_value' => '',
    );
}
    $fields[] = array(
        'key' => 'field_ss_footer_brand_accordion_end',
        'label' => 'Stone & Sparkle Section End',
        'name' => '',
        'type' => 'accordion',
        'endpoint' => 1,
    );

    // Helper: add a section (enabled + title + manual links)
    $add_section = function($slug, $label, $default_title) use (&$fields) {
        $fields[] = array(
            'key' => 'field_ss_footer_' . $slug . '_accordion',
            'label' => $label . ' Section',
            'name' => '',
            'type' => 'accordion',
            'open' => 0,
            'multi_expand' => 0,
            'endpoint' => 0,
        );

        $fields[] = array(
            'key' => 'field_ss_footer_' . $slug . '_enabled',
            'label' => 'footer_' . $slug . '_enabled',
            'name' => 'footer_' . $slug . '_enabled',
            'type' => 'true_false',
            'ui' => 1,
            'default_value' => 1,
        );

        $fields[] = array(
            'key' => 'field_ss_footer_' . $slug . '_title',
            'label' => 'footer_' . $slug . '_title',
            'name' => 'footer_' . $slug . '_title',
            'type' => 'text',
            'default_value' => $default_title,
        );

        // Manual links (ACF Free compatible): 8 link slots (label + url)
$fields[] = array(
    'key' => 'field_ss_footer_' . $slug . '_links_message',
    'label' => $label . ' Links',
    'name' => '',
    'type' => 'message',
    'message' => 'Fill label + URL. Leave empty to hide that row.',
);

for ($i = 1; $i <= 8; $i++) {
    $fields[] = array(
        'key' => 'field_ss_footer_' . $slug . '_link_' . $i . '_label',
        'label' => $slug . '_link_' . $i . '_label',
        'name' => $slug . '_link_' . $i . '_label',
        'type' => 'text',
        'default_value' => '',
    );
    $fields[] = array(
        'key' => 'field_ss_footer_' . $slug . '_link_' . $i . '_url',
        'label' => $slug . '_link_' . $i . '_url',
        'name' => $slug . '_link_' . $i . '_url',
        'type' => 'url',
        'default_value' => '',
    );
}

$fields[] = array(

            'key' => 'field_ss_footer_' . $slug . '_accordion_end',
            'label' => $label . ' Section End',
            'name' => '',
            'type' => 'accordion',
            'endpoint' => 1,
        );
    };

    $add_section('product', 'Products', 'Products');
    $add_section('about', 'About', 'About');
    $add_section('support', 'Support', 'Support');
    $add_section('contact', 'Contact', 'Contact');

    // Social
    $fields[] = array(
        'key' => 'field_ss_footer_social_enabled',
        'label' => 'footer_social_enabled',
        'name' => 'footer_social_enabled',
        'type' => 'true_false',
        'ui' => 1,
        'default_value' => 1,
    );
    $fields[] = array(
        'key' => 'field_ss_footer_social_title',
        'label' => 'footer_social_title',
        'name' => 'footer_social_title',
        'type' => 'text',
        'default_value' => 'Follow Us',
    );
    $fields[] = array(
        'key' => 'field_ss_footer_social_description',
        'label' => 'footer_social_description',
        'name' => 'footer_social_description',
        'type' => 'text',
        'default_value' => '',
    );

    for ($i = 1; $i <= 8; $i++) {
        $fields[] = array(
            'key' => 'field_ss_social_' . $i . '_enabled',
            'label' => 'social_' . $i . '_enabled',
            'name' => 'social_' . $i . '_enabled',
            'type' => 'true_false',
            'ui' => 1,
            'default_value' => 0,
        );
        $fields[] = array(
            'key' => 'field_ss_social_' . $i . '_icon',
            'label' => 'social_' . $i . '_icon',
            'name' => 'social_' . $i . '_icon',
            'type' => 'image',
            'return_format' => 'array',
            'preview_size' => 'thumbnail',
            'library' => 'all',
        );
        $fields[] = array(
            'key' => 'field_ss_social_' . $i . '_link',
            'label' => 'social_' . $i . '_link',
            'name' => 'social_' . $i . '_link',
            'type' => 'url',
            'default_value' => '',
        );
        $fields[] = array(
            'key' => 'field_ss_social_' . $i . '_tooltip',
            'label' => 'social_' . $i . '_tooltip',
            'name' => 'social_' . $i . '_tooltip',
            'type' => 'text',
            'default_value' => '',
        );
    }

    // Newsletter: visibility, title, subtitle, etc. are in the "Footer Newsletter" group below (ACF Free compatible).

    // Copyright
    $fields[] = array(
        'key' => 'field_ss_footer_copyright_enabled',
        'label' => 'footer_copyright_enabled',
        'name' => 'footer_copyright_enabled',
        'type' => 'true_false',
        'ui' => 1,
        'default_value' => 1,
    );
    $fields[] = array(
        'key' => 'field_ss_footer_copyright_text',
        'label' => 'footer_copyright_text',
        'name' => 'footer_copyright_text',
        'type' => 'text',
        'instructions' => 'You can use {year} and {site}. Example: © {year} {site}. All rights reserved.',
        'default_value' => '© {year} {site}. All rights reserved.',
    );

    $footer_page_id = ss_footer_settings_page_id();
    acf_add_local_field_group(array(
        'key' => 'group_ss_footer_settings',
        'title' => 'Footer Settings',
        'fields' => $fields,
        'location' => array(
            array(
                array('param' => 'options_page', 'operator' => '==', 'value' => 'ss-footer-settings'),
            ),
            array(
                array('param' => 'page', 'operator' => '==', 'value' => (string) $footer_page_id),
            ),
        ),
        'menu_order' => 0,
        'position' => 'normal',
        'style' => 'default',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
        'active' => true,
        'show_in_rest' => 0,
    ));

    // Footer Newsletter: ACF Free compatible (no accordion). Shows on the same page as Footer settings.
    acf_add_local_field_group(array(
        'key' => 'group_ss_footer_newsletter',
        'title' => 'Footer Newsletter',
        'fields' => array(
            array(
                'key' => 'field_ss_footer_newsletter_message',
                'label' => 'Newsletter section',
                'name' => '',
                'type' => 'message',
                'message' => 'Visibility and text for the newsletter block in the footer.',
            ),
            array(
                'key' => 'field_ss_footer_newsletter_enabled',
                'label' => 'Show newsletter section',
                'name' => 'footer_newsletter_enabled',
                'type' => 'true_false',
                'ui' => 1,
                'default_value' => 1,
                'instructions' => 'Show or hide the newsletter block in the footer.',
            ),
            array(
                'key' => 'field_ss_footer_newsletter_title',
                'label' => 'Newsletter title',
                'name' => 'footer_newsletter_title',
                'type' => 'text',
                'default_value' => 'Newsletter',
            ),
            array(
                'key' => 'field_ss_footer_newsletter_subtitle',
                'label' => 'Newsletter subtitle',
                'name' => 'footer_newsletter_subtitle',
                'type' => 'textarea',
                'new_lines' => 'br',
                'default_value' => 'Subscribe to get special offers, free giveaways, and once-in-a-lifetime deals.',
                'instructions' => 'Text below the title (the paragraph in the newsletter block).',
            ),
            array(
                'key' => 'field_ss_footer_newsletter_placeholder',
                'label' => 'Email placeholder',
                'name' => 'footer_newsletter_placeholder',
                'type' => 'text',
                'default_value' => 'Enter your email',
            ),
            array(
                'key' => 'field_ss_footer_newsletter_button_text',
                'label' => 'Button text',
                'name' => 'footer_newsletter_button_text',
                'type' => 'text',
                'default_value' => 'Join',
            ),
        ),
        'location' => array(
            array(
                array('param' => 'options_page', 'operator' => '==', 'value' => 'ss-footer-settings'),
            ),
            array(
                array('param' => 'page', 'operator' => '==', 'value' => (string) $footer_page_id),
            ),
        ),
        'menu_order' => 1,
        'position' => 'normal',
        'style' => 'default',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
        'active' => true,
        'show_in_rest' => 0,
    ));
});

/**
 * Size chart PDF: per-product metabox (native WP, no plugin).
 * Stores the selected PDF attachment ID in post meta:
 * - ss_size_chart_pdf_attachment_id
 */
define('SS_SIZE_CHART_PDF_META_KEY', 'ss_size_chart_pdf_attachment_id');

add_action('add_meta_boxes', function () {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    // In some environments get_current_screen() can be null; still allow meta box for safety.
    if ($screen && isset($screen->post_type) && $screen->post_type !== 'product') {
        return;
    }

    add_meta_box(
        'ss_size_chart_pdf_metabox',
        __('Size chart (PDF)', 'stone-sparkle'),
        'ss_render_size_chart_pdf_metabox',
        'product',
        'side',
        'default'
    );
});

/**
 * Render the Size chart PDF metabox.
 *
 * @param WP_Post $post
 */
function ss_render_size_chart_pdf_metabox($post) {
    $attachment_id = (int) get_post_meta($post->ID, SS_SIZE_CHART_PDF_META_KEY, true);
    $attachment_url = $attachment_id > 0 ? wp_get_attachment_url($attachment_id) : '';

    wp_enqueue_media();

    wp_nonce_field('ss_size_chart_pdf_save', 'ss_size_chart_pdf_nonce');
    ?>
    <p class="description">
        <?php esc_html_e('PDF displayed as a “Size chart” link next to the Size selector.', 'stone-sparkle'); ?>
    </p>

    <input type="hidden" id="ss_size_chart_pdf_attachment_id" name="ss_size_chart_pdf_attachment_id" value="<?php echo (int) $attachment_id; ?>">

    <p>
        <button
            type="button"
            class="button ss-size-chart-pdf__pick"
            data-ss-size-chart-pdf-target="#ss_size_chart_pdf_attachment_id"
            data-ss-size-chart-pdf-title="<?php echo esc_attr__('Select a PDF file', 'stone-sparkle'); ?>"
        >
            <?php echo esc_html($attachment_id > 0 ? __('Replace PDF', 'stone-sparkle') : __('Select PDF', 'stone-sparkle')); ?>
        </button>

        <?php if (!empty($attachment_url)) : ?>
            <span style="margin-left:8px;">
                <a href="<?php echo esc_url($attachment_url); ?>" target="_blank" rel="noopener noreferrer">
                    <?php echo esc_html__('View current', 'stone-sparkle'); ?>
                </a>
            </span>
        <?php endif; ?>
    </p>

    <script>
      (function(){
        var root = document.currentScript ? document.currentScript.parentElement : null;
        if (!root) return;

        var btn = root.querySelector('.ss-size-chart-pdf__pick');
        if (!btn || typeof wp === 'undefined' || !wp.media) return;

        var targetSel = btn.getAttribute('data-ss-size-chart-pdf-target') || '#ss_size_chart_pdf_attachment_id';
        var input = document.querySelector(targetSel);
        if (!input) return;

        var frame = null;

        btn.addEventListener('click', function(e){
          e.preventDefault();

          if (frame) {
            frame.open();
            return;
          }

          var title = btn.getAttribute('data-ss-size-chart-pdf-title') || 'Select PDF';
          frame = wp.media({
            title: title,
            button: { text: 'Use this file' },
            multiple: false
          });

          frame.on('select', function(){
            var selection = frame.state().get('selection');
            var attachment = selection && selection.first ? selection.first() : null;
            var id = attachment && attachment.id ? attachment.id : 0;
            input.value = String(id);
            btn.textContent = 'Replace PDF';
          });

          frame.open();
        });
      })();
    </script>
    <?php
}

add_action('save_post_product', function ($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (wp_is_post_revision($post_id)) {
        return;
    }
    if (!isset($_POST['ss_size_chart_pdf_nonce'])) {
        return;
    }

    $nonce = sanitize_text_field(wp_unslash((string) $_POST['ss_size_chart_pdf_nonce']));
    if (!wp_verify_nonce($nonce, 'ss_size_chart_pdf_save')) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $raw_id = isset($_POST['ss_size_chart_pdf_attachment_id']) ? (string) $_POST['ss_size_chart_pdf_attachment_id'] : '';
    $attachment_id = (int) $raw_id;

    if ($attachment_id <= 0) {
        delete_post_meta($post_id, SS_SIZE_CHART_PDF_META_KEY);
        return;
    }

    $mime = get_post_mime_type($attachment_id);
    if ($mime !== 'application/pdf') {
        // Refuse non-PDF uploads to keep front-end modal predictable.
        delete_post_meta($post_id, SS_SIZE_CHART_PDF_META_KEY);
        return;
    }

    update_post_meta($post_id, SS_SIZE_CHART_PDF_META_KEY, $attachment_id);
});

/**
 * Check if the current product has a visible variation attribute named "size".
 *
 * Supports:
 * - Global attributes stored as `pa_size`
 * - Custom product attributes stored as `size`
 *
 * @param WC_Product|null $product
 * @return bool
 */
function ss_product_has_visible_size_attribute($product) {
    if (!$product || !is_a($product, 'WC_Product_Variable')) {
        return false;
    }

    if (!method_exists($product, 'get_attributes')) {
        return false;
    }

    $attributes = $product->get_attributes();
    if (!is_array($attributes) || empty($attributes)) {
        return false;
    }

    foreach ($attributes as $attr_key => $attr_obj) {
        if (!is_string($attr_key)) {
            continue;
        }

        $normalized_key = null;
        if ($attr_key === 'size') {
            $normalized_key = 'size';
        } elseif (strpos($attr_key, 'pa_') === 0 && substr($attr_key, 3) === 'size') {
            $normalized_key = 'size';
        }

        if ($normalized_key !== 'size') {
            continue;
        }
        // Do not rely on Woo's "visible" flag here: even if the attribute isn't rendered as a select,
        // the frontend JS only inserts the link when the corresponding select exists in the DOM.
        return true;
    }

    return false;
}

/**
 * Frontend: if the product has a visible `size` variation attribute and a configured PDF,
 * render:
 * - an `ss-popup` modal with an iframe
 * - a hidden link template containing the resolved PDF URL (used by JS to insert next to the Size label)
 */
add_action('woocommerce_single_product_summary', function () {
    if (!function_exists('is_product') || !is_product()) {
        return;
    }
    if (!function_exists('wc_get_product')) {
        return;
    }
    if (!function_exists('get_post_meta')) {
        return;
    }

    global $product;
    if (!$product || !is_a($product, 'WC_Product')) {
        $product = wc_get_product(get_the_ID());
    }

    if (!$product || !is_a($product, 'WC_Product')) {
        return;
    }

    $pid = (int) $product->get_id();
    if ($pid <= 0) {
        return;
    }

    $attachment_id = (int) get_post_meta($pid, SS_SIZE_CHART_PDF_META_KEY, true);
    if ($attachment_id <= 0) {
        return;
    }

    $pdf_url = wp_get_attachment_url($attachment_id);
    if (empty($pdf_url)) {
        return;
    }

    $pdf_url_attr = esc_url($pdf_url);
    $link_text = esc_html__('Size chart', 'stone-sparkle');

    ?>
    <div style="display:none;">
        <a
            class="ss-size-chart-link-template"
            href="<?php echo $pdf_url_attr; ?>"
            data-ss-size-chart-pdf-url="<?php echo $pdf_url_attr; ?>"
        >
            <?php echo $link_text; ?>
        </a>
    </div>

    <div class="ss-popup ss-size-chart-popup" id="ssSizeChartPopup" aria-hidden="true">
        <div class="ss-popup__backdrop" data-ss-popup-close tabindex="-1"></div>
        <div class="ss-popup__dialog" role="dialog" aria-modal="true" aria-labelledby="ssSizeChartPopupTitle">
            <button
                class="ss-popup__close"
                type="button"
                aria-label="<?php echo esc_attr__('Close', 'stone-sparkle'); ?>"
                data-ss-popup-close
            >
                <span aria-hidden="true">&times;</span>
            </button>

            <div class="ss-popup__content">
                <h2 class="ss-popup__title" id="ssSizeChartPopupTitle"><?php echo esc_html__('Size chart', 'stone-sparkle'); ?></h2>
                <div class="ss-popup__body">
                    <?php echo esc_html__('Open the PDF size chart below.', 'stone-sparkle'); ?>
                </div>

                <div class="ss-size-chart-popup__frame" aria-label="<?php echo esc_attr__('Size chart PDF', 'stone-sparkle'); ?>">
                    <iframe
                        class="ss-size-chart-popup__iframe"
                        title="<?php echo esc_attr__('Size chart PDF', 'stone-sparkle'); ?>"
                        src=""
                        loading="lazy"
                    ></iframe>
                </div>
            </div>
        </div>
    </div>
    <?php
}, 55);
