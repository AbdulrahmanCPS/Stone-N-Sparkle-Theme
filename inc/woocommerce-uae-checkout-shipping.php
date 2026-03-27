<?php
/**
 * UAE emirate checkout fields: sync to billing/shipping city, ACF shipping prices, classic checkout only.
 *
 * ACF Free: shipping amounts are stored on a private page (see page-uae-shipping-settings.php), not Options.
 *
 * @package Stone_Sparkle
 */

if (!defined('ABSPATH')) {
	exit;
}

/** WooCommerce admin submenu slug (redirects to the settings page editor). */
const SS_UAE_SHIPPING_MENU_SLUG = 'ss-uae-emirate-shipping';

/** Page slug for ACF-backed UAE prices (ACF Free: data stored on the page, not options). Must match acf-json page_template rule. */
const SS_UAE_SETTINGS_PAGE_SLUG = 'uae-emirate-shipping';

/**
 * Canonical emirate keys (normalized slug => label + ACF option field name).
 * Slugs match checkout select option values from the field editor (spaces / lowercase).
 *
 * @return array<string, array{label:string, acf:string}>
 */
function ss_uae_emirate_definitions() {
	return array(
		'dubai'            => array(
			'label' => 'Dubai',
			'acf'   => 'dubai_shipping_price',
		),
		'abu dhabi'        => array(
			'label' => 'Abu Dhabi',
			'acf'   => 'abu_dhabi_shipping_price',
		),
		'sharjah'          => array(
			'label' => 'Sharjah',
			'acf'   => 'sharjah_shipping_price',
		),
		'ajman'            => array(
			'label' => 'Ajman',
			'acf'   => 'ajman_shipping_price',
		),
		'umm al quwain'    => array(
			'label' => 'Umm Al Quwain',
			'acf'   => 'umm_al_quwain_shipping_price',
		),
		'ras al khaimah'   => array(
			'label' => 'Ras Al Khaimah',
			'acf'   => 'ras_al_khaimah_shipping_price',
		),
		'fujairah'         => array(
			'label' => 'Fujairah',
			'acf'   => 'fujairah_shipping_price',
		),
	);
}

/**
 * Normalize raw emirate option value for lookup (hyphens/underscores → spaces).
 *
 * @param string $raw Posted option value.
 * @return string
 */
function ss_uae_normalize_emirate_slug($raw) {
	$s = strtolower(trim((string) $raw));
	$s = str_replace(array('-', '_'), ' ', $s);
	$s = preg_replace('/\s+/', ' ', $s);
	return $s;
}

/**
 * Human-readable city label from posted emirate value.
 *
 * @param string $raw Posted select value.
 * @return string Empty if unknown.
 */
function ss_uae_label_from_slug($raw) {
	$key = ss_uae_normalize_emirate_slug($raw);
	$def = ss_uae_emirate_definitions();
	return isset($def[$key]['label']) ? $def[$key]['label'] : '';
}

/**
 * Validate that a submitted emirate value exists in the configured list.
 *
 * @param string $raw Submitted emirate value.
 * @return bool
 */
function ss_uae_is_valid_emirate_value($raw) {
	return ss_uae_label_from_slug($raw) !== '';
}

/**
 * Normalize noisy UAE state placeholders (e.g. AEDUBAI) to empty.
 *
 * @param string $state Raw state value.
 * @return string
 */
function ss_uae_sanitize_state_for_uae($state) {
	$state = trim((string) $state);
	if ($state === '') {
		return '';
	}
	$compact = (string) preg_replace('/[^A-Z]/', '', strtoupper($state));
	if ($compact !== '' && strpos($compact, 'AE') === 0 && strlen($compact) > 2) {
		return '';
	}
	return $state;
}

/**
 * ACF field name from city/emirate string.
 *
 * Accepts either display labels ("Ras Al Khaimah") or stored select values
 * ("ras al khaimah", "ras-al-khaimah", "ras_al_khaimah").
 *
 * @param string $city_label City / emirate label or value.
 * @return string Field name or empty.
 */
