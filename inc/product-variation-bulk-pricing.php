<?php
/**
 * Bulk variation pricing: product data tab + AJAX preview/apply.
 *
 * @package Stone_Sparkle
 */

defined('ABSPATH') || exit;

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

    $variation_attributes = $product->get_variation_attributes();
    if (empty($variation_attributes)) {
        ?>
        <div id="ss_bulk_pricing_panel" class="panel woocommerce_options_panel hidden">
            <p class="ss-bulk-pricing__empty">
                <?php esc_html_e('Add variation attributes and generate variations before using bulk pricing.', 'stone-sparkle'); ?>
            </p>
        </div>
        <?php
        return;
    }

    wp_nonce_field('ss_bulk_variation_pricing', 'ss_bulk_variation_pricing_nonce');
    ?>
    <div id="ss_bulk_pricing_panel" class="panel woocommerce_options_panel hidden">
        <div class="ss-bulk-pricing" data-product-id="<?php echo esc_attr((string) $product->get_id()); ?>">
            <p class="ss-bulk-pricing__intro">
                <?php esc_html_e('Select which attribute options should receive the prices below, then click Apply. All options are selected by default — uncheck any you want to exclude.', 'stone-sparkle'); ?>
            </p>

            <?php foreach ($variation_attributes as $attribute_name => $options) : ?>
                <?php
                $attribute_label = wc_attribute_label($attribute_name, $product);
                $field_id        = 'ss-bulk-pricing-' . sanitize_title($attribute_name);
                $option_count    = count((array) $options);
                $is_large        = $option_count > 12;
                ?>
                <fieldset
                    class="ss-bulk-pricing__attribute<?php echo $is_large ? ' ss-bulk-pricing__attribute--large is-collapsed' : ' is-expanded'; ?>"
                    data-attribute="<?php echo esc_attr($attribute_name); ?>"
                    data-option-count="<?php echo esc_attr((string) $option_count); ?>"
                >
                    <?php
                    $body_id = $field_id . '-body';
                    ?>
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
                            <?php foreach ($options as $option_slug) : ?>
                                <?php
                                $option_slug = (string) $option_slug;
                                $option_name = ss_bulk_variation_pricing_option_label($attribute_name, $option_slug);
                                $input_id    = $field_id . '-' . sanitize_title($option_slug);
                                $search_label = function_exists('wc_strtolower') ? wc_strtolower($option_name) : strtolower($option_name);
                                ?>
                                <label
                                    class="ss-bulk-pricing__option"
                                    for="<?php echo esc_attr($input_id); ?>"
                                    data-option-label="<?php echo esc_attr($search_label); ?>"
                                >
                                    <input
                                        type="checkbox"
                                        id="<?php echo esc_attr($input_id); ?>"
                                        name="ss_bulk_pricing[<?php echo esc_attr($attribute_name); ?>][]"
                                        value="<?php echo esc_attr($option_slug); ?>"
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
 * Human-readable label for a variation attribute option slug.
 *
 * @param string $attribute_name Attribute key.
 * @param string $option_slug    Option slug.
 * @return string
 */
