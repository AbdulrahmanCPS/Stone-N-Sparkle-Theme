<?php
/**
 * Private View request flow for WooCommerce single product pages.
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SS_PRIVATE_VIEW_CPT', 'private_view_request');
define('SS_PRIVATE_VIEW_STATUS_META_KEY', 'ss_pvr_status');

function ss_private_view_statuses() {
    return [
        'new'       => __('New', 'stone-sparkle'),
        'contacted' => __('Contacted', 'stone-sparkle'),
        'scheduled' => __('Scheduled', 'stone-sparkle'),
        'closed'    => __('Closed', 'stone-sparkle'),
    ];
}

/**
 * @param string $key
 * @return string
 */
function ss_private_view_label_contact_method($key) {
    $map = [
        'email'    => __('Email', 'stone-sparkle'),
        'phone'    => __('Phone', 'stone-sparkle'),
        'whatsapp' => __('WhatsApp', 'stone-sparkle'),
    ];
    $key = sanitize_key($key);
    return isset($map[$key]) ? $map[$key] : $key;
}

/**
 * @param string $key
 * @return string
 */
function ss_private_view_label_appointment_type($key) {
    $map = [
        'in-store' => __('In-store', 'stone-sparkle'),
        'virtual'  => __('Virtual', 'stone-sparkle'),
    ];
    $key = sanitize_key($key);
    return isset($map[$key]) ? $map[$key] : $key;
}

add_action('init', function () {
    register_post_type(SS_PRIVATE_VIEW_CPT, [
        'labels' => [
            'name'          => __('Private View Requests', 'stone-sparkle'),
            'singular_name' => __('Private View Request', 'stone-sparkle'),
            'menu_name'     => __('Private View Requests', 'stone-sparkle'),
        ],
        'public'              => false,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'publicly_queryable'  => false,
        'exclude_from_search' => true,
        'show_in_rest'        => false,
        'menu_position'       => 58,
        'menu_icon'           => 'dashicons-calendar-alt',
        'supports'            => ['title'],
        'capability_type'     => 'post',
        'map_meta_cap'        => true,
    ]);
});

function ss_private_view_current_product() {
    if (!function_exists('wc_get_product')) {
        return null;
    }

    global $product;
    if ($product && is_a($product, 'WC_Product')) {
        return $product;
    }

    $id = get_the_ID();
    if (!$id) {
        return null;
    }

    $resolved = wc_get_product($id);
    return ($resolved && is_a($resolved, 'WC_Product')) ? $resolved : null;
}

add_action('woocommerce_after_add_to_cart_button', function () {
    if (!function_exists('is_product') || !is_product()) {
        return;
    }

    $product = ss_private_view_current_product();
    if (!$product) {
        return;
    }
    ?>
    <div class="ss-pdp-secondary" role="group" aria-label="<?php echo esc_attr__('Product actions', 'stone-sparkle'); ?>">
        <button type="button" class="ss-pdp-btn ss-private-view-trigger" data-ss-private-view-open>
            <?php echo esc_html__('Request Private View', 'stone-sparkle'); ?>
        </button>
    </div>
    <?php
}, 25);

function ss_private_view_feedback_data() {
    $result = isset($_GET['ss_pvr']) ? sanitize_key(wp_unslash((string) $_GET['ss_pvr'])) : '';
    $token  = isset($_GET['ss_pvr_token']) ? sanitize_key(wp_unslash((string) $_GET['ss_pvr_token'])) : '';

    $feedback = [
        'result'  => $result,
        'messages'=> [],
        'open'    => false,
    ];

    if ($result === 'success') {
        $feedback['open'] = true;
        return $feedback;
    }

    if ($result === 'error' && $token !== '') {
        $errors = get_transient('ss_pvr_errors_' . $token);
        if (is_array($errors) && !empty($errors)) {
            $feedback['messages'] = array_values(array_filter(array_map('sanitize_text_field', $errors)));
            $feedback['open'] = true;
        }
        delete_transient('ss_pvr_errors_' . $token);
    }

    return $feedback;
}