function ss_uae_acf_field_from_city_label($city_label) {
	$needle = ss_uae_normalize_emirate_slug($city_label);
	if ($needle === '') {
		return '';
	}
	foreach (ss_uae_emirate_definitions() as $slug => $def) {
		$label_norm = ss_uae_normalize_emirate_slug($def['label']);
		$slug_norm  = ss_uae_normalize_emirate_slug($slug);
		if ($needle === $label_norm || $needle === $slug_norm) {
			return $def['acf'];
		}
	}
	return '';
}

/**
 * Post ID of the private “UAE shipping” settings page, or 0.
 *
 * @return int
 */
function ss_uae_settings_page_id() {
	$page = get_page_by_path(SS_UAE_SETTINGS_PAGE_SLUG, OBJECT, 'page');
	return ($page && !is_wp_error($page)) ? (int) $page->ID : 0;
}

/**
 * Create the settings page once (theme activation or first privileged admin load).
 *
 * ACF Free has no Options UI; fields attach to this page via template location in acf-json.
 *
 * @return void
 */
function ss_uae_ensure_settings_page() {
	if (ss_uae_settings_page_id() > 0) {
		return;
	}
	$filter = current_filter();
	if ('after_switch_theme' !== $filter && !current_user_can('manage_woocommerce')) {
		return;
	}
	$post_id = wp_insert_post(
		array(
			'post_title'   => __('UAE emirate shipping', 'stone-sparkle'),
			'post_name'    => SS_UAE_SETTINGS_PAGE_SLUG,
			'post_status'  => 'private',
			'post_type'    => 'page',
			'post_content' => '<!-- UAE shipping settings (theme). Do not delete this page. -->',
		),
		true
	);
	if (is_wp_error($post_id) || !is_numeric($post_id)) {
		return;
	}
	update_post_meta((int) $post_id, '_wp_page_template', 'page-uae-shipping-settings.php');
}

add_action('after_switch_theme', 'ss_uae_ensure_settings_page');
add_action('admin_init', 'ss_uae_ensure_settings_page', 0);

/**
 * Read an emirate price field from the settings page (not options — ACF Free compatible).
 *
 * @param string $field_name ACF field name (e.g. dubai_shipping_price).
 * @return mixed|null
 */
function ss_uae_get_price_field($field_name) {
	if (!function_exists('get_field')) {
		return null;
	}
	$pid = ss_uae_settings_page_id();
	if ($pid <= 0) {
		return null;
	}
	return get_field((string) $field_name, $pid);
}

/**
 * WooCommerce → UAE shipping: create settings page if needed, then open the page editor (ACF fields).
 *
 * @return void
 */
function ss_uae_wc_submenu_render() {
	ss_uae_ensure_settings_page();
	$pid = ss_uae_settings_page_id();
	if ($pid > 0) {
		wp_safe_redirect(admin_url('post.php?post=' . $pid . '&action=edit'));
		exit;
	}
	wp_die(
		esc_html__('Unable to create the UAE shipping settings page. Please try again or contact the site administrator.', 'stone-sparkle'),
		esc_html__('UAE shipping', 'stone-sparkle'),
		array('response' => 500)
	);
}

/**
 * @return void
 */
function ss_uae_register_wc_admin_menu() {
	add_submenu_page(
		'woocommerce',
		__('UAE emirate shipping', 'stone-sparkle'),
		__('UAE shipping', 'stone-sparkle'),
		'manage_woocommerce',
		SS_UAE_SHIPPING_MENU_SLUG,
		'ss_uae_wc_submenu_render'
	);
}

add_action('admin_menu', 'ss_uae_register_wc_admin_menu', 99);

/**
 * Merge WooCommerce checkout post_data with top-level POST (update_order_review + normal submit).
 *
 * @return array<string, mixed>
 */
