(function() {
    $(document).ready(function() {

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
         * Check/uncheck contact us selection on load from local storage.
         */
        $('.contact-us-basket[data-local-storage="1"]').each(function(i, obj) {
            let selectedResourceIds = localStorage.getItem('contactus_selectedIds');
            if (selectedResourceIds !== null) {
                selectedResourceIds = JSON.parse(selectedResourceIds);
                const resourceId = parseInt($(this).val());
                const isSelected = selectedResourceIds.includes(resourceId);
                $(this).prop('checked', isSelected);
            }
        });

        $('body').on('click', '.contact-us-basket', function() {
            const checkbox = $(this);
            const resourceId = parseInt(checkbox.val());

            if (checkbox.data('localStorage')) {
                const selectedResourceIds = localStorage.getItem('contactus_selectedIds')
                    ? JSON.parse(localStorage.getItem('contactus_selectedIds'))
                    : [];
                const isSelected = selectedResourceIds.includes(resourceId);
                const isChecked = $(this)[0].checked
                if (isSelected && !isChecked) {
                    selectedResourceIds.splice(selectedResourceIds.indexOf(resourceId), 1);
                    localStorage.setItem('contactus_selectedIds', JSON.stringify(selectedResourceIds));
                } else if (!isSelected && isChecked) {
                    selectedResourceIds.push(resourceId);
                    localStorage.setItem('contactus_selectedIds', JSON.stringify(selectedResourceIds));
                }
                return;
            }

            const url = checkbox.data('url');
            $.ajax({
                url: url,
                data: resourceId ? { id: resourceId } : null,
                // beforeSend: beforeSpin(checkbox),
            })
            .done(function(data) {
                if (data.status === 'success') {
                    // Nothing to do for now.
                    // const selectedResources = data.data.selected_resources;
                } else if (data.status === 'error') {
                    alert(data.message ? data.message : 'An error occurred.');
                }
            })
            .fail(function(jqXHR, errorMsg) {
                alert(jqXHR.responseText, errorMsg);
            })
            .always(function () {
                // afterSpin(checkbox)
            });
        });

        $(document).on('click', 'button.contact-us-write', function() {
            const resourceId = $(this).data('id');
            const dialog = document.querySelector('dialog[data-id="' + resourceId + '"]');
            dialog.showModal();
        });

        $(document).on('click', '.popup-header-close-button', function(e) {
            this.closest('dialog').close();
        });

    });
})();
