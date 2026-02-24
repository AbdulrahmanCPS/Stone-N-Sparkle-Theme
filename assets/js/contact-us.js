/* Contact Us – phone country dropdown enhancer (non-invasive) */

(function(){
  'use strict';

  function ready(fn){
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn);
    else fn();
  }

  function hasIntlPhoneWidget(formEl){
    if (!formEl) return false;
    return !!formEl.querySelector(
      '.iti, .intl-tel-input, [data-intl-tel-input], select[name*="country" i][data-phone], .ff_phone, .ff-phone'
    );
  }

  function findPhoneInput(formEl){
    if (!formEl) return null;
    const selectors = ['input[type="tel"]', 'input[name*="phone" i]', 'input[id*="phone" i]'];
    for (const sel of selectors){
      const el = formEl.querySelector(sel);
      if (el) return el;
    }
    return null;
  }

  function buildCountryOptions(){
    return [
      { label: 'United Arab Emirates (+971)', value: '+971' },
      { label: 'Saudi Arabia (+966)', value: '+966' },
      { label: 'Qatar (+974)', value: '+974' },
      { label: 'Kuwait (+965)', value: '+965' },
      { label: 'Oman (+968)', value: '+968' },
      { label: 'Bahrain (+973)', value: '+973' },
      { label: 'United States (+1)', value: '+1' },
      { label: 'United Kingdom (+44)', value: '+44' },
      { label: 'India (+91)', value: '+91' },
      { label: 'Pakistan (+92)', value: '+92' },
      { label: 'Egypt (+20)', value: '+20' },
      { label: 'Turkey (+90)', value: '+90' },
    ];
  }

  function injectCountryPhoneRow(formEl, phoneInput){
    if (!formEl || !phoneInput) return;
    if (formEl.querySelector('.ss-contact__phone-row')) return;

    const labelCountry = (window.SS_CONTACT_US && window.SS_CONTACT_US.countryLabel) ? window.SS_CONTACT_US.countryLabel : 'Country';
    const required = !!(window.SS_CONTACT_US && window.SS_CONTACT_US.countryRequired);

    const row = document.createElement('div');
    row.className = 'ss-contact__phone-row';

    const left = document.createElement('div');
    const right = document.createElement('div');

    const l1 = document.createElement('label');
    l1.textContent = labelCountry + (required ? ' *' : '');
    l1.htmlFor = 'ss-phone-country';

    const sel = document.createElement('select');
    sel.id = 'ss-phone-country';
    sel.name = 'ss_phone_country_code';
    if (required) sel.required = true;

    for (const o of buildCountryOptions()){
      const opt = document.createElement('option');
      opt.value = o.value;
      opt.textContent = o.label;
      sel.appendChild(opt);
    }

    left.appendChild(l1);
    left.appendChild(sel);

    const l2 = document.createElement('label');
    l2.textContent = 'Phone';
    l2.htmlFor = 'ss-phone-number';

    const inp = document.createElement('input');
    inp.type = 'tel';
    inp.id = 'ss-phone-number';
    inp.name = 'ss_phone_number';
    inp.autocomplete = 'tel';
    inp.inputMode = 'tel';
    inp.placeholder = phoneInput.getAttribute('placeholder') || 'Enter your phone number';

    right.appendChild(l2);
    right.appendChild(inp);

    row.appendChild(left);
    row.appendChild(right);

    const group = phoneInput.closest('.ff-el-group') || phoneInput.closest('p') || phoneInput.parentElement;
    if (group && group.parentNode) group.parentNode.insertBefore(row, group);
    else formEl.insertBefore(row, formEl.firstChild);

    // Keep original for submission, but hide it from layout.
    phoneInput.style.position = 'absolute';
    phoneInput.style.left = '-9999px';
    phoneInput.style.width = '1px';
    phoneInput.style.height = '1px';
    phoneInput.setAttribute('aria-hidden', 'true');
    phoneInput.tabIndex = -1;

    function sync(){
      const code = sel.value || '';
      const num = inp.value || '';
      phoneInput.value = (code + ' ' + num).trim();
      phoneInput.dispatchEvent(new Event('input', { bubbles: true }));
    }
    sel.addEventListener('change', sync);
    inp.addEventListener('input', sync);
    sync();
  }

  ready(function(){
    const root = document.querySelector('.ss-contact');
    if (!root) return;
    const formEl = root.querySelector('form');
    if (!formEl) return;

    // If the form already provides its own country-code select (e.g. CF7 markup
    // with #gby-country-code), do NOT inject a second country selector.
    if (formEl.querySelector('#gby-country-code')) return;

    if (window.SS_CONTACT_US && window.SS_CONTACT_US.countryEnabled === false) return;
    if (hasIntlPhoneWidget(formEl)) return;

    const phoneInput = findPhoneInput(formEl);
    if (!phoneInput) return;

    injectCountryPhoneRow(formEl, phoneInput);
  });
})();
