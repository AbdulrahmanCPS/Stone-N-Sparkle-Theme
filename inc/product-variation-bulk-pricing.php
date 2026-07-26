<?php
/**
 * Bulk variation pricing: product data tab + AJAX preview/apply.
 *
 * @package Stone_Sparkle
 */

defined('ABSPATH') || exit;

/** Sentinel value for variations with an empty ("Any") attribute. */
define('SS_BULK_PRICING_ANY_SENTINEL', '__any__');

/**
 * Normalize an attribute key for consistent meta / parent lookups.
 *
 * @param string $key Attribute key.
 * @return string
 */
function ss_bulk_variation_pricing_normalize_attribute_key($key) {
    return sanitize_title((string) $key);
}

/**
 * Register the Bulk pricing tab on variable products.
 *
 * @param array<string, array<string, mixed>> $tabs Product data tabs.
 * @return array<string, array<string, mixed>>
 */
function ss_bulk_variation_pricing_product_data_tab($tabs) {
    $tabs['ss_bulk_pricing'] = [
        'label'    => __('Bulk pricing', 'stone-sparkle'),
        'target'   => 'ss_bulk_pricing_panel',
        'class'    => ['show_if_variable'],
        'priority' => 65,
    ];

    return $tabs;
}
add_filter('woocommerce_product_data_tabs', 'ss_bulk_variation_pricing_product_data_tab');

/**
 * Collect variation IDs for a variable product, with a direct query fallback.
 *
 * @param WC_Product_Variable $product Variable product.
 * @return int[]
 */
function ss_bulk_variation_pricing_get_variation_ids($product) {
    $ids = array_map('absint', (array) $product->get_children());
    $ids = array_values(array_filter($ids));

    if (!empty($ids)) {
        return $ids;
    }

    $fallback = get_posts([
        'post_parent'    => $product->get_id(),
        'post_type'      => 'product_variation',
        'post_status'    => ['publish', 'private'],
        'numberposts'    => -1,
        'fields'         => 'ids',
        'orderby'        => 'menu_order ID',
        'order'          => 'ASC',
        'suppress_filters' => true,
    ]);

    return array_values(array_map('absint', (array) $fallback));
}

/**
 * Load attribute_* meta for all variations of a product in one query.
 *
 * @param WC_Product_Variable $product Variable product.
 * @return array<int, array<string, string>> Variation ID => [ attribute_key => value ].
 */
function ss_bulk_variation_pricing_get_variation_rows($product) {
    global $wpdb;

    $variation_ids = ss_bulk_variation_pricing_get_variation_ids($product);
    if (empty($variation_ids)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($variation_ids), '%d'));
    $like         = $wpdb->esc_like('attribute_') . '%';
    $query        = "SELECT post_id, meta_key, meta_value
         FROM {$wpdb->postmeta}
         WHERE post_id IN ({$placeholders})
           AND meta_key LIKE %s";
    $args         = array_merge($variation_ids, [$like]);

    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- dynamic IN list.
    $sql = call_user_func_array([$wpdb, 'prepare'], array_merge([$query], $args));

    if (!is_string($sql) || $sql === '') {
        return [];
    }

    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
    $results = $wpdb->get_results($sql, ARRAY_A);
    $rows    = [];

    foreach ($variation_ids as $variation_id) {
        $rows[(int) $variation_id] = [];
    }

    if (!is_array($results)) {
        return $rows;
    }

    foreach ($results as $row) {
        $variation_id = isset($row['post_id']) ? (int) $row['post_id'] : 0;
        $meta_key     = isset($row['meta_key']) ? (string) $row['meta_key'] : '';

        if ($variation_id <= 0 || strpos($meta_key, 'attribute_') !== 0) {
            continue;
        }

        $attribute_name = ss_bulk_variation_pricing_normalize_attribute_key(
            substr($meta_key, strlen('attribute_'))
        );
        if ($attribute_name === '') {
            continue;
        }

        $rows[$variation_id][$attribute_name] = is_string($row['meta_value'] ?? null)
            ? (string) $row['meta_value']
            : '';
    }

    return $rows;
}

/**
 * Read an attribute value from a variation row using a normalized key.
 *
 * @param array<string, string> $attributes Variation attribute map.
 * @param string                $attribute_name Attribute key.
 * @return string
 */
