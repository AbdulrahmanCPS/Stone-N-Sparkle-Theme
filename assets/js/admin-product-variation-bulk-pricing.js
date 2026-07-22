(function ($) {
  'use strict';

  var config = window.ssBulkVariationPricing || {};
  var previewTimer = null;
  var filterTimer = null;

  function getRoot() {
    return $('.ss-bulk-pricing');
  }

  function getProductId($root) {
    return parseInt($root.data('product-id'), 10) || 0;
  }

  function formatSelectedCount(selected, total) {
    var template = config.i18n && config.i18n.selectedCount
      ? config.i18n.selectedCount
      : '%1$d of %2$d selected';

    return template
      .replace('%1$d', String(selected))
      .replace('%2$d', String(total));
  }

  function updateAttributeSelectionCount($attribute) {
    var total = $attribute.find('.ss-bulk-pricing__option').length;
    var selected = $attribute.find('.ss-bulk-pricing__option input[type="checkbox"]:checked').length;

    $attribute.find('.ss-bulk-pricing__selection-count').text(formatSelectedCount(selected, total));
  }

  function updateAllSelectionCounts($root) {
    $root.find('.ss-bulk-pricing__attribute').each(function () {
      updateAttributeSelectionCount($(this));
    });
  }

  function filterAttributeOptions($attribute, query) {
    var normalizedQuery = $.trim(query).toLowerCase();
    var visibleCount = 0;

    $attribute.find('.ss-bulk-pricing__option').each(function () {
      var $option = $(this);
      var label = String($option.data('option-label') || $option.text() || '').toLowerCase();
      var isMatch = normalizedQuery === '' || label.indexOf(normalizedQuery) !== -1;

      $option.toggleClass('is-hidden', !isMatch);
      if (isMatch) {
        visibleCount += 1;
      }
    });

    $attribute.find('.ss-bulk-pricing__filter-empty').prop('hidden', visibleCount > 0);
  }

  function collectFilters($root) {
    var filters = {};
    var valid = true;

    $root.find('.ss-bulk-pricing__attribute').each(function () {
      var $attribute = $(this);
      var attributeName = $attribute.data('attribute');
      var values = [];

      $attribute.find('.ss-bulk-pricing__option input[type="checkbox"]:checked').each(function () {
        values.push($(this).val());
      });

      if (!values.length) {
        valid = false;
      }

      filters[attributeName] = values;
    });

    return {
      valid: valid,
      filters: filters,
    };
  }

  function updatePreviewText($root, count) {
    var $preview = $root.find('.ss-bulk-pricing__preview');
    var text = '';

    if (count === 1) {
      text = config.i18n && config.i18n.previewSingular ? config.i18n.previewSingular : '1 variation will be updated.';
    } else if (config.i18n && config.i18n.previewPlural) {
      text = config.i18n.previewPlural.replace('%d', String(count));
    } else {
      text = count + ' variations will be updated.';
    }

    $preview.text(text);
  }

  function setLoading($root, isLoading) {
    $root.find('.ss-bulk-pricing__spinner').toggleClass('is-active', !!isLoading);
    $root.find('.ss-bulk-pricing__apply').prop('disabled', !!isLoading);
  }

  function showResult($root, message, isError) {
    var $result = $root.find('.ss-bulk-pricing__result');
    $result
      .removeClass('is-error is-success')
      .addClass(isError ? 'is-error' : 'is-success')
      .text(message || '');
  }

  function requestPreview($root) {
    var productId = getProductId($root);
    var collected = collectFilters($root);

    if (!productId) {
      return;
    }

    if (!collected.valid) {
      updatePreviewText($root, 0);
      showResult($root, config.i18n && config.i18n.validationError ? config.i18n.validationError : '', true);
      return;
    }

    showResult($root, '', false);

    $.post(config.ajaxUrl, {
      action: 'ss_bulk_variation_price_preview',
      nonce: config.nonce,
      product_id: productId,
      filters: collected.filters,
    })
      .done(function (response) {
        if (!response || !response.success) {
          updatePreviewText($root, 0);
          showResult($root, (response && response.data && response.data.message) || '', true);
          return;
        }

        updatePreviewText($root, parseInt(response.data.count, 10) || 0);
      })
      .fail(function () {
        updatePreviewText($root, 0);
      });
  }

  function schedulePreview($root) {
    window.clearTimeout(previewTimer);
    previewTimer = window.setTimeout(function () {
      requestPreview($root);
    }, 250);
  }

  function applyPricing($root) {
    var productId = getProductId($root);
    var collected = collectFilters($root);
    var regularPrice = $.trim($root.find('#ss_bulk_pricing_regular_price').val());
    var salePrice = $.trim($root.find('#ss_bulk_pricing_sale_price').val());

    if (!productId) {
      return;
    }

    if (!collected.valid) {
      showResult($root, config.i18n && config.i18n.validationError ? config.i18n.validationError : '', true);
      return;
    }

    if (!regularPrice) {
      showResult($root, config.i18n && config.i18n.regularRequired ? config.i18n.regularRequired : '', true);
      return;
    }

    setLoading($root, true);
    showResult($root, '', false);

    $.post(config.ajaxUrl, {
      action: 'ss_bulk_variation_price_apply',
      nonce: config.nonce,
      product_id: productId,
      filters: collected.filters,
      regular_price: regularPrice,
      sale_price: salePrice,
    })
      .done(function (response) {
        if (!response || !response.success) {
          showResult($root, (response && response.data && response.data.message) || (config.i18n && config.i18n.applyError) || '', true);
          return;
        }

        showResult($root, response.data.message || '', false);
        requestPreview($root);

        if (window.wp && wp.data && wp.data.dispatch) {
          try {
            wp.data.dispatch('core/notices').createNotice(
              'success',
              response.data.message,
              { isDismissible: true }
            );
          } catch (e) {
            // No block editor notice context on classic product screen.
          }
        }
      })
      .fail(function () {
        showResult($root, config.i18n && config.i18n.applyError ? config.i18n.applyError : '', true);
      })
      .always(function () {
        setLoading($root, false);
      });
  }

  function setAttributeCollapsed($attribute, isCollapsed) {
    var $toggle = $attribute.find('.ss-bulk-pricing__toggle').first();
    var collapsed = !!isCollapsed;

    $attribute.toggleClass('is-collapsed', collapsed).toggleClass('is-expanded', !collapsed);
    $toggle.attr('aria-expanded', collapsed ? 'false' : 'true');
    $toggle.attr(
      'aria-label',
      collapsed
        ? (config.i18n && config.i18n.expandAttribute ? config.i18n.expandAttribute : 'Expand attribute options')
        : (config.i18n && config.i18n.collapseAttribute ? config.i18n.collapseAttribute : 'Collapse attribute options')
    );
  }

  function toggleAttributeCollapsed($attribute) {
    setAttributeCollapsed($attribute, !$attribute.hasClass('is-collapsed'));
  }

  function bindEvents($root) {
    $root.on('change', '.ss-bulk-pricing__option input[type="checkbox"]', function () {
      updateAttributeSelectionCount($(this).closest('.ss-bulk-pricing__attribute'));
      schedulePreview($root);
    });

    $root.on('click', '.ss-bulk-pricing__toggle', function (event) {
      event.preventDefault();
      toggleAttributeCollapsed($(this).closest('.ss-bulk-pricing__attribute'));
    });

    $root.on('click', '.ss-bulk-pricing__select-all', function (event) {
      event.preventDefault();
      var $attribute = $(this).closest('.ss-bulk-pricing__attribute');
      $attribute.find('.ss-bulk-pricing__option input[type="checkbox"]').prop('checked', true);
      updateAttributeSelectionCount($attribute);
      schedulePreview($root);
    });

    $root.on('click', '.ss-bulk-pricing__clear-all', function (event) {
      event.preventDefault();
      var $attribute = $(this).closest('.ss-bulk-pricing__attribute');
      $attribute.find('.ss-bulk-pricing__option input[type="checkbox"]').prop('checked', false);
      updateAttributeSelectionCount($attribute);
      schedulePreview($root);
    });

    $root.on('input', '.ss-bulk-pricing__filter', function () {
      var $input = $(this);
      var $attribute = $input.closest('.ss-bulk-pricing__attribute');

      window.clearTimeout(filterTimer);
      filterTimer = window.setTimeout(function () {
        filterAttributeOptions($attribute, $input.val());
      }, 150);
    });

    $root.on('click', '.ss-bulk-pricing__apply', function (event) {
      event.preventDefault();
      applyPricing($root);
    });
  }

  function initBulkPricing() {
    var $root = getRoot();
    if (!$root.length || $root.data('ss-bulk-pricing-bound')) {
      return;
    }

    $root.data('ss-bulk-pricing-bound', true);
    bindEvents($root);
    $root.find('.ss-bulk-pricing__attribute').each(function () {
      var $attribute = $(this);
      setAttributeCollapsed($attribute, $attribute.hasClass('is-collapsed'));
    });
    updateAllSelectionCounts($root);
    requestPreview($root);
  }

  $(function () {
    initBulkPricing();

    $('body').on('woocommerce-product-type-change', function (event, type) {
      if (type === 'variable') {
        window.setTimeout(initBulkPricing, 300);
      }
    });

    $('body').on('click', '.product_data_tabs li.ss_bulk_pricing_options a', function () {
      window.setTimeout(function () {
        var $root = getRoot();
        updateAllSelectionCounts($root);
        requestPreview($root);
      }, 50);
    });
  });
})(jQuery);
