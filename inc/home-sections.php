<?php
/**
 * Dynamic Homepage Sections (ACF Free–compatible)
 *
 * Provides a meta box on the Homepage edit screen to add/remove/reorder sections.
 * Data stored as JSON in post meta `ss_home_sections`. Supports one-time migration
 * from legacy ACF fields (section_1…section_7, hide_section_1…7).
 *
 * Usage: Use "Add Section" to add a row. Set image (optional), button text, and link.
 * Use the handles to reorder. Save/Update the page to apply changes.
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SS_HOME_SECTIONS_META_KEY', 'ss_home_sections');
define('SS_HOME_SECTIONS_NONCE_ACTION', 'ss_home_sections_save');

/**
 * Check if the given post is the homepage (by slug or template).
 *
 * @param WP_Post|int|null $post Post object or ID.
 * @return bool
 */
function ss_is_home_page_post($post) {
    $p = is_numeric($post) ? get_post((int) $post) : $post;
    if (!$p || $p->post_type !== 'page') {
        return false;
    }
    if ($p->post_name === 'home-page') {
        return true;
    }
    if (get_page_template_slug($p->ID) === 'page-home-page.php') {
        return true;
    }
    return false;
}

/**
 * Get homepage sections for display. Runs migration from legacy ACF fields if needed.
 *
 * @param int $post_id Page ID (typically the homepage).
 * @return array<int, array{img: string, cta: string, link: string, target: string}> Same shape as template expects.
 */
function ss_get_home_sections($post_id) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return [];
    }

    $raw = get_post_meta($post_id, SS_HOME_SECTIONS_META_KEY, true);
    $sections = [];

    if (is_string($raw)) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            foreach ($decoded as $row) {
                $sections[] = ss_normalize_section_row($row);
            }
        }
    }

    // One-time migration from legacy ACF fields.
    if (empty($sections) && ss_is_home_page_post($post_id)) {
        $sections = ss_migrate_legacy_home_sections($post_id);
    }

    return $sections;
}

/**
 * Normalize a single section row from stored JSON to template shape.
 *
 * @param array<string, mixed> $row Stored row (images or image, button_text, button_url, button_target, show_button).
 * @return array{img: string, images: array<int, array{url: string, alt: string}>, cta: string, link: string, target: string, show_button: bool}
 */
function ss_normalize_section_row(array $row) {
    $images = [];
    if (isset($row['images']) && is_array($row['images'])) {
        foreach ($row['images'] as $entry) {
            $url = isset($entry['url']) ? (string) $entry['url'] : '';
            $alt = isset($entry['alt']) ? (string) $entry['alt'] : '';
            if ($url !== '') {
                $images[] = ['url' => $url, 'alt' => $alt];
            }
        }
    }
    if (empty($images) && isset($row['image']) && (string) $row['image'] !== '') {
        $images[] = ['url' => (string) $row['image'], 'alt' => ''];
    }
    $img = !empty($images) ? $images[0]['url'] : '';

    $cta = isset($row['button_text']) ? (string) $row['button_text'] : '';
    $url = isset($row['button_url']) ? (string) $row['button_url'] : '';
    $target = isset($row['button_target']) ? (string) $row['button_target'] : '';
    $show_button = true;
    if (isset($row['show_button'])) {
        $show_button = (bool) $row['show_button'] || $row['show_button'] === '1';
    }

    if ($cta === '') {
        $cta = __('Shop', 'stone-sparkle');
    }
    if ($url === '' && function_exists('wc_get_page_permalink')) {
        $url = wc_get_page_permalink('shop') ?: home_url('/shop/');
    }
    if ($url === '') {
        $url = home_url('/shop/');
    }
    if ($target !== '_blank') {
        $target = '_self';
    }

    return [
        'img'         => $img,
        'images'      => $images,
        'cta'         => $cta,
        'link'        => $url,
        'target'      => $target,
        'show_button' => $show_button,
    ];
}

/**
 * Migrate legacy ACF fields (section_1…7, hide_section_1…7) to ss_home_sections and save.
 *
 * @param int $post_id Homepage post ID.
 * @return array<int, array{img: string, cta: string, link: string, target: string}>
 */
