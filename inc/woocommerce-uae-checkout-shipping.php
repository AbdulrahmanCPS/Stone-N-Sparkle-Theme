<?php
/**
 * UAE emirate checkout fields: sync to billing/shipping city, ACF shipping prices, classic checkout only.
 *
 * @package Stone_Sparkle
 */

if (!defined('ABSPATH')) {
	exit;
}

/** Menu slug for ACF location + options context (must match acf-json). */
const SS_UAE_SHIPPING_OPTIONS_SLUG = 'ss-uae-emirate-shipping';

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
 * ACF option field name for a canonical city label (case-insensitive).
 *
 * @param string $city_label City / emirate label.
 * @return string Field name or empty.
 */
function ss_uae_acf_field_from_city_label($city_label) {
	$city_label = trim((string) $city_label);
	if ($city_label === '') {
		return '';
	}
	$needle = function_exists('mb_strtolower') ? mb_strtolower($city_label, 'UTF-8') : strtolower($city_label);
	foreach (ss_uae_emirate_definitions() as $def) {
		$lbl    = function_exists('mb_strtolower') ? mb_strtolower($def['label'], 'UTF-8') : strtolower($def['label']);
		if ($lbl === $needle) {
			return $def['acf'];
		}
	}
	return '';
}

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
	if (($data['billing_country'] ?? '') === 'AE' && !empty($data['billing_emirate'])) {
		$label = ss_uae_label_from_slug($data['billing_emirate']);
		if ($label !== '') {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WC checkout/AJAX; nonce verified by WC.
			$_POST['city'] = $label;
		}
	}
	$ship_diff = !empty($data['ship_to_different_address']);
	if (!$ship_diff) {
		if (($data['billing_country'] ?? '') === 'AE' && !empty($data['billing_emirate'])) {
			$label = ss_uae_label_from_slug($data['billing_emirate']);
			if ($label !== '') {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing
				$_POST['s_city'] = $label;
			}
		}
		return;
	}
	if (($data['shipping_country'] ?? '') === 'AE' && !empty($data['shipping_emirate'])) {
		$label = ss_uae_label_from_slug($data['shipping_emirate']);
		if ($label !== '') {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$_POST['s_city'] = $label;
		}
	}
}

/**
 * ACF: WooCommerce submenu for UAE prices (field definitions load from acf-json).
 *
 * @return void
 */
function ss_uae_register_acf_options_and_fields() {
	$slug = SS_UAE_SHIPPING_OPTIONS_SLUG;

	if (function_exists('acf_add_options_sub_page')) {
		acf_add_options_sub_page(
			array(
				'page_title'  => __('UAE emirate shipping', 'stone-sparkle'),
				'menu_title'  => __('UAE shipping', 'stone-sparkle'),
				'menu_slug'   => $slug,
				'parent_slug' => 'woocommerce',
				'capability'  => 'manage_woocommerce',
			)
		);
	} elseif (function_exists('acf_add_options_page')) {
		acf_add_options_page(
			array(
				'page_title'  => __('UAE emirate shipping', 'stone-sparkle'),
				'menu_title'  => __('UAE shipping', 'stone-sparkle'),
				'menu_slug'   => $slug,
				'capability'  => 'manage_woocommerce',
				'parent_slug' => 'woocommerce',
			)
		);
	}
}

add_action('acf/init', 'ss_uae_register_acf_options_and_fields');

/**
 * @return bool
 */
function ss_uae_is_checkout_ui() {
	return function_exists('is_checkout') && is_checkout() && (!function_exists('is_order_received_page') || !is_order_received_page());
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
 * Relax / enforce required flags for city vs emirate when customer country is AE.
 *
 * @param array<string, array<string, mixed>> $fields Checkout fields.
 * @return array<string, array<string, mixed>>
 */
function ss_uae_checkout_required_fields($fields) {
	if (!function_exists('WC') || !WC()->customer) {
		return $fields;
	}
	$bc = (string) WC()->customer->get_billing_country();
	$sc = (string) WC()->customer->get_shipping_country();

	if (isset($fields['billing']['billing_city'])) {
		$fields['billing']['billing_city']['required'] = ($bc !== 'AE');
	}
	if (isset($fields['billing']['billing_emirate'])) {
		$fields['billing']['billing_emirate']['required'] = ($bc === 'AE');
	}
	if (isset($fields['shipping']['shipping_city'])) {
		$fields['shipping']['shipping_city']['required'] = ($sc !== 'AE');
	}
	if (isset($fields['shipping']['shipping_emirate'])) {
		$fields['shipping']['shipping_emirate']['required'] = ($sc === 'AE');
	}
	return $fields;
}

add_filter('woocommerce_checkout_fields', 'ss_uae_checkout_required_fields', 9999);

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
	$ship_diff = !empty($data['ship_to_different_address']);
	if ($ship_diff && (($data['shipping_country'] ?? '') === 'AE') && !empty($data['shipping_emirate'])) {
		$label = ss_uae_label_from_slug($data['shipping_emirate']);
		if ($label !== '') {
			$data['shipping_city'] = $label;
		}
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
	// phpcs:enable WordPress.Security.NonceVerification.Missing
}

add_action('woocommerce_checkout_process', 'ss_uae_checkout_process_early_sync', 1);

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
	if ($acf_field === '' || !function_exists('get_field')) {
		return $rates;
	}
	$price_raw = get_field($acf_field, 'option');
	if ($price_raw === null || $price_raw === '' || !is_numeric($price_raw)) {
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
	return $rates;
}

add_filter('woocommerce_package_rates', 'ss_uae_package_rates', 50, 2);
