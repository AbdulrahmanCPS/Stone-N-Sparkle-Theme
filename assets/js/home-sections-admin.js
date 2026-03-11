/**
 * Homepage Sections meta box: sections and per-section images (add/remove/reorder), media picker.
 */
(function () {
    'use strict';

    var container = document.getElementById('ss-home-sections-container');
    if (!container) return;

    var list = document.getElementById('ss-home-sections-list');
    var rowTpl = document.getElementById('ss-home-section-row-tpl');
    var imageSlotTpl = document.getElementById('ss-home-section-image-slot-tpl');
    var addSectionBtn = document.getElementById('ss-home-sections-add');
    if (!list || !rowTpl || !addSectionBtn) return;

    var rowHtml = rowTpl.innerHTML;
    var imageSlotHtml = imageSlotTpl ? imageSlotTpl.innerHTML : '';

    function nextSectionIndex() {
        var rows = list.querySelectorAll('.ss-home-section-row');
        var max = -1;
        rows.forEach(function (row) {
            var idx = parseInt(row.getAttribute('data-index'), 10);
            if (!isNaN(idx) && idx > max) max = idx;
        });
        return max + 1;
    }

    function reindexRows() {
        var rows = list.querySelectorAll('.ss-home-section-row');
        rows.forEach(function (row, i) {
            row.setAttribute('data-index', String(i));
            row.querySelectorAll('[name^="ss_home_sections["]').forEach(function (input) {
                var name = input.getAttribute('name');
                if (!name) return;
                input.setAttribute('name', name.replace(/^(ss_home_sections)\[\d+\]/, '$1[' + i + ']'));
            });
            var numEl = row.querySelector('.ss-home-section-number');
            if (numEl) numEl.textContent = String(i + 1);
        });
    }

    function reindexImageSlotsInRow(row) {
        var ul = row.querySelector('.ss-home-section-images-list');
        if (!ul) return;
        var slots = ul.querySelectorAll('.ss-home-section-image-slot');
        slots.forEach(function (slot, j) {
            slot.setAttribute('data-image-index', String(j));
            slot.querySelectorAll('.ss-home-section-input-image-url, .ss-home-section-input-image-alt').forEach(function (input) {
                var name = input.getAttribute('name');
                if (!name) return;
                var m = name.match(/^(ss_home_sections\[\d+\]\[images\])\[\d+\](\[url\]|\[alt\])$/);
                if (m) input.setAttribute('name', m[1] + '[' + j + ']' + m[2]);
            });
        });
    }

    function addSectionRow() {
        var idx = nextSectionIndex();
        var html = rowHtml.replace(/\{\{INDEX\}\}/g, String(idx));
        var li = document.createElement('li');
        li.className = 'ss-home-section-row ss-home-section-row--collapsed';
        li.setAttribute('data-index', String(idx));
        li.innerHTML = html;
        list.appendChild(li);
        bindSectionRowActions(li);
        reindexRows();
    }

    function removeSectionRow(ev) {
        var row = ev.target.closest('.ss-home-section-row');
        if (row) row.remove();
        reindexRows();
    }

    function addImageSlot(row) {
        if (!imageSlotHtml) return;
        var ul = row.querySelector('.ss-home-section-images-list');
        if (!ul) return;
        var sectionIndex = row.getAttribute('data-index');
        var slots = ul.querySelectorAll('.ss-home-section-image-slot');
        var imageIndex = slots.length;
        var html = imageSlotHtml
            .replace(/\{\{SECTION_INDEX\}\}/g, sectionIndex)
            .replace(/\{\{IMAGE_INDEX\}\}/g, String(imageIndex));
        var li = document.createElement('li');
        li.className = 'ss-home-section-image-slot';
        li.setAttribute('data-image-index', String(imageIndex));
        li.innerHTML = html;
        ul.appendChild(li);
        bindImageSlotActions(li, row);
        reindexImageSlotsInRow(row);
    }

    function removeImageSlot(ev) {
        var slot = ev.target.closest('.ss-home-section-image-slot');
        if (!slot) return;
        var row = slot.closest('.ss-home-section-row');
        slot.remove();
        if (row) reindexImageSlotsInRow(row);
    }

    function bindImageSlotActions(slot, row) {
        if (!slot || slot._slotBound) return;
        slot._slotBound = true;

        var uploadBtn = slot.querySelector('.ss-home-section-upload');
        var removeBtn = slot.querySelector('.ss-home-section-remove-image');
        var urlInput = slot.querySelector('.ss-home-section-input-image-url');
        var altInput = slot.querySelector('.ss-home-section-input-image-alt');
        var preview = slot.querySelector('.ss-home-section-image-preview');

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
            removeBtn.addEventListener('click', removeImageSlot);
        }
    }

    function bindSectionRowActions(row) {
        if (!row || row._bound) return;
        row._bound = true;

        var toggleBtn = row.querySelector('.ss-home-section-toggle');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', function () {
                row.classList.toggle('ss-home-section-row--collapsed');
                var expanded = !row.classList.contains('ss-home-section-row--collapsed');
                toggleBtn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                toggleBtn.setAttribute('aria-label', expanded ? 'Collapse section' : 'Expand section');
            });
            var collapsed = row.classList.contains('ss-home-section-row--collapsed');
            toggleBtn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            toggleBtn.setAttribute('aria-label', collapsed ? 'Expand section' : 'Collapse section');
        }

        var removeRowBtn = row.querySelector('.ss-home-section-remove');
        if (removeRowBtn) {
            removeRowBtn.addEventListener('click', removeSectionRow);
        }

        var addImageBtn = row.querySelector('.ss-home-section-add-image');
        if (addImageBtn) {
            addImageBtn.addEventListener('click', function () {
                addImageSlot(row);
            });
        }

        var ul = row.querySelector('.ss-home-section-images-list');
        if (ul) {
            row.querySelectorAll('.ss-home-section-image-slot').forEach(function (slot) {
                bindImageSlotActions(slot, row);
            });
            if (window.jQuery && window.jQuery.ui && window.jQuery.ui.sortable) {
                window.jQuery(ul).sortable({
                    handle: '.ss-home-section-image-handle',
                    axis: 'y',
                    update: function () {
                        reindexImageSlotsInRow(row);
                    }
                });
            }
        }

        row.querySelectorAll('.ss-home-section-upload, .ss-home-section-remove-image').forEach(function (btn) {
            var slot = btn.closest('.ss-home-section-image-slot');
            if (slot && !slot._slotBound) bindImageSlotActions(slot, row);
        });
    }

    list.querySelectorAll('.ss-home-section-row').forEach(bindSectionRowActions);

    addSectionBtn.addEventListener('click', addSectionRow);

    if (window.jQuery && window.jQuery.ui && window.jQuery.ui.sortable) {
        window.jQuery(list).sortable({
            handle: '.ss-home-section-handle',
            axis: 'y',
            update: function () {
                reindexRows();
            }
        });
    }
})();