add_action('wp_footer', function () {
    if (!function_exists('is_product') || !is_product()) {
        return;
    }

    $product = ss_private_view_current_product();
    if (!$product) {
        return;
    }

    $feedback = ss_private_view_feedback_data();

    $product_id   = (int) $product->get_id();
    $product_name = $product->get_name();
    $product_sku  = (string) $product->get_sku();
    $product_url  = get_permalink($product_id);

    $ref_bits = [$product_name];
    if ($product_sku !== '') {
        $ref_bits[] = sprintf(__('SKU: %s', 'stone-sparkle'), $product_sku);
    }
    if ($product_url) {
        $ref_bits[] = $product_url;
    }
    $product_reference = implode(' | ', $ref_bits);
    ?>
    <div class="ss-popup ss-private-view-modal<?php echo $feedback['open'] ? ' is-open' : ''; ?>" id="ssPrivateViewModal" aria-hidden="<?php echo $feedback['open'] ? 'false' : 'true'; ?>" data-auto-open="<?php echo $feedback['open'] ? '1' : '0'; ?>">
        <div class="ss-popup__backdrop" data-ss-private-view-close tabindex="-1"></div>
        <div class="ss-popup__dialog ss-private-view-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="ssPrivateViewTitle">
            <button class="ss-popup__close" type="button" aria-label="<?php echo esc_attr__('Close', 'stone-sparkle'); ?>" data-ss-private-view-close>
                <span aria-hidden="true">&times;</span>
            </button>

            <div class="ss-popup__content">
                <h2 class="ss-popup__title" id="ssPrivateViewTitle"><?php echo esc_html__('Request Private View', 'stone-sparkle'); ?></h2>
                <div class="ss-popup__body">
                    <?php echo esc_html__('Book an in-store or virtual consultation with our private client team.', 'stone-sparkle'); ?>
                </div>

                <?php if ($feedback['result'] === 'success') : ?>
                    <p class="ss-private-view-feedback ss-private-view-feedback--success" role="status">
                        <?php echo esc_html__('Your request has been received. Our team will contact you shortly.', 'stone-sparkle'); ?>
                    </p>
                <?php elseif ($feedback['result'] === 'error' && !empty($feedback['messages'])) : ?>
                    <div class="ss-private-view-feedback ss-private-view-feedback--error" role="alert">
                        <p><?php echo esc_html__('Please check the highlighted details and try again.', 'stone-sparkle'); ?></p>
                        <ul>
                            <?php foreach ($feedback['messages'] as $message) : ?>
                                <li><?php echo esc_html($message); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form class="ss-private-view-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" novalidate>
                    <input type="hidden" name="action" value="ss_submit_private_view_request" />
                    <input type="hidden" name="product_id" value="<?php echo (int) $product_id; ?>" />
                    <input type="text" name="company" class="ss-private-view-form__hp" tabindex="-1" autocomplete="off" aria-hidden="true" />
                    <?php wp_nonce_field('ss_submit_private_view_request', 'ss_private_view_nonce'); ?>

                    <div class="ss-private-view-form__grid">
                        <div class="ss-popup__field">
                            <label for="ss_private_view_name"><?php echo esc_html__('Full name', 'stone-sparkle'); ?> *</label>
                            <input id="ss_private_view_name" type="text" name="full_name" required autocomplete="name" />
                        </div>

                        <div class="ss-popup__field">
                            <label for="ss_private_view_email"><?php echo esc_html__('Email', 'stone-sparkle'); ?> *</label>
                            <input id="ss_private_view_email" type="email" name="email" required autocomplete="email" />
                        </div>

                        <div class="ss-popup__field">
                            <label for="ss_private_view_phone"><?php echo esc_html__('Phone', 'stone-sparkle'); ?></label>
                            <input id="ss_private_view_phone" type="tel" name="phone" autocomplete="tel" />
                        </div>

                        <div class="ss-popup__field">
                            <label for="ss_private_view_contact_method"><?php echo esc_html__('Preferred contact method', 'stone-sparkle'); ?></label>
                            <select id="ss_private_view_contact_method" name="contact_method">
                                <option value="email"><?php echo esc_html__('Email', 'stone-sparkle'); ?></option>
                                <option value="phone"><?php echo esc_html__('Phone', 'stone-sparkle'); ?></option>
                                <option value="whatsapp"><?php echo esc_html__('WhatsApp', 'stone-sparkle'); ?></option>
                            </select>
                        </div>

                        <div class="ss-popup__field">
                            <label for="ss_private_view_appointment_type"><?php echo esc_html__('Appointment type', 'stone-sparkle'); ?> *</label>
                            <select id="ss_private_view_appointment_type" name="appointment_type" required>
                                <option value="in-store"><?php echo esc_html__('In-store', 'stone-sparkle'); ?></option>
                                <option value="virtual"><?php echo esc_html__('Virtual', 'stone-sparkle'); ?></option>
                            </select>
                        </div>

                        <div class="ss-popup__field">
                            <label for="ss_private_view_date_time"><?php echo esc_html__('Preferred date and time', 'stone-sparkle'); ?> *</label>
                            <input id="ss_private_view_date_time" type="datetime-local" name="preferred_datetime" required />
                        </div>

                        <div class="ss-popup__field ss-popup__field--full">
                            <label for="ss_private_view_product_reference"><?php echo esc_html__('Product reference', 'stone-sparkle'); ?></label>
                            <input id="ss_private_view_product_reference" type="text" name="product_reference" readonly value="<?php echo esc_attr($product_reference); ?>" />
                        </div>

                        <div class="ss-popup__field ss-popup__field--full">
                            <label for="ss_private_view_message"><?php echo esc_html__('Message / notes', 'stone-sparkle'); ?></label>
                            <textarea id="ss_private_view_message" name="message" rows="4"></textarea>
                        </div>
                    </div>

                    <label class="ss-private-view-form__consent">
                        <input type="checkbox" name="consent" value="1" required />
                        <span><?php echo esc_html__('I agree to be contacted regarding this private view request.', 'stone-sparkle'); ?> *</span>
                    </label>

                    <div class="ss-popup__actions">
                        <button class="ss-popup__submit" type="submit"><?php echo esc_html__('Send Request', 'stone-sparkle'); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php
});

