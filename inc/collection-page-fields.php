<?php
/**
 * Collection Page fields: ACF (term + per-page), fallback meta box, lookbook before/after.
 *
 * - When ACF is active: register field group for collection_term (pa_collection) and products_per_page.
 * - When ACF is inactive: show "Collection settings" meta box with dropdown and number input; save to post meta.
 * - Lookbook before/after: custom meta boxes, JSON in ss_collection_lookbook_before / ss_collection_lookbook_after (no ACF Repeater).
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SS_COLLECTION_LOOKBOOK_BEFORE_KEY', 'ss_collection_lookbook_before');
define('SS_COLLECTION_LOOKBOOK_AFTER_KEY', 'ss_collection_lookbook_after');
define('SS_COLLECTION_LOOKBOOK_NONCE_ACTION', 'ss_collection_lookbook_save');

// -----------------------------------------------------------------------------
// ACF field group (collection_term + products_per_page)
// -----------------------------------------------------------------------------
add_action('acf/include_fields', function () {
    if (!function_exists('acf_add_local_field_group')) {
        return;
    }
    if (!taxonomy_exists('pa_collection')) {
        return;
    }

    acf_add_local_field_group([
        'key'                   => 'group_ss_collection_page',
        'title'                 => __('Collection Settings', 'stone-sparkle'),
        'fields'                => [
            [
                'key'           => 'field_ss_collection_term',
                'label'         => __('Collection', 'stone-sparkle'),
                'name'          => 'collection_term',
                'type'          => 'taxonomy',
                'taxonomy'      => 'pa_collection',
                'field_type'    => 'select',
                'return_format' => 'id',
                'required'      => 1,
            ],
            [
                'key'           => 'field_ss_collection_products_per_page',
                'label'         => __('Products per page', 'stone-sparkle'),
                'name'          => 'products_per_page',
                'type'          => 'number',
                'default_value' => 12,
                'min'           => 1,
                'step'          => 1,
            ],
        ],
        'location' => [
            [
                [
                    'param'    => 'post_type',
                    'operator' => '==',
                    'value'    => 'collection_page',
                ],
            ],
        ],
    ]);
});

// -----------------------------------------------------------------------------
// Fallback meta box when ACF is not active (collection term + products per page)
// -----------------------------------------------------------------------------
add_action('add_meta_boxes', function () {
    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== 'collection_page') {
        return;
    }
    // Only show fallback when ACF is not active (ACF would show its own field group).
    if (function_exists('acf_get_field_groups') && !empty(acf_get_field_groups(['post_type' => 'collection_page']))) {
        return;
    }

    add_meta_box(
        'ss_collection_settings',
        __('Collection settings', 'stone-sparkle'),
        'ss_render_collection_settings_meta_box',
        'collection_page',
        'normal',
        'high'
    );
});

/**
 * Render fallback Collection settings meta box (term dropdown + products per page).
 */
function ss_render_collection_settings_meta_box($post) {
    wp_nonce_field('ss_collection_settings_save', 'ss_collection_settings_nonce');

    $term_id   = (int) get_post_meta($post->ID, 'collection_term_id', true);
    $per_page  = (int) get_post_meta($post->ID, 'collection_products_per_page', true);
    if ($per_page < 1) {
        $per_page = 12;
    }

    $terms = [];
    if (taxonomy_exists('pa_collection')) {
        $terms = get_terms(['taxonomy' => 'pa_collection', 'hide_empty' => false]);
    }
    ?>
    <p class="description" style="margin-bottom:12px;"><?php esc_html_e('Select the WooCommerce collection (pa_collection) and how many products to show per page.', 'stone-sparkle'); ?></p>
    <p>
        <label for="ss_collection_term_id"><strong><?php esc_html_e('Collection', 'stone-sparkle'); ?></strong></label><br>
        <select name="collection_term_id" id="ss_collection_term_id" class="widefat">
            <option value=""><?php esc_html_e('— Select —', 'stone-sparkle'); ?></option>
            <?php foreach ($terms as $t) : ?>
                <option value="<?php echo (int) $t->term_id; ?>" <?php selected($term_id, $t->term_id); ?>><?php echo esc_html($t->name); ?></option>
            <?php endforeach; ?>
        </select>
    </p>
    <p>
        <label for="ss_collection_products_per_page"><strong><?php esc_html_e('Products per page', 'stone-sparkle'); ?></strong></label><br>
        <input type="number" name="collection_products_per_page" id="ss_collection_products_per_page" value="<?php echo (int) $per_page; ?>" min="1" step="1" class="small-text">
    </p>
    <?php
}

