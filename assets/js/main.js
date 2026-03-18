/**
 * Header drawer menu (desktop + mobile)
 */
(function(){
  const burger = document.querySelector('.ss-burger');
  const drawer = document.getElementById('ssPrimaryNav');
  if (!burger || !drawer) return;

  const closeEls = drawer.querySelectorAll('[data-ss-drawer-close]');
  const panel = drawer.querySelector('.ss-drawer__panel');
  let lastFocus = null;

  const setOpen = (open) => {
    drawer.classList.toggle('is-open', open);
    drawer.setAttribute('aria-hidden', open ? 'false' : 'true');
    burger.setAttribute('aria-expanded', open ? 'true' : 'false');
    document.documentElement.classList.toggle('ss-no-scroll', open);

    if (open) {
      lastFocus = document.activeElement;
      // Focus the close button for accessibility (preventScroll avoids page jump on mobile).
      const closeBtn = drawer.querySelector('.ss-drawer__close');
      if (closeBtn) closeBtn.focus({ preventScroll: true });
    } else if (lastFocus && typeof lastFocus.focus === 'function') {
      lastFocus.focus();
      lastFocus = null;
    }
  };

  burger.addEventListener('click', () => setOpen(!drawer.classList.contains('is-open')));
  closeEls.forEach((el) => el.addEventListener('click', () => setOpen(false)));

  document.addEventListener('keydown', (e) => {
    if (!drawer.classList.contains('is-open')) return;

    if (e.key === 'Escape') {
      e.preventDefault();
      setOpen(false);
      return;
    }

    // Simple focus trap.
    if (e.key === 'Tab' && panel) {
      const focusables = panel.querySelectorAll('a, button, input, textarea, select, [tabindex]:not([tabindex="-1"])');
      if (!focusables.length) return;
      const first = focusables[0];
      const last = focusables[focusables.length - 1];

      if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    }
  });
})();

/**
 * Newsletter Popup (ACF Options)
 * - Keeps behavior simple: show by trigger + respect frequency.
 * - Styling is handled in CSS; JS only controls visibility + persistence.
 */
(function(){
  const cfg = (typeof window.SS_POPUP === 'object' && window.SS_POPUP) ? window.SS_POPUP : {};
  if (!cfg.enabled) return;

  const popup = document.getElementById('ssNewsletterPopup');
  if (!popup) return;

  const KEY = 'ss_popup_last_shown';
  const MS_PER_DAY = 24 * 60 * 60 * 1000;
  const freqDays = Number.isFinite(+cfg.frequencyDays) ? +cfg.frequencyDays : 0;
  const delayMs = Math.max(0, (Number.isFinite(+cfg.delaySeconds) ? +cfg.delaySeconds : 0) * 1000);
  const trigger = (cfg.trigger === 'on_scroll') ? 'on_scroll' : 'on_load';

  const now = () => Date.now();

  const storage = {
    get() {
      try {
        const v = window.localStorage.getItem(KEY);
        return v ? parseInt(v, 10) : 0;
      } catch (_) {
        return 0;
      }
    },
    set(ts) {
      try {
        window.localStorage.setItem(KEY, String(ts));
      } catch (_) {}
    }
  };

  const alreadyShownRecently = () => {
    if (freqDays <= 0) return false;
    const last = storage.get();
    if (!last || !Number.isFinite(last)) return false;
    return (now() - last) < (freqDays * MS_PER_DAY);
  };

  const setOpen = (open) => {
    popup.classList.toggle('is-open', open);
    popup.setAttribute('aria-hidden', open ? 'false' : 'true');
    document.documentElement.classList.toggle('ss-no-scroll', open);

    if (open) {
      storage.set(now());
      // Focus first field if present, else close button.
      const focusTarget = popup.querySelector('input, select, button');
      if (focusTarget && typeof focusTarget.focus === 'function') {
        setTimeout(() => focusTarget.focus(), 50);
      }
    }
  };

  const closeEls = popup.querySelectorAll('[data-ss-popup-close]');
  closeEls.forEach((el) => el.addEventListener('click', (e) => {
    e.preventDefault();
    setOpen(false);
  }));

  document.addEventListener('keydown', (e) => {
    if (!popup.classList.contains('is-open')) return;
    if (e.key === 'Escape') {
      e.preventDefault();
      setOpen(false);
    }
  });

  // Prevent accidental close when clicking inside dialog.
  const dialog = popup.querySelector('.ss-popup__dialog');
  if (dialog) {
    dialog.addEventListener('click', (e) => e.stopPropagation());
  }

  const scheduleShow = () => {
    if (alreadyShownRecently()) return;
    window.setTimeout(() => setOpen(true), delayMs);
  };

  if (trigger === 'on_load') {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', scheduleShow, { once: true });
    } else {
      scheduleShow();
    }
    return;
  }

  // on_scroll: show after 30% page scroll.
  let fired = false;
  const onScroll = () => {
    if (fired || alreadyShownRecently()) return;
    const doc = document.documentElement;
    const scrollTop = window.pageYOffset || doc.scrollTop || 0;
    const height = (doc.scrollHeight - doc.clientHeight) || 1;
    const ratio = scrollTop / height;
    if (ratio >= 0.3) {
      fired = true;
      window.removeEventListener('scroll', onScroll);
      scheduleShow();
    }
  };
  window.addEventListener('scroll', onScroll, { passive: true });
  // In case the page loads already scrolled (anchor links, restore), check once.
  onScroll();
})();