function ss_uae_get_merged_checkout_request() {
	$parsed = array();
	if (!empty($_POST['post_data']) && is_string($_POST['post_data'])) {
		parse_str(wp_unslash($_POST['post_data']), $parsed);
		if (!is_array($parsed)) {
			$parsed = array();
		}
	}
	$out = $parsed;
	// Shallow merge: direct $_POST wins for abbreviated AJAX keys and duplicates.
	foreach ($_POST as $k => $v) {
		if ('post_data' === $k) {
			continue;
		}
		$out[ $k ] = $v;
	}
	return $out;
}

/**
 * Patch abbreviated AJAX vars used by WC_Checkout::update_order_review (country, city, s_city, …).
 *
 * @param array<string, mixed> $data Parsed post_data array.
 * @return void
 */
function ss_uae_patch_abbreviated_checkout_post(array $data) {
	$has_uae_destination = false;

	if (($data['billing_country'] ?? '') === 'AE' && !empty($data['billing_emirate'])) {
		$label = ss_uae_label_from_slug($data['billing_emirate']);
		if ($label !== '') {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WC checkout/AJAX; nonce verified by WC.
			$_POST['city'] = $label;
			$has_uae_destination = true;
		}
	}
	$ship_diff = !empty($data['ship_to_different_address']);
	if (!$ship_diff) {
		if (($data['billing_country'] ?? '') === 'AE' && !empty($data['billing_emirate'])) {
			$label = ss_uae_label_from_slug($data['billing_emirate']);
			if ($label !== '') {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing
				$_POST['s_city'] = $label;
				$has_uae_destination = true;
			}
		}
		if ($has_uae_destination) {
			// Allow shipping calculation as soon as AE + emirate is chosen.
			// WC otherwise may keep "Enter your address to view shipping options."
			$_POST['has_full_address'] = '1';
		}
		return;
	}
	if (($data['shipping_country'] ?? '') === 'AE' && !empty($data['shipping_emirate'])) {
		$label = ss_uae_label_from_slug($data['shipping_emirate']);
		if ($label !== '') {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$_POST['s_city'] = $label;
			$has_uae_destination = true;
		}
	}

	if ($has_uae_destination) {
		$_POST['has_full_address'] = '1';
	}
}

/**
 * @return bool
 */
function ss_uae_is_checkout_ui() {
	return function_exists('is_checkout') && is_checkout() && (!function_exists('is_order_received_page') || !is_order_received_page());
}

/**
 * @return bool
 */
function ss_uae_is_account_edit_address_ui() {
	if (!function_exists('is_account_page') || !is_account_page()) {
		return false;
	}
	return function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('edit-address');
}

/**
 * Enqueue checkout script.
 *
 * @return void
 */
function ss_uae_enqueue_checkout_assets() {
	if (!ss_uae_is_checkout_ui()) {
		return;
	}
	$path = get_template_directory() . '/assets/js/checkout-uae.js';
	$ver  = file_exists($path) ? (string) filemtime($path) : (defined('SS_THEME_VERSION') ? SS_THEME_VERSION : '1');
	wp_enqueue_script(
		'stone-sparkle-checkout-uae',
		get_template_directory_uri() . '/assets/js/checkout-uae.js',
		array('jquery', 'wc-checkout'),
		$ver,
		true
	);
}

add_action('wp_enqueue_scripts', 'ss_uae_enqueue_checkout_assets', 20);

/**
 * Enqueue account edit-address helper script.
 *
 * @return void
 */
function ss_uae_enqueue_account_address_assets() {
	if (!ss_uae_is_account_edit_address_ui()) {
		return;
	}
	$path = get_template_directory() . '/assets/js/account-uae-address.js';
	$ver  = file_exists($path) ? (string) filemtime($path) : (defined('SS_THEME_VERSION') ? SS_THEME_VERSION : '1');
	wp_enqueue_script(
		'stone-sparkle-account-uae-address',
		get_template_directory_uri() . '/assets/js/account-uae-address.js',
		array('jquery'),
		$ver,
		true
	);
}

add_action('wp_enqueue_scripts', 'ss_uae_enqueue_account_address_assets', 21);

/**
 * Default checkout to shipping address when customer has saved shipping details.
 *
 * @param bool $checked WooCommerce computed value.
 * @return bool
 */