function ss_migrate_legacy_home_sections($post_id) {
    $post_id = (int) $post_id;
    $shop = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');
    if (!$shop) {
        $shop = home_url('/shop/');
    }

    $out = [];
    for ($i = 1; $i <= 7; $i++) {
        $hide = function_exists('get_field') ? (bool) get_field('hide_section_' . $i, $post_id) : false;
        if ($hide) {
            continue;
        }

        $img = function_exists('get_field') ? get_field('section_' . $i . '_image', $post_id) : '';
        if (is_array($img) && !empty($img['url'])) {
            $img = $img['url'];
        }
        $img = is_string($img) ? $img : '';

        $btn_text = function_exists('get_field') ? (string) get_field('section_' . $i . '_button_text', $post_id) : '';
        if ($btn_text === '') {
            $btn_text = __('Shop', 'stone-sparkle');
        }

        $btn_link = function_exists('get_field') ? get_field('section_' . $i . '_button_link', $post_id) : null;
        $url = $shop;
        $target = '_self';
        if (is_array($btn_link) && !empty($btn_link['url'])) {
            $url = $btn_link['url'];
            $target = !empty($btn_link['target']) ? $btn_link['target'] : '_self';
        }

        $out[] = [
            'images'        => $img !== '' ? [['url' => $img, 'alt' => '']] : [],
            'button_text'   => $btn_text,
            'button_url'    => $url,
            'button_target' => $target,
            'show_button'   => 1,
        ];
    }

    $json = wp_json_encode($out);
    if ($json !== false) {
        update_post_meta($post_id, SS_HOME_SECTIONS_META_KEY, $json);
    }

    return array_map('ss_normalize_section_row', $out);
}

/**
 * Register meta box only when editing the homepage.
 */
add_action('add_meta_boxes', function () {
    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== 'page') {
        return;
    }
    $post = get_post();
    if (!$post || !ss_is_home_page_post($post)) {
        return;
    }

    add_meta_box(
        'ss_home_sections',
        __('Homepage Sections', 'stone-sparkle'),
        'ss_render_home_sections_meta_box',
        'page',
        'normal',
        'high',
        ['post' => $post]
    );
});

/**
 * Render the Homepage Sections meta box.
 *
 * @param WP_Post $post Current post.
 * @param array<string, mixed> $args Meta box args; args['args']['post'] is the post.
 */
function ss_render_home_sections_meta_box($post, $args) {
    $post = isset($args['args']['post']) ? $args['args']['post'] : $post;
    if (!$post || !ss_is_home_page_post($post)) {
        return;
    }

    wp_nonce_field(SS_HOME_SECTIONS_NONCE_ACTION, 'ss_home_sections_nonce');

    $sections = ss_get_home_sections($post->ID);

    echo '<p class="description" style="margin-bottom:12px;">';
    esc_html_e('Use <strong>Add Section</strong> to add a row. Under <strong>Images</strong> add one or more images; order here is the slide order. With multiple images the section becomes a horizontal slider. Drag to reorder sections and images. Save/Update to apply.', 'stone-sparkle');
    echo '</p>';

    echo '<div id="ss-home-sections-container">';
    echo '<ul id="ss-home-sections-list" class="ss-home-sections-list">';

    foreach ($sections as $index => $section) {
        $images = isset($section['images']) && is_array($section['images']) ? $section['images'] : [];
        $cta = isset($section['cta']) ? $section['cta'] : '';
        $link = isset($section['link']) ? $section['link'] : '';
        $target = isset($section['target']) ? $section['target'] : '_self';
        $show_btn = isset($section['show_button']) ? (bool) $section['show_button'] : true;
        ss_render_one_section_row($index, $images, $cta, $link, $target, $show_btn);
    }

    echo '</ul>';
    echo '<p><button type="button" class="button" id="ss-home-sections-add">' . esc_html__('Add Section', 'stone-sparkle') . ' (+)</button></p>';
    echo '</div>';

    echo '<template id="ss-home-section-row-tpl">';
    ss_render_one_section_row('{{INDEX}}', [], '', '', '_self', true, true);
    echo '</template>';

    echo '<template id="ss-home-section-image-slot-tpl">';
    ss_render_one_image_slot('{{SECTION_INDEX}}', '{{IMAGE_INDEX}}', '', '', true);
    echo '</template>';
}

