jQuery(document).ready(function($) {
    'use strict';

    // Tab functionality
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        
        // Remove active states
        $('.nav-tab').removeClass('nav-tab-active');
        $('.tab-content').hide();
        
        // Add active states
        $(this).addClass('nav-tab-active');
        $($(this).attr('href')).show();
    });

    // Form submission handling
    $('.ldtt-form').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $button = $form.find('button[type="submit"], input[type="submit"]');
        const originalText = $button.val() || $button.text();
        
        // Show loading state
        $button.prop('disabled', true);
        if ($button.is('input')) {
            $button.val('Processing...');
        } else {
            $button.text('Processing...');
        }
        
        // Submit form normally (no AJAX for now)
        $form.off('submit').submit();
    });

    // Form validation
    $('input[required]').on('blur', function() {
        const $input = $(this);
        if (!$input.val().trim()) {
            $input.css('border-color', '#dc3232');
        } else {
            $input.css('border-color', '');
        }
    });

    // Confirmation for cleanup
    $('button[value="delete-items"]').on('click', function(e) {
        const $form = $(this).closest('form');
        const confirmed = $form.find('input[name="confirm"]').is(':checked');
        
        if (!confirmed) {
            e.preventDefault();
            alert('Please confirm that you understand this will permanently delete data.');
            return false;
        }
        
        if (!confirm('Are you sure you want to permanently delete the selected test data? This action cannot be undone.')) {
            e.preventDefault();
            return false;
        }
    });

    // Auto-hide notices after 5 seconds
    setTimeout(function() {
        $('.notice.is-dismissible').fadeOut();
    }, 5000);
});