function ss_private_view_format_datetime_for_human($raw) {
    if (!is_string($raw) || $raw === '') {
        return '';
    }
    $timestamp = strtotime($raw);
    if (!$timestamp) {
        return $raw;
    }
    return wp_date(get_option('date_format') . ' ' . get_option('time_format'), $timestamp);
}

function ss_private_view_error_redirect($product_url, $errors) {
    $token = wp_generate_password(12, false, false);
    set_transient('ss_pvr_errors_' . $token, $errors, 5 * MINUTE_IN_SECONDS);

    $url = add_query_arg([
        'ss_pvr'       => 'error',
        'ss_pvr_token' => $token,
    ], $product_url);

    wp_safe_redirect($url);
    exit;
}

add_action('admin_post_nopriv_ss_submit_private_view_request', 'ss_handle_private_view_request_submission');
add_action('admin_post_ss_submit_private_view_request', 'ss_handle_private_view_request_submission');

function ss_handle_private_view_request_submission() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        wp_safe_redirect(home_url('/'));
        exit;
    }

    $nonce = isset($_POST['ss_private_view_nonce']) ? sanitize_text_field(wp_unslash((string) $_POST['ss_private_view_nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'ss_submit_private_view_request')) {
        wp_safe_redirect(home_url('/'));
        exit;
    }

    $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
    $product_url = $product_id ? get_permalink($product_id) : home_url('/');
    if (!$product_url) {
        $product_url = home_url('/');
    }

    if (!empty($_POST['company'])) {
        wp_safe_redirect($product_url);
        exit;
    }

    $full_name          = isset($_POST['full_name']) ? sanitize_text_field(wp_unslash((string) $_POST['full_name'])) : '';
    $email              = isset($_POST['email']) ? sanitize_email(wp_unslash((string) $_POST['email'])) : '';
    $phone              = isset($_POST['phone']) ? sanitize_text_field(wp_unslash((string) $_POST['phone'])) : '';
    $contact_method     = isset($_POST['contact_method']) ? sanitize_key(wp_unslash((string) $_POST['contact_method'])) : 'email';
    $appointment_type   = isset($_POST['appointment_type']) ? sanitize_key(wp_unslash((string) $_POST['appointment_type'])) : 'in-store';
    $preferred_datetime = isset($_POST['preferred_datetime']) ? sanitize_text_field(wp_unslash((string) $_POST['preferred_datetime'])) : '';
    $product_reference  = isset($_POST['product_reference']) ? sanitize_text_field(wp_unslash((string) $_POST['product_reference'])) : '';
    $message            = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash((string) $_POST['message'])) : '';
    $consent            = isset($_POST['consent']) ? (bool) absint($_POST['consent']) : false;

    $errors = [];
    if ($full_name === '') {
        $errors[] = __('Full name is required.', 'stone-sparkle');
    }
    if ($email === '' || !is_email($email)) {
        $errors[] = __('Please provide a valid email address.', 'stone-sparkle');
    }
    if ($preferred_datetime === '') {
        $errors[] = __('Preferred date and time is required.', 'stone-sparkle');
    }
    if (!$consent) {
        $errors[] = __('Consent is required to continue.', 'stone-sparkle');
    }
    if (!in_array($contact_method, ['email', 'phone', 'whatsapp'], true)) {
        $contact_method = 'email';
    }
    if (!in_array($appointment_type, ['in-store', 'virtual'], true)) {
        $appointment_type = 'in-store';
    }

    if (!empty($errors)) {
        ss_private_view_error_redirect($product_url, $errors);
    }

    $post_id = wp_insert_post([
        'post_type'   => SS_PRIVATE_VIEW_CPT,
        'post_status' => 'publish',
        'post_title'  => sprintf(
            /* translators: 1: customer name, 2: product title */
            __('%1$s - %2$s', 'stone-sparkle'),
            $full_name,
            $product_id ? get_the_title($product_id) : __('Private View Request', 'stone-sparkle')
        ),
    ], true);

    if (is_wp_error($post_id) || !$post_id) {
        ss_private_view_error_redirect($product_url, [__('We could not submit your request. Please try again.', 'stone-sparkle')]);
    }

    update_post_meta($post_id, SS_PRIVATE_VIEW_STATUS_META_KEY, 'new');
    update_post_meta($post_id, 'ss_pvr_full_name', $full_name);
    update_post_meta($post_id, 'ss_pvr_email', $email);
    update_post_meta($post_id, 'ss_pvr_phone', $phone);
    update_post_meta($post_id, 'ss_pvr_contact_method', $contact_method);
    update_post_meta($post_id, 'ss_pvr_appointment_type', $appointment_type);
    update_post_meta($post_id, 'ss_pvr_preferred_datetime', $preferred_datetime);
    update_post_meta($post_id, 'ss_pvr_product_id', $product_id);
    update_post_meta($post_id, 'ss_pvr_product_reference', $product_reference);
    update_post_meta($post_id, 'ss_pvr_message', $message);
    update_post_meta($post_id, 'ss_pvr_consent', $consent ? '1' : '0');

    $admin_email = get_option('admin_email');
    $product_title = $product_id ? get_the_title($product_id) : '';
    $subject = sprintf(
        /* translators: %s: product title */
        __('New Private View Request - %s', 'stone-sparkle'),
        $product_title ?: __('Product Inquiry', 'stone-sparkle')
    );
    $body_lines = [
        __('A new private view request has been submitted:', 'stone-sparkle'),
        '',
        __('Requester:', 'stone-sparkle') . ' ' . $full_name,
        __('Email:', 'stone-sparkle') . ' ' . $email,
        __('Phone:', 'stone-sparkle') . ' ' . ($phone !== '' ? $phone : __('Not provided', 'stone-sparkle')),
        __('Preferred contact method:', 'stone-sparkle') . ' ' . ucfirst(str_replace('-', ' ', $contact_method)),
        __('Appointment type:', 'stone-sparkle') . ' ' . ucfirst(str_replace('-', ' ', $appointment_type)),
        __('Preferred date and time:', 'stone-sparkle') . ' ' . ss_private_view_format_datetime_for_human($preferred_datetime),
        __('Product reference:', 'stone-sparkle') . ' ' . $product_reference,
        __('Notes:', 'stone-sparkle') . ' ' . ($message !== '' ? $message : __('None', 'stone-sparkle')),
        '',
        __('Admin record:', 'stone-sparkle') . ' ' . admin_url('post.php?post=' . (int) $post_id . '&action=edit'),
    ];
    wp_mail($admin_email, $subject, implode("\n", $body_lines));

    $success_url = add_query_arg('ss_pvr', 'success', $product_url);
    wp_safe_redirect($success_url);
    exit;
}

