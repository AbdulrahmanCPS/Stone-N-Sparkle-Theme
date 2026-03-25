<?php
/**
 * You May Also Like (single-product recommendations)
 *
 * - ACF fields registered for `product` post type.
 * - Renders on Woo single product pages using WooCommerce hooks.
 * - Does NOT use WooCommerce upsells/cross-sells/related-product output.
 */

defined('ABSPATH') || exit;

if (!function_exists('ss_you_may_also_like_collection_taxonomy_slug')) {
    /**
     * Resolve the collection taxonomy slug used by this theme.
     *
     * The theme uses `pa_collection` (see collection-page templates/fields).
     * We still handle missing taxonomy gracefully.
     *
     * @return string Empty string when taxonomy is not available.
     */
    function ss_you_may_also_like_collection_taxonomy_slug() {
        if (taxonomy_exists('pa_collection')) {
            return 'pa_collection';
        }
        if (taxonomy_exists('collection')) {
            return 'collection';
        }
        return '';
    }
}

if (!function_exists('ss_you_may_also_like_get_manual_product_ids')) {
    /**
     * Normalize manual selections from ACF into an ordered, de-duplicated product ID list.
     *
     * @param int $product_id Current product ID (for exclusion).
     * @param int $limit Max products to return.
     * @return array<int>
     */
    function ss_you_may_also_like_get_manual_product_ids($product_id, $limit) {
        if (!function_exists('get_field') || !function_exists('wc_get_product')) {
            return [];
        }

        $raw = get_field('you_may_also_like_manual_products', $product_id);
        if (empty($raw)) {
            return [];
        }

        if (!is_array($raw)) {
            $raw = [$raw];
        }

        $out = [];
        $seen = [];

        foreach ($raw as $item) {
            if (count($out) >= $limit) {
                break;
            }

            $id = 0;
            if (is_numeric($item)) {
                $id = (int) $item;
            } elseif (is_object($item) && isset($item->ID)) {
                $id = (int) $item->ID;
            }

            if ($id <= 0 || $id === (int) $product_id) {
                continue;
            }
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;

            $p = wc_get_product($id);
            if (!$p || !is_a($p, 'WC_Product') || !$p->is_visible()) {
                continue;
            }

            $out[] = $id;
        }

        return $out;
    }
}

if (!function_exists('ss_you_may_also_like_get_auto_product_ids')) {
    /**
     * Fetch latest recommended products for auto mode.
     *
     * @param int $product_id Current product ID (for exclusion).
     * @param string $auto_logic Either "category" or "collection".
     * @param int $limit Max products to return.
     * @return array<int>
     */
    function ss_you_may_also_like_get_auto_product_ids($product_id, $auto_logic, $limit) {
        if (!function_exists('wc_get_product')) {
            return [];
        }

        $product_id = (int) $product_id;
        $limit = max(1, (int) $limit);
        $auto_logic = in_array($auto_logic, ['category', 'collection'], true) ? $auto_logic : 'category';

        $tax_query = [];

        if ($auto_logic === 'collection') {
            $collection_tax = ss_you_may_also_like_collection_taxonomy_slug();
            if ($collection_tax === '') {
                return [];
            }

            $term_ids = wp_get_post_terms($product_id, $collection_tax, ['fields' => 'ids']);
            if (empty($term_ids) || is_wp_error($term_ids)) {
                return [];
            }

            $tax_query = [
                [
                    'taxonomy' => $collection_tax,
                    'field'    => 'term_id',
                    'terms'    => array_values(array_map('intval', (array) $term_ids)),
                ],
            ];
        } else {
            // Category (product_cat).
            $term_ids = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
            if (empty($term_ids) || is_wp_error($term_ids)) {
                return [];
            }

            $tax_query = [
                [
                    'taxonomy' => 'product_cat',
                    'field'    => 'term_id',
                    'terms'    => array_values(array_map('intval', (array) $term_ids)),
                ],
            ];
        }

        $args = [
            'post_type'            => 'product',
            'post_status'          => 'publish',
            'posts_per_page'       => $limit,
            'ignore_sticky_posts'  => true,
            'orderby'              => 'date',
            'order'                => 'DESC',
            'post__not_in'        => [$product_id],
            'tax_query'            => $tax_query,
            'no_found_rows'        => true,
        ];

        $query = new WP_Query($args);
        if (!$query->have_posts()) {
            return [];
        }

        $out = [];
        while ($query->have_posts()) {
            $query->the_post();
            $id = (int) get_the_ID();
            if ($id <= 0 || $id === $product_id) {
                continue;
            }

            $p = wc_get_product($id);
            if ($p && is_a($p, 'WC_Product') && $p->is_visible()) {
                $out[] = $id;
            }

            if (count($out) >= $limit) {
                break;
            }
        }
        wp_reset_postdata();

        return $out;
    }
}