function ss_uae_default_ship_to_different_checked($checked) {
	if (!ss_uae_is_checkout_ui() || !is_user_logged_in() || !function_exists('WC') || !WC()->customer) {
		return $checked;
	}
	$customer = WC()->customer;
	$fields   = array(
		(string) $customer->get_shipping_address_1(),
		(string) $customer->get_shipping_address_2(),
		(string) $customer->get_shipping_city(),
		(string) $customer->get_shipping_state(),
		(string) $customer->get_shipping_postcode(),
		(string) $customer->get_shipping_first_name(),
		(string) $customer->get_shipping_last_name(),
	);
	foreach ($fields as $value) {
		if (trim($value) !== '') {
			return true;
		}
	}
	return $checked;
}

add_filter('woocommerce_ship_to_different_address_checked', 'ss_uae_default_ship_to_different_checked', 20);

/**
 * Relax / enforce required flags for city vs emirate when customer country is AE.
 *
 * @param array<string, array<string, mixed>> $fields Checkout fields.
 * @return array<string, array<string, mixed>>
 */
function ss_uae_checkout_required_fields($fields) {
	if (!function_exists('WC') || !WC()->customer) {
		return $fields;
	}

	// Fallback registration: if checkout-field plugin entries are missing, add emirate selects.
	$choices = array();
	foreach (ss_uae_emirate_definitions() as $slug => $def) {
		$choices[$slug] = $def['label'];
	}
	if (!isset($fields['billing'])) {
		$fields['billing'] = array();
	}
	if (!isset($fields['shipping'])) {
		$fields['shipping'] = array();
	}
	if (!isset($fields['billing']['billing_emirate'])) {
		$fields['billing']['billing_emirate'] = array(
			'type'     => 'select',
			'label'    => __('City / Emirate', 'stone-sparkle'),
			'required' => false,
			'class'    => array('form-row-wide'),
			'priority' => 72,
			'choices'  => $choices,
		);
	}
	if (!isset($fields['shipping']['shipping_emirate'])) {
		$fields['shipping']['shipping_emirate'] = array(
			'type'     => 'select',
			'label'    => __('City / Emirate', 'stone-sparkle'),
			'required' => false,
			'class'    => array('form-row-wide'),
			'priority' => 72,
			'choices'  => $choices,
		);
	}

	$bc = (string) WC()->customer->get_billing_country();
	$sc = (string) WC()->customer->get_shipping_country();

	if (isset($fields['billing']['billing_city'])) {
		$fields['billing']['billing_city']['required'] = ($bc !== 'AE');
	}
	if (isset($fields['billing']['billing_emirate'])) {
		$fields['billing']['billing_emirate']['required'] = ($bc === 'AE');
	}
	if (isset($fields['billing']['billing_state']) && $bc === 'AE') {
		$fields['billing']['billing_state']['required'] = false;
		$fields['billing']['billing_state']['default']  = '';
		$fields['billing']['billing_state']['placeholder'] = __('State / County (optional)', 'stone-sparkle');
	}
	if (isset($fields['billing']['billing_phone'])) {
		$fields['billing']['billing_phone']['required'] = true;
	}
	if (isset($fields['shipping']['shipping_city'])) {
		$fields['shipping']['shipping_city']['required'] = ($sc !== 'AE');
	}
	if (isset($fields['shipping']['shipping_emirate'])) {
		$fields['shipping']['shipping_emirate']['required'] = ($sc === 'AE');
	}
	if (isset($fields['shipping']['shipping_state']) && $sc === 'AE') {
		$fields['shipping']['shipping_state']['required'] = false;
		$fields['shipping']['shipping_state']['default']  = '';
		$fields['shipping']['shipping_state']['placeholder'] = __('State / County (optional)', 'stone-sparkle');
	}
	if (isset($fields['shipping']['shipping_phone'])) {
		$fields['shipping']['shipping_phone']['required'] = true;
	}
	return $fields;
}

add_filter('woocommerce_checkout_fields', 'ss_uae_checkout_required_fields', 9999);

