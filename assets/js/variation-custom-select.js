/**
 * Custom variation dropdown: native <select> option lists cannot be styled or animated in most browsers.
 * Keeps the real select for WooCommerce; syncs value and dispatches change for variation matching.
 */
(function () {
  'use strict';

  var OPEN_CLASS = 'is-open';
  var DATA_KEY = 'ssVariationCustomSelect';

  function closeAllExcept(wrap) {
    document.querySelectorAll('.ss-variation-select.' + OPEN_CLASS).forEach(function (w) {
      if (w !== wrap) {
        setOpen(w, false);
      }
    });
  }

  function setOpen(wrap, open) {
    var trigger = wrap.querySelector('.ss-variation-select__trigger');
    var panel = wrap.querySelector('.ss-variation-select__dropdown');
    if (!trigger || !panel) {
      return;
    }
    trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open) {
      window.clearTimeout(wrap._ssCloseT);
      wrap._ssCloseT = 0;
      panel.hidden = false;
      panel.setAttribute('aria-hidden', 'false');
      window.requestAnimationFrame(function () {
        wrap.classList.add(OPEN_CLASS);
      });
    } else {
      wrap.classList.remove(OPEN_CLASS);
      panel.setAttribute('aria-hidden', 'true');
      window.clearTimeout(wrap._ssCloseT);
      wrap._ssCloseT = window.setTimeout(function () {
        wrap._ssCloseT = 0;
        if (!wrap.classList.contains(OPEN_CLASS)) {
          panel.hidden = true;
        }
      }, 220);
    }
  }

  function labelSelectorForId(id) {
    if (typeof CSS !== 'undefined' && CSS.escape) {
      return 'label[for="' + CSS.escape(id) + '"]';
    }
    return 'label[for="' + String(id).replace(/\\/g, '\\\\').replace(/"/g, '\\"') + '"]';
  }

  function dispatchChange(select) {
    try {
      select.dispatchEvent(new Event('change', { bubbles: true }));
    } catch (e) {
      /* ignore */
    }
    if (typeof window.jQuery !== 'undefined' && window.jQuery.fn && window.jQuery.fn.trigger) {
      window.jQuery(select).trigger('change');
    }
  }

  function buildList(wrap, select) {
    var list = wrap.querySelector('.ss-variation-select__list');
    if (!list) {
      return;
    }
    list.innerHTML = '';
    Array.prototype.forEach.call(select.options, function (opt, index) {
      var li = document.createElement('li');
      li.setAttribute('role', 'presentation');
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.setAttribute('role', 'option');
      btn.className = 'ss-variation-select__option';
      if (opt.disabled) {
        btn.classList.add('is-disabled');
        btn.disabled = true;
        btn.setAttribute('aria-disabled', 'true');
      }
      if (opt.value === '') {
        btn.classList.add('is-placeholder');
      }
      btn.setAttribute('aria-selected', select.selectedIndex === index ? 'true' : 'false');
      btn.setAttribute('data-value', opt.value);
      btn.textContent = opt.textContent || opt.label || '';
      btn.addEventListener('click', function () {
        if (opt.disabled) {
          return;
        }
        select.value = opt.value;
        dispatchChange(select);
        syncFromSelect(wrap, select);
        buildList(wrap, select);
        setOpen(wrap, false);
        var tr = wrap.querySelector('.ss-variation-select__trigger');
        if (tr && typeof tr.focus === 'function') {
          tr.focus({ preventScroll: true });
        }
      });
      li.appendChild(btn);
      list.appendChild(li);
    });
  }

  function syncFromSelect(wrap, select) {
    var triggerLabel = wrap.querySelector('.ss-variation-select__value');
    if (triggerLabel) {
      var opt = select.options[select.selectedIndex];
      triggerLabel.textContent = opt ? (opt.textContent || '') : '';
    }
    wrap.classList.toggle('has-placeholder', !select.value || select.value === '');
    var trigger = wrap.querySelector('.ss-variation-select__trigger');
    if (trigger) {
      trigger.disabled = !!select.disabled;
    }
    var list = wrap.querySelector('.ss-variation-select__list');
    if (list) {
      Array.prototype.forEach.call(list.querySelectorAll('[role="option"]'), function (btn) {
        btn.setAttribute('aria-selected', btn.getAttribute('data-value') === select.value ? 'true' : 'false');
      });
    }
  }

  function focusSelectedOrFirst(wrap) {
    var panel = wrap.querySelector('.ss-variation-select__dropdown');
    if (!panel || panel.hidden) {
      return;
    }
    var selected = panel.querySelector('.ss-variation-select__option[aria-selected="true"]:not([disabled])');
    var first = panel.querySelector('.ss-variation-select__option:not([disabled])');
    var el = selected || first;
    if (el && typeof el.focus === 'function') {
      window.setTimeout(function () {
        el.focus({ preventScroll: true });
      }, 16);
    }
  }

  function enhanceSelect(select) {
    if (!select || select.dataset[DATA_KEY]) {
      return;
    }
    if (select.closest('.ss-variation-select')) {
      return;
    }

    select.dataset[DATA_KEY] = '1';

    var wrap = document.createElement('div');
    wrap.className = 'ss-variation-select';

    var listId = select.id ? select.id + '-ss-listbox' : 'ss-var-list-' + String(Math.random()).slice(2);

    var trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'ss-variation-select__trigger';
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');
    trigger.setAttribute('aria-controls', listId);

    var valueSpan = document.createElement('span');
    valueSpan.className = 'ss-variation-select__value';

    var chev = document.createElement('span');
    chev.className = 'ss-variation-select__chevron';
    chev.setAttribute('aria-hidden', 'true');
    trigger.appendChild(valueSpan);
    trigger.appendChild(chev);

    var panel = document.createElement('div');
    panel.className = 'ss-variation-select__dropdown';
    panel.id = listId;
    panel.hidden = true;
    panel.setAttribute('aria-hidden', 'true');

    var list = document.createElement('ul');
    list.className = 'ss-variation-select__list';
    list.setAttribute('role', 'listbox');
    panel.appendChild(list);

    select.classList.add('ss-variation-select__native');
    select.setAttribute('tabindex', '-1');
    select.setAttribute('aria-hidden', 'true');

    var parent = select.parentNode;
    parent.insertBefore(wrap, select);
    wrap.appendChild(select);
    wrap.appendChild(trigger);
    wrap.appendChild(panel);

    wrap.addEventListener('click', function (e) {
      e.stopPropagation();
    });

    buildList(wrap, select);
    syncFromSelect(wrap, select);

    var sid = select.id;
    if (sid) {
      var lab = document.querySelector(labelSelectorForId(sid));
      if (lab && !lab.dataset.ssVariationSelectLabel) {
        lab.dataset.ssVariationSelectLabel = '1';
        lab.addEventListener('click', function (e) {
          e.preventDefault();
          if (select.disabled) {
            return;
          }
          closeAllExcept(wrap);
          var open = !wrap.classList.contains(OPEN_CLASS);
          setOpen(wrap, open);
          if (open) {
            buildList(wrap, select);
            focusSelectedOrFirst(wrap);
          }
          trigger.focus({ preventScroll: true });
        });
      }
    }

    select.addEventListener('change', function () {
      syncFromSelect(wrap, select);
    });

    trigger.addEventListener('click', function (e) {
      e.stopPropagation();
      if (select.disabled) {
        return;
      }
      var willOpen = !wrap.classList.contains(OPEN_CLASS);
      closeAllExcept(wrap);
      setOpen(wrap, willOpen);
      if (willOpen) {
        buildList(wrap, select);
        focusSelectedOrFirst(wrap);
      }
    });

    trigger.addEventListener('keydown', function (e) {
      if (select.disabled) {
        return;
      }
      if (e.key === ' ' || e.key === 'Spacebar') {
        e.preventDefault();
        trigger.click();
        return;
      }
      if (e.key === 'Enter') {
        e.preventDefault();
        trigger.click();
        return;
      }
      if (e.key === 'ArrowDown' || e.key === 'Down') {
        e.preventDefault();
        closeAllExcept(wrap);
        setOpen(wrap, true);
        buildList(wrap, select);
        focusSelectedOrFirst(wrap);
        return;
      }
      if (e.key === 'Escape') {
        setOpen(wrap, false);
      }
    });

    panel.addEventListener('keydown', function (e) {
      var options = Array.prototype.slice.call(
        panel.querySelectorAll('.ss-variation-select__option:not([disabled])')
      );
      var cur = document.activeElement;
      var idx = options.indexOf(cur);
      if (e.key === 'Escape') {
        e.preventDefault();
        setOpen(wrap, false);
        trigger.focus({ preventScroll: true });
        return;
      }
      if (e.key === 'ArrowDown' || e.key === 'Down') {
        e.preventDefault();
        var next = Math.min(options.length - 1, idx < 0 ? 0 : idx + 1);
        if (options[next]) {
          options[next].focus({ preventScroll: true });
        }
        return;
      }
      if (e.key === 'ArrowUp' || e.key === 'Up') {
        e.preventDefault();
        var prev = Math.max(0, idx < 0 ? 0 : idx - 1);
        if (options[prev]) {
          options[prev].focus({ preventScroll: true });
        }
      }
    });
  }

  function enhanceForm(form) {
    if (!form) {
      return;
    }
    Array.prototype.forEach.call(form.querySelectorAll('select[name^="attribute_"]'), enhanceSelect);
  }

  function refreshAllInForm(form) {
    if (!form) {
      return;
    }
    window.requestAnimationFrame(function () {
      Array.prototype.forEach.call(form.querySelectorAll('.ss-variation-select'), function (wrap) {
        var sel = wrap.querySelector('.ss-variation-select__native');
        if (sel) {
          buildList(wrap, sel);
          syncFromSelect(wrap, sel);
        }
      });
    });
  }

  function init() {
    document.querySelectorAll('form.variations_form').forEach(enhanceForm);
  }

  function bindJQueryHooks() {
    if (bindJQueryHooks.done || typeof window.jQuery === 'undefined') {
      return;
    }
    bindJQueryHooks.done = true;
    var $ = window.jQuery;
    $(document.body)
      .on('wc_variation_form', 'form.variations_form', function () {
        enhanceForm(this);
      })
      .on('woocommerce_update_variation_values', 'form.variations_form', function () {
        refreshAllInForm(this);
      })
      .on('reset_data', 'form.variations_form', function () {
        refreshAllInForm(this);
      });
  }

  function boot() {
    init();
    bindJQueryHooks();
  }

  document.addEventListener('click', function () {
    document.querySelectorAll('.ss-variation-select.' + OPEN_CLASS).forEach(function (w) {
      setOpen(w, false);
    });
  });

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') {
      return;
    }
    document.querySelectorAll('.ss-variation-select.' + OPEN_CLASS).forEach(function (w) {
      setOpen(w, false);
    });
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
  } else {
    boot();
  }
})();
