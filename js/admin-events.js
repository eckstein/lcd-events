/**
 * LCD Events Admin JavaScript
 */
jQuery(document).ready(function($) {
    console.log('LCD Events admin script loaded');

    // ==============================
    // Email Action Modal Dialog - Using Unified Modal System
    // ==============================
    
    function showEmailActionModal(actionType, onChoose) {
        // Define configurations for different action types
        const configs = {
            assign: {
                title: 'Assign Person to Volunteer Shift',
                description: 'How would you like to handle this assignment?',
                actionOnly: 'Assign Only',
                actionWithEmail: 'Assign & Send Email',
                noEmail: false
            },
            unassign: {
                title: 'Remove Person from Volunteer Shift',
                description: 'How would you like to handle this removal?',
                actionOnly: 'Remove Only',
                actionWithEmail: 'Remove & Send Email',
                noEmail: false
            },
            confirm: {
                title: 'Confirm Volunteer',
                description: 'How would you like to handle this confirmation?',
                actionOnly: 'Confirm Only',
                actionWithEmail: 'Confirm & Send Email',
                noEmail: false
            },
            unconfirm: {
                title: 'Unconfirm Volunteer',
                description: 'How would you like to handle removing confirmation?',
                actionOnly: 'Unconfirm Only',
                actionWithEmail: 'Unconfirm & Send Email',
                noEmail: false
            },
            deny: {
                title: 'Send Cancellation Notice',
                description: 'How would you like to handle this cancellation?',
                actionOnly: 'Cancel Without Email',
                actionWithEmail: 'Cancel & Send Notice',
                noEmail: false
            }
        };

        const config = configs[actionType];
        if (!config) {
            console.error('Unknown action type:', actionType);
            return;
        }

        // Build modal content
        let content = `<div class="lcd-email-action-content">
            <p style="margin-bottom: 15px; color: #666;">${config.description}</p>`;
        
        if (!config.noEmail) {
            content += `
            <div style="margin-bottom: 20px;">
                <label for="lcd-additional-message" style="display: block; margin-bottom: 5px; font-weight: 600;">Additional Message (Optional):</label>
                <textarea id="lcd-additional-message" placeholder="Enter any additional message to include in the email (e.g., special instructions, notes, etc.)" rows="3" style="width: 100%; resize: vertical; padding: 8px; border: 1px solid #ccd0d4; border-radius: 4px;"></textarea>
                <p style="margin: 5px 0 0 0; font-size: 12px; color: #666; font-style: italic;">This message will be available as {additional_message} in your email templates.</p>
            </div>`;
        }
        
        content += '</div>';

        // Build buttons array
        const buttons = [
            {
                text: 'Cancel',
                className: 'lcd-btn-secondary',
                action: 'cancel'
            },
            {
                text: config.actionOnly,
                className: 'lcd-btn-secondary',
                action: 'action-only',
                callback: function() {
                    const additionalMessage = $('#lcd-additional-message').val().trim();
                    onChoose(false, additionalMessage);
                    return true; // Close modal
                }
            }
        ];

        if (config.actionWithEmail) {
            buttons.push({
                text: config.actionWithEmail,
                className: 'lcd-btn-primary',
                action: 'action-with-email',
                callback: function() {
                    const additionalMessage = $('#lcd-additional-message').val().trim();
                    onChoose(true, additionalMessage);
                    return true; // Close modal
                }
            });
        }

        // Open the modal using LCDModal
        LCDModal.open({
            title: config.title,
            content: content,
            size: 'medium',
            className: 'lcd-email-action-modal',
            buttons: buttons
        });
    }

    // ==============================
    // Meta Box (Read-Only) Functionality
    // ==============================
    
    // Handle CSV export from meta box
    $('.lcd-volunteer-shifts-readonly .export-volunteers-csv').on('click', function() {
        var eventId = $(this).data('event-id');
        var exportUrl = ajaxurl + '?action=lcd_export_volunteers_csv&event_id=' + eventId;
        
        // Create a temporary link and trigger download
        var link = document.createElement('a');
        link.href = exportUrl;
        link.download = '';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    });

    // ==============================
    // Event Poster Upload functionality
    var frame;
    $('.event-poster-upload').on('click', function(e) {
        e.preventDefault();

        if (frame) {
            frame.open();
            return;
        }

        frame = wp.media({
            title: lcdEventsAdmin.text.select_upload_poster,
            button: {
                text: lcdEventsAdmin.text.use_image
            },
            multiple: false
        });

        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            $('#event_poster').val(attachment.id);
            $('.event-poster-preview').html('<img src="' + attachment.sizes.medium.url + '" style="max-width: 100%; height: auto;">');
            $('.event-poster-remove').show();
        });

        frame.open();
    });

    $('.event-poster-remove').on('click', function(e) {
        e.preventDefault();
        $('#event_poster').val('');
        $('.event-poster-preview').empty();
        $(this).hide();
    });

    // Volunteer Shifts Management
    let shiftIndex = parseInt(lcdEventsAdmin.initial_shift_count || 0);
    
    // Initialize existing shift indices for admin page
    $('.volunteer-shift-item').each(function() {
        const currentIndex = parseInt($(this).data('index')) || 0;
        if (currentIndex >= shiftIndex) {
            shiftIndex = currentIndex + 1;
        }
    });
    
    // Toggle shift details
    $(document).on('click', '.toggle-shift-details', function() {
        const button = $(this);
        const shiftItem = button.closest('.volunteer-shift-item');
        const details = shiftItem.find('.shift-details');
        const icon = button.find('.dashicons');
        const isExpanded = button.data('expanded') === true;
        
        if (isExpanded) {
            details.slideUp(200);
            icon.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
            button.data('expanded', false);
        } else {
            details.slideDown(200);
            icon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
            button.data('expanded', true);

            // Initialize Select2 when expanding if not already initialized
            const select = details.find('.assign-volunteer-select');
            if (!select.data('select2init')) {
                initShiftPersonSearch(shiftItem);
            }
        }
    });
    
    // Make entire shift summary clickable to expand/collapse
    $(document).on('click', '.shift-summary', function(e) {
        // Don't toggle if clicking on action buttons
        if ($(e.target).closest('.shift-summary-actions').length > 0) {
            return;
        }
        
        const summary = $(this);
        const shiftItem = summary.closest('.volunteer-shift-item');
        const details = shiftItem.find('.shift-details');
        const toggleButton = summary.find('.toggle-shift-details');
        const icon = toggleButton.find('.dashicons');
        const isExpanded = toggleButton.data('expanded') === true;
        
        if (isExpanded) {
            details.slideUp(200);
            icon.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
            toggleButton.data('expanded', false);
            summary.removeClass('shift-summary-expanded');
        } else {
            details.slideDown(200);
            icon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
            toggleButton.data('expanded', true);
            summary.addClass('shift-summary-expanded');

            // Initialize Select2 when expanding if not already initialized
            const select = details.find('.assign-volunteer-select');
            if (!select.data('select2init')) {
                initShiftPersonSearch(shiftItem);
            }
        }
    });
    
    // Prevent action button clicks from bubbling up to shift summary
    $(document).on('click', '.shift-summary-actions .button', function(e) {
        e.stopPropagation();
    });
    
    // Add new shift - admin page only
    $(document).on('click', '.add-new-shift', function() {
        const button = $(this);
        const eventId = button.data('event-id');
        
        if (!eventId) {
            console.error('No event ID found for add shift button');
            return;
        }
        
        // Admin page only - get template for specific event
        const template = $(`#volunteer-shift-template-${eventId} .volunteer-shift-item`).clone();
        const container = $(`#volunteer-shifts-container-${eventId}`);
        
        if (template.length === 0) {
            console.error('Template not found for adding new shift');
            return;
        }
        
        const html = template.prop('outerHTML')
            .replace(/__INDEX__/g, shiftIndex);
        
        const newShift = $(html);
        
        // Insert the new shift at the end of the container (before the closing </form> tag)
        container.append(newShift);
        
        newShift.find('.shift-details').show();
        newShift.find('.toggle-shift-details').data('expanded', true)
            .find('.dashicons').removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');

        initShiftPersonSearch(newShift); 
        
        // Focus on the title field
        newShift.find('input[name*="[title]"]').focus();
        
        shiftIndex++;
        updateShiftSummaries(); 
    });
    
    // Individual shift saving
    $(document).on('click', '.save-shift-btn', function() {
        const button = $(this);
        const shiftItem = button.closest('.volunteer-shift-item');
        const eventId = button.data('event-id');
        const shiftIndex = button.data('shift-index');
        const statusSpan = button.siblings('.shift-save-status');
        
        // Collect shift data from form fields
        const shiftData = {
            title: shiftItem.find('input[name*="[title]"]').val(),
            description: shiftItem.find('textarea[name*="[description]"]').val(),
            date: shiftItem.find('input[name*="[date]"]').val(),
            start_time: shiftItem.find('input[name*="[start_time]"]').val(),
            end_time: shiftItem.find('input[name*="[end_time]"]').val(),
            max_volunteers: shiftItem.find('input[name*="[max_volunteers]"]').val()
        };
        
        // Validation
        if (!shiftData.title.trim()) {
            LCDModal.alert({
                title: 'Validation Error',
                content: '<p>Shift title is required.</p>',
                size: 'small'
            });
            shiftItem.find('input[name*="[title]"]').focus();
            return;
        }
        
        // Show saving state
        const originalText = button.html();
        button.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> Saving...');
        statusSpan.removeClass('success error').addClass('saving').text('Saving...').show();
        
        $.ajax({
            url: lcdEventsAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'lcd_save_individual_shift',
                nonce: lcdEventsAdmin.save_shift_nonce,
                event_id: eventId,
                shift_index: shiftIndex,
                shift_data: shiftData
            },
            success: function(response) {
                if (response.success) {
                    statusSpan.removeClass('saving error').addClass('success').text('Saved!');
                    
                    // Update shift summary with new data
                    updateShiftSummaries();
                    
                    // Hide status after 2 seconds
                    setTimeout(function() {
                        statusSpan.fadeOut();
                    }, 2000);
                } else {
                    statusSpan.removeClass('saving success').addClass('error').text(response.data.message || 'Save failed');
                }
            },
            error: function(xhr, status, error) {
                let errorMessage = 'Save failed';
                if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    errorMessage = xhr.responseJSON.data.message;
                }
                statusSpan.removeClass('saving success').addClass('error').text(errorMessage);
            },
            complete: function() {
                button.prop('disabled', false).html(originalText);
            }
        });
    });
    
    // Remove shift
    $(document).on('click', '.remove-shift', function() {
        const shiftItem = $(this).closest('.volunteer-shift-item');
        const hasSignups = shiftItem.find('.signup-row').length > 0;
        const shiftTitle = shiftItem.find('input[name*="[title]"]').val() || 'Untitled Shift';
        
        if (hasSignups) {
            // Create a single modal with both confirmation and email choice for shifts with volunteers
            const modalContent = `
                <div class="lcd-remove-shift-content">
                    <p style="margin-bottom: 15px;">Are you sure you want to remove the shift <strong>"${shiftTitle}"</strong>?</p>
                    <p style="margin-bottom: 15px; color: #d63638;"><strong>Warning:</strong> This will unassign all ${hasSignups} volunteer(s) from this shift.</p>
                    <div style="margin-bottom: 20px;">
                        <label for="lcd-additional-message" style="display: block; margin-bottom: 5px; font-weight: 600;">Additional Message (Optional):</label>
                        <textarea id="lcd-additional-message" placeholder="Enter any additional message to include in the cancellation email (e.g., reason for cancellation, apology, etc.)" rows="3" style="width: 100%; resize: vertical; padding: 8px; border: 1px solid #ccd0d4; border-radius: 4px;"></textarea>
                        <p style="margin: 5px 0 0 0; font-size: 12px; color: #666; font-style: italic;">This message will be included in the cancellation email if you choose to send one.</p>
                    </div>
                </div>
            `;

            LCDModal.open({
                title: 'Remove Volunteer Shift',
                content: modalContent,
                size: 'medium',
                className: 'lcd-remove-shift-modal',
                buttons: [
                    {
                        text: 'Cancel',
                        className: 'lcd-btn-secondary',
                        action: 'cancel'
                    },
                    {
                        text: 'Remove Without Email',
                        className: 'lcd-btn-secondary',
                        action: 'remove-no-email',
                        callback: function() {
                            const additionalMessage = $('#lcd-additional-message').val().trim();
                            removeShiftWithEmails(shiftItem, false, additionalMessage);
                            return true; // Close modal
                        }
                    },
                    {
                        text: 'Remove & Send Cancellation',
                        className: 'lcd-btn-danger',
                        action: 'remove-with-email',
                        callback: function() {
                            const additionalMessage = $('#lcd-additional-message').val().trim();
                            removeShiftWithEmails(shiftItem, true, additionalMessage);
                            return true; // Close modal
                        }
                    }
                ]
            });
        } else {
            // No volunteers, just confirm removal
            LCDModal.confirm({
                title: 'Remove Volunteer Shift',
                content: `<p>Are you sure you want to remove the shift <strong>"${shiftTitle}"</strong>?</p>`,
                size: 'small'
            }).then(function(confirmed) {
                if (confirmed) {
                    shiftItem.slideUp(200, function() {
                        $(this).remove();
                        updateShiftSummaries();
                    });
                }
            });
        }
    });
    
    // Helper function to remove shift and send emails if needed
    function removeShiftWithEmails(shiftItem, sendEmail, additionalMessage) {
        if (sendEmail) {
            // Get all volunteer signup IDs from this shift
            const signupIds = [];
            shiftItem.find('.signup-row').each(function() {
                const signupId = $(this).data('signup-id');
                if (signupId) {
                    signupIds.push(signupId);
                }
            });
            
            // Send cancellation emails to all volunteers
            if (signupIds.length > 0) {
                $.ajax({
                    url: lcdEventsAdmin.ajaxurl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'lcd_send_shift_cancellation_emails',
                        nonce: lcdEventsAdmin.unassign_person_nonce,
                        signup_ids: signupIds,
                        additional_message: additionalMessage || ''
                    },
                    success: function(response) {
                        if (!response.success) {
                            console.warn('Some cancellation emails may not have been sent:', response.data);
                        }
                    },
                    error: function() {
                        console.warn('Failed to send cancellation emails');
                    }
                });
            }
        }
        
        // Remove the shift from UI
        shiftItem.slideUp(200, function() {
            $(this).remove();
            updateShiftSummaries();
        });
    }
    
    // Remove volunteer from shift
    $(document).on('click', '.remove-volunteer', function() {
        const signupId = $(this).data('signup-id');
        const signupRow = $(this).closest('.signup-row');
        const volunteerName = signupRow.find('.signup-name').text().trim();
        const shiftItem = signupRow.closest('.volunteer-shift-item');
        const shiftTitle = shiftItem.find('input[name*="[title]"]').val() || 'Untitled Shift';
        
        // Create a single modal with both confirmation and email choice
        const modalContent = `
            <div class="lcd-remove-volunteer-content">
                <p style="margin-bottom: 15px;">Are you sure you want to remove <strong>${volunteerName}</strong> from the shift <strong>"${shiftTitle}"</strong>?</p>
                <div style="margin-bottom: 20px;">
                    <label for="lcd-additional-message" style="display: block; margin-bottom: 5px; font-weight: 600;">Additional Message (Optional):</label>
                    <textarea id="lcd-additional-message" placeholder="Enter any additional message to include in the email (e.g., reason for removal, apology, etc.)" rows="3" style="width: 100%; resize: vertical; padding: 8px; border: 1px solid #ccd0d4; border-radius: 4px;"></textarea>
                    <p style="margin: 5px 0 0 0; font-size: 12px; color: #666; font-style: italic;">This message will be included in the notification email if you choose to send one.</p>
                </div>
            </div>
        `;

        LCDModal.open({
            title: 'Remove Volunteer',
            content: modalContent,
            size: 'medium',
            className: 'lcd-remove-volunteer-modal',
            buttons: [
                {
                    text: 'Cancel',
                    className: 'lcd-btn-secondary',
                    action: 'cancel'
                },
                {
                    text: 'Remove Without Email',
                    className: 'lcd-btn-secondary',
                    action: 'remove-no-email',
                    callback: function() {
                        const additionalMessage = $('#lcd-additional-message').val().trim();
                        removeVolunteerFromShift(signupId, signupRow, shiftItem, false, additionalMessage);
                        return true; // Close modal
                    }
                },
                {
                    text: 'Remove & Send Email',
                    className: 'lcd-btn-danger',
                    action: 'remove-with-email',
                    callback: function() {
                        const additionalMessage = $('#lcd-additional-message').val().trim();
                        removeVolunteerFromShift(signupId, signupRow, shiftItem, true, additionalMessage);
                        return true; // Close modal
                    }
                }
            ]
        });
    });
    
    // Helper function to remove volunteer from shift
    function removeVolunteerFromShift(signupId, signupRow, shiftItem, sendEmail, additionalMessage) {
        $.ajax({
            url: lcdEventsAdmin.ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'lcd_unassign_person_from_shift',
                nonce: lcdEventsAdmin.unassign_person_nonce,
                signup_id: signupId,
                send_email: sendEmail,
                additional_message: additionalMessage || ''
            },
            success: function(response) {
                if (response.success) {
                    // Remove the signup row with animation
                    signupRow.fadeOut(200, function() {
                        $(this).remove();
                        
                        // Update signup count
                        updateShiftSummaryCount(shiftItem);
                        
                        // If no more signups, remove the entire signups section
                        const signupsSection = shiftItem.find('.shift-signups');
                        if (signupsSection.find('.signup-row').length === 0) {
                            signupsSection.remove();
                        }
                    });
                } else {
                    const errorMessage = response.data && response.data.message 
                        ? response.data.message 
                        : (lcdEventsAdmin.text.error_unassigning || 'Could not remove volunteer. Please try again.');
                    LCDModal.alert({
                        title: 'Error',
                        content: `<p>${errorMessage}</p>`,
                        size: 'small'
                    });
                }
            },
            error: function() {
                const errorMessage = lcdEventsAdmin.text.error_unassigning || 'Could not remove volunteer. Please try again.';
                LCDModal.alert({
                    title: 'Error',
                    content: `<p>${errorMessage}</p>`,
                    size: 'small'
                });
            }
        });
    }
    
    // Update shift summaries when form fields change
    function updateShiftSummaries() {
        $('.volunteer-shift-item').each(function() {
            const shiftItem = $(this);
            const title = shiftItem.find('input[name*="[title]"]').val() || lcdEventsAdmin.text.untitled_shift || 'Untitled Shift';
            const dateInput = shiftItem.find('input[name*="[date]"]').val();
            const startTime = shiftItem.find('input[name*="[start_time]"]').val();
            const endTime = shiftItem.find('input[name*="[end_time]"]').val();
            
            shiftItem.find('.shift-title-summary strong').text(title);
            
            let metaHtml = '';
            if (dateInput) {
                const dateParts = dateInput.split('-');
                const dateObj = new Date(dateParts[0], dateParts[1] - 1, dateParts[2]);
                metaHtml += '<span class="shift-date-summary">' + dateObj.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' }) + '</span>';
            }

            let timeString = '';
            if (startTime) {
                const startTimeObj = new Date('1970-01-01T' + startTime + ':00');
                timeString = startTimeObj.toLocaleTimeString([], {hour: 'numeric', minute: '2-digit'});
            }
            if (endTime) {
                const endTimeObj = new Date('1970-01-01T' + endTime + ':00');
                timeString += (timeString ? ' - ' : '') + endTimeObj.toLocaleTimeString([], {hour: 'numeric', minute: '2-digit'});
            }
            if(timeString){
                metaHtml += '<span class="shift-time-summary">' + timeString + '</span>';
            }

            const signupCountBadge = shiftItem.find('.shift-signups-summary');
            if(signupCountBadge.length > 0) {
                metaHtml += signupCountBadge.prop('outerHTML');
            }
            
            if (metaHtml.trim() === '') {
                metaHtml = '<span class="shift-placeholder">' + (lcdEventsAdmin.text.enter_details || 'Click Edit to configure') + '</span>';
            }
            
            shiftItem.find('.shift-meta-summary').html(metaHtml);
        });
    }
    
    $(document).on('input change', '.shift-details input, .shift-details textarea', function() {
        updateShiftSummaries();
    });
    
    // Auto-fill shift date from event date (admin page only)
    $(document).on('focus', '.shift-date', function() {
        // Only auto-fill if we're on an admin page with form data
        const eventForm = $(this).closest('form');
        if (!$(this).val() && eventForm.length > 0) {
            const eventDateInput = eventForm.find('input[name="event_date"]');
            if (eventDateInput.val()) {
                $(this).val(eventDateInput.val());
                updateShiftSummaries();
            }
        }
    });
    

    
    // Initialize Select2 for person search
    function initShiftPersonSearch(container) {
        container.find('.assign-volunteer-select').each(function() {
            const $select = $(this);
            if ($select.data('select2init')) return;
            $select.data('select2init', true);

            $select.select2({
                ajax: {
                    url: lcdEventsAdmin.ajaxurl,
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {
                            q: params.term, 
                            action: 'lcd_search_people_for_shifts',
                            nonce: lcdEventsAdmin.search_people_nonce
                        };
                    },
                    processResults: function (data, params) {
                        params.page = params.page || 1;
                        
                        if (data.success && data.data && data.data.items) {
                            return {
                                results: data.data.items,
                                pagination: {
                                    more: (params.page * 10) < (data.data.total_count || 0)
                                }
                            };
                        } else {
                            return {
                                results: [],
                                pagination: { more: false }
                            };
                        }
                    },
                    cache: true
                },
                placeholder: lcdEventsAdmin.text.search_placeholder || 'Search by name or email...',
                minimumInputLength: 2,
                allowClear: true,
                width: '100%',
                language: {
                    inputTooShort: function () {
                        return lcdEventsAdmin.text.input_too_short || 'Please enter 2 or more characters';
                    },
                    searching: function () {
                        return lcdEventsAdmin.text.searching || 'Searching...';
                    },
                    noResults: function () {
                        return lcdEventsAdmin.text.no_results || 'No people found matching your search.';
                    },
                    loadingMore: function () {
                        return 'Loading more results...';
                    }
                }
            });

            // Handle selection
            $select.on('select2:select', function (e) {
                const data = e.params.data;
                const personId = data.id;
                const shiftItem = $(this).closest('.volunteer-shift-item');
                const eventId = shiftItem.data('event-id') || $(this).data('event-id');
                const shiftIndex = shiftItem.data('index') || $(this).data('shift-index');
                const notes = shiftItem.find('.shift-assignment-notes').val();

                assignPersonToShift(personId, shiftIndex, shiftItem, eventId, notes);
                
                // Clear the select and notes
                $(this).val(null).trigger('change');
                shiftItem.find('.shift-assignment-notes').val('');
            });
        });
    }

    function assignPersonToShift(personId, shiftIndex, shiftItemElement, eventId, notes = '') {
        showEmailActionModal('assign', function(sendEmail, additionalMessage) {
            const shiftTitle = shiftItemElement.find('input[name*="[title]"]').val() || 'Untitled Shift';

            $.ajax({
                url: lcdEventsAdmin.ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'lcd_assign_person_to_shift',
                    nonce: lcdEventsAdmin.assign_person_nonce,
                    event_id: eventId,
                    shift_index: shiftIndex,
                    shift_title: shiftTitle,
                    person_id: personId,
                    assignment_notes: notes,
                    send_email: sendEmail,
                    additional_message: additionalMessage || ''
                },
            success: function(response) {
                if (response.success) {
                    // Find or create the signups section
                    let signupsSection = shiftItemElement.find('.shift-signups');
                    if (signupsSection.length === 0) {
                        // Create the signups section if it doesn't exist
                        const signupsHtml = `
                            <div class="shift-signups">
                                <h5>
                                    <span class="dashicons dashicons-groups"></span>
                                    ${lcdEventsAdmin.text.registered_volunteers || 'Registered Volunteers:'}
                                    <span class="signups-count">(1)</span>
                                </h5>
                                <div class="signups-list">
                                    <table class="signups-table">
                                        <thead>
                                            <tr>
                                                <th>Volunteer</th>
                                                <th>Contact</th>
                                                <th>Notes</th>
                                                <th>Status</th>
                                                <th>Signup Date</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                        `;
                        shiftItemElement.find('.assign-volunteer-section').before(signupsHtml);
                        signupsSection = shiftItemElement.find('.shift-signups');
                    }

                    // Add the new signup to the table body
                    const tableBody = signupsSection.find('.signups-table tbody');
                    tableBody.append(response.data.new_signup_html);

                    // Update the count
                    const newCount = tableBody.find('tr').length;
                    signupsSection.find('.signups-count').text(`(${newCount})`);
                    
                    // Update shift summary
                    updateShiftSummaryCount(shiftItemElement);
                } else {
                    let errorMessage = lcdEventsAdmin.text.error_assigning || 'Could not assign volunteer. Please try again.';
                    if (response.data && response.data.message) {
                        errorMessage = response.data.message;
                    }
                    LCDModal.alert({
                        title: 'Error',
                        content: `<p>${errorMessage}</p>`,
                        size: 'small'
                    });
                }
            },
            error: function(xhr, status, error) {
                // Display more detailed error information
                let errorMessage = lcdEventsAdmin.text.error_assigning || 'Could not assign volunteer. Please try again.';
                if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    errorMessage = xhr.responseJSON.data.message;
                } else if (xhr.status === 404) {
                    errorMessage = 'AJAX request failed: 404 Not Found. Check that the WordPress AJAX URL is correct.';
                } else if (xhr.status !== 200) {
                    errorMessage = `AJAX request failed: ${xhr.status} ${error}`;
                }
                LCDModal.alert({
                    title: 'Error', 
                    content: `<p>${errorMessage}</p>`,
                    size: 'small'
                });
            }
        });
        });
    }

    // Unassign person from shift
    $(document).on('click', '.unassign-volunteer', function() {
        const button = $(this);
        const signupRow = button.closest('tr.signup-row');
        const signupId = signupRow.data('signup-id');
        const shiftItem = button.closest('.volunteer-shift-item');

        showEmailActionModal('unassign', function(sendEmail, additionalMessage) {
            $.ajax({
                url: lcdEventsAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'lcd_unassign_person_from_shift',
                    nonce: lcdEventsAdmin.unassign_person_nonce,
                    signup_id: signupId,
                    send_email: sendEmail,
                    additional_message: additionalMessage || ''
                },
            success: function(response) {
                if (response.success) {
                    signupRow.fadeOut(200, function() {
                        const tableBody = $(this).closest('tbody');
                        $(this).remove();
                        
                        // Update count in header
                        const remainingSignups = tableBody.find('tr').length;
                        tableBody.closest('.shift-signups').find('.signups-count').text(`(${remainingSignups})`);
                        
                        // If no more signups, remove the entire signups section
                        if (remainingSignups === 0) {
                            tableBody.closest('.shift-signups').remove();
                        }

                        // Update the summary count
                        updateShiftSummaryCount(shiftItem);
                    });
                } else {
                    LCDModal.alert({
                        title: 'Error',
                        content: `<p>${lcdEventsAdmin.text.error_unassigning || 'Could not remove volunteer. Please try again.'}</p>`,
                        size: 'small'
                    });
                }
            },
            error: function() {
                LCDModal.alert({
                    title: 'Error',
                    content: `<p>${lcdEventsAdmin.text.error_unassigning || 'Could not remove volunteer. Please try again.'}</p>`,
                    size: 'small'
                });
            }
        });
        });
    });
    
    updateShiftSummaries();
    
    // Inline notes editing functionality
    $(document).on('click', '.edit-notes', function() {
        const notesDisplay = $(this).closest('.signup-notes-display');
        const notesEdit = notesDisplay.siblings('.signup-notes-edit');
        notesDisplay.hide();
        notesEdit.show().find('textarea').focus();
    });
    
    $(document).on('click', '.cancel-notes', function() {
        const notesEdit = $(this).closest('.signup-notes-edit');
        const notesDisplay = notesEdit.siblings('.signup-notes-display');
        notesEdit.hide();
        notesDisplay.show();
    });
    
    $(document).on('click', '.save-notes', function() {
        const button = $(this);
        const notesEdit = button.closest('.signup-notes-edit');
        const notesDisplay = notesEdit.siblings('.signup-notes-display');
        const signupRow = button.closest('tr.signup-row');
        const signupId = signupRow.data('signup-id');
        const newNotes = notesEdit.find('textarea').val();

        $.ajax({
            url: lcdEventsAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'lcd_edit_volunteer_notes',
                nonce: lcdEventsAdmin.edit_notes_nonce,
                signup_id: signupId,
                notes: newNotes
            },
            success: function(response) {
                if (response.success) {
                    const notesText = notesDisplay.find('.signup-notes-text');
                    if (newNotes) {
                        notesText.text(newNotes).removeClass('no-notes');
                    } else {
                        notesText.text(lcdEventsAdmin.text.no_notes || 'No notes').addClass('no-notes');
                    }
                    notesEdit.hide();
                    notesDisplay.show();
                } else {
                    LCDModal.alert({
                        title: 'Error',
                        content: `<p>${lcdEventsAdmin.text.error_editing_notes || 'Could not save notes. Please try again.'}</p>`,
                        size: 'small'
                    });
                }
            },
            error: function() {
                LCDModal.alert({
                    title: 'Error',
                    content: `<p>${lcdEventsAdmin.text.error_editing_notes || 'Could not save notes. Please try again.'}</p>`,
                    size: 'small'
                });
            }
        });
    });

    // Toggle volunteer confirmed status
    $(document).on('click', '.toggle-confirmed', function() {
        const button = $(this);
        const signupRow = button.closest('tr.signup-row');
        const signupId = signupRow.data('signup-id');
        const currentlyConfirmed = button.hasClass('confirmed');
        const newConfirmed = !currentlyConfirmed;
        const volunteerName = signupRow.find('.signup-name').text().trim();
        const shiftItem = signupRow.closest('.volunteer-shift-item');
        const shiftTitle = shiftItem.find('input[name*="[title]"]').val() || 'Untitled Shift';

        if (newConfirmed) {
            // For confirming, show email action modal
            showEmailActionModal('confirm', function(sendEmail, additionalMessage) {
                toggleVolunteerConfirmation(signupId, newConfirmed, sendEmail, additionalMessage, button, signupRow);
            });
        } else {
            // For unconfirming, just show simple confirmation dialog (no email)
            LCDModal.confirm({
                title: 'Unconfirm Volunteer',
                content: `<p>Are you sure you want to unconfirm <strong>${volunteerName}</strong> for the shift <strong>"${shiftTitle}"</strong>?</p><p>This will mark their assignment as unconfirmed but they will remain assigned to the shift.</p>`,
                size: 'medium'
            }).then(function(confirmed) {
                if (confirmed) {
                    toggleVolunteerConfirmation(signupId, newConfirmed, false, '', button, signupRow);
                }
            });
        }
    });
    
    // Helper function to handle the actual confirmation toggle
    function toggleVolunteerConfirmation(signupId, newConfirmed, sendEmail, additionalMessage, button, signupRow) {
        $.ajax({
            url: lcdEventsAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'lcd_toggle_volunteer_confirmed',
                nonce: lcdEventsAdmin.toggle_confirmed_nonce,
                signup_id: signupId,
                confirmed: newConfirmed ? '1' : '0',
                send_email: sendEmail,
                additional_message: additionalMessage || ''
            },
            success: function(response) {
                if (response.success) {
                    if (newConfirmed) {
                        button.removeClass('unconfirmed').addClass('confirmed');
                        button.attr('title', 'Mark as unconfirmed');
                        button.text('Unconfirm');
                        signupRow.removeClass('status-unconfirmed').addClass('status-confirmed');
                    } else {
                        button.removeClass('confirmed').addClass('unconfirmed');
                        button.attr('title', 'Mark as confirmed');
                        button.text('Confirm');
                        signupRow.removeClass('status-confirmed').addClass('status-unconfirmed');
                    }
                } else {
                    LCDModal.alert({
                        title: 'Error',
                        content: '<p>Could not update confirmation status. Please try again.</p>',
                        size: 'small'
                    });
                }
            },
            error: function() {
                LCDModal.alert({
                    title: 'Error',
                    content: '<p>Could not update confirmation status. Please try again.</p>',
                    size: 'small'
                });
            }
        });
    }

    // Helper function to update shift summary count
    function updateShiftSummaryCount(shiftItem) {
        const tableBody = shiftItem.find('.signups-table tbody');
        const count = tableBody.find('tr').length;
        const maxVolunteersInput = shiftItem.find('input[name*="[max_volunteers]"]');
        const maxVolunteers = maxVolunteersInput.val() || '';
        
        let summaryText;
        if (maxVolunteers && parseInt(maxVolunteers) > 0) {
            summaryText = `${count} / ${maxVolunteers} volunteers`;
        } else {
            summaryText = `${count} volunteers`;
        }
        
        let summaryBadge = shiftItem.find('.shift-signups-summary');
        if (summaryBadge.length === 0) {
            // Create the summary badge if it doesn't exist
            shiftItem.find('.shift-meta-summary').append('<span class="shift-signups-summary"></span>');
            summaryBadge = shiftItem.find('.shift-signups-summary');
        }
        summaryBadge.text(summaryText);
        
        // Update the summary display
        updateShiftSummaries();
    }

    // Export functionality
    $(document).on('click', '.export-volunteers-csv', function() {
        const eventId = $(this).data('event-id');
        if (!eventId) {
            LCDModal.alert({
                title: 'Error',
                content: '<p>No event ID found</p>',
                size: 'small'
            });
            return;
        }
        
        // Create a temporary link to trigger download
        const url = lcdEventsAdmin.ajaxurl + '?action=lcd_export_volunteers_csv&event_id=' + eventId;
        window.open(url, '_blank');
    });

    $(document).on('click', '.export-volunteers-pdf', function() {
        const eventId = $(this).data('event-id');
        if (!eventId) {
            LCDModal.alert({
                title: 'Error',
                content: '<p>No event ID found</p>',
                size: 'small'
            });
            return;
        }
        
        // Create a temporary link to trigger download
        const url = lcdEventsAdmin.ajaxurl + '?action=lcd_export_volunteers_pdf&event_id=' + eventId;
        window.open(url, '_blank');
    });
}); 