/**
 * Account address field config for the UAE emirate selector.
 *
 * @return array<string, mixed>
 */
function ss_uae_account_emirate_field_config() {
	$options = array('' => __('Select an emirate', 'stone-sparkle'));
	foreach (ss_uae_emirate_definitions() as $slug => $def) {
		$options[$slug] = $def['label'];
	}
	return array(
		'label'    => __('City / Emirate', 'stone-sparkle'),
		'type'     => 'select',
		'required' => false,
		'class'    => array('form-row-wide'),
		'priority' => 72,
		'options'  => $options,
		'choices'  => $options,
	);
}

/**
 * Keep billing account-address fields aligned with UAE/non-UAE checkout behavior.
 *
 * @param array<string, array<string, mixed>> $fields  Billing fields.
 * @param string                              $country Country code.
 * @return array<string, array<string, mixed>>
 */
function ss_uae_billing_account_fields($fields, $country) {
	$country = strtoupper((string) $country);
	if (!isset($fields['billing_emirate'])) {
		$fields['billing_emirate'] = ss_uae_account_emirate_field_config();
	}
	if (isset($fields['billing_city'])) {
		$fields['billing_city']['required'] = ($country !== 'AE');
	}
	if (isset($fields['billing_emirate'])) {
		$fields['billing_emirate']['required'] = ($country === 'AE');
	}
	if (isset($fields['billing_state']) && $country === 'AE') {
		$fields['billing_state']['required']    = false;
		$fields['billing_state']['default']     = '';
		$fields['billing_state']['placeholder'] = __('State / County (optional)', 'stone-sparkle');
	}
	if (isset($fields['billing_phone'])) {
		$fields['billing_phone']['required'] = true;
	}
	return $fields;
}

add_filter('woocommerce_billing_fields', 'ss_uae_billing_account_fields', 20, 2);

/**
 * Keep shipping account-address fields aligned with UAE/non-UAE checkout behavior.
 *
 * @param array<string, array<string, mixed>> $fields  Shipping fields.
 * @param string                              $country Country code.
 * @return array<string, array<string, mixed>>
 */
function ss_uae_shipping_account_fields($fields, $country) {
	$country = strtoupper((string) $country);
	if (!isset($fields['shipping_emirate'])) {
		$fields['shipping_emirate'] = ss_uae_account_emirate_field_config();
	}
	if (isset($fields['shipping_city'])) {
		$fields['shipping_city']['required'] = ($country !== 'AE');
	}
	if (isset($fields['shipping_emirate'])) {
		$fields['shipping_emirate']['required'] = ($country === 'AE');
	}
	if (isset($fields['shipping_state']) && $country === 'AE') {
		$fields['shipping_state']['required']    = false;
		$fields['shipping_state']['default']     = '';
		$fields['shipping_state']['placeholder'] = __('State / County (optional)', 'stone-sparkle');
	}
	if (isset($fields['shipping_phone'])) {
		$fields['shipping_phone']['required'] = true;
	}
	return $fields;
}

add_filter('woocommerce_shipping_fields', 'ss_uae_shipping_account_fields', 20, 2);

/**
 * Ensure posted data carries human-readable city for validation and order (runs after WC builds $data).
 *
 * @param array<string, mixed> $data Posted checkout data.
 * @return array<string, mixed>
 */
function ss_uae_filter_checkout_posted_data($data) {
	if (!is_array($data)) {
		return $data;
	}
	if (($data['billing_country'] ?? '') === 'AE' && !empty($data['billing_emirate'])) {
		$label = ss_uae_label_from_slug($data['billing_emirate']);
		if ($label !== '') {
			$data['billing_city'] = $label;
			if (empty($data['ship_to_different_address'])) {
				$data['shipping_city'] = $label;
			}
		}
	}
	if (($data['billing_country'] ?? '') === 'AE') {
		$data['billing_state'] = ss_uae_sanitize_state_for_uae($data['billing_state'] ?? '');
	}
	$ship_diff = !empty($data['ship_to_different_address']);
	if ($ship_diff && (($data['shipping_country'] ?? '') === 'AE') && !empty($data['shipping_emirate'])) {
		$label = ss_uae_label_from_slug($data['shipping_emirate']);
		if ($label !== '') {
			$data['shipping_city'] = $label;
		}
	}
	if (($data['shipping_country'] ?? '') === 'AE') {
		$data['shipping_state'] = ss_uae_sanitize_state_for_uae($data['shipping_state'] ?? '');
	}
	return $data;
}

