/**
 * Email Restriction Plugin JS
 * Handles all interactive functionality for the email restriction admin page
 */

jQuery(document).ready(function($) {
    // Edit Email Modal functionality
    $('.edit-email').click(function() {
        var id = $(this).data('id');
        var email = $(this).data('email');
        
        // Populate the modal with email data
        $('#email_id').val(id);
        $('#email_edit').val(email);
        $('#edit-email-modal').show();
    });
    
    // Close modal when clicking on X button
    $('.close-modal').click(function() {
        $('#edit-email-modal').hide();
    });
    
    // Close modal when clicking outside the modal content
    $(window).click(function(event) {
        if ($(event.target).is('#edit-email-modal')) {
            $('#edit-email-modal').hide();
        }
    });
    
    // Tooltips for search info icon
    $('.dashicons-info-outline').hover(
        function() {
            $(this).css('color', '#2271b1');
        },
        function() {
            $(this).css('color', '');
        }
    );
    
    // Form validation for search
    $('#search-form').submit(function(e) {
        var searchTerm = $('#search_term').val().trim();
        // Prevent form submission if search term is empty
        if (searchTerm === '') {
            alert('Please enter a search term.');
            e.preventDefault();
            return false;
        }
    });
    
    // Accessibility improvements
    $('.dashicons-info-outline').attr('tabindex', '0').on('keypress', function(e) {
        // Trigger tooltip on Enter or Space key
        if (e.which === 13 || e.which === 32) {
            $(this).tooltip('show');
        }
    });
});