/**
 * Output a single image slot (one item in the section's images list).
 *
 * @param string|int $section_index Section index.
 * @param string|int $image_index Image index.
 * @param string $url Image URL.
 * @param string $alt Alt text.
 * @param bool $inner_only If true, output only inner content (for JS template clone into <li>).
 */
function ss_render_one_image_slot($section_index, $image_index, $url, $alt, $inner_only = false) {
    $prefix = 'ss_home_sections[' . $section_index . '][images][' . $image_index . ']';
    $inner = function () use ($prefix, $url, $alt) {
        ?>
        <span class="ss-home-section-image-handle" aria-label="<?php esc_attr_e('Drag to reorder image', 'stone-sparkle'); ?>">⋮⋮</span>
        <input type="hidden" name="<?php echo esc_attr($prefix); ?>[url]" class="ss-home-section-input-image-url" value="<?php echo esc_attr($url); ?>" />
        <input type="hidden" name="<?php echo esc_attr($prefix); ?>[alt]" class="ss-home-section-input-image-alt" value="<?php echo esc_attr($alt); ?>" />
        <div class="ss-home-section-image-preview">
            <?php if ($url !== '') : ?>
                <img src="<?php echo esc_url($url); ?>" alt="" style="max-width:80px;height:auto;" />
            <?php endif; ?>
        </div>
        <button type="button" class="button ss-home-section-upload"><?php esc_html_e('Select image', 'stone-sparkle'); ?></button>
        <button type="button" class="button ss-home-section-remove-image" <?php echo $url === '' ? ' style="display:none;"' : ''; ?>><?php esc_html_e('Remove', 'stone-sparkle'); ?></button>
        <?php
    };
    if ($inner_only) {
        $inner();
        return;
    }
    ?>
    <li class="ss-home-section-image-slot" data-image-index="<?php echo esc_attr((string) $image_index); ?>">
        <?php $inner(); ?>
    </li>
    <?php
}

/**
 * Output a single section row (for existing data or template clone).
 *
 * @param string|int $index Row index or placeholder.
 * @param array<int, array{url: string, alt: string}> $images Array of image url/alt.
 * @param string $button_text Button label.
 * @param string $button_url Button URL.
 * @param string $button_target _self or _blank.
 * @param bool $show_button Whether to show the section button on the front end.
 * @param bool $inner_only If true, output only the inner content (no <li> wrapper). Use for the <template> so JS can inject into a single <li>.
 */
