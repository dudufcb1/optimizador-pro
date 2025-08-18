/**
 * OptimizadorPro Admin JavaScript
 */

jQuery(document).ready(function($) {
    
    // Tab functionality
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        
        var targetTab = $(this).data('tab');
        
        // Update active tab
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        // Show target content
        $('.tab-content').removeClass('active');
        $('#' + targetTab + '-tab').addClass('active');
        
        // Update URL hash
        window.location.hash = targetTab;
    });
    
    // Handle initial hash
    if (window.location.hash) {
        var hash = window.location.hash.substring(1);
        var $targetTab = $('[data-tab="' + hash + '"]');
        if ($targetTab.length) {
            $targetTab.trigger('click');
        }
    }
    
    // Advanced option warnings
    $('.advanced-option').on('change', function() {
        if ($(this).is(':checked')) {
            var confirmed = confirm(
                'This is an advanced option that could potentially break your site if not configured properly. ' +
                'Make sure you understand what this does and test thoroughly. Continue?'
            );
            
            if (!confirmed) {
                $(this).prop('checked', false);
            }
        }
    });
    
    // Cache clear functionality
    $('#clear-cache-btn').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var originalText = $button.text();
        
        $button.text('Clearing...').prop('disabled', true);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'optimizador_pro_clear_cache',
                _ajax_nonce: optimizadorProAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $button.text('Cleared!').removeClass('button-secondary').addClass('button-primary');
                    
                    // Show success message
                    $('<div class="notice notice-success is-dismissible"><p>' + response.data.message + '</p></div>')
                        .insertAfter('.wrap h1');
                    
                    // Reset button after 2 seconds
                    setTimeout(function() {
                        $button.text(originalText).removeClass('button-primary').addClass('button-secondary').prop('disabled', false);
                    }, 2000);
                    
                    // Refresh cache status
                    refreshCacheStatus();
                } else {
                    $button.text('Error').prop('disabled', false);
                    alert('Error clearing cache. Please try again.');
                }
            },
            error: function() {
                $button.text('Error').prop('disabled', false);
                alert('Error clearing cache. Please try again.');
            }
        });
    });
    
    // Refresh cache status
    function refreshCacheStatus() {
        // This would be implemented if we had a separate endpoint for cache status
        // For now, we'll just reload the page section
        setTimeout(function() {
            location.reload();
        }, 1000);
    }
    
    // Form validation
    $('form').on('submit', function(e) {
        var hasErrors = false;
        
        // Validate exclusion fields (basic check for valid patterns)
        $('textarea[name*="exclusions"]').each(function() {
            var value = $(this).val().trim();
            if (value) {
                var lines = value.split('\n');
                for (var i = 0; i < lines.length; i++) {
                    var line = lines[i].trim();
                    if (line && line.length < 2) {
                        alert('Exclusion patterns should be at least 2 characters long: "' + line + '"');
                        hasErrors = true;
                        $(this).focus();
                        break;
                    }
                }
            }
        });
        
        if (hasErrors) {
            e.preventDefault();
        }
    });
    
    // Auto-save indication
    var formChanged = false;
    $('input, textarea, select').on('change', function() {
        formChanged = true;
        if (!$('.unsaved-changes').length) {
            $('<div class="notice notice-warning unsaved-changes"><p>You have unsaved changes.</p></div>')
                .insertAfter('.wrap h1');
        }
    });
    
    $('form').on('submit', function() {
        formChanged = false;
        $('.unsaved-changes').remove();
    });
    
    // Warn before leaving with unsaved changes
    $(window).on('beforeunload', function() {
        if (formChanged) {
            return 'You have unsaved changes. Are you sure you want to leave?';
        }
    });
    
    // Tooltips for advanced options
    $('[data-tooltip]').each(function() {
        $(this).attr('title', $(this).data('tooltip'));
    });
    
    // Toggle sections
    $('.section-toggle').on('click', function() {
        var $section = $(this).next('.section-content');
        $section.slideToggle();
        $(this).find('.dashicons').toggleClass('dashicons-arrow-down dashicons-arrow-up');
    });

});