add_filter('manage_' . SS_PRIVATE_VIEW_CPT . '_posts_columns', function ($columns) {
    $columns = [
        'cb'          => isset($columns['cb']) ? $columns['cb'] : '',
        'title'       => __('Request', 'stone-sparkle'),
        'requester'   => __('Requester', 'stone-sparkle'),
        'product'     => __('Product', 'stone-sparkle'),
        'appointment' => __('Appointment', 'stone-sparkle'),
        'pref_dt'     => __('Preferred date/time', 'stone-sparkle'),
        'notes'       => __('Message / notes', 'stone-sparkle'),
        'status'      => __('Status', 'stone-sparkle'),
        'date'        => __('Submitted', 'stone-sparkle'),
    ];
    return $columns;
});

add_action('manage_' . SS_PRIVATE_VIEW_CPT . '_posts_custom_column', function ($column, $post_id) {
    if ($column === 'requester') {
        $name = (string) get_post_meta($post_id, 'ss_pvr_full_name', true);
        $email = (string) get_post_meta($post_id, 'ss_pvr_email', true);
        echo esc_html(trim($name));
        if ($email !== '') {
            echo '<br><a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>';
        }
        return;
    }

    if ($column === 'product') {
        $product_id = (int) get_post_meta($post_id, 'ss_pvr_product_id', true);
        if ($product_id > 0) {
            $title = get_the_title($product_id);
            $url = get_edit_post_link($product_id);
            if ($url) {
                echo '<a href="' . esc_url($url) . '">' . esc_html($title) . '</a>';
            } else {
                echo esc_html($title);
            }
        } else {
            echo esc_html((string) get_post_meta($post_id, 'ss_pvr_product_reference', true));
        }
        return;
    }

    if ($column === 'pref_dt') {
        echo esc_html(ss_private_view_format_datetime_for_human((string) get_post_meta($post_id, 'ss_pvr_preferred_datetime', true)));
        return;
    }

    if ($column === 'appointment') {
        $appt = (string) get_post_meta($post_id, 'ss_pvr_appointment_type', true);
        $contact = (string) get_post_meta($post_id, 'ss_pvr_contact_method', true);
        echo esc_html(ss_private_view_label_appointment_type($appt));
        if ($contact !== '') {
            echo '<br><span class="description">' . esc_html(sprintf(
                /* translators: %s: contact method label */
                __('Via %s', 'stone-sparkle'),
                ss_private_view_label_contact_method($contact)
            )) . '</span>';
        }
        return;
    }

    if ($column === 'notes') {
        $msg = (string) get_post_meta($post_id, 'ss_pvr_message', true);
        if ($msg === '') {
            echo '<span aria-hidden="true">—</span>';
            return;
        }
        echo esc_html(wp_html_excerpt($msg, 100, '…'));
        return;
    }

    if ($column === 'status') {
        $value = (string) get_post_meta($post_id, SS_PRIVATE_VIEW_STATUS_META_KEY, true);
        $statuses = ss_private_view_statuses();
        echo esc_html(isset($statuses[$value]) ? $statuses[$value] : $statuses['new']);
        return;
    }
}, 10, 2);

