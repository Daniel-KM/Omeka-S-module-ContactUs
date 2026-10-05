$(document).ready(function() {

    /* Update contact messages. */

    // Toggle the status of a message.
    $('#content').on('click', 'a.toggle-property', function(e) {
        e.preventDefault();

        var button = $(this);
        var url = button.data('toggle-url');
        var property = button.data('property');
        var status = button.data('status');
        $
            .ajax({
                url: url,
                beforeSend: function() {
                    button.removeClass('o-icon-' + status).addClass('fas fa-sync fa-spin');
                }
            })
            .done(function(data) {
                if (data.status === 'success') {
                    status = data.data.action.status;
                }
                button.data('status', status);
                var row = button.closest('.contact-message')
                var iconLink = row.find('.toggle-property.' + property);
                iconLink.data('status', status);
                if (data.status !== 'success' && data.message && data.message.length) {
                    alert(data.message);
                }
            })
            .fail(function(jqXHR, textStatus) {
                if (jqXHR.responseJSON && jqXHR.responseJSON.message) {
                    alert(jqXHR.responseJSON.message);
                } else if (jqXHR.status == 404) {
                    alert(Omeka.jsTranslate('The contact message doesn’t exist.'));
                } else {
                    alert(Omeka.jsTranslate('Something went wrong'));
                }
            })
            .always(function () {
                button.removeClass('fas fa-sync fa-spin').addClass('o-icon-' + status);
            });
    });

    // Approve or reject a list of messages.
    $('#content').on('click', 'a.batch-property', function(e) {
        e.preventDefault();

        var selected = $('.batch-edit td input[name="resource_ids[]"][type="checkbox"]:checked');
        if (selected.length == 0) {
            return;
        }
        var checked = selected.map(function() { return $(this).val(); }).get();
        var button = $(this);
        var url = button.data('batch-property-url');
        var property = button.data('property');
        var status = button.data('status');
        $
            .ajax({
                url: url,
                data: {resource_ids: checked},
                beforeSend: function() {
                    selected.closest('.contact-message').find('.toggle-property.' + property).each(function() {
                        $(this).removeClass('o-icon-' + $(this).data('status')).addClass('fas fa-sync fa-spin');
                    });
                    $('.select-all').prop('checked', false);
                }
            })
            .done(function(data) {
                if (data.status === 'success') {
                    status = data.data.action.status;
                }
                selected.closest('.contact-message').each(function() {
                    var row = $(this);
                    row.find('input[type="checkbox"]').prop('checked', false);
                    var iconLink = row.find('.toggle-property.' + property);
                    iconLink.data('status', status);
                    iconLink.removeClass('fas fa-sync fa-spin').addClass('o-icon-' + status);
                });
                if (data.status !== 'success' && data.message.length) {
                    alert(data.message);
                }
            })
            .fail(function(jqXHR, textStatus) {
                selected.closest('.contact-message').find('.toggle-property.' + property).each(function() {
                    $(this).removeClass('fas fa-sync fa-spin').addClass('o-icon-' + $(this).data('status'));
                });
                if (jqXHR.status == 404) {
                    alert(Omeka.jsTranslate('The contact message doesn’t exist.'));
                } else {
                    alert(Omeka.jsTranslate('Something went wrong'));
                }
            });
    });

    // Resend messages to the author, for example false positives. A message
    // resent is not a spam anymore and cannot be resent twice.
    const markResent = function(ids) {
        ids.forEach(function(id) {
            const row = $('.batch-edit td input[name="resource_ids[]"][value="' + id + '"]').closest('.contact-message');
            row.find('a.resend-message').replaceWith(
                $('<span class="resent fas fa-paper-plane"></span>').attr('title', Omeka.jsTranslate('Resent to the author'))
            );
            row.find('.toggle-property.is_spam')
                .removeClass('o-icon-spam').addClass('o-icon-not-spam').data('status', 'not-spam');
        });
    };

    const resend = function(url, button) {
        if (!window.confirm(Omeka.jsTranslate('Resend to the author, without any check of spam?'))) {
            return;
        }
        $
            .ajax({
                url: url,
                method: 'POST',
                beforeSend: function() {
                    button.removeClass('fa-paper-plane').addClass('fa-sync fa-spin');
                }
            })
            .done(function(data) {
                markResent(data.data && data.data.sent ? data.data.sent : []);
                if (data.message) {
                    alert(data.message + (data.data && data.data.errors && data.data.errors.length ? '\n' + data.data.errors.join('\n') : ''));
                }
            })
            .fail(function(jqXHR) {
                alert(jqXHR.responseJSON && jqXHR.responseJSON.message
                    ? jqXHR.responseJSON.message
                    : Omeka.jsTranslate('Something went wrong'));
            })
            .always(function() {
                button.removeClass('fa-sync fa-spin').addClass('fa-paper-plane');
            });
    };

    $('#content').on('click', 'a.resend-message', function(e) {
        e.preventDefault();
        resend($(this).data('resend-url'), $(this));
    });

    // The batch resend runs in a job after a confirmation in the sidebar, that
    // displays the number of selected messages and gets them on submit.
    $('#content').on('click', 'a.resend-selected', function() {
        const count = $('.batch-edit td input[name="resource_ids[]"][type="checkbox"]:checked').length;
        $('#sidebar-resend-selected .resend-count').text(count);
        $('#confirm-resend-selected [type="submit"]').prop('disabled', !count);
    });

    $('#confirm-resend-selected').on('submit', function() {
        const confirmForm = $(this);
        confirmForm.find('input[name="resource_ids[]"]').remove();
        $('#batch-form').find('.batch-edit td input[name="resource_ids[]"]:checked').each(function() {
            confirmForm.append($(this).clone().attr('type', 'hidden'));
        });
    });

    // Reply to the contact: the dialog is opened by Common
    // (button-dialog-common); just avoid the anchor jumping to the top of the
    // page.
    $('#content').on('click', 'a.reply-message', function(e) {
        e.preventDefault();
    });

    // Close the reply dialog after a successful jSend send.
    document.addEventListener('o:jsend-success', function() {
        var dialog = document.querySelector('dialog.dialog-send-message.dialog-contactus');
        if (dialog && dialog.open) {
            dialog.close();
        }
    });

});
