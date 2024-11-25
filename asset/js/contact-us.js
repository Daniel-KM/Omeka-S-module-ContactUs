(function() {
    $(document).ready(function() {

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
