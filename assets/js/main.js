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
      // Focus the close button for accessibility.
      const closeBtn = drawer.querySelector('.ss-drawer__close');
      if (closeBtn) closeBtn.focus();
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