function ss_bulk_variation_pricing_row_attribute_value($attributes, $attribute_name) {
    $norm = ss_bulk_variation_pricing_normalize_attribute_key($attribute_name);

    if (array_key_exists($norm, $attributes)) {
        return (string) $attributes[$norm];
    }

    if (array_key_exists($attribute_name, $attributes)) {
        return (string) $attributes[$attribute_name];
    }

    foreach ($attributes as $key => $value) {
        if (ss_bulk_variation_pricing_normalize_attribute_key((string) $key) === $norm) {
            return (string) $value;
        }
    }

    return '';
}

/**
 * Build checkbox options from parent attribute terms unioned with variation-row values.
 *
 * Empty ("Any") variation meta adds the SS_BULK_PRICING_ANY_SENTINEL option at the end.
 *
 * @param WC_Product_Variable $product Variable product.
 * @return array<string, string[]> Attribute key => list of option values.
 */
function ss_bulk_variation_pricing_build_options($product) {
    $attribute_keys = [];

    foreach ($product->get_attributes() as $attribute) {
        if (!$attribute instanceof WC_Product_Attribute || !$attribute->get_variation()) {
            continue;
        }

        $name = $attribute->get_name();
        if ($name !== '') {
            $attribute_keys[] = ss_bulk_variation_pricing_normalize_attribute_key($name);
        }
    }

    $rows = ss_bulk_variation_pricing_get_variation_rows($product);

    if (empty($attribute_keys)) {
        foreach ($rows as $attrs) {
            foreach (array_keys($attrs) as $key) {
                $attribute_keys[] = ss_bulk_variation_pricing_normalize_attribute_key((string) $key);
            }
        }
        $attribute_keys = array_values(array_unique($attribute_keys));
    }

    if (empty($attribute_keys)) {
        return [];
    }

    $parent_attributes = [];
    foreach ((array) $product->get_variation_attributes() as $parent_key => $parent_values) {
        $parent_attributes[ss_bulk_variation_pricing_normalize_attribute_key((string) $parent_key)] = (array) $parent_values;
    }

    $options = [];

    foreach ($attribute_keys as $attribute_name) {
        $values = [];
        $seen   = [];

        if (!empty($parent_attributes[$attribute_name])) {
            foreach ($parent_attributes[$attribute_name] as $parent_value) {
                $parent_value = (string) $parent_value;
                if ($parent_value === '' || isset($seen[$parent_value])) {
                    continue;
                }
                $seen[$parent_value] = true;
                $values[]            = $parent_value;
            }
        }

        $has_any = false;

        foreach ($rows as $attrs) {
            $raw = ss_bulk_variation_pricing_row_attribute_value($attrs, $attribute_name);

            if ($raw === '') {
                $has_any = true;
                continue;
            }

            if (isset($seen[$raw])) {
                continue;
            }

            $seen[$raw] = true;
            $values[]   = $raw;
        }

        if ($has_any) {
            $values[] = SS_BULK_PRICING_ANY_SENTINEL;
        }

        if (!empty($values)) {
            $options[$attribute_name] = $values;
        }
    }

    return $options;
}

/**
 * Human-readable label for a variation attribute option value.
 *
 * @param string $attribute_name Attribute key.
 * @param string $option_value   Option value (slug, label, or Any sentinel).
 * @return string
 */
function ss_bulk_variation_pricing_option_label($attribute_name, $option_value) {
    if ($option_value === SS_BULK_PRICING_ANY_SENTINEL) {
        $label = wc_attribute_label($attribute_name);
        return sprintf(
            /* translators: %s: attribute name */
            __('Any %s', 'stone-sparkle'),
            $label !== '' ? $label : $attribute_name
        );
    }

    if (taxonomy_exists($attribute_name)) {
        $term = get_term_by('slug', $option_value, $attribute_name);
        if ($term && !is_wp_error($term)) {
            return $term->name;
        }
    }

    return $option_value;
}

/**
 * Render the Bulk pricing panel inside Product data.
 *
 * @return void
 */