function ss_render_one_section_row($index, $images, $button_text, $button_url, $button_target, $show_button = true, $inner_only = false) {
    $name_prefix = 'ss_home_sections[' . $index . ']';
    $display_num = is_numeric($index) ? (int) $index + 1 : 1;
    $inner = function () use ($name_prefix, $index, $images, $button_text, $button_url, $button_target, $show_button, $display_num) {
        ?>
        <span class="ss-home-section-handle" aria-label="<?php esc_attr_e('Drag to reorder', 'stone-sparkle'); ?>">⋮⋮</span>
        <div class="ss-home-section-header">
            <button type="button" class="ss-home-section-toggle" aria-expanded="false" aria-label="<?php esc_attr_e('Expand section', 'stone-sparkle'); ?>">
                <span class="ss-home-section-toggle-icon" aria-hidden="true">&#9654;</span>
                <?php echo esc_html(__('Section', 'stone-sparkle') . ' '); ?><span class="ss-home-section-number"><?php echo (int) $display_num; ?></span>
            </button>
        </div>
        <div class="ss-home-section-fields">
            <div class="ss-home-section-field ss-home-section-images">
                <label><?php esc_html_e('Images (slider on front)', 'stone-sparkle'); ?></label>
                <p class="description"><?php esc_html_e('Add one or more images. Order here is the slide order. With multiple images, the section becomes a horizontal slider.', 'stone-sparkle'); ?></p>
                <ul class="ss-home-section-images-list">
                <?php
                if (!empty($images)) {
                    foreach ($images as $j => $img) {
                        $u = isset($img['url']) ? $img['url'] : '';
                        $a = isset($img['alt']) ? $img['alt'] : '';
                        ss_render_one_image_slot($index, $j, $u, $a, false);
                    }
                }
                ?>
                </ul>
                <p class="ss-home-section-add-image-wrap">
                    <button type="button" class="button button-secondary ss-home-section-add-image" aria-label="<?php esc_attr_e('Add another image to this section', 'stone-sparkle'); ?>"><?php esc_html_e('Add image', 'stone-sparkle'); ?> +</button>
                    <span class="description"><?php esc_html_e('Add one or more images; order is the slide order.', 'stone-sparkle'); ?></span>
                </p>
            </div>
            <div class="ss-home-section-field ss-home-section-show-button">
                <label>
                    <input type="checkbox" name="<?php echo esc_attr($name_prefix); ?>[show_button]" value="1" <?php checked($show_button); ?> />
                    <?php esc_html_e('Show button', 'stone-sparkle'); ?>
                </label>
                <p class="description"><?php esc_html_e('Uncheck to hide the button for this section on the front end.', 'stone-sparkle'); ?></p>
            </div>
            <div class="ss-home-section-field">
                <label><?php esc_html_e('Button text', 'stone-sparkle'); ?></label>
                <input type="text" name="<?php echo esc_attr($name_prefix); ?>[button_text]" class="widefat ss-home-section-input-cta" value="<?php echo esc_attr($button_text); ?>" placeholder="<?php esc_attr_e('Shop', 'stone-sparkle'); ?>" />
            </div>
            <div class="ss-home-section-field">
                <label><?php esc_html_e('Button URL', 'stone-sparkle'); ?></label>
                <input type="url" name="<?php echo esc_attr($name_prefix); ?>[button_url]" class="widefat ss-home-section-input-url" value="<?php echo esc_attr($button_url); ?>" />
            </div>
            <div class="ss-home-section-field">
                <label><?php esc_html_e('Open in new tab', 'stone-sparkle'); ?></label>
                <select name="<?php echo esc_attr($name_prefix); ?>[button_target]">
                    <option value="_self" <?php selected($button_target, '_self'); ?>><?php esc_html_e('No', 'stone-sparkle'); ?></option>
                    <option value="_blank" <?php selected($button_target, '_blank'); ?>><?php esc_html_e('Yes', 'stone-sparkle'); ?></option>
                </select>
            </div>
        </div>
        <button type="button" class="button ss-home-section-remove" aria-label="<?php esc_attr_e('Remove section', 'stone-sparkle'); ?>"><?php esc_html_e('Remove', 'stone-sparkle'); ?></button>
        <?php
    };

    if ($inner_only) {
        $inner();
        return;
    }
    ?>
    <li class="ss-home-section-row ss-home-section-row--collapsed" data-index="<?php echo esc_attr((string) $index); ?>">
    <?php $inner(); ?>
    </li>
    <?php
}

/**
 * Save homepage sections on post save.
 */