add_filter('woocommerce_checkout_posted_data', 'ss_uae_filter_checkout_posted_data', 5);

/**
 * AJAX update_order_review: set $_POST city / s_city before customer save.
 *
 * @param string $post_data_string Raw post_data from WC (may be empty).
 * @return void
 */
function ss_uae_on_checkout_update_order_review($post_data_string) {
	$data = array();
	if (is_string($post_data_string) && $post_data_string !== '') {
		parse_str($post_data_string, $data);
	}
	if (!is_array($data) || empty($data['billing_country'])) {
		$data = ss_uae_get_merged_checkout_request();
	}
	ss_uae_patch_abbreviated_checkout_post($data);
}

add_action('woocommerce_checkout_update_order_review', 'ss_uae_on_checkout_update_order_review', 5);

/**
 * Non-AJAX safety: sync $_POST before get_posted_data reads it.
 *
 * @return void
 */
function ss_uae_checkout_process_early_sync() {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- WC verifies nonce in process_checkout.
	if (empty($_POST['woocommerce-process-checkout-nonce'])) {
		return;
	}
	$data = array();
	if (!empty($_POST['billing_country'])) {
		$data['billing_country']      = wc_clean(wp_unslash($_POST['billing_country']));
		$data['billing_emirate']     = isset($_POST['billing_emirate']) ? wc_clean(wp_unslash($_POST['billing_emirate'])) : '';
		$data['ship_to_different_address'] = !empty($_POST['ship_to_different_address']) && !wc_ship_to_billing_address_only();
		$data['shipping_country']   = isset($_POST['shipping_country']) ? wc_clean(wp_unslash($_POST['shipping_country'])) : '';
		$data['shipping_emirate']   = isset($_POST['shipping_emirate']) ? wc_clean(wp_unslash($_POST['shipping_emirate'])) : '';
		ss_uae_patch_abbreviated_checkout_post($data);
	}
	if (($data['billing_country'] ?? '') === 'AE' && !empty($data['billing_emirate'])) {
		$label = ss_uae_label_from_slug($data['billing_emirate']);
		if ($label !== '') {
			$_POST['billing_city'] = $label;
		}
	}
	if (($data['billing_country'] ?? '') === 'AE') {
		$_POST['billing_state'] = ss_uae_sanitize_state_for_uae($_POST['billing_state'] ?? '');
	}
	$ship_diff = !empty($data['ship_to_different_address']);
	if (!$ship_diff) {
		if (($data['billing_country'] ?? '') === 'AE' && !empty($data['billing_emirate'])) {
			$label = ss_uae_label_from_slug($data['billing_emirate']);
			if ($label !== '') {
				$_POST['shipping_city'] = $label;
			}
		}
	} elseif (($data['shipping_country'] ?? '') === 'AE' && !empty($data['shipping_emirate'])) {
		$label = ss_uae_label_from_slug($data['shipping_emirate']);
		if ($label !== '') {
			$_POST['shipping_city'] = $label;
		}
	}
	if (($data['shipping_country'] ?? '') === 'AE') {
		$_POST['shipping_state'] = ss_uae_sanitize_state_for_uae($_POST['shipping_state'] ?? '');
	}
	// phpcs:enable WordPress.Security.NonceVerification.Missing
}

add_action('woocommerce_checkout_process', 'ss_uae_checkout_process_early_sync', 1);

/**
 * Keep UAE state fields visually empty when noisy placeholders are present.
 *
 * @param mixed  $value Current field value.
 * @param string $input Input key.
 * @return mixed
 */