/**
 * Footer Newsletter: AJAX submission
 * Submits via fetch so the page never refreshes and the user stays in place.
 * Falls back to normal POST if fetch is unavailable.
 */
(function(){
  var form = document.querySelector('.ss-footer-newsletter__form');
  if (!form) return;

  form.addEventListener('submit', function(e) {
    e.preventDefault();

    var input = form.querySelector('.ss-footer-newsletter__input');
    var btn   = form.querySelector('.ss-footer-newsletter__btn');
    var email = input ? input.value.trim() : '';

    if (!email) {
      if (input) input.focus();
      return;
    }

    var savedText = btn ? btn.textContent : '';
    if (btn) {
      btn.disabled = true;
      btn.textContent = '…';
    }

    var body = new FormData(form);

    fetch(form.action, {
      method: 'POST',
      body: body,
    })
    .then(function() {
      showMsg(form, 'Thank you for subscribing!', 'success');
      if (input) input.value = '';
    })
    .catch(function() {
      showMsg(form, 'Something went wrong. Please try again.', 'error');
    })
    .finally(function() {
      if (btn) {
        btn.disabled = false;
        btn.textContent = savedText;
      }
    });
  });

  function showMsg(form, text, type) {
    var prev = form.querySelector('.ss-footer-newsletter__msg');
    if (prev) prev.remove();

    var el = document.createElement('p');
    el.className = 'ss-footer-newsletter__msg ss-footer-newsletter__msg--' + type;
    el.textContent = text;
    form.appendChild(el);

    setTimeout(function() {
      if (el.parentNode) el.remove();
    }, 5000);
  }
})();

/**
 * WooCommerce: Add-to-cart toast
 * - Replaces the default top notice bar with a small popup.
 * - Uses Woo's jQuery event: `added_to_cart`.
 */
(function(){
  const TEXT = 'Added to cart!';

  const ensureToast = () => {
    let toast = document.getElementById('ssToast');
    if (toast) return toast;

    toast = document.createElement('div');
    toast.id = 'ssToast';
    toast.className = 'ss-toast';
    toast.setAttribute('aria-live', 'polite');
    toast.setAttribute('aria-atomic', 'true');
    toast.innerHTML = `
      <div class="ss-toast__inner" role="status">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M20 6 9 17l-5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <span class="ss-toast__text"></span>
      </div>
    `;
    document.body.appendChild(toast);
    return toast;
  };

  let hideTimer = null;
  const show = (msg) => {
    const toast = ensureToast();
    const textEl = toast.querySelector('.ss-toast__text');
    if (textEl) textEl.textContent = msg || TEXT;

    toast.classList.add('is-visible');
    if (hideTimer) window.clearTimeout(hideTimer);
    hideTimer = window.setTimeout(() => {
      toast.classList.remove('is-visible');
    }, 2200);
  };

  // WooCommerce triggers `added_to_cart` on document.body.
  if (window.jQuery) {
    window.jQuery(document.body).on('added_to_cart', function(){
      show(TEXT);
    });
  }
})();

/**
 * My Account: logout link requires confirmation before redirecting.
 * Prevents accidental instant logout; presents as a button in the nav.
 */
(function(){
  const nav = document.querySelector('.woocommerce-MyAccount-navigation');
  if (!nav) return;
  const logoutLink = nav.querySelector('.woocommerce-MyAccount-navigation-link--customer-logout a');
  if (!logoutLink) return;

  logoutLink.addEventListener('click', function(e){
    e.preventDefault();
    if (window.confirm('Are you sure you want to log out?')) {
      window.location.href = logoutLink.getAttribute('href') || logoutLink.href;
    }
  });
})();

/**
 * Size chart PDF:
 * - Inserts a "Size chart" link next to the visible Size variation label.
 * - Opens the PDF in a modal viewer using the theme's `.ss-popup` styling.
 */