function ss_bulk_variation_pricing_option_label($attribute_name, $option_slug) {
    if (taxonomy_exists($attribute_name)) {
        $term = get_term_by('slug', $option_slug, $attribute_name);
        if ($term && !is_wp_error($term)) {
            return $term->name;
        }
    }

    return $option_slug;
}

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
        '1.0.4'
    );

    wp_enqueue_script(
        'ss-bulk-variation-pricing-admin',
        get_template_directory_uri() . '/assets/js/admin-product-variation-bulk-pricing.js',
        ['jquery'],
        '1.0.4',
        true
    );

    wp_localize_script(
        'ss-bulk-variation-pricing-admin',
        'ssBulkVariationPricing',
        [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('ss_bulk_variation_pricing'),
            'i18n'    => [
                'previewSingular' => __('1 variation will be updated.', 'stone-sparkle'),
                'previewPlural'   => __('%d variations will be updated.', 'stone-sparkle'),
                'applySuccess'    => __('Updated %d variation(s).', 'stone-sparkle'),
                'applyError'      => __('Could not update variations. Please try again.', 'stone-sparkle'),
                'validationError' => __('Select at least one option for every attribute.', 'stone-sparkle'),
                'regularRequired' => __('Enter a regular price before applying.', 'stone-sparkle'),
                'selectedCount'   => __('%1$d of %2$d selected', 'stone-sparkle'),
                'expandAttribute' => __('Expand attribute options', 'stone-sparkle'),
                'collapseAttribute' => __('Collapse attribute options', 'stone-sparkle'),
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
        $attribute_name = wc_clean((string) $attribute_name);
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
 * Merge posted filters with the product's variation attributes.
 *
 * @param WC_Product_Variable      $product Variable product.
 * @param array<string, mixed>     $raw     Raw attribute map from the request.
 * @return array<string, string[]>|WP_Error
 */
function ss_bulk_variation_pricing_resolve_filters($product, $raw) {
    $parsed = ss_bulk_variation_pricing_parse_filters($raw);
    if (is_wp_error($parsed)) {
        return $parsed;
    }

    $variation_attributes = $product->get_variation_attributes();
    if (empty($variation_attributes)) {
        return new WP_Error('ss_bulk_pricing_no_attributes', __('No variation attributes found for this product.', 'stone-sparkle'));
    }

    $resolved = [];

    foreach ($variation_attributes as $attribute_name => $options) {
        if (isset($parsed[$attribute_name]) && !empty($parsed[$attribute_name])) {
            $resolved[$attribute_name] = $parsed[$attribute_name];
            continue;
        }

        $resolved[$attribute_name] = array_values(array_map('strval', (array) $options));
    }

    return $resolved;
}

/**
 * Resolve a variation attribute value by attribute key.
 *
 * @param WC_Product_Variation $variation       Variation product.
 * @param string               $attribute_name  Attribute key from the parent product.
 * @return string
 */
function ss_bulk_variation_pricing_get_variation_attribute_value($variation, $attribute_name) {
    $attributes = $variation->get_attributes();

    if (isset($attributes[$attribute_name])) {
        return (string) $attributes[$attribute_name];
    }

    $meta_key = 'attribute_' . $attribute_name;
    $meta_value = $variation->get_meta($meta_key, true);

    return is_string($meta_value) ? $meta_value : '';
}

/**
 * Compare a stored variation value against admin-selected options.
 *
 * Custom attributes keep their exact label (e.g. "test 2"); taxonomy terms use slugs.
 *
 * @param string   $variation_value Stored variation attribute value.
 * @param string[] $allowed_values  Selected option values from the admin UI.
 * @return bool
 */
function ss_bulk_variation_pricing_value_matches($variation_value, $allowed_values) {
    $variation_value = wc_clean((string) $variation_value);
    if ($variation_value === '') {
        return false;
    }

    foreach ($allowed_values as $allowed_value) {
        $allowed_value = wc_clean((string) $allowed_value);
        if ($allowed_value === '') {
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
 * Determine whether a variation matches the selected attribute filters.
 *
 * @param WC_Product_Variation   $variation Variation product.
 * @param array<string, string[]> $filters   Selected attribute values.
 * @return bool
 */
function ss_bulk_variation_pricing_variation_matches($variation, $filters) {
    foreach ($filters as $attribute_name => $allowed_values) {
        $variation_value = ss_bulk_variation_pricing_get_variation_attribute_value($variation, $attribute_name);

        if (!ss_bulk_variation_pricing_value_matches($variation_value, $allowed_values)) {
            return false;
        }
    }

    return true;
}

/**
 * Collect variation IDs that match the selected filters.
 *
 * @param WC_Product_Variable    $product Variable product.
 * @param array<string, string[]> $filters Selected attribute slugs.
 * @return int[]
 */
function ss_bulk_variation_pricing_matching_variation_ids($product, $filters) {
    $matching_ids = [];

    foreach ($product->get_children() as $variation_id) {
        $variation = wc_get_product($variation_id);
        if (!$variation || !$variation->is_type('variation')) {
            continue;
        }

        if (ss_bulk_variation_pricing_variation_matches($variation, $filters)) {
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

    $count = count(ss_bulk_variation_pricing_matching_variation_ids($product, $filters));

    wp_send_json_success(['count' => $count]);
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
