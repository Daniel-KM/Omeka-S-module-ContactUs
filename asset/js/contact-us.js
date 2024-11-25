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

        $('body').on('click', '.contact-us-basket', function() {
            const checkbox = $(this);
            const id = checkbox.val();
            const url = checkbox.data('url');
            $.ajax({
                url: url,
                data: id ? { id: id } : null,
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
