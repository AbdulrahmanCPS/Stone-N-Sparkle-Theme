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
	var feedbackClass = 'ss-shipping-feedback';

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

	function currentShippingSummaryText() {
		var $shippingRow = $('.woocommerce-checkout-review-order-table tfoot tr.shipping').first();
		if (!$shippingRow.length) {
			return '';
		}
		var text = $shippingRow.find('td').text().replace(/\s+/g, ' ').trim();
		return text;
	}

	function ensureFeedbackNode(fieldSelector) {
		var $field = $(fieldSelector);
		if (!$field.length) {
			return $();
		}
		var $node = $field.find('.' + feedbackClass);
		if ($node.length) {
			return $node.first();
		}
		$node = $('<p/>', {
			class: feedbackClass,
			'aria-live': 'polite'
		});
		$field.append($node);
		return $node;
	}

	function updateShippingFeedback() {
		var text = currentShippingSummaryText();
		var isShipDifferent = shipToDifferent();
		var billingAE = $('#billing_country').val() === AE;
		var shippingAE = isShipDifferent && $('#shipping_country').val() === AE;
		var hasBillingEmirate = $('#billing_emirate').val();
		var hasShippingEmirate = $('#shipping_emirate').val();
		var hasBillingCity = ($('#billing_city').val() || '').toString().trim();
		var hasShippingCity = ($('#shipping_city').val() || '').toString().trim();

		var $billingEmirateNode = ensureFeedbackNode('#billing_emirate_field');
		var $billingCityNode = ensureFeedbackNode('#billing_city_field');
		var $shippingEmirateNode = ensureFeedbackNode('#shipping_emirate_field');
		var $shippingCityNode = ensureFeedbackNode('#shipping_city_field');

		$billingEmirateNode.removeClass('is-visible').text('');
		$billingCityNode.removeClass('is-visible').text('');
		$shippingEmirateNode.removeClass('is-visible').text('');
		$shippingCityNode.removeClass('is-visible').text('');

		if (!text) {
			return;
		}

		if (!isShipDifferent) {
			if (billingAE && hasBillingEmirate) {
				$billingEmirateNode.text('Shipping: ' + text).addClass('is-visible');
			} else if (!billingAE && hasBillingCity) {
				$billingCityNode.text('Shipping: ' + text).addClass('is-visible');
			}
			return;
		}

		if (shippingAE && hasShippingEmirate) {
			$shippingEmirateNode.text('Shipping: ' + text).addClass('is-visible');
		} else if (!shippingAE && hasShippingCity) {
			$shippingCityNode.text('Shipping: ' + text).addClass('is-visible');
		}
	}

	$(function () {
		var $body = $(document.body);

		$body.on('change.ssUaeCheckout', '#billing_country, #billing_emirate', function () {
			applyBilling();
			applyShipping();
			updateShippingFeedback();
			requestTotalsUpdate();
		});

		$body.on(
			'change.ssUaeCheckout',
			'#shipping_country, #shipping_emirate, #ship-to-different-address input',
			function () {
				applyShipping();
				updateShippingFeedback();
				requestTotalsUpdate();
			}
		);

		$body.on('updated_checkout.ssUaeCheckout', function () {
			applyAll();
			updateShippingFeedback();
		});

		applyAll();
		updateShippingFeedback();
	});
})(jQuery);