add_action('save_post_collection_page', function ($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!isset($_POST['ss_collection_settings_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ss_collection_settings_nonce'])), 'ss_collection_settings_save')) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    if (isset($_POST['collection_term_id'])) {
        $tid = (int) $_POST['collection_term_id'];
        update_post_meta($post_id, 'collection_term_id', $tid);
    }
    if (isset($_POST['collection_products_per_page'])) {
        $pp = (int) $_POST['collection_products_per_page'];
        update_post_meta($post_id, 'collection_products_per_page', $pp >= 1 ? $pp : 12);
    }
}, 10, 1);

// -----------------------------------------------------------------------------
// Lookbook before / after meta boxes
// -----------------------------------------------------------------------------
add_action('add_meta_boxes', function () {
    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== 'collection_page') {
        return;
    }

    add_meta_box(
        'ss_collection_lookbook',
        __('Lookbook images', 'stone-sparkle'),
        'ss_render_collection_lookbook_meta_box',
        'collection_page',
        'normal',
        'default'
    );
});

/**
 * Get lookbook images from post meta (decoded JSON).
 *
 * @param int $post_id Post ID.
 * @param string $key Meta key (before or after).
 * @return array<int, array{url: string, alt: string}>
 */
function ss_get_collection_lookbook($post_id, $key) {
    $raw = get_post_meta($post_id, $key, true);
    if (!is_string($raw)) {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }
    $out = [];
    foreach ($decoded as $row) {
        if (!is_array($row)) {
            continue;
        }
        $url = isset($row['url']) ? (string) $row['url'] : '';
        $alt = isset($row['alt']) ? (string) $row['alt'] : '';
        $out[] = ['url' => $url, 'alt' => $alt];
    }
    return $out;
}

/**
 * Render one lookbook image slot (for meta box list or template).
 *
 * @param string $list_key 'before' or 'after'.
 * @param int|string $index Slot index.
 * @param string $url Image URL.
 * @param string $alt Alt text.
 * @param bool $inner_only If true, output only inner content for JS template.
 */
function ss_render_collection_lookbook_slot($list_key, $index, $url, $alt, $inner_only = false) {
    $name_prefix = 'ss_collection_lookbook_' . $list_key . '[' . $index . ']';
    $inner = function () use ($name_prefix, $url, $alt) {
        ?>
        <span class="ss-collection-lookbook-handle" aria-label="<?php esc_attr_e('Drag to reorder', 'stone-sparkle'); ?>">⋮⋮</span>
        <input type="hidden" name="<?php echo esc_attr($name_prefix); ?>[url]" class="ss-collection-lookbook-input-url" value="<?php echo esc_attr($url); ?>">
        <input type="hidden" name="<?php echo esc_attr($name_prefix); ?>[alt]" class="ss-collection-lookbook-input-alt" value="<?php echo esc_attr($alt); ?>">
        <div class="ss-collection-lookbook-preview">
            <?php if ($url !== '') : ?>
                <img src="<?php echo esc_url($url); ?>" alt="" style="max-width:80px;height:auto;">
            <?php endif; ?>
        </div>
        <button type="button" class="button ss-collection-lookbook-upload"><?php esc_html_e('Select image', 'stone-sparkle'); ?></button>
        <button type="button" class="button ss-collection-lookbook-remove" <?php echo $url === '' ? ' style="display:none;"' : ''; ?>><?php esc_html_e('Remove', 'stone-sparkle'); ?></button>
        <?php
    };
    if ($inner_only) {
        $inner();
        return;
    }
    ?>
    <li class="ss-collection-lookbook-slot" data-index="<?php echo esc_attr((string) $index); ?>">
        <?php $inner(); ?>
    </li>
    <?php
}

/**
 * Render Lookbook meta box (before + after lists).
 */
