jQuery(document).ready(function($) {
    'use strict';

    // Debug: Check if elements exist
    console.log('LDTT Admin Script Loaded');
    console.log('Nav tabs found:', $('.nav-tab').length);
    console.log('Tab content found:', $('.tab-content').length);

    // Initialize tabs - show the first active tab
    $('.tab-content').hide();
    $('.nav-tab-active').each(function() {
        var target = $(this).attr('href');
        console.log('Active tab target:', target);
        if (target) {
            $(target).show();
        }
    });

    // Tab functionality
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        
        console.log('Tab clicked:', $(this).text());
        console.log('Target href:', $(this).attr('href'));
        
        // Remove active states
        $('.nav-tab').removeClass('nav-tab-active');
        $('.tab-content').hide();
        
        // Add active states
        $(this).addClass('nav-tab-active');
        var target = $(this).attr('href');
        if (target) {
            $(target).show();
            console.log('Showing target:', target);
        }
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

    // Distribution preview functionality - Enhanced
    function updateDistributionPreview() {
        var totalUsers = parseInt($('input[name="total_users"]').val()) || 100;
        var groupLeaders = parseFloat($('input[name="group_leaders"]').val()) || 1;
        var groupMembers = parseFloat($('input[name="group_members"]').val()) || 2;
        var courseEnrolled = parseFloat($('input[name="course_enrolled"]').val()) || 5;
        var userMode = $('input[name="user_mode"]:checked').val() || 'create_new';
        var safeMode = $('input[name="safe_group_creation"]').is(':checked');
        
        var leadersCount = Math.max(1, Math.round(totalUsers * (groupLeaders / 100)));
        var membersCount = Math.max(1, Math.round(totalUsers * (groupMembers / 100)));
        var enrolledCount = Math.max(1, Math.round(totalUsers * (courseEnrolled / 100)));
        var regularCount = totalUsers - leadersCount - membersCount - enrolledCount;
        
        var progressText = $('input[name="create_progress"]').is(':checked') ? ' with realistic progress' : '';
        var modeText = userMode === 'use_existing' ? 'existing users will be randomly assigned as' : 'new users will be created as';
        var safeModeText = safeMode ? ' (Enhanced Safe Mode)' : ' (Standard Mode)';
        
        var preview = '<p><strong>Distribution Preview for ' + totalUsers + ' users:</strong></p>' +
                     '<p><em>Using ' + (userMode === 'use_existing' ? 'existing users' : 'new users') + safeModeText + '</em></p><ul>' +
            '<li><span style="color: #d54e21;"><strong>' + leadersCount + ' ' + modeText + '</strong></span> → Group Leaders (with administrative access)</li>' +
            '<li><span style="color: #2271b1;"><strong>' + membersCount + ' ' + modeText + '</strong></span> → Group Members (enrolled in groups)</li>' +
            '<li><span style="color: #00a32a;"><strong>' + enrolledCount + ' ' + modeText + '</strong></span> → Course Students (enrolled in courses' + progressText + ')</li>';
            
        if (userMode === 'create_new' && regularCount > 0) {
            preview += '<li><span style="color: #646970;"><strong>' + Math.max(0, regularCount) + ' users will be created as</strong></span> → Regular Test Users</li>';
        }
        
        preview += '</ul>';
        
        // Add safe mode info
        if (safeMode) {
            preview += '<div style="background: #d4edda; border: 1px solid #c3e6cb; padding: 10px; margin-top: 10px; border-radius: 4px;">' +
                '<strong>✅ Enhanced Safe Mode:</strong> Using improved group leader creation method to prevent LearnDash compatibility issues. ' +
                'This mode ensures reliable group leader assignment and reduces the risk of PHP fatal errors.' +
                '</div>';
        } else {
            preview += '<div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; margin-top: 10px; border-radius: 4px;">' +
                '<strong>⚠️ Standard Mode:</strong> Using standard LearnDash functions which may cause compatibility issues on some setups. ' +
                'Consider enabling Enhanced Safe Mode for better reliability.' +
                '</div>';
        }
        
        // Add warning if percentages don't add up properly
        var totalPercentage = groupLeaders + groupMembers + courseEnrolled;
        if (totalPercentage > 100) {
            preview += '<div style="background: #fcf8e3; border: 1px solid #faebcc; padding: 10px; margin-top: 10px; border-radius: 4px;">' +
                '<strong>⚠️ Warning:</strong> Total percentage exceeds 100% (' + totalPercentage.toFixed(1) + '%). Some users may be assigned to multiple roles.' +
                '</div>';
        } else if (regularCount < 0) {
            preview += '<div style="background: #f2dede; border: 1px solid #ebccd1; padding: 10px; margin-top: 10px; border-radius: 4px;">' +
                '<strong>❌ Error:</strong> Percentages are too high. Reduce the percentages or increase total users.' +
                '</div>';
        }
        
        // Add info about existing users requirement
        if (userMode === 'use_existing') {
            preview += '<div style="background: #d9edf7; border: 1px solid #bce8f1; padding: 10px; margin-top: 10px; border-radius: 4px;">' +
                '<strong>ℹ️ Note:</strong> This will randomly select from existing users (excluding administrators). If fewer than ' + totalUsers + ' users exist, all available users will be used.' +
                '</div>';
        }
        
        $('#distribution-preview').html(preview);
        
        // Enable/disable submit button based on validation
        var $submitButton = $('button[value="enhanced-user-distribution"]');
        if (regularCount < 0) {
            $submitButton.prop('disabled', true).text('Fix Percentages First');
        } else {
            $submitButton.prop('disabled', false).text('Create Distributed User Base');
        }
    }

    // Initialize distribution preview on page load
    if ($('#distribution-preview').length) {
        updateDistributionPreview();
    }

    // Add event listeners for distribution preview - Enhanced
    $(document).on('input change', 'input[name="total_users"], input[name="group_leaders"], input[name="group_members"], input[name="course_enrolled"], input[name="create_progress"], input[name="user_mode"], input[name="safe_group_creation"]', updateDistributionPreview);

    // Enhanced form validation for distribution tab
    $('#distribution form').on('submit', function(e) {
        var totalUsers = parseInt($('input[name="total_users"]').val()) || 100;
        var groupLeaders = parseFloat($('input[name="group_leaders"]').val()) || 1;
        var groupMembers = parseFloat($('input[name="group_members"]').val()) || 2;
        var courseEnrolled = parseFloat($('input[name="course_enrolled"]').val()) || 5;
        var safeMode = $('input[name="safe_group_creation"]').is(':checked');
        
        var leadersCount = Math.max(1, Math.round(totalUsers * (groupLeaders / 100)));
        var membersCount = Math.max(1, Math.round(totalUsers * (groupMembers / 100)));
        var enrolledCount = Math.max(1, Math.round(totalUsers * (courseEnrolled / 100)));
        var regularCount = totalUsers - leadersCount - membersCount - enrolledCount;
        
        if (regularCount < 0) {
            e.preventDefault();
            alert('Error: Your percentages are too high for the total number of users. Please adjust the percentages or increase the total users.');
            return false;
        }
        
        if (totalUsers < 10) {
            e.preventDefault();
            alert('Please create at least 10 users for meaningful distribution.');
            return false;
        }
        
        // Enhanced confirmation dialog for large user creation
        if (totalUsers > 500) {
            var safeModeText = safeMode ? ' (Enhanced Safe Mode enabled)' : ' (Standard Mode - consider enabling Safe Mode)';
            if (!confirm('You are about to create ' + totalUsers + ' users' + safeModeText + '. This may take several minutes. Do you want to continue?')) {
                e.preventDefault();
                return false;
            }
        }
        
        // Warning for group leader creation without safe mode
        if (leadersCount > 5 && !safeMode) {
            if (!confirm('You are creating ' + leadersCount + ' group leaders without Enhanced Safe Mode. This may cause compatibility issues with some LearnDash setups. Consider enabling Enhanced Safe Mode. Continue anyway?')) {
                e.preventDefault();
                return false;
            }
        }
    });

    // Safe mode toggle handling
    $('input[name="safe_group_creation"]').on('change', function() {
        var $checkbox = $(this);
        var $description = $checkbox.closest('td').find('.description');
        
        if ($checkbox.is(':checked')) {
            $description.html(
                'Recommended: Uses improved method to prevent LearnDash compatibility issues when creating group leaders<br>' +
                '<strong>Note: This is enabled by default to fix known group leader creation bugs</strong>'
            ).css('color', '#155724');
        } else {
            $description.html(
                'Warning: Standard mode may cause PHP fatal errors with group leader creation in some LearnDash setups<br>' +
                '<strong>Consider keeping Enhanced Safe Mode enabled for better reliability</strong>'
            ).css('color', '#856404');
        }
        
        // Update preview
        updateDistributionPreview();
    });

    // Real-time percentage validation with enhanced feedback
    $('input[name="group_leaders"], input[name="group_members"], input[name="course_enrolled"]').on('input', function() {
        var $input = $(this);
        var value = parseFloat($input.val()) || 0;
        var fieldName = $input.attr('name').replace('_', ' ');
        
        // Visual feedback for percentage ranges
        if (value < 0) {
            $input.css('border-color', '#dc3232');
            showFieldMessage($input, 'Percentage cannot be negative', 'error');
        } else if (value > 50) {
            $input.css('border-color', '#ffb900');
            showFieldMessage($input, 'High percentage - ensure total doesn\'t exceed 100%', 'warning');
        } else if (value > 0) {
            $input.css('border-color', '#00a32a');
            clearFieldMessage($input);
        } else {
            $input.css('border-color', '');
            clearFieldMessage($input);
        }
    });

    // Helper functions for field messages
    function showFieldMessage($input, message, type) {
        var $existing = $input.next('.ldtt-field-message');
        if ($existing.length) {
            $existing.remove();
        }
        
        var color = type === 'error' ? '#dc3232' : '#ffb900';
        var $message = $('<div class="ldtt-field-message" style="color: ' + color + '; font-size: 12px; margin-top: 2px;">' + message + '</div>');
        $input.after($message);
    }
    
    function clearFieldMessage($input) {
        $input.next('.ldtt-field-message').remove();
    }

    // Number input validation
    $('input[type="number"]').on('input', function() {
        var $input = $(this);
        var min = parseFloat($input.attr('min'));
        var max = parseFloat($input.attr('max'));
        var value = parseFloat($input.val());
        
        if (!isNaN(min) && value < min) {
            $input.css('border-color', '#dc3232');
        } else if (!isNaN(max) && value > max) {
            $input.css('border-color', '#dc3232');
        } else {
            $input.css('border-color', '');
        }
    });

    // Enhanced progress indicator for long-running operations
    $('.ldtt-form button[type="submit"]').on('click', function() {
        var $button = $(this);
        var command = $button.val();
        
        // Show progress for operations that create many items
        var progressCommands = ['enhanced-user-distribution', 'create-courses', 'create-lessons', 'create-topics'];
        
        if (progressCommands.indexOf(command) !== -1) {
            var totalUsers = parseInt($('input[name="total_users"]').val()) || 0;
            var safeMode = $('input[name="safe_group_creation"]').is(':checked');
            
            // Create enhanced progress indicator
            var progressMessage = 'Processing...';
            if (command === 'enhanced-user-distribution') {
                progressMessage = 'Creating ' + totalUsers + ' users with distribution' + 
                                (safeMode ? ' (Enhanced Safe Mode)' : '') + '...';
            }
            
            var $progress = $('<div class="ldtt-progress-indicator" style="margin-top: 10px; display: none;"></div>')
                .html('<div style="background: #f1f1f1; border-radius: 4px; padding: 12px;">' +
                      '<div style="background: #2271b1; height: 6px; border-radius: 3px; width: 0%; transition: width 0.3s;"></div>' +
                      '<p style="margin: 8px 0 0 0; font-size: 13px; color: #666;">' + progressMessage + '</p>' +
                      '<p style="margin: 4px 0 0 0; font-size: 11px; color: #999;">This may take a few moments. Please do not close this page.</p>' +
                      '</div>');
            
            $button.after($progress);
            $progress.fadeIn();
            
            // Animate progress bar with more realistic progression
            var width = 0;
            var progressInterval = setInterval(function() {
                var increment = Math.random() * 5;
                if (width < 30) increment *= 2; // Faster initial progress
                if (width > 70) increment *= 0.5; // Slower as it approaches completion
                
                width += increment;
                if (width > 90) {
                    width = 90;
                    clearInterval(progressInterval);
                }
                $progress.find('div div').css('width', width + '%');
            }, 250);
        }
    });

    // Tooltip functionality for help text
    $('[data-tooltip]').each(function() {
        $(this).on('mouseenter', function() {
            var tooltip = $(this).data('tooltip');
            var $tooltip = $('<div class="ldtt-tooltip"></div>')
                .text(tooltip)
                .css({
                    position: 'absolute',
                    background: '#333',
                    color: '#fff',
                    padding: '6px 12px',
                    borderRadius: '4px',
                    fontSize: '12px',
                    whiteSpace: 'nowrap',
                    zIndex: 1000,
                    pointerEvents: 'none',
                    boxShadow: '0 2px 8px rgba(0,0,0,0.2)'
                });
            
            $('body').append($tooltip);
            
            var offset = $(this).offset();
            $tooltip.css({
                top: offset.top - $tooltip.outerHeight() - 8,
                left: offset.left + ($(this).outerWidth() / 2) - ($tooltip.outerWidth() / 2)
            });
        }).on('mouseleave', function() {
            $('.ldtt-tooltip').remove();
        });
    });

    // Advanced features toggle
    if ($('.ldtt-advanced-options').length) {
        $('.ldtt-advanced-toggle').on('click', function(e) {
            e.preventDefault();
            var $advanced = $('.ldtt-advanced-options');
            var $toggle = $(this);
            
            if ($advanced.is(':visible')) {
                $advanced.slideUp();
                $toggle.text('Show Advanced Options');
            } else {
                $advanced.slideDown();
                $toggle.text('Hide Advanced Options');
            }
        });
    }

    // Bulk operations confirmation
    $('input[type="checkbox"][name$="[]"]').on('change', function() {
        var checkedCount = $('input[type="checkbox"][name$="[]"]:checked').length;
        var $bulkButton = $('.ldtt-bulk-action');
        
        if (checkedCount > 0) {
            $bulkButton.prop('disabled', false).text('Process ' + checkedCount + ' items');
        } else {
            $bulkButton.prop('disabled', true).text('Select items first');
        }
    });

    // Responsive tab handling for mobile
    if ($(window).width() < 768) {
        $('.nav-tab-wrapper').addClass('ldtt-mobile-tabs');
        
        $('.nav-tab').on('click', function() {
            setTimeout(function() {
                $('html, body').animate({
                    scrollTop: $('.tab-content:visible').offset().top - 50
                }, 300);
            }, 100);
        });
    }

    // Enhanced auto-save form data to localStorage
    if (typeof(Storage) !== "undefined") {
        // Save form data on input with enhanced keys
        $('.ldtt-form input, .ldtt-form select').on('change', function() {
            var formId = $(this).closest('form').attr('id') || 'ldtt-form';
            var inputName = $(this).attr('name');
            var inputValue = $(this).val();
            var inputType = $(this).attr('type');
            
            if (inputName && inputValue && inputType !== 'submit' && inputType !== 'button') {
                // Handle checkboxes specially
                if (inputType === 'checkbox') {
                    inputValue = $(this).is(':checked') ? '1' : '0';
                }
                
                localStorage.setItem('ldtt_' + formId + '_' + inputName, inputValue);
            }
        });
        
        // Restore form data on page load with enhanced restoration
        $('.ldtt-form input, .ldtt-form select').each(function() {
            var formId = $(this).closest('form').attr('id') || 'ldtt-form';
            var inputName = $(this).attr('name');
            var inputType = $(this).attr('type');
            var savedValue = localStorage.getItem('ldtt_' + formId + '_' + inputName);
            
            if (savedValue && !$(this).is('[type="submit"]') && !$(this).is('[type="button"]')) {
                if (inputType === 'checkbox') {
                    $(this).prop('checked', savedValue === '1');
                } else {
                    $(this).val(savedValue);
                }
            }
        });
        
        // Clear saved data when form is successfully submitted
        $('.ldtt-form').on('submit', function() {
            var formId = $(this).attr('id') || 'ldtt-form';
            // Clear only after a delay to ensure form submission succeeded
            setTimeout(function() {
                for (var i = localStorage.length - 1; i >= 0; i--) {
                    var key = localStorage.key(i);
                    if (key && key.startsWith('ldtt_' + formId + '_')) {
                        localStorage.removeItem(key);
                    }
                }
            }, 2000);
        });
    }

    // Initialize any existing tooltips or help elements
    $('.description').each(function() {
        $(this).css('font-style', 'italic');
    });

    // Enhanced loading overlay for heavy operations
    function showLoadingOverlay(message, isLargeOperation) {
        var spinnerSize = isLargeOperation ? '50px' : '40px';
        var $overlay = $('<div id="ldtt-loading-overlay"></div>')
            .css({
                position: 'fixed',
                top: 0,
                left: 0,
                width: '100%',
                height: '100%',
                background: 'rgba(255, 255, 255, 0.9)',
                zIndex: 100000,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                flexDirection: 'column'
            })
            .html('<div style="text-align: center;">' +
                  '<div style="width: ' + spinnerSize + '; height: ' + spinnerSize + '; border: 4px solid #f3f3f3; border-top: 4px solid #2271b1; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 20px;"></div>' +
                  '<p style="font-size: 16px; color: #333; font-weight: 600;">' + (message || 'Processing...') + '</p>' +
                  '<p style="font-size: 14px; color: #666; margin-top: 10px;">Please do not close this page or navigate away.</p>' +
                  '</div>');
        
        $('body').append($overlay);
        
        // Add CSS animation if not already present
        if (!$('#ldtt-spinner-style').length) {
            $('head').append('<style id="ldtt-spinner-style">@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }</style>');
        }
    }

    // Remove loading overlay
    function hideLoadingOverlay() {
        $('#ldtt-loading-overlay').remove();
    }

    // Show enhanced loading for distribution command
    $('button[value="enhanced-user-distribution"]').on('click', function() {
        var totalUsers = parseInt($('input[name="total_users"]').val()) || 100;
        var safeMode = $('input[name="safe_group_creation"]').is(':checked');
        if (totalUsers > 50) {
            var message = 'Creating ' + totalUsers + ' users with distribution and progress...';
            if (safeMode) {
                message += ' (Enhanced Safe Mode)';
            }
            showLoadingOverlay(message, totalUsers > 200);
        }
    });

    // Show loading for cleanup operations
    $('button[value="delete-items"]').on('click', function() {
        var checkedItems = $('#cleanup input[type="checkbox"]:checked').length;
        if (checkedItems > 0) {
            showLoadingOverlay('Cleaning up test data...', false);
        }
    });

    // Safe mode recommendation system
    function checkSafeModeRecommendation() {
        var $safeModeCheckbox = $('input[name="safe_group_creation"]');
        var $userModeRadio = $('input[name="user_mode"]:checked');
        var totalUsers = parseInt($('input[name="total_users"]').val()) || 100;
        var groupLeaders = parseFloat($('input[name="group_leaders"]').val()) || 1;
        
        if ($safeModeCheckbox.length && !$safeModeCheckbox.is(':checked')) {
            var leadersCount = Math.max(1, Math.round(totalUsers * (groupLeaders / 100)));
            
            // Show recommendation if creating many group leaders
            if (leadersCount > 3) {
                showSafeModeRecommendation();
            }
        }
    }
    
    function showSafeModeRecommendation() {
        if ($('#ldtt-safe-mode-notice').length) return; // Don't show multiple notices
        
        var $notice = $('<div id="ldtt-safe-mode-notice" class="notice notice-warning" style="margin: 10px 0;">' +
            '<p><strong>Recommendation:</strong> Consider enabling Enhanced Safe Mode for reliable group leader creation. ' +
            '<a href="#" id="enable-safe-mode">Enable Safe Mode</a> | ' +
            '<a href="#" id="dismiss-safe-mode">Dismiss</a></p>' +
            '</div>');
        
        $('#distribution-preview').before($notice);
        
        // Handle enabling safe mode
        $notice.find('#enable-safe-mode').on('click', function(e) {
            e.preventDefault();
            $('input[name="safe_group_creation"]').prop('checked', true).trigger('change');
            $notice.fadeOut();
        });
        
        // Handle dismissing notice
        $notice.find('#dismiss-safe-mode').on('click', function(e) {
            e.preventDefault();
            $notice.fadeOut();
        });
        
        // Auto-dismiss after 10 seconds
        setTimeout(function() {
            $notice.fadeOut();
        }, 10000);
    }
    
    // Check for safe mode recommendation when values change
    $(document).on('input change', 'input[name="total_users"], input[name="group_leaders"], input[name="safe_group_creation"]', function() {
        setTimeout(checkSafeModeRecommendation, 100);
    });
});