function ss_bulk_variation_pricing_product_data_panel() {
    global $post;

    if (!$post instanceof WP_Post) {
        return;
    }

    $product = wc_get_product($post->ID);
    if (!$product || !$product->is_type('variable')) {
        return;
    }

    $built_options = ss_bulk_variation_pricing_build_options($product);
    if (empty($built_options)) {
        ?>
        <div id="ss_bulk_pricing_panel" class="panel woocommerce_options_panel hidden">
            <p class="ss-bulk-pricing__empty">
                <?php esc_html_e('Add variation attributes and generate variations before using bulk pricing.', 'stone-sparkle'); ?>
            </p>
        </div>
        <?php
        return;
    }

    $total_variations = count(ss_bulk_variation_pricing_get_variation_ids($product));

    wp_nonce_field('ss_bulk_variation_pricing', 'ss_bulk_variation_pricing_nonce');
    ?>
    <div id="ss_bulk_pricing_panel" class="panel woocommerce_options_panel hidden">
        <div
            class="ss-bulk-pricing"
            data-product-id="<?php echo esc_attr((string) $product->get_id()); ?>"
            data-total-variations="<?php echo esc_attr((string) $total_variations); ?>"
        >
            <p class="ss-bulk-pricing__intro">
                <?php esc_html_e('Select which attribute options should receive the prices below, then click Apply. All options are selected by default — uncheck any you want to exclude.', 'stone-sparkle'); ?>
            </p>

            <?php foreach ($built_options as $attribute_name => $options) : ?>
                <?php
                $attribute_label = wc_attribute_label($attribute_name, $product);
                $field_id        = 'ss-bulk-pricing-' . sanitize_title($attribute_name);
                $option_count    = count((array) $options);
                $is_large        = $option_count > 12;
                $body_id         = $field_id . '-body';
                ?>
                <fieldset
                    class="ss-bulk-pricing__attribute<?php echo $is_large ? ' ss-bulk-pricing__attribute--large is-collapsed' : ' is-expanded'; ?>"
                    data-attribute="<?php echo esc_attr($attribute_name); ?>"
                    data-option-count="<?php echo esc_attr((string) $option_count); ?>"
                >
                    <div class="ss-bulk-pricing__attribute-header">
                        <button
                            type="button"
                            class="ss-bulk-pricing__toggle"
                            aria-expanded="<?php echo $is_large ? 'false' : 'true'; ?>"
                            aria-controls="<?php echo esc_attr($body_id); ?>"
                        >
                            <span class="ss-bulk-pricing__toggle-icon" aria-hidden="true"></span>
                            <span class="ss-bulk-pricing__attribute-label">
                                <?php echo esc_html($attribute_label); ?>
                                <span class="ss-bulk-pricing__attribute-count">
                                    <?php
                                    printf(
                                        /* translators: %d: number of attribute options */
                                        esc_html(_n('%d option', '%d options', $option_count, 'stone-sparkle')),
                                        (int) $option_count
                                    );
                                    ?>
                                </span>
                            </span>
                        </button>

                        <div class="ss-bulk-pricing__attribute-toolbar">
                            <span class="ss-bulk-pricing__selection-count" aria-live="polite"></span>
                            <div class="ss-bulk-pricing__attribute-actions" role="group" aria-label="<?php echo esc_attr(sprintf(__('Selection actions for %s', 'stone-sparkle'), $attribute_label)); ?>">
                                <button type="button" class="ss-bulk-pricing__action-btn ss-bulk-pricing__select-all">
                                    <?php esc_html_e('Select all', 'stone-sparkle'); ?>
                                </button>
                                <button type="button" class="ss-bulk-pricing__action-btn ss-bulk-pricing__clear-all">
                                    <?php esc_html_e('Clear all', 'stone-sparkle'); ?>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div id="<?php echo esc_attr($body_id); ?>" class="ss-bulk-pricing__attribute-body">
                    <?php if ($is_large) : ?>
                        <div class="ss-bulk-pricing__filter-wrap">
                            <input
                                type="search"
                                class="ss-bulk-pricing__filter"
                                placeholder="<?php esc_attr_e('Search options…', 'stone-sparkle'); ?>"
                                aria-label="<?php echo esc_attr(sprintf(__('Search %s options', 'stone-sparkle'), $attribute_label)); ?>"
                                autocomplete="off"
                            >
                        </div>
                    <?php endif; ?>

                    <div class="ss-bulk-pricing__options-wrap">
                        <div class="ss-bulk-pricing__options">
                            <?php foreach ($options as $option_value) : ?>
                                <?php
                                $option_value  = (string) $option_value;
                                $option_name   = ss_bulk_variation_pricing_option_label($attribute_name, $option_value);
                                $input_id      = $field_id . '-' . sanitize_title($option_value);
                                $search_label  = function_exists('wc_strtolower') ? wc_strtolower($option_name) : strtolower($option_name);
                                $is_any_option = ($option_value === SS_BULK_PRICING_ANY_SENTINEL);
                                ?>
                                <label
                                    class="ss-bulk-pricing__option<?php echo $is_any_option ? ' ss-bulk-pricing__option--any' : ''; ?>"
                                    for="<?php echo esc_attr($input_id); ?>"
                                    data-option-label="<?php echo esc_attr($search_label); ?>"
                                >
                                    <input
                                        type="checkbox"
                                        id="<?php echo esc_attr($input_id); ?>"
                                        name="ss_bulk_pricing[<?php echo esc_attr($attribute_name); ?>][]"
                                        value="<?php echo esc_attr($option_value); ?>"
                                        checked
                                    >
                                    <span class="ss-bulk-pricing__option-text"><?php echo esc_html($option_name); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <p class="ss-bulk-pricing__filter-empty" hidden>
                        <?php esc_html_e('No options match your search.', 'stone-sparkle'); ?>
                    </p>
                    </div>
                </fieldset>
            <?php endforeach; ?>

            <div class="ss-bulk-pricing__prices">
                <p class="form-field ss-bulk-pricing__price-field">
                    <label for="ss_bulk_pricing_regular_price">
                        <?php esc_html_e('Regular price', 'stone-sparkle'); ?>
                    </label>
                    <input
                        type="text"
                        class="wc_input_price"
                        id="ss_bulk_pricing_regular_price"
                        name="ss_bulk_pricing_regular_price"
                        placeholder="<?php echo esc_attr(wc_format_localized_price('0')); ?>"
                    >
                </p>
                <p class="form-field ss-bulk-pricing__price-field">
                    <label for="ss_bulk_pricing_sale_price">
                        <?php esc_html_e('Sale price', 'stone-sparkle'); ?>
                        <span class="description"><?php esc_html_e('(optional)', 'stone-sparkle'); ?></span>
                    </label>
                    <input
                        type="text"
                        class="wc_input_price"
                        id="ss_bulk_pricing_sale_price"
                        name="ss_bulk_pricing_sale_price"
                        placeholder="<?php echo esc_attr(wc_format_localized_price('0')); ?>"
                    >
                </p>
            </div>

            <p class="ss-bulk-pricing__preview" aria-live="polite">
                <?php esc_html_e('0 variations will be updated.', 'stone-sparkle'); ?>
            </p>

            <p class="ss-bulk-pricing__actions">
                <button type="button" class="button button-primary ss-bulk-pricing__apply">
                    <?php esc_html_e('Apply to matching variations', 'stone-sparkle'); ?>
                </button>
                <span class="spinner ss-bulk-pricing__spinner" aria-hidden="true"></span>
            </p>

            <p class="ss-bulk-pricing__result" aria-live="polite"></p>
        </div>
    </div>
    <?php
}
add_action('woocommerce_product_data_panels', 'ss_bulk_variation_pricing_product_data_panel');

