(function () {
    'use strict';

    var config = window.ContactUsFields || {};
    var labels = config.labels || {};
    var types = config.types || ['text', 'textarea', 'email', 'tel', 'number', 'url', 'date', 'time', 'datetime', 'password', 'hidden', 'select', 'radio', 'checkbox', 'multicheckbox'];
    var typesWithValues = ['select', 'radio', 'multicheckbox'];

    function t(key, fallback) {
        return labels[key] || fallback;
    }

    // ---- YAML helpers ----------------------------------------------------

    function parseYaml(text) {
        if (!window.jsyaml) {
            return null;
        }
        try {
            var data = window.jsyaml.load(text);
        } catch (e) {
            return null;
        }
        if (data === null || data === undefined) {
            return {};
        }
        return (typeof data === 'object' && !Array.isArray(data)) ? data : null;
    }

    function dumpYaml(map) {
        if (!window.jsyaml || !Object.keys(map).length) {
            return '';
        }
        return window.jsyaml.dump(map, { lineWidth: -1, noRefs: true });
    }

    // An option value keeps its type: a scalar, a boolean/number, or an inline
    // map/list typed as YAML (e.g. "{class: required}").
    function optionValueToString(value) {
        if (value === null || value === undefined) {
            return '';
        }
        if (typeof value === 'object') {
            return window.jsyaml ? window.jsyaml.dump(value, { flowLevel: 0, lineWidth: -1 }).trim() : JSON.stringify(value);
        }
        return String(value);
    }

    function stringToOptionValue(str) {
        str = (str == null ? '' : String(str)).trim();
        if (str === '') {
            return '';
        }
        if (window.jsyaml) {
            try {
                return window.jsyaml.load(str);
            } catch (e) {
                return str;
            }
        }
        return str;
    }

    // ---- YAML entry <-> row data -----------------------------------------

    function valuesToPairs(values) {
        if (Array.isArray(values)) {
            return values.map(function (v) { return { k: String(v), v: '' }; });
        }
        if (values && typeof values === 'object') {
            return Object.keys(values).map(function (k) { return { k: k, v: String(values[k]) }; });
        }
        return [];
    }

    function mapToPairs(obj, valueToString) {
        if (!obj || typeof obj !== 'object') {
            return [];
        }
        return Object.keys(obj).map(function (k) {
            return { k: k, v: valueToString ? valueToString(obj[k]) : String(obj[k]) };
        });
    }

    function entryToData(name, value) {
        var data = { name: name, type: 'text', label: '', required: false, values: [], options: [], attributes: [] };
        if (value === null || value === undefined) {
            return data;
        }
        if (typeof value !== 'object') {
            data.label = String(value);
        } else {
            data.label = value.label != null ? String(value.label) : '';
            data.type = (value.type || 'text').toLowerCase();
            data.required = !!value.required;
            data.values = valuesToPairs(value.values);
            data.options = mapToPairs(value.options, optionValueToString);
            data.attributes = mapToPairs(value.attributes, String);
        }
        if (data.label.indexOf('* ') === 0) {
            data.required = true;
            data.label = data.label.slice(2).trim();
        }
        return data;
    }

    function pairsToValues(pairs) {
        var kept = pairs.filter(function (p) { return p.k.trim() !== ''; });
        if (!kept.length) {
            return null;
        }
        var hasLabel = kept.some(function (p) { return p.v.trim() !== ''; });
        if (!hasLabel) {
            return kept.map(function (p) { return p.k; });
        }
        var map = {};
        kept.forEach(function (p) { map[p.k] = p.v.trim() !== '' ? p.v : p.k; });
        return map;
    }

    function pairsToMap(pairs, valueParser) {
        var map = {};
        pairs.forEach(function (p) {
            if (p.k.trim() === '') {
                return;
            }
            map[p.k] = valueParser ? valueParser(p.v) : p.v;
        });
        return Object.keys(map).length ? map : null;
    }

    function rowToEntry(row) {
        var name = (row.name || '').trim();
        if (!name) {
            return null;
        }
        var type = row.type || 'text';
        var entry = {};
        if (row.label) {
            entry.label = row.label;
        }
        if (type !== 'text') {
            entry.type = type;
        }
        if (row.required) {
            entry.required = true;
        }
        if (typesWithValues.indexOf(type) !== -1) {
            var values = pairsToValues(row.values);
            if (values) {
                entry.values = values;
            }
        }
        var options = pairsToMap(row.options, stringToOptionValue);
        if (options) {
            entry.options = options;
        }
        var attributes = pairsToMap(row.attributes, function (v) { return v; });
        if (attributes) {
            entry.attributes = attributes;
        }
        var keys = Object.keys(entry);
        if (!keys.length) {
            return { name: name, value: null };
        }
        if (keys.length === 1 && keys[0] === 'label') {
            return { name: name, value: entry.label };
        }
        return { name: name, value: entry };
    }

    // ---- Form mode: rows editor ------------------------------------------

    function makePair(pair, keyPlaceholder, valuePlaceholder) {
        var $ = window.jQuery;
        var $pair = $('<div class="cf-pair"></div>');
        $pair.append($('<input class="cf-pk" type="text">').attr('placeholder', keyPlaceholder).val(pair.k));
        $pair.append($('<input class="cf-pv" type="text">').attr('placeholder', valuePlaceholder).val(pair.v));
        $pair.append('<button type="button" class="cf-pair-del o-icon-delete" title="' + t('remove', 'Remove') + '"></button>');
        return $pair;
    }

    function makeGroup(kind, title, keyPlaceholder, valuePlaceholder, pairs) {
        var $ = window.jQuery;
        var $sub = $('<div class="cf-sub"></div>').attr('data-kind', kind).attr('data-key-ph', keyPlaceholder).attr('data-val-ph', valuePlaceholder);
        $sub.append('<span class="cf-sub-title">' + title + '</span>');
        var $pairs = $('<div class="cf-pairs"></div>');
        pairs.forEach(function (p) { $pairs.append(makePair(p, keyPlaceholder, valuePlaceholder)); });
        $sub.append($pairs);
        if (!pairs.length) {
            $sub.hide();
        }
        return $sub;
    }

    function makeRow(data) {
        data = data || { name: '', type: 'text', label: '', required: false, values: [], options: [], attributes: [] };
        var $ = window.jQuery;
        var $row = $('<div class="cf-row"></div>');
        var $line1 = $('<div class="cf-line cf-line1"></div>');

        $line1.append('<span class="cf-drag" title="' + t('drag', 'Move') + '">☰</span>');
        $line1.append('<span class="cf-move"><button type="button" class="cf-up" title="' + t('moveUp', 'Move up') + '">▲</button><button type="button" class="cf-down" title="' + t('moveDown', 'Move down') + '">▼</button></span>');
        $line1.append($('<input class="cf-name" type="text">').attr('placeholder', t('colName', 'name')).val(data.name));
        $line1.append($('<input class="cf-label" type="text">').attr('placeholder', t('colLabel', 'label')).val(data.label));
        var $type = $('<select class="cf-type"></select>');
        types.forEach(function (ty) {
            $type.append($('<option></option>').attr('value', ty).text(ty).prop('selected', ty === data.type));
        });
        $line1.append($type);
        var key = t('colKey', 'key');
        var val = t('colValue', 'value');
        var $subactions = $('<div class="cf-subactions"></div>');
        $subactions.append('<button type="button" class="cf-remove-field o-icon-delete" title="' + t('deleteField', 'Delete this field') + '"></button>');
        $subactions.append($('<label class="cf-required-wrap"><input type="checkbox" class="cf-required"> ' + t('colRequired', 'required') + '</label>').find('.cf-required').prop('checked', data.required).end());
        $subactions.append('<button type="button" class="cf-subaction o-icon-add" data-kind="values">' + t('addValue', 'Add a value') + '</button>');
        $subactions.append('<button type="button" class="cf-subaction o-icon-add" data-kind="attributes">' + t('addAttribute', 'Add an attribute') + '</button>');
        $subactions.append('<button type="button" class="cf-subaction o-icon-add" data-kind="options">' + t('addOption', 'Add an option') + '</button>');

        var $groups = $('<div class="cf-groups"></div>');
        $groups.append(makeGroup('values', t('valuesTitle', 'Values'), val, t('colLabel', 'label'), data.values));
        $groups.append(makeGroup('attributes', t('attributesTitle', 'Attributes'), key, val, data.attributes));
        $groups.append(makeGroup('options', t('optionsTitle', 'Options'), key, val, data.options));

        $row.append($line1).append($subactions).append($groups);
        toggleValues($row);
        return $row;
    }

    function toggleValues($row) {
        var type = $row.find('.cf-type').val();
        var withValues = typesWithValues.indexOf(type) !== -1;
        $row.find('.cf-subaction[data-kind="values"]').toggle(withValues);
        var $values = $row.find('.cf-sub[data-kind="values"]');
        if (!withValues) {
            $values.hide();
        } else if ($values.find('.cf-pair').length) {
            $values.show();
        }
    }

    function readPairs($row, kind) {
        var $ = window.jQuery;
        return $row.find('.cf-sub[data-kind="' + kind + '"] .cf-pair').map(function () {
            var $p = $(this);
            return { k: $p.find('.cf-pk').val(), v: $p.find('.cf-pv').val() };
        }).get();
    }

    function readRow($row) {
        return {
            name: $row.find('.cf-name').val(),
            type: $row.find('.cf-type').val(),
            label: $row.find('.cf-label').val(),
            required: $row.find('.cf-required').is(':checked'),
            values: readPairs($row, 'values'),
            options: readPairs($row, 'options'),
            attributes: readPairs($row, 'attributes')
        };
    }

    function serialize($rows, textarea) {
        var map = {};
        $rows.find('.cf-row').each(function () {
            var entry = rowToEntry(readRow(window.jQuery(this)));
            if (entry) {
                map[entry.name] = entry.value;
            }
        });
        textarea.value = dumpYaml(map);
    }

    // ---- Preview: read-only mock of the form -----------------------------

    function previewInput(field) {
        var $ = window.jQuery;
        var type = field.type;
        if (type === 'textarea') {
            return $('<textarea rows="3" disabled></textarea>');
        }
        if (type === 'select') {
            var $select = $('<select disabled></select>');
            (field.values || []).forEach(function (p) {
                $select.append($('<option></option>').text(p.v || p.k));
            });
            return $select;
        }
        if (type === 'radio' || type === 'multicheckbox') {
            var $wrap = $('<span class="cf-preview-choices"></span>');
            var itype = type === 'radio' ? 'radio' : 'checkbox';
            (field.values || []).forEach(function (p) {
                $wrap.append(
                    $('<label class="cf-preview-choice"></label>')
                        .append($('<input disabled>').attr('type', itype))
                        .append(document.createTextNode(' ' + (p.v || p.k)))
                );
            });
            return $wrap;
        }
        if (type === 'checkbox') {
            return $('<input type="checkbox" disabled>');
        }
        var htmlType = {
            tel: 'tel', email: 'email', number: 'number', url: 'url',
            date: 'date', time: 'time', datetime: 'datetime-local',
            password: 'password'
        }[type] || 'text';
        return $('<input disabled>').attr('type', htmlType);
    }

    function renderPreview(fields) {
        var $ = window.jQuery;
        var $form = $('<div class="cf-preview"></div>');
        fields.forEach(function (field) {
            if (field.type === 'hidden' || !(field.name || '').trim()) {
                return;
            }
            var labelText = (field.label || field.name) + (field.required ? ' *' : '');
            $form.append(
                $('<div class="cf-preview-field"></div>')
                    .append($('<label></label>').text(labelText))
                    .append(previewInput(field))
            );
        });
        $form.append('<div class="cf-preview-actions"><button type="button" disabled>' + t('send', 'Send message') + '</button></div>');
        return $form;
    }

    function buildEditor(textarea) {
        var $ = window.jQuery;
        if (!$ || !window.jsyaml) {
            return;
        }
        var $textarea = $(textarea);
        var enableForm = textarea.dataset.enableForm !== '0';
        var enableYaml = textarea.dataset.enableYaml !== '0';
        var enablePreview = textarea.dataset.enablePreview === '1';
        var defaultDisplay = textarea.dataset.defaultDisplay || 'form';
        if (!enableForm && !enableYaml) {
            enableYaml = true;
        }

        var $toggle = $('<button type="button" class="button contactus-fields-toggle">' + t('editAsForm', 'Edit as a form') + '</button>');
        var $editor = $('<div class="contactus-fields-editor" style="display:none;"></div>');
        var $rows = $('<div class="cf-rows"></div>');
        var $add = $('<button type="button" class="button cf-add o-icon-add" style="display:none;">' + t('addField', 'Add a field') + '</button>');
        var $preview = $('<button type="button" class="button cf-preview-toggle">' + t('preview', 'Preview') + '</button>');
        var $actions = $('<div class="contactus-fields-actions"></div>').append($add);
        // The toggle only makes sense when both modes are available.
        if (enableForm && enableYaml) {
            $actions.append($toggle);
        }
        if (enablePreview) {
            $actions.append($preview);
        }
        var $previewPanel = $('<div class="cf-preview-panel" style="display:none;"></div>');
        $editor.append($rows);
        $textarea.after($editor);
        $editor.after($actions);
        $actions.after($previewPanel);

        function currentFields() {
            var list = [];
            if ($editor.is(':visible')) {
                $rows.find('.cf-row').each(function () {
                    var data = readRow($(this));
                    if ((data.name || '').trim()) {
                        list.push(data);
                    }
                });
            } else {
                var map = parseYaml(textarea.value);
                if (map) {
                    Object.keys(map).forEach(function (name) {
                        list.push(entryToData(name, map[name]));
                    });
                }
            }
            return list;
        }

        function refreshPreview() {
            if ($previewPanel.is(':visible')) {
                $previewPanel.empty().append(renderPreview(currentFields()));
            }
        }

        $preview.on('click', function () {
            if ($previewPanel.is(':visible')) {
                $previewPanel.hide();
                $preview.text(t('preview', 'Preview'));
                return;
            }
            refreshPreviewForce();
            $previewPanel.show();
            $preview.text(t('hidePreview', 'Hide preview'));
        });
        function refreshPreviewForce() {
            $previewPanel.empty().append(renderPreview(currentFields()));
        }
        $editor.on('input change', refreshPreview);
        $editor.on('click', '.cf-remove-field, .cf-pair-del, .cf-subaction, .cf-up, .cf-down, .cf-add', function () {
            window.setTimeout(refreshPreview, 0);
        });

        // Disable the up button of the first field and the down button of the
        // last field.
        function updateMoveButtons() {
            var $all = $rows.find('.cf-row');
            $all.find('.cf-up, .cf-down').prop('disabled', false);
            $all.first().find('.cf-up').prop('disabled', true);
            $all.last().find('.cf-down').prop('disabled', true);
        }

        // Native drag and drop to reorder fields (jQuery UI sortable is not
        // loaded on the settings pages).
        var dragged = null;
        $editor.on('mousedown', '.cf-drag', function () {
            $(this).closest('.cf-row').attr('draggable', 'true');
        });
        $editor.on('dragstart', '.cf-row', function (event) {
            dragged = this;
            $(this).addClass('cf-dragging');
            try {
                event.originalEvent.dataTransfer.effectAllowed = 'move';
                event.originalEvent.dataTransfer.setData('text/plain', '');
            } catch (e) {
                // Ignore browsers refusing setData.
            }
        });
        $editor.on('dragend', '.cf-row', function () {
            $(this).removeAttr('draggable').removeClass('cf-dragging');
            dragged = null;
            updateMoveButtons();
            serialize($rows, textarea);
        });
        $editor.on('dragover', '.cf-row', function (event) {
            if (!dragged || dragged === this) {
                return;
            }
            event.preventDefault();
            var rect = this.getBoundingClientRect();
            var after = (event.originalEvent.clientY - rect.top) > rect.height / 2;
            this.parentNode.insertBefore(dragged, after ? this.nextSibling : this);
        });

        $editor.on('input change', 'input, select', function () {
            if ($(this).hasClass('cf-type')) {
                toggleValues($(this).closest('.cf-row'));
            }
            serialize($rows, textarea);
        });
        $editor.on('click', '.cf-remove-field', function () {
            $(this).closest('.cf-row').remove();
            updateMoveButtons();
            serialize($rows, textarea);
        });
        $editor.on('click', '.cf-pair-del', function () {
            var $sub = $(this).closest('.cf-sub');
            $(this).closest('.cf-pair').remove();
            if (!$sub.find('.cf-pair').length) {
                $sub.hide();
            }
            serialize($rows, textarea);
        });
        $editor.on('click', '.cf-subaction', function () {
            var kind = $(this).attr('data-kind');
            var $sub = $(this).closest('.cf-row').find('.cf-sub[data-kind="' + kind + '"]');
            $sub.show();
            $sub.find('.cf-pairs').append(makePair({ k: '', v: '' }, $sub.attr('data-key-ph'), $sub.attr('data-val-ph')));
        });
        $editor.on('click', '.cf-up', function () {
            var $row = $(this).closest('.cf-row');
            $row.prev('.cf-row').before($row);
            updateMoveButtons();
            serialize($rows, textarea);
        });
        $editor.on('click', '.cf-down', function () {
            var $row = $(this).closest('.cf-row');
            $row.next('.cf-row').after($row);
            updateMoveButtons();
            serialize($rows, textarea);
        });
        $add.on('click', function () {
            $rows.append(makeRow());
            updateMoveButtons();
            refreshPreview();
        });
        $textarea.on('input', refreshPreview);

        function showText() {
            serialize($rows, textarea);
            $editor.hide();
            $add.hide();
            $textarea.show();
            $toggle.text(t('editAsForm', 'Edit as a form'));
            refreshPreview();
        }

        function showForm(silent) {
            var map = parseYaml(textarea.value);
            if (map === null) {
                if (!silent) {
                    window.alert(t('invalidYaml', 'The YAML cannot be parsed. Fix it before switching to the form.'));
                }
                return false;
            }
            $rows.empty();
            Object.keys(map).forEach(function (name) {
                $rows.append(makeRow(entryToData(name, map[name])));
            });
            updateMoveButtons();
            $textarea.hide();
            $editor.show();
            $add.show();
            $toggle.text(t('editAsText', 'Edit as text'));
            refreshPreview();
            return true;
        }

        $toggle.on('click', function () {
            if ($editor.is(':visible')) {
                showText();
            } else {
                showForm(false);
            }
        });

        // Open the default display, honoring the enabled modes. Do not call
        // showText() at init: the form is empty, so it would wipe the textarea.
        if (enableForm && (defaultDisplay === 'form' || !enableYaml)) {
            showForm(true);
        }
    }

    // ---- Init ------------------------------------------------------------

    function init() {
        var textareas = document.querySelectorAll('textarea.contactus-fields-dsl');
        Array.prototype.forEach.call(textareas, function (textarea) {
            if (textarea.dataset.contactusFieldsReady) {
                return;
            }
            textarea.dataset.contactusFieldsReady = '1';
            buildEditor(textarea);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
