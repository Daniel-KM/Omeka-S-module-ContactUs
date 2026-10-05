'use strict';

/**
 * Requires common-dialog.js.
 */

(function () {
    $(document).ready(function() {

        /**
         * Use common-dialog.js.
         *
         * @see Comment, ContactUs, Contribute, Generate, Guest, Resa, SearchHistory, Selection, TwoFactorAuth.
         */

        /**
         * Check if a resource is selected (local session).
         */
        const isSelectedForContact = function (resourceId) {
            let selectedResourceIds = localStorage.getItem('contactus_selectedIds');
            if (selectedResourceIds !== null) {
                selectedResourceIds = JSON.parse(selectedResourceIds);
                resourceId = parseInt(resourceId);
                if (resourceId) {
                    return selectedResourceIds.includes(resourceId);
                }
            }
            return false;
        };

        /**
         * On load, check/uncheck contact us selection from local storage.
         */
       $('.contact-us-selection[data-local-storage="1"]').each(function(i, obj) {
            // Don't check the template itself during the init.
            const resourceId = $(this).val();
            if (!isNaN(resourceId)) {
                $(this).prop('checked', isSelectedForContact(resourceId));
            }
        });

        /**
         * On load, prepare the selection list for contact for visitor with local storage.
         */
        $('.resource-list.contact-us-template').each(function() {
            /**
             * Escape text as html.
             */
            const escapeHtml = function(string) {
                return ('' + string)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;');
            };

            /**
             * Fill a template.
             *
             * Replace {placeholder} by resource data or some specific data.
             */
            const fillTemplate = function(template, resource) {
                var output = template;
                const regex = /\{[\w:-]+\}/gm;
                var matches, val, vals, property, isMulti;
                while ((matches = regex.exec(template)) !== null) {
                    if (matches.index === regex.lastIndex) regex.lastIndex++;
                    matches.forEach((match, groupIndex) => {
                        switch (match) {
                            case '{resource_url}':
                                val = urlSiteBase + '/' + resourceController(resource) + '/' + resource['o:id'];
                                output = output.replace(match, val);
                                break;
                            case '{thumbnail_url}':
                                val = resource['thumbnail_display_urls'] ? resource['thumbnail_display_urls']['medium'] : null;
                                val = val ? val : defaultThumbnailUrl;
                                output = output.replace(match, val);
                                break;
                            case '{thumbnail_label}':
                                val = resource['thumbnail_display_urls']
                                    ? (resource['o:title'] ? resource['o:title'] : defaultThumbnailLabel)
                                    : defaultThumbnailLabel;
                                output = output.replace(match, escapeHtml(val));
                                break;
                            case '{resource_id}':
                                val = resource['o:id'] ? resource['o:id'] : '';
                                output = output.replace(match, escapeHtml(val));
                                break;
                            case '{resource_id_checked}':
                                val = resource['o:id'] ? resource['o:id'] : '';
                                output = output.replace(match, isSelectedForContact(val) ? 'checked="checked"' : '');
                                break;
                            case '{resource_title}':
                                val = resource['o:title'] ? resource['o:title'] : defaultUntitled;
                                output = output.replace(match, escapeHtml(val));
                                break;
                            case '{resource_description}':
                                val = resource['dcterms:description'] && resource['dcterms:description'][0] && resource['dcterms:description'][0]['@value']
                                    ? resource['dcterms:description'][0]['@value']
                                    : '';
                                output = output.replace(match, escapeHtml(val));
                                break;
                            case '{total_resources}':
                                val = selectedResourceIds.length <= 1 ? browseControls.data('label-count-singular') : browseControls.data('label-count-plural');
                                val = val.replace('%d', selectedResourceIds.length);
                                output = output.replace(match, escapeHtml(val));
                                break;
                            default:
                                // Manage properties and simple keys (like o:id).
                                isMulti = match.substr(0, 8) === '{_multi:';
                                property = isMulti ? match.substring(8, match.length - 1) : match.substring(1, match.length - 1);
                                if (resource[property] && resource[property].length && typeof resource[property] === 'object' && Array.isArray(resource[property])) {
                                    vals = [];
                                    resource[property].forEach((value) => {
                                        // Linked resource or uri.
                                        // TODO Build url? Search url or item url or Advanced Search url?
                                        if (value['@id']) {
                                            if (value['display_title']) {
                                                val = value['display_title'];
                                            } else if (value['o:label']) {
                                                val = value['o:label'];
                                            } else {
                                                val = ['@id'];
                                            }
                                        } else {
                                            val = value['@value'] ? value['@value'] : null;
                                        }
                                        if (val && val.length) {
                                            vals.push(escapeHtml(val));
                                        }
                                    });
                                    val = vals.length
                                        ? '<span class="value-content">' + (isMulti ? vals.join("</span>\n" + '<span class="value-content">') : vals[0]) + "</span>\n"
                                        : '';
                                } else if (resource[property] && resource[property].length && typeof resource[property] !== 'object') {
                                    // Example: o:id.
                                    val = resource[property];
                                } else {
                                    val = '';
                                }
                                output = output.replace(match, val);
                                break;
                        }
                    });
                }
                return output;
            };

            const resourceList = $(this);
            const noResource = $('.no-resource.contact-us-template');
            const selectedResourceIds = localStorage.getItem('contactus_selectedIds')
                ? JSON.parse(localStorage.getItem('contactus_selectedIds'))
                : [];

            var filledTemplate;

            // Update the browse controls first.
            const browseControls = $('.browse-controls.contact-us-template');
            if (browseControls.length) {
                filledTemplate = fillTemplate(browseControls[0].outerHTML, {});
                let browseControlsUpdated = browseControls;
                browseControlsUpdated.html($(filledTemplate).html());
                browseControlsUpdated
                    .removeClass('contact-us-template hidden')
                    .removeData()
                    .show();
            }

            // Update the resource list if any.

            if (!selectedResourceIds || !selectedResourceIds.length) {
                resourceList.remove();
                noResource
                    .removeClass('contact-us-template hidden')
                    .show();
                return;
            }

            noResource.remove();

            const urlApi = resourceList.data('url-api');
            const urlSiteBase = resourceList.data('url-site-base') || resourceList.data('url-base-item');
            const typeToController = {
                'o:Item': 'item',
                'o:ItemSet': 'item-set',
                'o:Media': 'media',
                'o:DigitalObject': 'digital-object',
            };
            const resourceController = function(resource) {
                const types = Array.isArray(resource['@type']) ? resource['@type'] : [resource['@type']];
                for (const t of types) {
                    if (typeToController[t]) return typeToController[t];
                }
                return 'item';
            };
            const defaultUntitled = resourceList.data('default-untitled');
            const defaultThumbnailUrl = resourceList.data('default-thumbnail-url');
            const defaultThumbnailLabel = resourceList.data('default-thumbnail-label');

            const templateRowHtml = resourceList.html();

            resourceList.find('> li').remove();
            resourceList
                .removeClass('contact-us-template hidden')
                .removeData()
                .show();

            selectedResourceIds.forEach((resourceId) => {
                resourceId = parseInt(resourceId);
                if (!resourceId) {
                    return;
                }
                $.ajax({
                    url: urlApi + '/' + resourceId,
                })
                .done(function(data) {
                    filledTemplate = fillTemplate(templateRowHtml, data);
                    resourceList.append(filledTemplate);
                });
            });

        });

        /**
         * Update selection when the user or visitor click selection checkbox.
         *
         * The selection list may be limited by the max size of selections.
         */
        $('body').on('click', '.contact-us-selection', function() {
            const checkbox = $(this);
            const resourceId = parseInt(checkbox.val());
            if (!resourceId) {
                return;
            }

            // The local storage is used in all cases, visitor or user.
            // For user, the local storage is just synchronized with the results of the ajax request.

            // For visitor.
            if (checkbox.data('localStorage')) {
                const maxResources = checkbox.data('max-resources') ? parseInt(checkbox.data('max-resources')) : 0;
                let selectedResourceIds = localStorage.getItem('contactus_selectedIds')
                    ? JSON.parse(localStorage.getItem('contactus_selectedIds'))
                    : [];
                let hasDialog = false;
                const isSelected = selectedResourceIds.includes(resourceId);
                const isChecked = $(this)[0].checked
                if (isSelected && !isChecked) {
                    selectedResourceIds.splice(selectedResourceIds.indexOf(resourceId), 1);
                    localStorage.setItem('contactus_selectedIds', JSON.stringify(selectedResourceIds));
                } else if (!isSelected && isChecked) {
                    if (maxResources && selectedResourceIds.length >= maxResources) {
                        // Uncheck the box.
                        hasDialog = true;
                        checkbox.prop('checked', false);
                        let message = checkbox.data('message-fail');
                        CommonDialog.dialogAlert({
                            heading: Omeka.jsTranslate('Contact'),
                            message: message && message.length ? message : (data.message ? data.message : 'An error occurred.'),
                        });
                    } else {
                        selectedResourceIds.push(resourceId);
                        localStorage.setItem('contactus_selectedIds', JSON.stringify(selectedResourceIds));
                    }
                }
                // For visitors with a larger selection list before a change of
                // the config, slice the list.
                if (maxResources && selectedResourceIds.length >= maxResources) {
                    selectedResourceIds = selectedResourceIds.splice(0, maxResources);
                    localStorage.setItem('contactus_selectedIds', JSON.stringify(selectedResourceIds));
                    if (!hasDialog) {
                        let message = checkbox.data('message-fail');
                        CommonDialog.dialogAlert({
                            heading: Omeka.jsTranslate('Contact'),
                            message: message && message.length ? message : (data.message ? data.message : 'An error occurred.'),
                        });
                    }
                } else {
                    $(document).trigger('o:contact-us-selection-updated', {
                        status: 'success',
                        data: {
                            selected_resources: selectedResourceIds
                        }
                    });
                }
                return;
            }

            // For user: ajax with response routed through CommonDialog.jSend*.
            const url = checkbox.data('url');
            const target = checkbox[0];
            fetch(url + (resourceId ? '?id=' + resourceId : ''), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
            })
            .then(response => response.json().catch(() => ({ status: 'error', message: Omeka.jsTranslate('An error occurred.') })))
            .then(data => {
                if (!data || data.status !== 'success') {
                    checkbox.prop('checked', false);
                    CommonDialog.jSendFail(data || {}, { target: target });
                    return;
                }
                localStorage.setItem('contactus_selectedIds', JSON.stringify(data.data.selected_resources));
                $(document).trigger('o:contact-us-selection-updated', data);
            })
            .catch(error => {
                checkbox.prop('checked', false);
                CommonDialog.jSendFail(error, { target: target });
            });
        });

        /**
         * Submit the contact us form via ajax when inside a dialog, via button.
         */
        $(document).on('submit', 'dialog #contact-us', function(ev) {
            const form = this;
            const promise = CommonDialog.jSend(ev);
            if (!promise) {
                return;
            }
            promise.then(data => {
                if (data && data.status === 'success') {
                    const dialog = form.closest('dialog');
                    if (dialog) {
                        dialog.close();
                    }
                    $(document).trigger('o:contact-us-email-sent', data);
                }
            });
        });

        /**
         * Display the contact us form, that may be a dialog or a div.
         */
        $(document).on('click', 'button.contact-us-write', function() {
            const dialog = document.querySelector('dialog.popup-contact-us');
            if (dialog) {
                dialog.showModal();
                $(dialog).trigger('o:dialog-opened');
            } else {
                $('.contact-us-form').removeClass('hidden').show();
            }
        });

    });
})();