/**
 * Enqueue admin assets on the product edit screen.
 *
 * @param string $hook_suffix Current admin page hook.
 * @return void
 */
function ss_bulk_variation_pricing_admin_assets($hook_suffix) {
    if (!in_array($hook_suffix, ['post.php', 'post-new.php'], true)) {
        return;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->post_type !== 'product') {
        return;
    }

    $product_id = isset($_GET['post']) ? absint(wp_unslash($_GET['post'])) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if ($product_id > 0) {
        $product = wc_get_product($product_id);
        if (!$product || !$product->is_type('variable')) {
            return;
        }
    }

    wp_enqueue_style(
        'ss-bulk-variation-pricing-admin',
        get_template_directory_uri() . '/assets/css/admin-product-variation-bulk-pricing.css',
        [],
        '1.0.6'
    );

    wp_enqueue_script(
        'ss-bulk-variation-pricing-admin',
        get_template_directory_uri() . '/assets/js/admin-product-variation-bulk-pricing.js',
        ['jquery'],
        '1.0.6',
        true
    );

    wp_localize_script(
        'ss-bulk-variation-pricing-admin',
        'ssBulkVariationPricing',
        [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('ss_bulk_variation_pricing'),
            'i18n'    => [
                'previewSingular'   => __('1 variation will be updated.', 'stone-sparkle'),
                'previewPlural'     => __('%d variations will be updated.', 'stone-sparkle'),
                'previewOfTotal'    => __('%1$d of %2$d variations will be updated.', 'stone-sparkle'),
                'applySuccess'      => __('Updated %d variation(s).', 'stone-sparkle'),
                'applyError'        => __('Could not update variations. Please try again.', 'stone-sparkle'),
                'validationError'   => __('Select at least one option for every attribute.', 'stone-sparkle'),
                'regularRequired'   => __('Enter a regular price before applying.', 'stone-sparkle'),
                'selectedCount'     => __('%1$d of %2$d selected', 'stone-sparkle'),
                'expandAttribute'   => __('Expand attribute options', 'stone-sparkle'),
                'collapseAttribute' => __('Collapse attribute options', 'stone-sparkle'),
                'ajaxFail'          => __('Preview request failed (HTTP %d). Check the browser console or try again.', 'stone-sparkle'),
                'zeroMatchHint'     => __('No variations match the selected options. Open the Variations tab to confirm each variation has the expected attribute values.', 'stone-sparkle'),
            ],
            'largeOptionsThreshold' => 12,
        ]
    );
}
add_action('admin_enqueue_scripts', 'ss_bulk_variation_pricing_admin_assets');