if (!function_exists('ss_you_may_also_like_get_product_ids')) {
    /**
     * Decide product recommendations based on ACF mode.
     *
     * @param int $product_id Current product ID.
     * @param int $limit Max products to return.
     * @return array<int>
     */
    function ss_you_may_also_like_get_product_ids($product_id, $limit) {
        if (!function_exists('get_field')) {
            return [];
        }

        $mode_raw = get_field('you_may_also_like_mode', $product_id);
        $mode = ($mode_raw === 'auto') ? 'auto' : 'manual';

        if ($mode === 'manual') {
            return ss_you_may_also_like_get_manual_product_ids($product_id, $limit);
        }

        $auto_logic = get_field('you_may_also_like_auto_logic', $product_id);
        if (!is_string($auto_logic) || $auto_logic === '') {
            $auto_logic = 'category';
        }

        return ss_you_may_also_like_get_auto_product_ids($product_id, $auto_logic, $limit);
    }
}

if (!function_exists('ss_render_you_may_also_like_section')) {
    /**
     * Render the "You May Also Like" section on single product pages.
     */
    function ss_render_you_may_also_like_section() {
        if (!function_exists('is_product') || !is_product()) {
            return;
        }

        if (!function_exists('get_field') || !function_exists('wc_get_product')) {
            return;
        }

        $product_id = (int) get_queried_object_id();
        if ($product_id <= 0) {
            $product_id = (int) get_the_ID();
        }
        if ($product_id <= 0) {
            return;
        }

        $enabled = get_field('show_you_may_also_like', $product_id);
        if (!$enabled) {
            return;
        }

        $limit = 4;
        $product_ids = ss_you_may_also_like_get_product_ids($product_id, $limit);

        if (empty($product_ids)) {
            return;
        }

        echo '<section class="ss-you-may-also-like" aria-label="' . esc_attr__('You May Also Like', 'stone-sparkle') . '">';
        echo '  <div class="ss-container">';
        $heading = function_exists('get_field') ? get_field('you_may_also_like_heading', $product_id) : '';
        $heading = is_string($heading) ? trim($heading) : '';
        if ($heading === '') {
            $heading = (string) __('You May Also Like', 'stone-sparkle');
        }
        echo '    <h2 class="ss-you-may-also-like__title">' . esc_html($heading) . '</h2>';

        // Wrap in `.woocommerce` so the theme's existing `ul.products` grid styles apply here too.
        echo '    <div class="woocommerce ss-you-may-also-like__woocommerce-loop">';
        echo '      <div class="ss-you-may-also-like__strip">';
        do_action('woocommerce_before_shop_loop');
        woocommerce_product_loop_start();

        global $product;
        $original_product = isset($GLOBALS['product']) ? $GLOBALS['product'] : null;
        $original_post    = isset($GLOBALS['post']) ? $GLOBALS['post'] : null;

        foreach ($product_ids as $pid) {
            $pid = (int) $pid;
            if ($pid <= 0 || $pid === $product_id) {
                continue;
            }

            global $post;
            $post = get_post($pid);
            if (!$post || !($post instanceof WP_Post)) {
                continue;
            }

            setup_postdata($post); // ensures the_title(), the_permalink(), etc. use the right post
            $GLOBALS['product'] = wc_get_product($pid);
            if ($GLOBALS['product'] && is_a($GLOBALS['product'], 'WC_Product') && $GLOBALS['product']->is_visible()) {
                wc_get_template_part('content', 'product');
            }
        }

        $GLOBALS['product'] = $original_product;
        if ($original_post instanceof WP_Post) {
            $GLOBALS['post'] = $original_post;
        }
        wp_reset_postdata();

        woocommerce_product_loop_end();
        do_action('woocommerce_after_shop_loop');

        echo '      </div>';
        echo '    </div>';
        echo '  </div>';
        echo '</section>';
    }
}

