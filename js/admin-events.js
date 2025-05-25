/**
 * LCD Events Admin JavaScript
 */
jQuery(document).ready(function($) {
    console.log('LCD Events admin script loaded');

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
            const select = details.find('.lcd-person-search-select');
            if (!select.data('select2init')) {
                initShiftPersonSearch(shiftItem);
            }
        }
    });
    
    // Add new shift
    $('#add-volunteer-shift').on('click', function() {
        const template = $('#volunteer-shift-template .volunteer-shift-item').clone();
        const html = template.prop('outerHTML')
            .replace(/__INDEX__/g, shiftIndex);
        
        const newShift = $(html);
        $('#volunteer-shifts-container').append(newShift);
        
        newShift.find('.shift-details').show();
        newShift.find('.toggle-shift-details').data('expanded', true)
            .find('.dashicons').removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');

        initShiftPersonSearch(newShift); 
        
        shiftIndex++;
        updateShiftSummaries(); 
    });
    
    // Remove shift
    $(document).on('click', '.remove-shift', function() {
        const shiftItem = $(this).closest('.volunteer-shift-item');
        const hasSignups = shiftItem.find('.signup-item-compact').length > 0;
        
        if (hasSignups) {
            if (!confirm(lcdEventsAdmin.text.confirm_remove_shift)) {
                return;
            }
        }
        
        shiftItem.slideUp(200, function() {
            $(this).remove();
            updateShiftSummaries();
        });
    });
    
    // Update shift summaries when form fields change
    function updateShiftSummaries() {
        $('.volunteer-shift-item').each(function() {
            const shiftItem = $(this);
            const title = shiftItem.find('input[name*="[title]"]').val() || lcdEventsAdmin.text.untitled_shift;
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
                metaHtml = '<span class="shift-placeholder">' + lcdEventsAdmin.text.enter_details + '</span>';
            }
            
            shiftItem.find('.shift-meta-summary').html(metaHtml);
        });
    }
    
    $(document).on('input change', '.shift-details input, .shift-details textarea', function() {
        updateShiftSummaries();
    });
    
    $(document).on('focus', '.shift-date', function() {
        if (!$(this).val() && $('#event_date').val()) {
            $(this).val($('#event_date').val());
            updateShiftSummaries();
        }
    });
    
    // Initialize Select2 for person search
    function initShiftPersonSearch(container) {
        container.find('.lcd-person-search-select').each(function() {
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
                dropdownParent: $select.closest('.assign-volunteer-controls'),
                language: {
                    inputTooShort: function() { return lcdEventsAdmin.text.input_too_short || 'Please enter 2 or more characters'; },
                    searching: function() { return lcdEventsAdmin.text.searching || 'Searching...'; },
                    noResults: function() { return lcdEventsAdmin.text.no_results || 'No people found matching your search.'; },
                    errorLoading: function() { return lcdEventsAdmin.text.error_loading || 'Could not load search results.'; }
                }
            }).on('select2:select', function (e) {
                const person = e.params.data;
                const shiftItem = $(this).closest('.volunteer-shift-item');
                const shiftIndex = shiftItem.data('index');
                
                // Get event ID - either from data attribute (overview page) or global variable (edit page)
                let eventId = shiftItem.data('event-id');
                if (!eventId && typeof lcdEventsAdmin.event_id !== 'undefined') {
                    eventId = lcdEventsAdmin.event_id;
                }
                
                if (eventId) {
                    assignPersonToShift(person.id, shiftIndex, shiftItem, eventId);
                } else {
                    console.error('No event ID found for shift assignment');
                    alert('Error: Could not determine event ID for assignment');
                }
            });
        });
    }
    
    // Initialize for existing items on page load
    $(document).ready(function() {
        // Initialize for single event edit page
        initShiftPersonSearch($('#volunteer-shifts-container'));
        
        // Initialize for volunteer shifts overview page
        initShiftPersonSearch($('.lcd-volunteer-shifts-overview'));
        
        // Initialize Select2 for any expanded shifts on page load
        $('.shift-details:visible').each(function() {
            initShiftPersonSearch($(this).closest('.volunteer-shift-item'));
        });
    });

    // Assign person to shift
    function assignPersonToShift(personId, shiftIndex, shiftItemElement, eventId) {
        const assignmentNotes = shiftItemElement.find('.shift-assignment-notes').val();
        
        // Get shift title - different methods for edit page vs overview page
        let shiftTitle = shiftItemElement.find('.shift-title-summary strong').text();
        if (!shiftTitle || shiftTitle === '') {
            // Fallback for edit page
            const shiftTitleInput = shiftItemElement.find('input[name*="[title]"]');
            shiftTitle = shiftTitleInput.val() || 'Shift ' + (shiftIndex + 1);
        }

        $.ajax({
            url: lcdEventsAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'lcd_assign_person_to_shift',
                nonce: lcdEventsAdmin.assign_person_nonce,
                person_id: personId,
                event_id: eventId,
                shift_index: shiftIndex,
                shift_title: shiftTitle,
                assignment_notes: assignmentNotes
            },
            success: function(response) {
                if (response.success) {
                    // Add the new signup to the list
                    const signupsList = shiftItemElement.find('.signups-list');
                    if (signupsList.length === 0) {
                        // Create signups section if it doesn't exist
                        const registeredVolunteersText = lcdEventsAdmin.text.registered_volunteers || 'Registered Volunteers:';
                        const signupsHtml = `
                            <div class="shift-signups">
                                <h5>
                                    <span class="dashicons dashicons-groups"></span>
                                    ${registeredVolunteersText}
                                    <span class="signups-count">(1)</span>
                                </h5>
                                <div class="signups-list">
                                    ${response.data.new_signup_html}
                                </div>
                            </div>
                        `;
                        // Insert after description if it exists, otherwise at the beginning of shift-details
                        const insertAfter = shiftItemElement.find('.shift-description');
                        if (insertAfter.length > 0) {
                            insertAfter.after(signupsHtml);
                        } else {
                            shiftItemElement.find('.shift-details').prepend(signupsHtml);
                        }
                    } else {
                        signupsList.append(response.data.new_signup_html);
                        // Update count
                        const count = signupsList.children().length;
                        signupsList.closest('.shift-signups').find('.signups-count').text(`(${count})`);
                    }

                    // Clear the select and notes
                    shiftItemElement.find('.lcd-person-search-select').val(null).trigger('change');
                    shiftItemElement.find('.shift-assignment-notes').val('');

                    // Update the summary count
                    updateShiftSummaryCount(shiftItemElement);
                    
                    // Update shift summaries if on edit page
                    if (typeof updateShiftSummaries === 'function') {
                        updateShiftSummaries();
                    }
                } else {
                    alert(lcdEventsAdmin.text.error_assigning);
                }
            },
            error: function() {
                alert(lcdEventsAdmin.text.error_assigning);
            }
        });
    }

    // Unassign person from shift
    $(document).on('click', '.unassign-volunteer', function() {
        if (!confirm(lcdEventsAdmin.text.confirm_unassign)) {
            return;
        }

        const button = $(this);
        const signupItem = button.closest('.signup-item-compact');
        const signupId = signupItem.data('signup-id');
        const shiftItem = button.closest('.volunteer-shift-item');

        $.ajax({
            url: lcdEventsAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'lcd_unassign_person_from_shift',
                nonce: lcdEventsAdmin.unassign_person_nonce,
                signup_id: signupId
            },
            success: function(response) {
                if (response.success) {
                    signupItem.fadeOut(200, function() {
                        const signupsList = $(this).closest('.signups-list');
                        $(this).remove();
                        
                        // Update count in header
                        const remainingSignups = signupsList.children().length;
                        signupsList.closest('.shift-signups').find('.signups-count').text(`(${remainingSignups})`);
                        
                        // If no more signups, remove the entire signups section
                        if (remainingSignups === 0) {
                            signupsList.closest('.shift-signups').remove();
                        }

                        // Update the summary count
                        updateShiftSummaryCount(shiftItem);
                    });
                } else {
                    alert(lcdEventsAdmin.text.error_unassigning);
                }
            },
            error: function() {
                alert(lcdEventsAdmin.text.error_unassigning);
            }
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
        const signupItem = button.closest('.signup-item-compact');
        const signupId = signupItem.data('signup-id');
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
                    const notesText = notesDisplay.find('.notes-text');
                    if (newNotes) {
                        notesText.text(newNotes).removeClass('no-notes');
                    } else {
                        notesText.text(lcdEventsAdmin.text.no_notes).addClass('no-notes');
                    }
                    notesEdit.hide();
                    notesDisplay.show();
                } else {
                    alert(lcdEventsAdmin.text.error_editing_notes);
                }
            },
            error: function() {
                alert(lcdEventsAdmin.text.error_editing_notes);
            }
        });
    });

    // Helper function to update shift summary count
    function updateShiftSummaryCount(shiftItem) {
        const signupsList = shiftItem.find('.signups-list');
        const count = signupsList.children().length;
        const maxVolunteers = shiftItem.find('.shift-signups-summary').text().split('/')[1]?.trim() || '∞';
        const summaryText = maxVolunteers === '∞' 
            ? `${count} volunteers`
            : `${count} / ${maxVolunteers} volunteers`;
        shiftItem.find('.shift-signups-summary').text(summaryText);
    }
}); 