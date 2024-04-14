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
                if (data.status !== 'success' && data.message.length) {
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

});
