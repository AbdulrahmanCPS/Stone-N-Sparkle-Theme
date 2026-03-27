/**
 * My Account edit-address: toggle emirate/city fields based on country.
 */
(function ($) {
	'use strict';

	var AE = 'AE';

	function normalizeStateValue(value) {
		var raw = (value || '').toString().trim();
		if (!raw) {
			return '';
		}
		var compact = raw.toUpperCase().replace(/[^A-Z]/g, '');
		if (compact.indexOf('AE') === 0 && compact.length > 2) {
			return '';
		}
		return raw;
	}

	function syncEmirateToCity(prefix) {
		var $emirate = $('#' + prefix + '_emirate');
		var $city = $('#' + prefix + '_city');
		if (!$emirate.length || !$city.length) {
			return;
		}
		var label = $emirate.find('option:selected').text().trim();
		if (label) {
			$city.val(label);
		}
	}

	function applyAddressMode(prefix) {
		var $country = $('#' + prefix + '_country');
		if (!$country.length) {
			return;
		}
		var isAE = $country.val() === AE;

		var $emirateField = $('#' + prefix + '_emirate_field');
		var $cityField = $('#' + prefix + '_city_field');
		var $state = $('#' + prefix + '_state');
		var $emirate = $('#' + prefix + '_emirate');
		var $city = $('#' + prefix + '_city');

		if ($emirateField.length) {
			if (isAE) {
				$emirateField.show();
			} else {
				$emirateField.hide();
			}
		}
		if ($cityField.length) {
			if (isAE) {
				$cityField.hide();
			} else {
				$cityField.show();
			}
		}
		if ($emirate.length) {
			$emirate.prop('required', isAE);
		}
		if ($city.length) {
			$city.prop('required', !isAE);
		}
		if ($state.length && isAE) {
			$state.val(normalizeStateValue($state.val()));
		}
		if (isAE) {
			syncEmirateToCity(prefix);
		}
	}

	$(function () {
		var prefixes = ['billing', 'shipping'];

		prefixes.forEach(function (prefix) {
			if (!$('#' + prefix + '_country').length) {
				return;
			}
			applyAddressMode(prefix);

			$(document.body).on('change.ssAccountUae', '#' + prefix + '_country', function () {
				applyAddressMode(prefix);
			});
			$(document.body).on('change.ssAccountUae', '#' + prefix + '_emirate', function () {
				applyAddressMode(prefix);
			});
		});

		$('form.woocommerce-EditAccountForm, form.edit-address, form.woocommerce-address-fields').on(
			'submit.ssAccountUae',
			function () {
				prefixes.forEach(function (prefix) {
					applyAddressMode(prefix);
				});
			}
		);
	});
})(jQuery);
