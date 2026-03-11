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
 * @param array<string, mixed> $row Stored row (image, button_text, button_url, button_target, show_button).
 * @return array{img: string, cta: string, link: string, target: string, show_button: bool}
 */
function ss_normalize_section_row(array $row) {
    $img = isset($row['image']) ? (string) $row['image'] : '';
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
            'image'         => $img,
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
    esc_html_e('Use <strong>Add Section</strong> to add a row. Set image (optional), button text, and link. Drag to reorder. Save/Update the page to apply changes.', 'stone-sparkle');
    echo '</p>';

    echo '<div id="ss-home-sections-container">';
    echo '<ul id="ss-home-sections-list" class="ss-home-sections-list">';

    foreach ($sections as $index => $section) {
        $img = isset($section['img']) ? $section['img'] : '';
        $cta = isset($section['cta']) ? $section['cta'] : '';
        $link = isset($section['link']) ? $section['link'] : '';
        $target = isset($section['target']) ? $section['target'] : '_self';
        $show_btn = isset($section['show_button']) ? (bool) $section['show_button'] : true;
        ss_render_one_section_row($index, $img, $cta, $link, $target, $show_btn);
    }

    echo '</ul>';
    echo '<p><button type="button" class="button" id="ss-home-sections-add">' . esc_html__('Add Section', 'stone-sparkle') . ' (+)</button></p>';
    echo '</div>';

    echo '<template id="ss-home-section-row-tpl">';
    ss_render_one_section_row('{{INDEX}}', '', '', '', '_self', true);
    echo '</template>';
}

/**
 * Output a single section row (for existing data or template clone).
 *
 * @param string|int $index Row index or placeholder.
 * @param string $image Image URL.
 * @param string $button_text Button label.
 * @param string $button_url Button URL.
 * @param string $button_target _self or _blank.
 * @param bool $show_button Whether to show the section button on the front end.
 */
function ss_render_one_section_row($index, $image, $button_text, $button_url, $button_target, $show_button = true) {
    $name_prefix = 'ss_home_sections[' . $index . ']';
    ?>
    <li class="ss-home-section-row" data-index="<?php echo esc_attr((string) $index); ?>">
        <span class="ss-home-section-handle" aria-label="<?php esc_attr_e('Drag to reorder', 'stone-sparkle'); ?>">⋮⋮</span>
        <div class="ss-home-section-fields">
            <div class="ss-home-section-field ss-home-section-image">
                <label><?php esc_html_e('Image', 'stone-sparkle'); ?></label>
                <input type="hidden" name="<?php echo esc_attr($name_prefix); ?>[image]" class="ss-home-section-input-image" value="<?php echo esc_attr($image); ?>" />
                <div class="ss-home-section-image-preview">
                    <?php if ($image !== '') : ?>
                        <img src="<?php echo esc_url($image); ?>" alt="" style="max-width:120px;height:auto;" />
                    <?php endif; ?>
                </div>
                <button type="button" class="button ss-home-section-upload"><?php esc_html_e('Select image', 'stone-sparkle'); ?></button>
                <button type="button" class="button ss-home-section-remove-image" <?php echo $image === '' ? ' style="display:none;"' : ''; ?>><?php esc_html_e('Remove image', 'stone-sparkle'); ?></button>
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
        $image = isset($row['image']) ? esc_url_raw(sanitize_text_field(wp_unslash($row['image']))) : '';
        $button_text = isset($row['button_text']) ? sanitize_text_field(wp_unslash($row['button_text'])) : '';
        $button_url = isset($row['button_url']) ? esc_url_raw(sanitize_text_field(wp_unslash($row['button_url']))) : '';
        $button_target = isset($row['button_target']) ? sanitize_text_field(wp_unslash($row['button_target'])) : '_self';
        if ($button_target !== '_blank') {
            $button_target = '_self';
        }
        $show_button = isset($row['show_button']) && ( $row['show_button'] === '1' || $row['show_button'] === true ) ? 1 : 0;
        $sections[] = [
            'image'         => $image,
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
        .ss-home-section-row { display:flex; align-items:flex-start; gap:12px; margin-bottom:16px; padding:12px; background:#f6f7f7; border:1px solid #c3c4c7; border-radius:4px; }
        .ss-home-section-handle { cursor:move; color:#787c82; padding:4px 6px; user-select:none; }
        .ss-home-section-fields { flex:1; display:grid; gap:10px; }
        .ss-home-section-field label { display:block; font-weight:600; margin-bottom:4px; }
        .ss-home-section-image-preview { margin-bottom:8px; }
        .ss-home-section-image-preview img { border:1px solid #c3c4c7; border-radius:4px; }
    ');
});