/**
 * Parse and sanitize selected attribute filters from the request.
 *
 * @param array<string, mixed> $raw Raw attribute map.
 * @return array<string, string[]>|WP_Error
 */
function ss_bulk_variation_pricing_parse_filters($raw) {
    if (!is_array($raw)) {
        return new WP_Error('ss_bulk_pricing_invalid_filters', __('Invalid attribute filters.', 'stone-sparkle'));
    }

    $filters = [];

    foreach ($raw as $attribute_name => $values) {
        $attribute_name = ss_bulk_variation_pricing_normalize_attribute_key(wc_clean((string) $attribute_name));
        if ($attribute_name === '') {
            continue;
        }

        if (!is_array($values)) {
            $values = [$values];
        }

        $selected_values = [];
        foreach ($values as $value) {
            $value = wc_clean((string) $value);
            if ($value !== '') {
                $selected_values[] = $value;
            }
        }

        $selected_values = array_values(array_unique($selected_values));
        if (empty($selected_values)) {
            return new WP_Error(
                'ss_bulk_pricing_empty_attribute',
                __('Select at least one option for every attribute.', 'stone-sparkle')
            );
        }

        $filters[$attribute_name] = $selected_values;
    }

    return $filters;
}

/**
 * Merge posted filters with options derived from parent terms + variation rows.
 *
 * @param WC_Product_Variable  $product Variable product.
 * @param array<string, mixed> $raw     Raw attribute map from the request.
 * @return array<string, string[]>|WP_Error
 */
function ss_bulk_variation_pricing_resolve_filters($product, $raw) {
    $parsed = ss_bulk_variation_pricing_parse_filters($raw);
    if (is_wp_error($parsed)) {
        return $parsed;
    }

    $built_options = ss_bulk_variation_pricing_build_options($product);
    if (empty($built_options)) {
        return new WP_Error('ss_bulk_pricing_no_attributes', __('No variation attributes found for this product.', 'stone-sparkle'));
    }

    $resolved = [];

    foreach ($built_options as $attribute_name => $options) {
        $norm = ss_bulk_variation_pricing_normalize_attribute_key($attribute_name);

        if (isset($parsed[$norm]) && !empty($parsed[$norm])) {
            $resolved[$attribute_name] = $parsed[$norm];
            continue;
        }

        if (isset($parsed[$attribute_name]) && !empty($parsed[$attribute_name])) {
            $resolved[$attribute_name] = $parsed[$attribute_name];
            continue;
        }

        $resolved[$attribute_name] = array_values(array_map('strval', (array) $options));
    }

    return $resolved;
}

/**
 * Whether every specific (non-Any) option is included in the selected values.
 *
 * @param string[] $all_options    Full option list for the attribute.
 * @param string[] $allowed_values Selected values.
 * @return bool
 */
