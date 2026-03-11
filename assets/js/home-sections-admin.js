/**
 * Homepage Sections meta box: add/remove/reorder rows, media picker for image.
 */
(function () {
    'use strict';

    var container = document.getElementById('ss-home-sections-container');
    if (!container) return;

    var list = document.getElementById('ss-home-sections-list');
    var tpl = document.getElementById('ss-home-section-row-tpl');
    var addBtn = document.getElementById('ss-home-sections-add');
    if (!list || !tpl || !addBtn) return;

    var rowHtml = tpl.innerHTML;

    function nextIndex() {
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
            var prefix = 'ss_home_sections[' + i + ']';
            row.querySelectorAll('[name^="ss_home_sections["]').forEach(function (input) {
                var name = input.getAttribute('name');
                if (!name) return;
                var match = name.match(/\]\[([^\]]+)\]$/);
                if (match) input.setAttribute('name', prefix + '[' + match[1] + ']');
            });
        });
    }

    function addRow() {
        var idx = nextIndex();
        var html = rowHtml.replace(/\{\{INDEX\}\}/g, String(idx));
        var li = document.createElement('li');
        li.className = 'ss-home-section-row';
        li.setAttribute('data-index', String(idx));
        li.innerHTML = html;
        list.appendChild(li);
        bindRowActions(li);
        reindexRows();
    }

    function removeRow(ev) {
        var row = ev.target.closest('.ss-home-section-row');
        if (row) row.remove();
        reindexRows();
    }

    function bindRowActions(row) {
        if (!row || row._bound) return;
        row._bound = true;

        var uploadBtn = row.querySelector('.ss-home-section-upload');
        var removeImgBtn = row.querySelector('.ss-home-section-remove-image');
        var imageInput = row.querySelector('.ss-home-section-input-image');
        var preview = row.querySelector('.ss-home-section-image-preview');
        var removeRowBtn = row.querySelector('.ss-home-section-remove');

        if (uploadBtn && imageInput && preview) {
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
                    imageInput.value = url;
                    preview.innerHTML = url ? '<img src="' + url.replace(/"/g, '&quot;') + '" alt="" style="max-width:120px;height:auto;" />' : '';
                    if (removeImgBtn) removeImgBtn.style.display = url ? '' : 'none';
                });
                frame.open();
            });
        }

        if (removeImgBtn && imageInput && preview) {
            removeImgBtn.addEventListener('click', function () {
                imageInput.value = '';
                preview.innerHTML = '';
                removeImgBtn.style.display = 'none';
            });
        }

        if (removeRowBtn) {
            removeRowBtn.addEventListener('click', removeRow);
        }
    }

    // Bind existing rows
    list.querySelectorAll('.ss-home-section-row').forEach(bindRowActions);

    addBtn.addEventListener('click', addRow);

    // jQuery UI Sortable for reorder (theme enqueues jquery-ui-sortable)
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