(function(){
  const initSizeChartModal = () => {
    const template = document.querySelector('.ss-size-chart-link-template');
    if (!template) return;

    const popup = document.getElementById('ssSizeChartPopup');
    if (!popup) return;

    const iframe = popup.querySelector('iframe.ss-size-chart-popup__iframe');
    if (!iframe) return;

    const pdfUrl = template.getAttribute('data-ss-size-chart-pdf-url') || template.getAttribute('href') || '';
    if (!pdfUrl) return;

    const closeEls = popup.querySelectorAll('[data-ss-popup-close]');

    let lastFocus = null;
    const setOpen = (open) => {
      popup.classList.toggle('is-open', open);
      popup.setAttribute('aria-hidden', open ? 'false' : 'true');
      document.documentElement.classList.toggle('ss-no-scroll', open);

      if (open) {
        lastFocus = document.activeElement;
        iframe.src = pdfUrl;
        const closeBtn = popup.querySelector('[data-ss-popup-close]');
        if (closeBtn && typeof closeBtn.focus === 'function') {
          // Prevent focus from jumping the page.
          closeBtn.focus({ preventScroll: true });
        }
      } else {
        // Clearing src stops PDF from keeping network/CPU resources.
        iframe.src = '';
        if (lastFocus && typeof lastFocus.focus === 'function') {
          lastFocus.focus({ preventScroll: true });
        }
      }
    };

    // Bind close.
    closeEls.forEach((el) => {
      el.addEventListener('click', function(e){
        e.preventDefault();
        setOpen(false);
      });
    });

    document.addEventListener('keydown', function(e){
      if (!popup.classList.contains('is-open')) return;
      if (e.key === 'Escape') {
        e.preventDefault();
        setOpen(false);
      }
    });

    // Insert link next to the visible Size label.
    const variationsForm = document.querySelector('form.variations_form');
    if (!variationsForm) return;

    const insertOnce = () => {
      const selects = variationsForm.querySelectorAll('select[name^="attribute_"]');
      if (!selects.length) return false;

      const insertForSelect = (sel) => {
        const name = sel.getAttribute('name') || '';
        const attrKey = name.replace(/^attribute_/, '');
        if (!attrKey) return false;

        const normalized = attrKey === 'size' || (attrKey.indexOf('pa_') === 0 && attrKey.substring(3) === 'size')
          ? 'size'
          : null;

        if (normalized !== 'size') return false;

        // Find the cell that contains the "Size" label (so we can append the link there).
        // WooCommerce uses table: tr > td.label + td.value; theme CSS also targets .variations .label.
        const row = sel.closest('tr');
        const valueCell = sel.closest('td') || sel.closest('.value') || sel.parentElement;
        const labelCell =
          (row && (row.querySelector('td.label') || row.querySelector('.label'))) ||
          (valueCell && valueCell.previousElementSibling && (valueCell.previousElementSibling.classList.contains('label') || valueCell.previousElementSibling.matches('td.label'))) ? valueCell.previousElementSibling : null;

        if (!labelCell) return false;

        if (labelCell.querySelector('.ss-size-chart-link')) return true;

        const link = document.createElement('a');
        link.className = 'ss-size-chart-link';
        link.href = pdfUrl;
        link.setAttribute('data-ss-size-chart-pdf-url', pdfUrl);
        link.textContent = (template.textContent || 'Size chart').trim();
        link.addEventListener('click', function(e){
          e.preventDefault();
          setOpen(true);
        });

        labelCell.appendChild(link);
        return true;
      };

      let inserted = false;
      selects.forEach((sel) => {
        if (inserted) return;
        inserted = insertForSelect(sel) === true;
      });

      // Fallback: if Woo markup is unexpected, try matching any .label whose text is "Size".
      if (!inserted) {
        const labels = variationsForm.querySelectorAll('.label, td.label');
        const sizeLabel = Array.prototype.find.call(labels, (el) => {
          const t = (el.textContent || '').toLowerCase().trim();
          return t === 'size' || t.includes('size');
        });
        if (sizeLabel && !sizeLabel.querySelector('.ss-size-chart-link')) {
          const link = document.createElement('a');
          link.className = 'ss-size-chart-link';
          link.href = pdfUrl;
          link.setAttribute('data-ss-size-chart-pdf-url', pdfUrl);
          link.textContent = (template.textContent || 'Size chart').trim();
          link.addEventListener('click', function(e){
            e.preventDefault();
            setOpen(true);
          });
          sizeLabel.appendChild(link);
          inserted = true;
        }
      }

      return inserted;
    };

    // Try immediately.
    if (insertOnce()) return;

    // Retry in case the variations form is injected after DOMContentLoaded.
    let observer = null;
    const maxAttempts = 20;
    let attempts = 0;
    observer = new MutationObserver(() => {
      attempts++;
      if (attempts >= maxAttempts) {
        if (observer) observer.disconnect();
        observer = null;
        return;
      }
      if (insertOnce()) {
        if (observer) observer.disconnect();
        observer = null;
      }
    });
    observer.observe(document.body, { childList: true, subtree: true });
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSizeChartModal, { once: true });
  } else {
    initSizeChartModal();
  }
})();