function ss_render_collection_lookbook_meta_box($post) {
    wp_nonce_field(SS_COLLECTION_LOOKBOOK_NONCE_ACTION, 'ss_collection_lookbook_nonce');

    $before = ss_get_collection_lookbook($post->ID, SS_COLLECTION_LOOKBOOK_BEFORE_KEY);
    $after  = ss_get_collection_lookbook($post->ID, SS_COLLECTION_LOOKBOOK_AFTER_KEY);

    echo '<p class="description" style="margin-bottom:14px;">';
    esc_html_e('Add images to show above and below the product grid. Use "Add image" to add slots; drag to reorder. Save/Update to apply.', 'stone-sparkle');
    echo '</p>';

    foreach (['before' => __('Lookbook before products', 'stone-sparkle'), 'after' => __('Lookbook after products', 'stone-sparkle')] as $key => $label) {
        $items = $key === 'before' ? $before : $after;
        $list_id = 'ss-collection-lookbook-' . $key . '-list';
        ?>
        <div class="ss-collection-lookbook-block">
            <p><strong><?php echo esc_html($label); ?></strong></p>
            <ul class="ss-collection-lookbook-list" id="<?php echo esc_attr($list_id); ?>">
                <?php
                foreach ($items as $i => $item) {
                    $u = isset($item['url']) ? $item['url'] : '';
                    $a = isset($item['alt']) ? $item['alt'] : '';
                    ss_render_collection_lookbook_slot($key, $i, $u, $a, false);
                }
                ?>
            </ul>
            <p>
                <button type="button" class="button ss-collection-lookbook-add" data-list="<?php echo esc_attr($key); ?>"><?php esc_html_e('Add image', 'stone-sparkle'); ?> +</button>
            </p>
        </div>
        <?php
    }

    echo '<template id="ss-collection-lookbook-slot-tpl-before">';
    ss_render_collection_lookbook_slot('before', '{{INDEX}}', '', '', true);
    echo '</template>';
    echo '<template id="ss-collection-lookbook-slot-tpl-after">';
    ss_render_collection_lookbook_slot('after', '{{INDEX}}', '', '', true);
    echo '</template>';
}

add_action('save_post_collection_page', function ($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!isset($_POST['ss_collection_lookbook_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ss_collection_lookbook_nonce'])), SS_COLLECTION_LOOKBOOK_NONCE_ACTION)) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    foreach (['before' => SS_COLLECTION_LOOKBOOK_BEFORE_KEY, 'after' => SS_COLLECTION_LOOKBOOK_AFTER_KEY] as $key => $meta_key) {
        $raw = isset($_POST['ss_collection_lookbook_' . $key]) && is_array($_POST['ss_collection_lookbook_' . $key])
            ? $_POST['ss_collection_lookbook_' . $key]
            : [];
        $out = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $url = isset($row['url']) ? esc_url_raw(sanitize_text_field(wp_unslash($row['url']))) : '';
            $alt = isset($row['alt']) ? sanitize_text_field(wp_unslash($row['alt'])) : '';
            $out[] = ['url' => $url, 'alt' => $alt];
        }
        $json = wp_json_encode($out);
        if ($json !== false) {
            update_post_meta($post_id, $meta_key, $json);
        }
    }
}, 20, 1);

// -----------------------------------------------------------------------------
// Admin script and styles for lookbook meta box
// -----------------------------------------------------------------------------
add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'post.php' && $hook !== 'post-new.php') {
        return;
    }
    $post_id = 0;
    if (isset($_GET['post'])) {
        $post_id = (int) $_GET['post'];
    }
    if ($post_id <= 0 && isset($_POST['post_ID'])) {
        $post_id = (int) $_POST['post_ID'];
    }
    $post = $post_id ? get_post($post_id) : null;
    if (!$post || $post->post_type !== 'collection_page') {
        return;
    }

    wp_enqueue_media();
    wp_enqueue_script(
        'ss-collection-page-admin',
        get_template_directory_uri() . '/assets/js/collection-page-admin.js',
        ['jquery', 'jquery-ui-sortable'],
        defined('SS_THEME_VERSION') ? SS_THEME_VERSION : '1.0',
        true
    );

    wp_add_inline_style('wp-admin', '
        .ss-collection-lookbook-block { margin-bottom: 20px; }
        .ss-collection-lookbook-list { list-style:none; margin:8px 0; padding:0; }
        .ss-collection-lookbook-slot { display:flex; align-items:center; gap:8px; margin-bottom:8px; padding:8px; background:#fff; border:1px solid #c3c4c7; border-radius:4px; }
        .ss-collection-lookbook-handle { cursor:move; color:#787c82; user-select:none; }
        .ss-collection-lookbook-preview img { border:1px solid #c3c4c7; border-radius:4px; }
    ');
});
