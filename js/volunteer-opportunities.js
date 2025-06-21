/**
 * Volunteer Opportunities Page JavaScript
 * Handles sign-up button interactions and UI feedback
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Handle volunteer sign-up button clicks
        $('.volunteer-signup-btn').on('click', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var eventId = $button.data('event-id');
            var shiftIndex = $button.data('shift-index');
            var shiftTitle = $button.data('shift-title');
            
            // Don't proceed if button is disabled
            if ($button.hasClass('btn-disabled') || $button.prop('disabled')) {
                return;
            }
            
            // Show loading state
            var originalText = $button.html();
            $button.html('<span class="dashicons dashicons-update"></span> ' + 'Signing up...');
            $button.prop('disabled', true);
            
            // TODO: Implement actual sign-up functionality
            // For now, just show a placeholder message
            setTimeout(function() {
                alert('Sign-up functionality will be implemented soon!\n\n' +
                      'Event ID: ' + eventId + '\n' +
                      'Shift: ' + shiftTitle + '\n' +
                      'Shift Index: ' + shiftIndex);
                
                // Restore button state
                $button.html(originalText);
                $button.prop('disabled', false);
            }, 1000);
        });
        
        // Add smooth scrolling for internal links
        $('a[href^="#"]').on('click', function(e) {
            e.preventDefault();
            
            var target = this.hash;
            var $target = $(target);
            
            if ($target.length) {
                $('html, body').animate({
                    scrollTop: $target.offset().top - 100
                }, 500);
            }
        });
        
        // Add hover effects for shift cards
        $('.volunteer-shift-card').hover(
            function() {
                $(this).addClass('card-hover');
            },
            function() {
                $(this).removeClass('card-hover');
            }
        );
        
        // Add click tracking for analytics (placeholder)
        $('.volunteer-shift-card').on('click', '.event-details-btn', function() {
            // TODO: Add analytics tracking for event detail clicks
            console.log('Event details clicked for:', $(this).attr('href'));
        });
        
        // Add keyboard accessibility
        $('.volunteer-signup-btn, .event-details-btn').on('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                $(this).click();
            }
        });
        
        // Auto-refresh page data every 5 minutes to keep volunteer counts current
        // (Only if there are volunteer opportunities visible)
        if ($('.volunteer-shift-card').length > 0) {
            setInterval(function() {
                // TODO: Implement AJAX refresh of volunteer counts
                console.log('Auto-refresh volunteer counts (placeholder)');
            }, 300000); // 5 minutes
        }
    });

})(jQuery); 