function ss_bulk_variation_pricing_all_specific_selected($all_options, $allowed_values) {
    $specific = [];
    foreach ((array) $all_options as $option) {
        $option = (string) $option;
        if ($option !== '' && $option !== SS_BULK_PRICING_ANY_SENTINEL) {
            $specific[] = $option;
        }
    }

    if (empty($specific)) {
        return false;
    }

    foreach ($specific as $option) {
        $found = false;
        foreach ((array) $allowed_values as $allowed_value) {
            $allowed_value = (string) $allowed_value;
            if ($allowed_value === '' || $allowed_value === SS_BULK_PRICING_ANY_SENTINEL) {
                continue;
            }
            if ($option === $allowed_value) {
                $found = true;
                break;
            }
            if (function_exists('wc_strtolower') && wc_strtolower($option) === wc_strtolower($allowed_value)) {
                $found = true;
                break;
            }
            if (sanitize_title($option) === sanitize_title($allowed_value)) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            return false;
        }
    }

    return true;
}

/**
 * Compare a stored variation value against admin-selected options.
 *
 * Empty stored values match when Any is selected, or when every specific
 * option for that attribute is selected.
 *
 * @param string   $variation_value Stored variation attribute value.
 * @param string[] $allowed_values  Selected option values from the admin UI.
 * @param string[] $all_options     Full option list for the attribute.
 * @return bool
 */
function ss_bulk_variation_pricing_value_matches($variation_value, $allowed_values, $all_options = []) {
    $variation_value = is_string($variation_value) ? $variation_value : (string) $variation_value;

    // "Any" attribute on the variation (empty meta).
    if ($variation_value === '') {
        if (in_array(SS_BULK_PRICING_ANY_SENTINEL, $allowed_values, true)) {
            return true;
        }

        return ss_bulk_variation_pricing_all_specific_selected($all_options, $allowed_values);
    }

    $variation_value = wc_clean($variation_value);

    foreach ($allowed_values as $allowed_value) {
        $allowed_value = wc_clean((string) $allowed_value);
        if ($allowed_value === '' || $allowed_value === SS_BULK_PRICING_ANY_SENTINEL) {
            continue;
        }

        if ($variation_value === $allowed_value) {
            return true;
        }

        if (function_exists('wc_strtolower') && wc_strtolower($variation_value) === wc_strtolower($allowed_value)) {
            return true;
        }

        if (sanitize_title($variation_value) === sanitize_title($allowed_value)) {
            return true;
        }
    }

    return false;
}

/**
 * Determine whether a variation attribute map matches the selected filters.
 *
 * @param array<string, string>   $attributes    Variation attribute map.
 * @param array<string, string[]> $filters       Selected attribute values.
 * @param array<string, string[]> $built_options Full option lists per attribute.
 * @return bool
 */
function ss_bulk_variation_pricing_row_matches($attributes, $filters, $built_options = []) {
    foreach ($filters as $attribute_name => $allowed_values) {
        $variation_value = ss_bulk_variation_pricing_row_attribute_value($attributes, $attribute_name);
        $norm            = ss_bulk_variation_pricing_normalize_attribute_key($attribute_name);
        $all_options     = [];

        if (isset($built_options[$attribute_name])) {
            $all_options = (array) $built_options[$attribute_name];
        } elseif (isset($built_options[$norm])) {
            $all_options = (array) $built_options[$norm];
        }

        if (!ss_bulk_variation_pricing_value_matches($variation_value, $allowed_values, $all_options)) {
            return false;
        }
    }

    return true;
}

/**
 * Collect variation IDs that match the selected filters.
 *
 * @param WC_Product_Variable     $product Variable product.
 * @param array<string, string[]> $filters Selected attribute values.
 * @return int[]
 */
function ss_bulk_variation_pricing_matching_variation_ids($product, $filters) {
    $matching_ids  = [];
    $built_options = ss_bulk_variation_pricing_build_options($product);

    foreach (ss_bulk_variation_pricing_get_variation_rows($product) as $variation_id => $attributes) {
        if (ss_bulk_variation_pricing_row_matches($attributes, $filters, $built_options)) {
            $matching_ids[] = (int) $variation_id;
        }
    }

    return $matching_ids;
}

/**
 * Verify AJAX request access for a product.
 *
 * @param int $product_id Product ID.
 * @return WC_Product_Variable|WP_Error
 */