/**
 * Read-only summary of submitted fields (stored in post meta).
 *
 * @param WP_Post $post
 */
function ss_render_private_view_details_metabox($post) {
    $post_id = (int) $post->ID;
    $product_id = (int) get_post_meta($post_id, 'ss_pvr_product_id', true);

    $contact_raw = (string) get_post_meta($post_id, 'ss_pvr_contact_method', true);
    $appt_raw = (string) get_post_meta($post_id, 'ss_pvr_appointment_type', true);

    $rows = [
        [
            'key'   => 'full_name',
            'label' => __('Full name', 'stone-sparkle'),
            'value' => (string) get_post_meta($post_id, 'ss_pvr_full_name', true),
        ],
        [
            'key'   => 'email',
            'label' => __('Email', 'stone-sparkle'),
            'value' => (string) get_post_meta($post_id, 'ss_pvr_email', true),
        ],
        [
            'key'   => 'phone',
            'label' => __('Phone', 'stone-sparkle'),
            'value' => (string) get_post_meta($post_id, 'ss_pvr_phone', true),
        ],
        [
            'key'   => 'contact_method',
            'label' => __('Preferred contact method', 'stone-sparkle'),
            'value' => $contact_raw !== '' ? ss_private_view_label_contact_method($contact_raw) : '',
        ],
        [
            'key'   => 'appointment_type',
            'label' => __('Appointment type', 'stone-sparkle'),
            'value' => $appt_raw !== '' ? ss_private_view_label_appointment_type($appt_raw) : '',
        ],
        [
            'key'   => 'preferred_datetime',
            'label' => __('Preferred date and time', 'stone-sparkle'),
            'value' => ss_private_view_format_datetime_for_human((string) get_post_meta($post_id, 'ss_pvr_preferred_datetime', true)),
        ],
        [
            'key'   => 'product',
            'label' => __('Product', 'stone-sparkle'),
            'value' => '',
        ],
        [
            'key'   => 'product_reference',
            'label' => __('Product reference', 'stone-sparkle'),
            'value' => (string) get_post_meta($post_id, 'ss_pvr_product_reference', true),
        ],
        [
            'key'   => 'consent',
            'label' => __('Consent to contact', 'stone-sparkle'),
            'value' => ((string) get_post_meta($post_id, 'ss_pvr_consent', true) === '1')
                ? __('Yes', 'stone-sparkle')
                : __('No', 'stone-sparkle'),
        ],
    ];

    echo '<table class="widefat striped ss-pvr-details" style="max-width:720px;">';
    foreach ($rows as $row) {
        if ($row['key'] === 'product') {
            echo '<tr>';
            echo '<th scope="row" style="width:200px;">' . esc_html($row['label']) . '</th>';
            echo '<td>';
            if ($product_id > 0) {
                $title = get_the_title($product_id);
                $url = get_edit_post_link($product_id);
                if ($url) {
                    echo '<a href="' . esc_url($url) . '">' . esc_html($title) . '</a>';
                } else {
                    echo esc_html($title);
                }
                echo ' <span class="description">(ID ' . (int) $product_id . ')</span>';
            } else {
                echo '<span class="description">' . esc_html__('Not linked', 'stone-sparkle') . '</span>';
            }
            echo '</td></tr>';
            continue;
        }

        $val = $row['value'];
        echo '<tr><th scope="row">' . esc_html($row['label']) . '</th><td>';
        if ($row['key'] === 'email' && $val !== '' && is_email($val)) {
            echo '<a href="mailto:' . esc_attr($val) . '">' . esc_html($val) . '</a>';
        } else {
            echo $val !== '' ? esc_html($val) : '<span class="description">' . esc_html__('—', 'stone-sparkle') . '</span>';
        }
        echo '</td></tr>';
    }

    $message = (string) get_post_meta($post_id, 'ss_pvr_message', true);
    echo '<tr><th scope="row" style="vertical-align:top;">' . esc_html__('Message / notes', 'stone-sparkle') . '</th><td>';
    if ($message !== '') {
        echo '<div style="white-space:pre-wrap;max-height:240px;overflow:auto;">' . esc_html($message) . '</div>';
    } else {
        echo '<span class="description">' . esc_html__('None', 'stone-sparkle') . '</span>';
    }
    echo '</td></tr>';
    echo '</table>';
    echo '<p class="description">' . esc_html__(
        'These details were submitted from the storefront form and are read-only here.',
        'stone-sparkle'
    ) . '</p>';
}

