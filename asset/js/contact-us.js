'use strict';

(function ($) {
    $(document).ready(function() {

        /**
         * @see ContactUs, Guest, SearchHistory, Selection, TwoFactorAuth.
         */

        const beforeSpin = function (element) {
            var span = $(element).find('span');
            if (!span.length) {
                span = $(element).next('span.appended');
                if (!span.length) {
                    $('<span class="appended"></span>').insertAfter($(element));
                    span = $(element).next('span');
                }
            }
            element.hide();
            span.addClass('fas fa-sync fa-spin');
        };

        const afterSpin = function (element) {
            var span = $(element).find('span');
            if (!span.length) {
                span = $(element).next('span.appended');
                if (span.length) {
                    span.remove();
                }
            } else {
                span.removeClass('fas fa-sync fa-spin');
            }
            element.show();
        };

        /**
         * Get the main message of jSend output, in particular for status fail.
         */
        const jSendMessage = function(data) {
            if (typeof data !== 'object') {
                return null;
            }
            if (data.message) {
                return data.message;
            }
            if (!data.data) {
                return null;
            }
            for (let value of Object.values(data.data)) {
                if (typeof value === 'string' && value.length) {
                    return value;
                }
            }
            return null;
        }

        const dialogMessage = function (message, nl2br = false) {
            // Use a dialog to display a message, that should be escaped.
            var dialog = document.querySelector('dialog.popup-message');
            if (!dialog) {
                dialog = `
    <dialog class="popup popup-dialog dialog-message popup-message" data-is-dynamic="1">
        <div class="dialog-background">
            <div class="dialog-panel">
                <div class="dialog-header">
                    <button type="button" class="dialog-header-close-button" title="Close" autofocus="autofocus">
                        <span class="dialog-close">🗙</span>
                    </button>
                </div>
                <div class="dialog-contents">
                    {{ message }}
                </div>
            </div>
        </div>
    </dialog>`;
                $('body').append(dialog);
                dialog = document.querySelector('dialog.dialog-message');
            }
            if (nl2br) {
                message = message.replace(/(?:\r\n|\r|\n)/g, '<br/>');
            }
            dialog.innerHTML = dialog.innerHTML.replace('{{ message }}', message);
            dialog.showModal();
            $(dialog).trigger('o:dialog-opened');
        };

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
                                val = urlBaseItem + '/' + resource['o:id'];
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
            const urlBaseItem = resourceList.data('url-base-item');
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
                        dialogMessage(message && message.length ? message : (data.message ? data.message : 'An error occurred.'));
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
                        dialogMessage(message && message.length ? message : (data.message ? data.message : 'An error occurred.'));
                    }
                }
                return;
            }

            // For user.
            const url = checkbox.data('url');
            $.ajax({
                url: url,
                data: resourceId ? { id: resourceId } : null,
                // beforeSend: beforeSpin(checkbox),
            })
            .done(function(data) {
                if (data.status !== 'success') {
                    // Uncheck the box.
                    checkbox.prop('checked', false);
                    let message = checkbox.data('message-fail');
                    dialogMessage(message && message.length ? message : (data.message ? data.message : 'An error occurred.'));
                }
            })
            .fail(function (xhr, textStatus, errorThrown) {
                const data = xhr.responseJSON;
                if (data && data.status === 'fail') {
                    // Fail is always an email/password error here.
                    let msg = jSendMessage(data);
                    dialogMessage(msg ? msg : 'Check input', true);
                    form[0].reset();
                } else {
                    // Error is a server error (in particular cannot send mail).
                    let msg = data && data.status === 'error' && data.message && data.message.length ? data.message : 'An error occurred.';
                    dialogMessage(msg, true);
                }
            })
            .always(function () {
                // afterSpin(checkbox)
            });
        });

        /**
         * Submit the contact us form via ajax when inside a dialog, via button.
         */
        $(document).on('submit', 'dialog #contact-us', function(ev) {
            ev.preventDefault();
            const form = $(this);
            const urlForm = form.attr('action') ? form.attr('action') : window.location.href;
            const submitButton = form.find('[type=submit]');
            $
                .ajax({
                    type: 'POST',
                    url: urlForm,
                    data: form.serialize(),
                    beforeSend: beforeSpin(submitButton),
                })
                .done(function(data) {
                    // Success to send message.
                    // So close the form and display the message.
                    // form[0].reset();
                    $(form).closest('dialog')[0].close();
                    let msg = jSendMessage(data);
                    dialogMessage(msg ? msg : 'Email successfully sent.', true);
                })
                .fail(function (xhr, textStatus, errorThrown) {
                    const data = xhr.responseJSON;
                    if (data && data.status === 'fail') {
                        let msg = jSendMessage(data);
                        dialogMessage(msg ? msg : 'Check input', true);
                    } else {
                        $(form).closest('dialog')[0].close();
                        // Error is a server error (in particular cannot send mail).
                        let msg = data && data.status === 'error' && data.message && data.message.length ? data.message : 'An error occurred.';
                        dialogMessage(msg, true);
                    }
                })
                .always(function () {
                    afterSpin(submitButton)
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

        $(document).on('click', '.dialog-header-close-button', function(e) {
            const dialog = this.closest('dialog.popup');
            if (dialog) {
                dialog.close();
                if (dialog.hasAttribute('data-is-dynamic') && dialog.getAttribute('data-is-dynamic')) {
                    dialog.remove();
                }
            } else {
                $(this).closest('.popup').addClass('hidden').hide();
            }
        });

    });
})(jQuery);