function ss_uae_checkout_state_value($value, $input) {
	if ($input !== 'billing_state' && $input !== 'shipping_state') {
		return $value;
	}
	if (!function_exists('WC') || !WC()->customer) {
		return $value;
	}
	$country = ($input === 'billing_state')
		? (string) WC()->customer->get_billing_country()
		: (string) WC()->customer->get_shipping_country();
	if ($country !== 'AE') {
		return $value;
	}
	return ss_uae_sanitize_state_for_uae((string) $value);
}

add_filter('woocommerce_checkout_get_value', 'ss_uae_checkout_state_value', 10, 2);

/**
 * Extra validation and remove city required errors when emirate is used.
 *
 * @param array<string, mixed> $data Posted data.
 * @param WP_Error           $errors Errors.
 * @return void
 */
function ss_uae_after_checkout_validation($data, $errors) {
	if (!$errors instanceof WP_Error) {
		return;
	}
	if (($data['billing_country'] ?? '') === 'AE') {
		if (empty($data['billing_emirate'])) {
			$errors->add(
				'billing_emirate_required',
				__('Please select your emirate.', 'stone-sparkle')
			);
		} elseif (!ss_uae_is_valid_emirate_value((string) $data['billing_emirate'])) {
			$errors->add(
				'billing_emirate_invalid',
				__('Please select a valid emirate.', 'stone-sparkle')
			);
		} else {
			$errors->remove('billing_city_required');
		}
	}
	$ship_diff = !empty($data['ship_to_different_address']);
	if ($ship_diff && ($data['shipping_country'] ?? '') === 'AE') {
		if (empty($data['shipping_emirate'])) {
			$errors->add(
				'shipping_emirate_required',
				__('Please select your shipping emirate.', 'stone-sparkle')
			);
		} elseif (!ss_uae_is_valid_emirate_value((string) $data['shipping_emirate'])) {
			$errors->add(
				'shipping_emirate_invalid',
				__('Please select a valid shipping emirate.', 'stone-sparkle')
			);
		} else {
			$errors->remove('shipping_city_required');
		}
	}
}

add_action('woocommerce_after_checkout_validation', 'ss_uae_after_checkout_validation', 10, 2);

/**
 * Backup: correct package destination city from merged request (AE only).
 *
 * @param array<int, array<string, mixed>> $packages Packages.
 * @return array<int, array<string, mixed>>
 */
function ss_uae_cart_shipping_packages($packages) {
	if (!is_array($packages)) {
		return $packages;
	}
	$data = ss_uae_get_merged_checkout_request();
	$ship_diff = !empty($data['ship_to_different_address']);

	foreach ($packages as $i => $package) {
		if (empty($package['destination']['country']) || $package['destination']['country'] !== 'AE') {
			continue;
		}
		if ($ship_diff) {
			if (!empty($data['shipping_emirate'])) {
				$label = ss_uae_label_from_slug($data['shipping_emirate']);
				if ($label !== '') {
					$packages[ $i ]['destination']['city'] = $label;
				}
			}
		} elseif (!empty($data['billing_emirate'])) {
				$label = ss_uae_label_from_slug($data['billing_emirate']);
			if ($label !== '') {
				$packages[ $i ]['destination']['city'] = $label;
			}
		}
	}
	return $packages;
}

add_filter('woocommerce_cart_shipping_packages', 'ss_uae_cart_shipping_packages', 99);

/**
 * Replace flat-rate cost with ACF emirate price (UAE destination only).
 *
 * @param array<string, WC_Shipping_Rate> $rates Rates.
 * @param array<string, mixed>            $package Package.
 * @return array<string, WC_Shipping_Rate>
 */
