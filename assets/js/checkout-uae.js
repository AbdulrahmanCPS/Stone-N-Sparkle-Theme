/**
 * UAE checkout: toggle emirate vs city rows and sync emirate labels into WooCommerce city fields.
 */
(function ($) {
	'use strict';

	if (window.ssUaeCheckoutBound) {
		return;
	}
	window.ssUaeCheckoutBound = true;

	var AE = 'AE';

	function shipToDifferent() {
		var $box = $('#ship-to-different-address');
		if (!$box.length) {
			return false;
		}
		return $box.find('input').is(':checked');
	}

	function syncSelectLabelToCity(selectSel, citySel) {
		var $s = $(selectSel);
		var $c = $(citySel);
		if (!$s.length || !$c.length) {
			return;
		}
		var text = $s.find('option:selected').text().trim();
		if (text) {
			$c.val(text);
		}
	}

	function forceSelect2Width(selectSel) {
		var $select = $(selectSel);
		if (!$select.length) return;
		var $container = $select.next('.select2');
		if ($container.length) {
			$container.css({ width: '100%', maxWidth: '100%' });
		}
		var s2 = $select.data('select2');
		if (s2 && s2.$container) {
			s2.$container.css({ width: '100%', maxWidth: '100%' });
		}
	}

	function syncSameAsBilling() {
		if (shipToDifferent()) {
			return;
		}
		if ($('#billing_country').val() !== AE) {
			return;
		}
		syncSelectLabelToCity('#billing_emirate', '#billing_city');
		var label = $('#billing_emirate option:selected').text().trim();
		if (label) {
			$('#shipping_city').val(label);
		}
	}

	function applyBilling() {
		var $be = $('#billing_emirate_field');
		var $bc = $('#billing_city_field');
		var country = $('#billing_country').val();

		if ($be.length) {
			if (country === AE) {
				$be.show();
				forceSelect2Width('#billing_emirate');
				syncSelectLabelToCity('#billing_emirate', '#billing_city');
			} else {
				$be.hide();
			}
		}
		if ($bc.length) {
			if (country === AE) {
				$bc.hide();
				syncSelectLabelToCity('#billing_emirate', '#billing_city');
			} else {
				$bc.show();
			}
		}
	}

	function applyShipping() {
		var $se = $('#shipping_emirate_field');
		var $sc = $('#shipping_city_field');

		if (!shipToDifferent()) {
			if ($se.length) {
				$se.hide();
			}
			if ($sc.length) {
				if ($('#billing_country').val() === AE) {
					$sc.hide();
				} else {
					$sc.show();
				}
			}
			syncSameAsBilling();
			return;
		}

		var sc = $('#shipping_country').val();

		if ($se.length) {
			if (sc === AE) {
				$se.show();
				forceSelect2Width('#shipping_emirate');
				syncSelectLabelToCity('#shipping_emirate', '#shipping_city');
			} else {
				$se.hide();
			}
		}
		if ($sc.length) {
			if (sc === AE) {
				$sc.hide();
				syncSelectLabelToCity('#shipping_emirate', '#shipping_city');
			} else {
				$sc.show();
			}
		}
	}

	function applyAll() {
		applyBilling();
		applyShipping();
	}

	function requestTotalsUpdate() {
		$(document.body).trigger('update_checkout');
	}

	$(function () {
		var $body = $(document.body);

		$body.on('change.ssUaeCheckout', '#billing_country, #billing_emirate', function () {
			applyBilling();
			applyShipping();
			requestTotalsUpdate();
		});

		$body.on(
			'change.ssUaeCheckout',
			'#shipping_country, #shipping_emirate, #ship-to-different-address input',
			function () {
				applyShipping();
				requestTotalsUpdate();
			}
		);

		$body.on('updated_checkout.ssUaeCheckout', function () {
			applyAll();
		});

		applyAll();
	});
})(jQuery);