function ss_bulk_variation_pricing_verify_request($product_id) {
    if (!current_user_can('edit_product', $product_id)) {
        return new WP_Error('ss_bulk_pricing_forbidden', __('You are not allowed to edit this product.', 'stone-sparkle'), ['status' => 403]);
    }

    check_ajax_referer('ss_bulk_variation_pricing', 'nonce');

    $product = wc_get_product($product_id);
    if (!$product || !$product->is_type('variable')) {
        return new WP_Error('ss_bulk_pricing_invalid_product', __('Bulk pricing is only available for variable products.', 'stone-sparkle'));
    }

    return $product;
}

/**
 * AJAX: preview how many variations match the current filters.
 *
 * @return void
 */
function ss_bulk_variation_pricing_ajax_preview() {
    $product_id = isset($_POST['product_id']) ? absint(wp_unslash($_POST['product_id'])) : 0;
    $product    = ss_bulk_variation_pricing_verify_request($product_id);
    if (is_wp_error($product)) {
        wp_send_json_error(['message' => $product->get_error_message()], (int) ($product->get_error_data()['status'] ?? 400));
    }

    $raw_filters = isset($_POST['filters']) ? wp_unslash($_POST['filters']) : [];
    $filters     = ss_bulk_variation_pricing_resolve_filters($product, $raw_filters);
    if (is_wp_error($filters)) {
        wp_send_json_error(['message' => $filters->get_error_message()]);
    }

    $total = count(ss_bulk_variation_pricing_get_variation_ids($product));
    $count = count(ss_bulk_variation_pricing_matching_variation_ids($product, $filters));

    wp_send_json_success([
        'count' => $count,
        'total' => $total,
    ]);
}
add_action('wp_ajax_ss_bulk_variation_price_preview', 'ss_bulk_variation_pricing_ajax_preview');

/**
 * AJAX: apply regular/sale prices to matching variations.
 *
 * @return void
 */
function ss_bulk_variation_pricing_ajax_apply() {
    $product_id = isset($_POST['product_id']) ? absint(wp_unslash($_POST['product_id'])) : 0;
    $product    = ss_bulk_variation_pricing_verify_request($product_id);
    if (is_wp_error($product)) {
        wp_send_json_error(['message' => $product->get_error_message()], (int) ($product->get_error_data()['status'] ?? 400));
    }

    $raw_filters = isset($_POST['filters']) ? wp_unslash($_POST['filters']) : [];
    $filters     = ss_bulk_variation_pricing_resolve_filters($product, $raw_filters);
    if (is_wp_error($filters)) {
        wp_send_json_error(['message' => $filters->get_error_message()]);
    }

    $regular_price_raw = isset($_POST['regular_price']) ? wp_unslash($_POST['regular_price']) : '';
    $sale_price_raw    = isset($_POST['sale_price']) ? wp_unslash($_POST['sale_price']) : '';

    if ($regular_price_raw === '' || $regular_price_raw === null) {
        wp_send_json_error(['message' => __('Enter a regular price before applying.', 'stone-sparkle')]);
    }

    $regular_price = wc_format_decimal(wc_clean((string) $regular_price_raw));
    $sale_price    = $sale_price_raw === '' ? '' : wc_format_decimal(wc_clean((string) $sale_price_raw));

    if ($regular_price === '') {
        wp_send_json_error(['message' => __('Enter a valid regular price before applying.', 'stone-sparkle')]);
    }

    if ($sale_price !== '' && (float) $sale_price >= (float) $regular_price) {
        wp_send_json_error(['message' => __('Sale price must be lower than the regular price.', 'stone-sparkle')]);
    }

    $matching_ids = ss_bulk_variation_pricing_matching_variation_ids($product, $filters);
    $updated      = 0;

    foreach ($matching_ids as $variation_id) {
        $variation = wc_get_product($variation_id);
        if (!$variation || !$variation->is_type('variation')) {
            continue;
        }

        $variation->set_regular_price($regular_price);
        $variation->set_sale_price($sale_price);
        $variation->save();
        ++$updated;
    }

    if ($updated > 0) {
        WC_Product_Variable::sync($product_id);
        wc_delete_product_transients($product_id);
    }

    wp_send_json_success([
        'updated' => $updated,
        'message' => sprintf(
            /* translators: %d: number of updated variations */
            _n('Updated %d variation.', 'Updated %d variations.', $updated, 'stone-sparkle'),
            $updated
        ),
    ]);
}
add_action('wp_ajax_ss_bulk_variation_price_apply', 'ss_bulk_variation_pricing_ajax_apply');