function ss_uae_package_rates( $rates, $package ) {
	if (empty($package['destination']['country']) || $package['destination']['country'] !== 'AE') {
		return $rates;
	}
	$city = isset($package['destination']['city']) ? trim((string) $package['destination']['city']) : '';
	$acf_field = ss_uae_acf_field_from_city_label($city);
	if ($acf_field === '') {
		if (function_exists('wc_get_logger')) {
			wc_get_logger()->debug(
				'UAE shipping: no emirate match for destination city "' . $city . '"',
				array('source' => 'ss-uae-shipping')
			);
		}
		return $rates;
	}
	$price_raw = ss_uae_get_price_field($acf_field);
	if ($price_raw === null || $price_raw === '' || !is_numeric($price_raw)) {
		if (function_exists('wc_get_logger')) {
			wc_get_logger()->debug(
				'UAE shipping: no numeric ACF price for field "' . $acf_field . '"',
				array('source' => 'ss-uae-shipping')
			);
		}
		return $rates;
	}
	$price = (float) $price_raw;

	foreach ($rates as $rate_id => $rate) {
		if (!is_object($rate) || !method_exists($rate, 'get_method_id')) {
			continue;
		}
		$adjust = ('flat_rate' === $rate->get_method_id());
		$adjust = (bool) apply_filters('ss_uae_adjust_shipping_rate', $adjust, $rate, $package);
		if (!$adjust) {
			continue;
		}
		$rate->set_cost((string) wc_format_decimal($price));
	}
	if (function_exists('wc_get_logger')) {
		wc_get_logger()->debug(
			'UAE shipping: applied price ' . wc_format_decimal($price) . ' for city "' . $city . '" using field "' . $acf_field . '"',
			array('source' => 'ss-uae-shipping')
		);
	}
	return $rates;
}

add_filter('woocommerce_package_rates', 'ss_uae_package_rates', 50, 2);

/**
 * Resolve canonical emirate label from a city/emirate value.
 *
 * @param string $city City string from checkout destination.
 * @return string
 */
function ss_uae_emirate_label_from_city($city) {
	$needle = ss_uae_normalize_emirate_slug($city);
	if ($needle === '') {
		return '';
	}
	foreach (ss_uae_emirate_definitions() as $slug => $def) {
		$label_norm = ss_uae_normalize_emirate_slug($def['label']);
		$slug_norm  = ss_uae_normalize_emirate_slug($slug);
		if ($needle === $label_norm || $needle === $slug_norm) {
			return (string) $def['label'];
		}
	}
	return '';
}

/**
 * Return selected UAE emirate label from checkout customer destination.
 *
 * @return string
 */
function ss_uae_checkout_selected_emirate_label() {
	if (!function_exists('WC') || !WC()->customer) {
		return '';
	}
	$country = (string) WC()->customer->get_shipping_country();
	$city    = (string) WC()->customer->get_shipping_city();

	if ($country !== 'AE') {
		$country = (string) WC()->customer->get_billing_country();
		$city    = (string) WC()->customer->get_billing_city();
	}
	if ($country !== 'AE') {
		return '';
	}
	return ss_uae_emirate_label_from_city($city);
}

/**
 * Checkout clarity: always show selected shipping method cost in label text.
 *
 * Example output: "UAE test — 40 AED"
 *
 * @param string           $label  Rendered method label.
 * @param WC_Shipping_Rate $method Shipping method object.
 * @return string
 */
function ss_uae_checkout_shipping_label_with_cost($label, $method) {
	if (!ss_uae_is_checkout_ui() || !is_object($method) || !method_exists($method, 'get_cost')) {
		return $label;
	}
	$cost = $method->get_cost();
	if ($cost === '' || !is_numeric($cost)) {
		return $label;
	}
	$amount = wc_price((float) $cost);
	$text   = method_exists($method, 'get_label')
		? trim((string) $method->get_label())
		: trim(wp_strip_all_tags((string) $label));
	$emirate = ss_uae_checkout_selected_emirate_label();
	if ($emirate !== '') {
		if ((float) $cost <= 0.000001) {
			return sprintf('%1$s — %2$s', $emirate, __('Free shipping', 'woocommerce'));
		}
		return sprintf('%1$s — %2$s', $emirate, wp_strip_all_tags($amount));
	}
	return sprintf('%1$s — %2$s', $text, wp_strip_all_tags($amount));
}

add_filter('woocommerce_cart_shipping_method_full_label', 'ss_uae_checkout_shipping_label_with_cost', 20, 2);
