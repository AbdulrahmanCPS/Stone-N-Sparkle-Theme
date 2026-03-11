/**
 * Single product: wrap default quantity input in a stepper with − (left) and + (right) buttons.
 * Preserves WooCommerce min/max and triggers change so variation/cart logic still works.
 */
(function () {
  'use strict';

  function initQuantityStepper() {
    if (!document.body.classList.contains('single-product')) return;

    var form = document.querySelector('.single-product div.product form.cart');
    if (!form) return;

    var quantityWrappers = form.querySelectorAll('.quantity');
    quantityWrappers.forEach(function (wrapper) {
      if (wrapper.querySelector('.gby-qty-stepper')) return;
      var input = wrapper.querySelector('input.qty');
      if (!input || input.type !== 'number') return;

      var stepper = document.createElement('div');
      stepper.className = 'gby-qty-stepper';

      var btnMinus = document.createElement('button');
      btnMinus.type = 'button';
      btnMinus.className = 'gby-qty-btn gby-qty-btn--minus';
      btnMinus.setAttribute('aria-label', 'Decrease quantity');
      btnMinus.innerHTML = '−';

      var btnPlus = document.createElement('button');
      btnPlus.type = 'button';
      btnPlus.className = 'gby-qty-btn gby-qty-btn--plus';
      btnPlus.setAttribute('aria-label', 'Increase quantity');
      btnPlus.innerHTML = '+';

      function getMin() {
        var min = parseInt(input.getAttribute('min'), 10);
        return isNaN(min) ? 1 : min;
      }
      function getMax() {
        var max = parseInt(input.getAttribute('max'), 10);
        return isNaN(max) ? '' : max;
      }
      function getVal() {
        var val = parseInt(input.value, 10);
        return isNaN(val) ? getMin() : val;
      }
      function setVal(val) {
        var min = getMin();
        var max = getMax();
        val = Math.max(min, val);
        if (max !== '') val = Math.min(max, val);
        input.value = val;
        btnMinus.disabled = val <= min;
        btnPlus.disabled = max !== '' && val >= max;
        input.dispatchEvent(new Event('change', { bubbles: true }));
      }
      function updateButtons() {
        var val = getVal();
        var min = getMin();
        var max = getMax();
        btnMinus.disabled = val <= min;
        btnPlus.disabled = max !== '' && val >= max;
      }

      btnMinus.addEventListener('click', function () {
        setVal(getVal() - 1);
      });
      btnPlus.addEventListener('click', function () {
        setVal(getVal() + 1);
      });
      input.addEventListener('change', updateButtons);

      stepper.appendChild(btnMinus);
      stepper.appendChild(input);
      stepper.appendChild(btnPlus);
      wrapper.appendChild(stepper);
      updateButtons();
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initQuantityStepper);
  } else {
    initQuantityStepper();
  }

  // Re-run when WooCommerce updates the form (e.g. after variation selection).
  if (typeof jQuery !== 'undefined') {
    jQuery(document.body).on('found_variation reset_data', 'form.variations_form', function () {
      setTimeout(initQuantityStepper, 50);
    });
  }
})();
