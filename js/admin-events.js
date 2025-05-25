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
                        return {
                            results: data.data.items,
                            pagination: {
                                more: (params.page * 10) < (data.data.total_count || 0)
                            }
                        };
                    },
                    cache: true
                },
                placeholder: lcdEventsAdmin.text.search_placeholder,
                minimumInputLength: 2,
                allowClear: true,
                width: '100%',
                dropdownParent: $select.parent(),
                language: {
                    inputTooShort: function() { return lcdEventsAdmin.text.input_too_short; },
                    searching: function() { return lcdEventsAdmin.text.searching; },
                    noResults: function() { return lcdEventsAdmin.text.no_results; },
                    errorLoading: function() { return lcdEventsAdmin.text.error_loading; }
                }
            }).on('select2:select', function (e) {
                const person = e.params.data;
                const shiftItem = $(this).closest('.volunteer-shift-item');
                const shiftIndex = shiftItem.data('index');
                assignPersonToShift(person.id, shiftIndex, shiftItem);
                $(this).val(null).trigger('change');
            });
        });
    }
    
    // Initialize for existing items
    initShiftPersonSearch($('#volunteer-shifts-container'));

    function assignPersonToShift(personId, shiftIndex, shiftItemElement) {
        const eventId = parseInt(lcdEventsAdmin.event_id);
        const shiftTitleInput = shiftItemElement.find('input[name="volunteer_shifts[' + shiftIndex + '][title]"]');
        const shiftTitle = shiftTitleInput.val() || lcdEventsAdmin.text.shift + ' ' + (shiftIndex + 1);
        const notesTextarea = shiftItemElement.find('.shift-assignment-notes');
        const assignmentNotes = notesTextarea.val().trim();

        $.ajax({
            url: lcdEventsAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'lcd_assign_person_to_shift',
                nonce: lcdEventsAdmin.assign_person_nonce,
                event_id: eventId,
                shift_index: shiftIndex,
                shift_title: shiftTitle, 
                person_id: personId,
                assignment_notes: assignmentNotes
            },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data && response.data.new_signup_html) {
                    const signupsList = shiftItemElement.find('.signups-list');
                    if(signupsList.length === 0){
                        shiftItemElement.find('.assign-volunteer-section').before('<div class="shift-signups"><h5><span class="dashicons dashicons-groups"></span> ' + lcdEventsAdmin.text.registered_volunteers + ' <span class="signups-count">(0)</span></h5><div class="signups-list"></div></div>');
                    }
                    shiftItemElement.find('.signups-list').append(response.data.new_signup_html);
                    
                    notesTextarea.val('');
                    
                    const countSpan = shiftItemElement.find('.shift-signups h5 .signups-count');
                    const currentCount = parseInt(countSpan.text().replace(/[^0-9]/g, '')) || 0;
                    countSpan.text('(' + (currentCount + 1) + ')');
                    updateShiftSummaries();

                } else {
                    alert(lcdEventsAdmin.text.error_assigning + ' ' + (response.data && response.data.message ? response.data.message : '(No specific error given)'));
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                alert(lcdEventsAdmin.text.error_assigning + ' (AJAX Error: ' + textStatus + ' - ' + errorThrown + ')');
            }
        });
    }

    $(document).on('click', '.unassign-volunteer', function() {
        if (!confirm(lcdEventsAdmin.text.confirm_unassign)) {
            return;
        }
        const $button = $(this);
        const $signupItem = $button.closest('.signup-item-compact');
        const signupId = $signupItem.data('signup-id');
        const shiftItemElement = $button.closest('.volunteer-shift-item');

        if (!signupId) {
            alert('Error: Could not find signup ID to remove.');
            return;
        }

        $.ajax({
            url: lcdEventsAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'lcd_unassign_person_from_shift',
                nonce: lcdEventsAdmin.unassign_person_nonce,
                signup_id: signupId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $signupItem.slideUp(200, function() { 
                        $(this).remove(); 
                        const countSpan = shiftItemElement.find('.shift-signups h5 .signups-count');
                        const currentCount = parseInt(countSpan.text().replace(/[^0-9]/g, '')) || 0;
                        countSpan.text('(' + Math.max(0, currentCount - 1) + ')');
                        if (Math.max(0, currentCount - 1) === 0 && shiftItemElement.find('.signups-list .signup-item-compact').length === 0) {
                            shiftItemElement.find('.shift-signups').slideUp(200, function() { $(this).remove(); });
                        }
                        updateShiftSummaries();
                    });
                } else {
                    alert(lcdEventsAdmin.text.error_unassigning + ' ' + (response.data && response.data.message ? response.data.message : '(No specific error given)'));
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                 alert(lcdEventsAdmin.text.error_unassigning + ' (AJAX Error: ' + textStatus + ' - ' + errorThrown + ')');
            }
        });
    });
    
    updateShiftSummaries();
    
    // Inline notes editing functionality
    $(document).on('click', '.edit-notes', function() {
        const $button = $(this);
        const $notesContainer = $button.closest('.signup-notes');
        const $display = $notesContainer.find('.signup-notes-display');
        const $edit = $notesContainer.find('.signup-notes-edit');
        
        $display.hide();
        $edit.show();
        $edit.find('.notes-edit-field').focus();
    });
    
    $(document).on('click', '.cancel-notes', function() {
        const $button = $(this);
        const $notesContainer = $button.closest('.signup-notes');
        const $display = $notesContainer.find('.signup-notes-display');
        const $edit = $notesContainer.find('.signup-notes-edit');
        const $textarea = $edit.find('.notes-edit-field');
        
        const originalNotes = $display.find('.notes-text').hasClass('no-notes') ? '' : $display.find('.notes-text').text();
        $textarea.val(originalNotes);
        
        $edit.hide();
        $display.show();
    });
    
    $(document).on('click', '.save-notes', function() {
        const $button = $(this);
        const $notesContainer = $button.closest('.signup-notes');
        const $signupItem = $button.closest('.signup-item-compact');
        const signupId = $signupItem.data('signup-id');
        const $textarea = $notesContainer.find('.notes-edit-field');
        const newNotes = $textarea.val().trim();
        
        if (!signupId) {
            alert('Error: Could not find signup ID to update notes.');
            return;
        }
        
        $button.prop('disabled', true);
        $notesContainer.find('.cancel-notes').prop('disabled', true);
        
        $.ajax({
            url: lcdEventsAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'lcd_edit_volunteer_notes',
                nonce: lcdEventsAdmin.edit_notes_nonce,
                signup_id: signupId,
                notes: newNotes
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const $display = $notesContainer.find('.signup-notes-display');
                    const $edit = $notesContainer.find('.signup-notes-edit');
                    const $notesText = $display.find('.notes-text');
                    
                    if (newNotes) {
                        $notesText.removeClass('no-notes').text(newNotes);
                        $display.find('.edit-notes').attr('title', lcdEventsAdmin.text.edit_notes || 'Edit notes');
                    } else {
                        $notesText.addClass('no-notes').text(lcdEventsAdmin.text.no_notes || 'No notes');
                        $display.find('.edit-notes').attr('title', lcdEventsAdmin.text.add_notes || 'Add notes');
                    }
                    
                    $edit.hide();
                    $display.show();
                } else {
                    alert(lcdEventsAdmin.text.error_editing_notes + ' ' + (response.data && response.data.message ? response.data.message : ''));
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                alert(lcdEventsAdmin.text.error_editing_notes + ' (AJAX Error: ' + textStatus + ' - ' + errorThrown + ')');
            },
            complete: function() {
                $button.prop('disabled', false);
                $notesContainer.find('.cancel-notes').prop('disabled', false);
            }
        });
    });

    // Additional Buttons Management
    let buttonIndex = $('.additional-button-item').length;
    
    // Add new button
    $('#add-additional-button').on('click', function() {
        const template = $('#additional-button-template').html();
        const html = template.replace(/__INDEX__/g, buttonIndex);
        
        const newButton = $(html);
        $('#additional-buttons-container').append(newButton);
        
        buttonIndex++;
        
        // Focus on the new button's text field
        newButton.find('input[type="text"]').focus();
    });
    
    // Remove button
    $(document).on('click', '.remove-additional-button', function() {
        const buttonItem = $(this).closest('.additional-button-item');
        buttonItem.slideUp(200, function() {
            $(this).remove();
            updateButtonNumbers();
        });
    });
    
    // Update button numbers
    function updateButtonNumbers() {
        $('.additional-button-item').each(function(index) {
            $(this).find('.button-number').text(index + 1);
            $(this).find('input').each(function() {
                const newName = $(this).attr('name').replace(/\[\d+\]/, '[' + index + ']');
                $(this).attr('name', newName);
            });
        });
    }
}); 