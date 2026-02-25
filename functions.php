<?php
/**
 * Stone Sparkle theme functions
 */

if (!defined('ABSPATH')) { exit; }

define('SS_THEME_VERSION', '0.2.1');

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

// Remove related products from single product pages
remove_action('woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20);


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
    wp_enqueue_style(
        'stone-sparkle-main',
        get_template_directory_uri() . '/assets/css/main.css',
        [],
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
 * - Fixed 576x576 stage
 * - Left thumbnails
 * - Prev/Next controls
 * - Wheel zoom + drag pan (JS)
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
            $full  = wp_get_attachment_image_url($id, 'large');
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
 * Category Lookbook Image Fields
 * Enables adding lookbook images to product categories
 */
require_once get_template_directory() . '/category-lookbook-fields.php';

/**
 * Single product: add "Request Private View" + "Add To Wishlist" buttons
 * directly under the main Add to cart button.
 */
add_action('woocommerce_after_add_to_cart_button', function () {
    if (!function_exists('is_product') || !is_product()) {
        return;
    }

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

    $pid = $product->get_id();

    // These pages can be created in WP. The querystring makes it easy to pre-fill forms later.
    $private_url = add_query_arg(['product_id' => $pid], home_url('/private-viewing/'));
    $wishlist_url = add_query_arg(['product_id' => $pid], home_url('/wishlist/'));

    echo '<div class="ss-pdp-secondary" role="group" aria-label="Product actions">';
    echo '<a class="ss-pdp-btn" href="' . esc_url($private_url) . '">Request Private View</a>';
    echo '<a class="ss-pdp-btn ss-pdp-btn--outline" href="' . esc_url($wishlist_url) . '">Add To Wishlist</a>';
    echo '</div>';
}, 25);

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
 * Change this if you move the Footer Settings page.
 * You can also override via the filter 'ss_footer_settings_page_id'.
 */
function ss_footer_settings_page_id() {
    $default_id = 402; // matches your current ACF location rule
    return (int) apply_filters('ss_footer_settings_page_id', $default_id);
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

    // Newsletter
    $fields[] = array(
        'key' => 'field_ss_footer_newsletter_accordion',
        'label' => 'Newsletter Section',
        'name' => '',
        'type' => 'accordion',
        'open' => 0,
        'multi_expand' => 0,
        'endpoint' => 0,
    );
    $fields[] = array(
        'key' => 'field_ss_footer_newsletter_enabled',
        'label' => 'footer_newsletter_enabled',
        'name' => 'footer_newsletter_enabled',
        'type' => 'true_false',
        'ui' => 1,
        'default_value' => 1,
    );
    $fields[] = array(
        'key' => 'field_ss_footer_newsletter_title',
        'label' => 'footer_newsletter_title',
        'name' => 'footer_newsletter_title',
        'type' => 'text',
        'default_value' => 'Newsletter',
    );
    $fields[] = array(
        'key' => 'field_ss_footer_newsletter_subtitle',
        'label' => 'footer_newsletter_subtitle',
        'name' => 'footer_newsletter_subtitle',
        'type' => 'textarea',
        'new_lines' => 'br',
        'default_value' => 'Subscribe to get special offers, free giveaways, and once-in-a-lifetime deals.',
    );
    $fields[] = array(
        'key' => 'field_ss_footer_newsletter_placeholder',
        'label' => 'footer_newsletter_placeholder',
        'name' => 'footer_newsletter_placeholder',
        'type' => 'text',
        'default_value' => 'Enter your email',
    );
    $fields[] = array(
        'key' => 'field_ss_footer_newsletter_button_text',
        'label' => 'footer_newsletter_button_text',
        'name' => 'footer_newsletter_button_text',
        'type' => 'text',
        'default_value' => 'Join',
    );
    $fields[] = array(
        'key' => 'field_ss_footer_newsletter_accordion_end',
        'label' => 'Newsletter Section End',
        'name' => '',
        'type' => 'accordion',
        'endpoint' => 1,
    );

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

    acf_add_local_field_group(array(
        'key' => 'group_ss_footer_settings',
        'title' => 'Footer Settings',
        'fields' => $fields,
        'location' => array(
            array(
                array('param' => 'options_page', 'operator' => '==', 'value' => 'ss-footer-settings'),
            ),
            array(
                array('param' => 'page', 'operator' => '==', 'value' => '402'),
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
});