add_action('save_post_page', function ($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!isset($_POST['ss_home_sections_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ss_home_sections_nonce'])), SS_HOME_SECTIONS_NONCE_ACTION)) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    if (!ss_is_home_page_post($post_id)) {
        return;
    }

    $raw = isset($_POST['ss_home_sections']) && is_array($_POST['ss_home_sections']) ? $_POST['ss_home_sections'] : [];
    $sections = [];

    foreach ($raw as $row) {
        if (!is_array($row)) {
            continue;
        }
        $images = [];
        if (isset($row['images']) && is_array($row['images'])) {
            foreach ($row['images'] as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $u = isset($entry['url']) ? esc_url_raw(sanitize_text_field(wp_unslash($entry['url']))) : '';
                $a = isset($entry['alt']) ? sanitize_text_field(wp_unslash($entry['alt'])) : '';
                $images[] = ['url' => $u, 'alt' => $a];
            }
        }
        $button_text = isset($row['button_text']) ? sanitize_text_field(wp_unslash($row['button_text'])) : '';
        $button_url = isset($row['button_url']) ? esc_url_raw(sanitize_text_field(wp_unslash($row['button_url']))) : '';
        $button_target = isset($row['button_target']) ? sanitize_text_field(wp_unslash($row['button_target'])) : '_self';
        if ($button_target !== '_blank') {
            $button_target = '_self';
        }
        $show_button = isset($row['show_button']) && ( $row['show_button'] === '1' || $row['show_button'] === true ) ? 1 : 0;
        $sections[] = [
            'images'        => $images,
            'button_text'   => $button_text,
            'button_url'    => $button_url,
            'button_target' => $button_target,
            'show_button'   => $show_button,
        ];
    }

    $json = wp_json_encode($sections);
    if ($json !== false) {
        update_post_meta($post_id, SS_HOME_SECTIONS_META_KEY, $json);
    }
}, 10, 1);

/**
 * Enqueue admin script and styles only on homepage edit screen.
 */
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
    if ($post_id <= 0 || !ss_is_home_page_post($post_id)) {
        return;
    }

    wp_enqueue_media();
    wp_enqueue_script(
        'ss-home-sections-admin',
        get_template_directory_uri() . '/assets/js/home-sections-admin.js',
        ['jquery', 'jquery-ui-sortable'],
        SS_THEME_VERSION,
        true
    );

    wp_add_inline_style('wp-admin', '
        .ss-home-sections-list { list-style:none; margin:0; padding:0; }
        .ss-home-section-row { display:flex; align-items:flex-start; gap:12px; margin-bottom:16px; padding:12px; background:#f6f7f7; border:1px solid #c3c4c7; border-radius:4px; flex-wrap:wrap; }
        .ss-home-section-handle { cursor:move; color:#787c82; padding:4px 6px; user-select:none; }
        .ss-home-section-header { flex:1; min-width:0; }
        .ss-home-section-toggle { cursor:pointer; background:none; border:none; padding:4px 0; margin:0; font-size:14px; font-weight:600; color:#1d2327; text-align:left; display:flex; align-items:center; gap:6px; }
        .ss-home-section-toggle:hover { color:#2271b1; }
        .ss-home-section-toggle-icon { font-size:10px; line-height:1; transition:transform 0.2s ease; }
        .ss-home-section-row:not(.ss-home-section-row--collapsed) .ss-home-section-toggle-icon { transform:rotate(90deg); }
        .ss-home-section-row--collapsed .ss-home-section-fields { display:none !important; }
        .ss-home-section-fields { flex:1 1 100%; display:grid; gap:10px; min-width:0; }
        .ss-home-section-field label { display:block; font-weight:600; margin-bottom:4px; }
        .ss-home-section-images-list { list-style:none; margin:8px 0; padding:0; }
        .ss-home-section-image-slot { display:flex; align-items:center; gap:8px; margin-bottom:8px; padding:8px; background:#fff; border:1px solid #c3c4c7; border-radius:4px; }
        .ss-home-section-image-handle { cursor:move; color:#787c82; user-select:none; }
        .ss-home-section-image-preview { margin:0; }
        .ss-home-section-image-preview img { border:1px solid #c3c4c7; border-radius:4px; }
        .ss-home-section-add-image-wrap { margin:10px 0 0; padding:10px; background:#f0f0f1; border:1px dashed #c3c4c7; border-radius:4px; }
        .ss-home-section-add-image-wrap .ss-home-section-add-image { margin-right:8px; }
    ');
});

/**
 * Enqueue front-end slider script on homepage.
 */
add_action('wp_enqueue_scripts', function () {
    if (!is_page('home-page') && !is_front_page()) {
        return;
    }
    wp_enqueue_script(
        'ss-home-section-slider',
        get_template_directory_uri() . '/assets/js/home-section-slider.js',
        [],
        defined('SS_THEME_VERSION') ? SS_THEME_VERSION : '1.0',
        true
    );
}, 20);