// ACF fields (per-product editor UI).
add_action('acf/include_fields', function () {
    if (!function_exists('acf_add_local_field_group')) {
        return;
    }

    acf_add_local_field_group([
        'key' => 'group_ss_you_may_also_like',
        'title' => 'You May Also Like',
        'fields' => [
            [
                'key' => 'field_ss_you_may_also_like_show',
                'label' => 'Enable You May Also Like',
                'name' => 'show_you_may_also_like',
                'type' => 'true_false',
                'ui' => 1,
                'ui_on_text' => 'Enabled',
                'ui_off_text' => 'Disabled',
                'instructions' => 'Turn this on to show a "You May Also Like" section on this product page.',
                'default_value' => 0,
            ],
            [
                'key' => 'field_ss_you_may_also_like_heading',
                'label' => 'Section Heading',
                'name' => 'you_may_also_like_heading',
                'type' => 'text',
                'default_value' => 'You May Also Like',
                'instructions' => 'Optional. Change the title shown above the recommended products. Leave blank to use the default heading.',
                'conditional_logic' => [
                    'status' => 1,
                    'allorany' => 'all',
                    'rules' => [
                        [
                            'field' => 'field_ss_you_may_also_like_show',
                            'operator' => '==',
                            'value' => '1',
                        ],
                    ],
                ],
            ],
            [
                'key' => 'field_ss_you_may_also_like_admin_hint',
                'label' => 'Configuration help',
                'name' => '',
                'type' => 'message',
                'message' => 'If you enable this section, choose a recommendation source below. When set to manual, you must pick products; when set to auto, products will be generated based on the selected rule.',
                'conditional_logic' => [
                    'status' => 1,
                    'allorany' => 'all',
                    'rules' => [
                        [
                            'field' => 'field_ss_you_may_also_like_show',
                            'operator' => '==',
                            'value' => '1',
                        ],
                    ],
                ],
            ],
            [
                'key' => 'field_ss_you_may_also_like_mode',
                'label' => 'Recommendation Source',
                'name' => 'you_may_also_like_mode',
                'type' => 'radio',
                'choices' => [
                    'manual' => 'Manual Selection',
                    'auto' => 'Automatic',
                ],
                'instructions' => 'Choose whether to manually pick products or generate recommendations automatically.',
                'default_value' => 'manual',
                'layout' => 'horizontal',
                'conditional_logic' => [
                    'status' => 1,
                    'allorany' => 'all',
                    'rules' => [
                        [
                            'field' => 'field_ss_you_may_also_like_show',
                            'operator' => '==',
                            'value' => '1',
                        ],
                    ],
                ],
            ],
            [
                'key' => 'field_ss_you_may_also_like_manual_products',
                'label' => 'Manual Products',
                'name' => 'you_may_also_like_manual_products',
                'type' => 'post_object',
                'post_type' => ['product'],
                'multiple' => 1,
                'max' => 4,
                'return_format' => 'id',
                'ui' => 1,
                'instructions' => 'Select up to 4 products to display. The current product will never be repeated.',
                'conditional_logic' => [
                    'status' => 1,
                    'allorany' => 'all',
                    'rules' => [
                        [
                            'field' => 'field_ss_you_may_also_like_show',
                            'operator' => '==',
                            'value' => '1',
                        ],
                        [
                            'field' => 'field_ss_you_may_also_like_mode',
                            'operator' => '==',
                            'value' => 'manual',
                        ],
                    ],
                ],
            ],
            [
                'key' => 'field_ss_you_may_also_like_auto_logic',
                'label' => 'Automatic Recommendation Rule',
                'name' => 'you_may_also_like_auto_logic',
                'type' => 'select',
                'choices' => [
                    'category' => 'Same Category',
                    'collection' => 'Same Collection',
                ],
                'instructions' => 'Controls how products are selected automatically.',
                'default_value' => 'category',
                'ui' => 1,
                'conditional_logic' => [
                    'status' => 1,
                    'allorany' => 'all',
                    'rules' => [
                        [
                            'field' => 'field_ss_you_may_also_like_show',
                            'operator' => '==',
                            'value' => '1',
                        ],
                        [
                            'field' => 'field_ss_you_may_also_like_mode',
                            'operator' => '==',
                            'value' => 'auto',
                        ],
                    ],
                ],
            ],
        ],
        'location' => [
            [
                [
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'product',
                ],
            ],
        ],
        'menu_order' => 0,
        'position' => 'normal',
        'style' => 'default',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
        'active' => true,
        'show_in_rest' => 0,
    ]);
});

// WooCommerce single product hook: appears below the main product container.
add_action('woocommerce_after_single_product', 'ss_render_you_may_also_like_section', 25);