/**
 * Self-hosted proof-of-work for contact forms.
 *
 * The server embeds a random salt and a difficulty on every form tagged with
 * data-pow-salt. The browser must find a decimal nonce such that sha256(salt +
 * ':' + nonce) starts with N leading hex zeros. The nonce is written to the
 * hidden pow_nonce input and validated server-side.
 *
 * Uses crypto.subtle when available. It is missing outside a secure context
 * (a page not served in https) and in some old browsers or webviews, where the
 * form was submitted without nonce and the message marked as spam, so a small
 * implementation in plain javascript is used as fallback.
 */
(function () {
    const K = new Uint32Array([
        0x428a2f98, 0x71374491, 0xb5c0fbcf, 0xe9b5dba5, 0x3956c25b, 0x59f111f1, 0x923f82a4, 0xab1c5ed5,
        0xd807aa98, 0x12835b01, 0x243185be, 0x550c7dc3, 0x72be5d74, 0x80deb1fe, 0x9bdc06a7, 0xc19bf174,
        0xe49b69c1, 0xefbe4786, 0x0fc19dc6, 0x240ca1cc, 0x2de92c6f, 0x4a7484aa, 0x5cb0a9dc, 0x76f988da,
        0x983e5152, 0xa831c66d, 0xb00327c8, 0xbf597fc7, 0xc6e00bf3, 0xd5a79147, 0x06ca6351, 0x14292967,
        0x27b70a85, 0x2e1b2138, 0x4d2c6dfc, 0x53380d13, 0x650a7354, 0x766a0abb, 0x81c2c92e, 0x92722c85,
        0xa2bfe8a1, 0xa81a664b, 0xc24b8b70, 0xc76c51a3, 0xd192e819, 0xd6990624, 0xf40e3585, 0x106aa070,
        0x19a4c116, 0x1e376c08, 0x2748774c, 0x34b0bcb5, 0x391c0cb3, 0x4ed8aa4a, 0x5b9cca4f, 0x682e6ff3,
        0x748f82ee, 0x78a5636f, 0x84c87814, 0x8cc70208, 0x90befffa, 0xa4506ceb, 0xbef9a3f7, 0xc67178f2,
    ]);

    /**
     * Synchronous sha-256 of a string encoded in utf-8, as hexadecimal.
     */
    function sha256HexFallback(message) {
        const data = new TextEncoder().encode(message);
        const length = data.length;
        const padded = new Uint8Array(((length + 9 + 63) >> 6) << 6);
        padded.set(data);
        padded[length] = 0x80;
        const view = new DataView(padded.buffer);
        view.setUint32(padded.length - 8, Math.floor(length / 0x20000000));
        view.setUint32(padded.length - 4, (length << 3) >>> 0);
        const h = new Uint32Array([
            0x6a09e667, 0xbb67ae85, 0x3c6ef372, 0xa54ff53a, 0x510e527f, 0x9b05688c, 0x1f83d9ab, 0x5be0cd19,
        ]);
        const w = new Uint32Array(64);
        const rotr = function (x, n) { return (x >>> n) | (x << (32 - n)); };
        for (let offset = 0; offset < padded.length; offset += 64) {
            for (let i = 0; i < 16; i++) {
                w[i] = view.getUint32(offset + i * 4);
            }
            for (let i = 16; i < 64; i++) {
                const s0 = rotr(w[i - 15], 7) ^ rotr(w[i - 15], 18) ^ (w[i - 15] >>> 3);
                const s1 = rotr(w[i - 2], 17) ^ rotr(w[i - 2], 19) ^ (w[i - 2] >>> 10);
                w[i] = (w[i - 16] + s0 + w[i - 7] + s1) >>> 0;
            }
            let a = h[0], b = h[1], c = h[2], d = h[3], e = h[4], f = h[5], g = h[6], k = h[7];
            for (let i = 0; i < 64; i++) {
                const t1 = (k + (rotr(e, 6) ^ rotr(e, 11) ^ rotr(e, 25)) + ((e & f) ^ (~e & g)) + K[i] + w[i]) >>> 0;
                const t2 = ((rotr(a, 2) ^ rotr(a, 13) ^ rotr(a, 22)) + ((a & b) ^ (a & c) ^ (b & c))) >>> 0;
                k = g; g = f; f = e; e = (d + t1) >>> 0;
                d = c; c = b; b = a; a = (t1 + t2) >>> 0;
            }
            h[0] += a; h[1] += b; h[2] += c; h[3] += d; h[4] += e; h[5] += f; h[6] += g; h[7] += k;
        }
        let hex = '';
        for (let i = 0; i < 8; i++) {
            hex += h[i].toString(16).padStart(8, '0');
        }
        return hex;
    }

    const hasSubtle = !!(window.crypto && window.crypto.subtle);

    async function sha256Hex(message) {
        if (!hasSubtle) {
            return sha256HexFallback(message);
        }
        const buf = new TextEncoder().encode(message);
        const hash = await crypto.subtle.digest('SHA-256', buf);
        const bytes = new Uint8Array(hash);
        let hex = '';
        for (let i = 0; i < bytes.length; i++) {
            hex += bytes[i].toString(16).padStart(2, '0');
        }
        return hex;
    }

    async function solve(salt, difficulty) {
        const prefix = '0'.repeat(difficulty);
        for (let n = 0; n < 1e7; n++) {
            const h = await sha256Hex(salt + ':' + n);
            if (h.startsWith(prefix)) return n;
            // Yield to the UI every 4096 iterations so the tab stays responsive
            // on low-end hardware. Each pause lasts at least 4 ms in browsers,
            // so a pause every 256 iterations tripled the time to solve.
            if ((n & 0xfff) === 0) {
                await new Promise(function (r) { setTimeout(r, 0); });
            }
        }
        return null;
    }

    function setupForm(form) {
        const salt = form.getAttribute('data-pow-salt');
        const diff = parseInt(form.getAttribute('data-pow-difficulty') || '4', 10);
        const input = form.querySelector('input[name="pow_nonce"]');
        if (!salt || !input) return;
        const submits = form.querySelectorAll('[type="submit"]');
        for (const b of submits) { b.disabled = true; }
        solve(salt, diff).then(function (nonce) {
            if (nonce !== null) {
                input.value = String(nonce);
            }
            for (const b of submits) { b.disabled = false; }
        }).catch(function () {
            for (const b of submits) { b.disabled = false; }
        });
    }

    function init() {
        const forms = document.querySelectorAll('form[data-pow-salt]');
        forms.forEach(setupForm);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