add_action('add_meta_boxes', function () {
    add_meta_box(
        'ss_private_view_details_box',
        __('Request details', 'stone-sparkle'),
        'ss_render_private_view_details_metabox',
        SS_PRIVATE_VIEW_CPT,
        'normal',
        'high'
    );

    add_meta_box(
        'ss_private_view_status_box',
        __('Private View Status', 'stone-sparkle'),
        function ($post) {
            $current = (string) get_post_meta($post->ID, SS_PRIVATE_VIEW_STATUS_META_KEY, true);
            if ($current === '') {
                $current = 'new';
            }
            $statuses = ss_private_view_statuses();
            wp_nonce_field('ss_private_view_status_save', 'ss_private_view_status_nonce');
            echo '<p><label for="ss_private_view_status">' . esc_html__('Status', 'stone-sparkle') . '</label></p>';
            echo '<select id="ss_private_view_status" name="ss_private_view_status">';
            foreach ($statuses as $key => $label) {
                echo '<option value="' . esc_attr($key) . '" ' . selected($current, $key, false) . '>' . esc_html($label) . '</option>';
            }
            echo '</select>';
        },
        SS_PRIVATE_VIEW_CPT,
        'side',
        'default'
    );
});

add_action('save_post_' . SS_PRIVATE_VIEW_CPT, function ($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (wp_is_post_revision($post_id)) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    if (!isset($_POST['ss_private_view_status_nonce'])) {
        return;
    }

    $nonce = sanitize_text_field(wp_unslash((string) $_POST['ss_private_view_status_nonce']));
    if (!wp_verify_nonce($nonce, 'ss_private_view_status_save')) {
        return;
    }

    $statuses = ss_private_view_statuses();
    $status = isset($_POST['ss_private_view_status']) ? sanitize_key(wp_unslash((string) $_POST['ss_private_view_status'])) : 'new';
    if (!isset($statuses[$status])) {
        $status = 'new';
    }
    update_post_meta($post_id, SS_PRIVATE_VIEW_STATUS_META_KEY, $status);
});
