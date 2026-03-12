/**
 * Collection Page lookbook meta box: add/remove/reorder image slots, media picker.
 * Two lists: "Lookbook before products" and "Lookbook after products".
 */
(function () {
    'use strict';

    var listBefore = document.getElementById('ss-collection-lookbook-before-list');
    var listAfter = document.getElementById('ss-collection-lookbook-after-list');
    var tplBefore = document.getElementById('ss-collection-lookbook-slot-tpl-before');
    var tplAfter = document.getElementById('ss-collection-lookbook-slot-tpl-after');
    if (!listBefore || !listAfter) return;

    var tplHtml = {
        before: tplBefore ? tplBefore.innerHTML : '',
        after: tplAfter ? tplAfter.innerHTML : ''
    };

    function nextIndex(list) {
        var slots = list.querySelectorAll('.ss-collection-lookbook-slot');
        var max = -1;
        slots.forEach(function (slot) {
            var idx = parseInt(slot.getAttribute('data-index'), 10);
            if (!isNaN(idx) && idx > max) max = idx;
        });
        return max + 1;
    }

    function reindexList(list, listKey) {
        var prefix = 'ss_collection_lookbook_' + listKey;
        var slots = list.querySelectorAll('.ss-collection-lookbook-slot');
        slots.forEach(function (slot, i) {
            slot.setAttribute('data-index', String(i));
            slot.querySelectorAll('.ss-collection-lookbook-input-url, .ss-collection-lookbook-input-alt').forEach(function (input) {
                var name = input.getAttribute('name');
                if (!name) return;
                var suffix = name.indexOf('[url]') !== -1 ? '[url]' : '[alt]';
                input.setAttribute('name', prefix + '[' + i + ']' + suffix);
            });
        });
    }

    function addSlot(listKey) {
        var list = listKey === 'before' ? listBefore : listAfter;
        var html = tplHtml[listKey];
        if (!html) return;

        var idx = nextIndex(list);
        html = html.replace(/\{\{INDEX\}\}/g, String(idx));

        var li = document.createElement('li');
        li.className = 'ss-collection-lookbook-slot';
        li.setAttribute('data-index', String(idx));
        li.innerHTML = html;
        list.appendChild(li);

        bindSlotActions(li, listKey);
        reindexList(list, listKey);
    }

    function removeSlot(ev) {
        var slot = ev.target.closest('.ss-collection-lookbook-slot');
        if (!slot) return;
        var list = slot.closest('.ss-collection-lookbook-list');
        var listKey = list && list.id === 'ss-collection-lookbook-before-list' ? 'before' : 'after';
        slot.remove();
        if (list) reindexList(list, listKey);
    }

    function bindSlotActions(slot, listKey) {
        if (!slot || slot._slotBound) return;
        slot._slotBound = true;

        var uploadBtn = slot.querySelector('.ss-collection-lookbook-upload');
        var removeBtn = slot.querySelector('.ss-collection-lookbook-remove');
        var urlInput = slot.querySelector('.ss-collection-lookbook-input-url');
        var altInput = slot.querySelector('.ss-collection-lookbook-input-alt');
        var preview = slot.querySelector('.ss-collection-lookbook-preview');

        if (uploadBtn && urlInput && preview) {
            uploadBtn.addEventListener('click', function () {
                var frame = wp.media({
                    title: 'Choose Lookbook Image',
                    button: { text: 'Use this image' },
                    multiple: false,
                    library: { type: 'image' }
                });
                frame.on('select', function () {
                    var att = frame.state().get('selection').first().toJSON();
                    var url = att.url || '';
                    var alt = (att.alt && typeof att.alt === 'string') ? att.alt : '';
                    urlInput.value = url;
                    if (altInput) altInput.value = alt;
                    preview.innerHTML = url ? '<img src="' + url.replace(/"/g, '&quot;') + '" alt="" style="max-width:80px;height:auto;" />' : '';
                    if (removeBtn) removeBtn.style.display = url ? '' : 'none';
                });
                frame.open();
            });
        }

        if (removeBtn) {
            removeBtn.addEventListener('click', removeSlot);
        }
    }

    function initList(list, listKey) {
        list.querySelectorAll('.ss-collection-lookbook-slot').forEach(function (slot) {
            bindSlotActions(slot, listKey);
        });
        if (window.jQuery && window.jQuery.ui && window.jQuery.ui.sortable) {
            window.jQuery(list).sortable({
                handle: '.ss-collection-lookbook-handle',
                axis: 'y',
                update: function () {
                    reindexList(list, listKey);
                }
            });
        }
    }

    document.querySelectorAll('.ss-collection-lookbook-add').forEach(function (btn) {
        var listKey = btn.getAttribute('data-list');
        if (listKey === 'before' || listKey === 'after') {
            btn.addEventListener('click', function () {
                addSlot(listKey);
            });
        }
    });

    initList(listBefore, 'before');
    initList(listAfter, 'after');
})();
