/**
 * Volunteer Opportunities Page JavaScript
 * Handles sign-up button interactions and UI feedback
 * Now using the unified LCDModal system
 */

(function($) {
    'use strict';

    // Event Filter functionality
    var eventFilter = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            // Handle event filter dropdown change
            $(document).on('change', '.event-filter-select', this.handleFilterChange);
        },

        handleFilterChange: function() {
            var $select = $(this);
            var selectedEventId = $select.val();
            
            // Add a subtle loading state
            $select.prop('disabled', true);
            $select.after('<span class="filter-loading"> <span class="spinner" style="display: inline-block; width: 16px; height: 16px; margin-left: 8px;"></span></span>');
            
            // Build the new URL with query parameters
            var currentUrl = new URL(window.location.href);
            
            if (selectedEventId && selectedEventId !== '') {
                currentUrl.searchParams.set('event_filter', selectedEventId);
            } else {
                currentUrl.searchParams.delete('event_filter');
            }
            
            // Navigate to the new URL
            window.location.href = currentUrl.toString();
        }
    };

    var volunteerSignup = {
        currentModal: null,
        currentShiftData: null,

        init: function() {
            this.bindEvents();
            this.setupModalTemplates();
        },

        bindEvents: function() {
        // Handle volunteer sign-up button clicks
            $(document).on('click', '.volunteer-signup-btn', this.handleSignupClick);
            
            // Form submissions (delegate to the modal content)
            $(document).on('submit', '#volunteer-guest-signup-form', this.handleGuestSignup);
            $(document).on('submit', '#volunteer-account-signup-form', this.handleAccountSignup);
            $(document).on('submit', '#volunteer-login-form', this.handleLoginSubmit);
            
            // Modal option buttons
            $(document).on('click', '.signup-option-guest', this.showGuestForm);
            $(document).on('click', '.signup-option-login', this.showLoginForm);
            $(document).on('click', '.signup-option-account', this.showAccountForm);
            $(document).on('click', '.back-to-options', this.showSignupOptions);
            
            // Keyboard accessibility
            $(document).on('keydown', '.volunteer-signup-btn', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    $(this).click();
                }
            });
        },

        setupModalTemplates: function() {
            // Pre-define modal content templates
            this.templates = {
                signupOptions: `
                    <div class="signup-method-selection">
                            <h4>How would you like to sign up?</h4>
                            <div class="signup-options">
                                <button class="signup-option signup-option-guest">
                                    <span class="dashicons dashicons-admin-users"></span>
                                    <div class="option-content">
                                        <strong>Sign up as Guest</strong>
                                        <small>Quick signup without creating an account</small>
                                    </div>
                                </button>
                                
                                <button class="signup-option signup-option-login">
                                    <span class="dashicons dashicons-lock"></span>
                                    <div class="option-content">
                                        <strong>Login to Existing Account</strong>
                                        <small>I already have an account</small>
                                    </div>
                                </button>
                                
                                <button class="signup-option signup-option-account">
                                    <span class="dashicons dashicons-plus-alt"></span>
                                    <div class="option-content">
                                        <strong>Sign up & Create Account</strong>
                                        <small>Create an account to manage your information</small>
                                    </div>
                                </button>
                            </div>
                        </div>
                `,
                        
                guestForm: `
                    <div class="guest-signup-form-container">
                            <h4>Sign Up as Guest</h4>
                                                          <form id="volunteer-guest-signup-form">
                                  <div class="form-group">
                                     <label for="guest-first-name">First Name *</label>
                                     <input type="text" id="guest-first-name" name="first_name" required>
                                  </div>
                                  
                                  <div class="form-group">
                                     <label for="guest-last-name">Last Name *</label>
                                     <input type="text" id="guest-last-name" name="last_name" required>
                                  </div>
                                
                                <div class="form-group">
                                    <label for="guest-email">Email Address *</label>
                                    <input type="email" id="guest-email" name="email" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="guest-phone">Phone Number</label>
                                    <input type="tel" id="guest-phone" name="phone">
                                </div>
                                
                                <div class="form-group">
                                    <label for="guest-notes">Additional Notes</label>
                                    <textarea id="guest-notes" name="notes" rows="3" placeholder="Any special accommodations, questions, or additional information..."></textarea>
                                </div>
                            </form>
                        </div>
                `,
                        
                loginForm: `
                    <div class="login-form-container">
                            <h4>Login to Your Account</h4>
                            <form id="volunteer-login-form">
                                <div class="form-group">
                                    <label for="login-username">Email or Username *</label>
                                    <input type="text" id="login-username" name="username" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="login-password">Password *</label>
                                    <input type="password" id="login-password" name="password" required>
                                </div>
                                
                                <div class="form-group checkbox-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" id="login-remember" name="remember">
                                        <span class="checkmark"></span>
                                        Remember me
                                    </label>
                                </div>
                                
                                <div class="login-links">
                                    <a href="#" onclick="window.open(lcdVolunteerData.lostPasswordUrl, '_blank'); return false;">Forgot your password?</a>
                                </div>
                            </form>
                        </div>
                `,
                        
                accountForm: `
                    <div class="account-signup-form-container">
                            <h4>Sign up & Create Account</h4>
                                                          <form id="volunteer-account-signup-form">
                                  <div class="form-group">
                                     <label for="account-first-name">First Name *</label>
                                     <input type="text" id="account-first-name" name="first_name" required>
                                  </div>
                                  
                                  <div class="form-group">
                                     <label for="account-last-name">Last Name *</label>
                                     <input type="text" id="account-last-name" name="last_name" required>
                                  </div>
                                
                                <div class="form-group">
                                    <label for="account-email">Email Address *</label>
                                    <input type="email" id="account-email" name="email" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="account-phone">Phone Number</label>
                                    <input type="tel" id="account-phone" name="phone">
                                </div>
                                
                                <div class="form-group">
                                    <label for="account-notes">Additional Notes</label>
                                    <textarea id="account-notes" name="notes" rows="3" placeholder="Any special accommodations, questions, or additional information..."></textarea>
                                </div>
                                
                                <div class="form-group account-info">
                                    <div class="account-confirmation">
                                        <span class="dashicons dashicons-yes-alt"></span>
                                        <span>We'll create an account for you and send setup instructions to your email.</span>
                                    </div>
                                    <input type="hidden" id="create-account-checkbox" name="create_account" value="true">
                                </div>
                            </form>
                    </div>
                `
            };
        },

        handleSignupClick: function(e) {
            e.preventDefault();
            var $button = $(this);
            
            // Store current shift data
            volunteerSignup.currentShiftData = {
                eventId: $button.data('event-id'),
                shiftIndex: $button.data('shift-index'),
                shiftTitle: $button.data('shift-title'),
                eventTitle: $button.closest('.event-card').find('.event-title').text() || 'Event',
                eventDate: $button.closest('.event-card').find('.event-date').text() || '',
                eventLocation: $button.closest('.event-card').find('.event-location').text() || ''
            };
            
            // Check if user is logged in
            if (lcdVolunteerData.isLoggedIn) {
                volunteerSignup.openLoggedInModal();
            } else {
                volunteerSignup.openSignupOptionsModal();
            }
        },

        openSignupOptionsModal: function() {
            var eventInfo = this.buildEventInfoHtml();
            
            this.currentModal = LCDModal.open({
                title: 'Sign Up for Volunteer Shift',
                content: eventInfo + this.templates.signupOptions,
                size: 'medium',
                className: 'volunteer-signup-modal',
                closable: true,
                closeOnBackdrop: true
            });
        },

        openLoggedInModal: function() {
            var eventInfo = this.buildEventInfoHtml();
            var buttons = [
                {
                    text: 'Cancel',
                    action: 'cancel',
                    className: 'lcd-btn-secondary'
                },
                {
                    text: 'Sign Me Up!',
                    action: 'signup',
                    className: 'lcd-btn-primary',
                    callback: function(e, modal, settings) {
                        volunteerSignup.submitLoggedInSignup();
                        return false; // Don't close modal yet
                    }
                }
            ];

            this.currentModal = LCDModal.open({
                title: 'Confirm Volunteer Signup',
                content: eventInfo + '<p>Are you ready to sign up for this volunteer shift?</p>',
                size: 'medium',
                className: 'volunteer-signup-modal',
                buttons: buttons,
                closable: true,
                closeOnBackdrop: true
            });
        },

        buildEventInfoHtml: function() {
            var data = this.currentShiftData;
            return `
                <div class="event-info-summary">
                    <h5>${data.shiftTitle}</h5>
                    <p><strong>Event:</strong> ${data.eventTitle}</p>
                    ${data.eventDate ? `<p><strong>Date:</strong> ${data.eventDate}</p>` : ''}
                    ${data.eventLocation ? `<p><strong>Location:</strong> ${data.eventLocation}</p>` : ''}
                </div>
            `;
        },

        showGuestForm: function(e) {
            e.preventDefault();
            volunteerSignup.updateModalContent(
                'Sign Up as Guest',
                volunteerSignup.buildEventInfoHtml() + volunteerSignup.templates.guestForm,
                [
                    {
                        text: 'Back',
                        action: 'back',
                        className: 'lcd-btn-secondary',
                        callback: function() {
                            volunteerSignup.showSignupOptions();
                            return false;
                        }
                    },
                    {
                        text: 'Sign Up',
                        action: 'submit',
                        className: 'lcd-btn-primary',
                        callback: function() {
                            $('#volunteer-guest-signup-form').submit();
                            return false;
                        }
                    }
                ]
            );
        },

        showLoginForm: function(e) {
            e.preventDefault();
            volunteerSignup.updateModalContent(
                'Login to Your Account', 
                volunteerSignup.buildEventInfoHtml() + volunteerSignup.templates.loginForm,
                [
                    {
                        text: 'Back',
                        action: 'back',
                        className: 'lcd-btn-secondary',
                        callback: function() {
                            volunteerSignup.showSignupOptions();
                            return false;
                        }
                    },
                    {
                        text: 'Login & Continue',
                        action: 'login',
                        className: 'lcd-btn-primary',
                        callback: function() {
                            $('#volunteer-login-form').submit();
                            return false;
                        }
                    }
                ]
            );
        },

        showAccountForm: function(e) {
            e.preventDefault();
            volunteerSignup.updateModalContent(
                'Sign up & Create Account',
                volunteerSignup.buildEventInfoHtml() + volunteerSignup.templates.accountForm,
                [
                    {
                        text: 'Back',
                        action: 'back',
                        className: 'lcd-btn-secondary',
                        callback: function() {
                            volunteerSignup.showSignupOptions();
                            return false;
                        }
                    },
                    {
                        text: 'Sign Up',
                        action: 'submit',
                        className: 'lcd-btn-primary',
                        callback: function() {
                            $('#volunteer-account-signup-form').submit();
                            return false;
                        }
                    }
                ]
            );
        },

        showSignupOptions: function() {
            volunteerSignup.updateModalContent(
                'Sign Up for Volunteer Shift',
                volunteerSignup.buildEventInfoHtml() + volunteerSignup.templates.signupOptions
            );
        },

        updateModalContent: function(title, content, buttons = []) {
            if (!this.currentModal) return;
            
            // Update title
            this.currentModal.find('.lcd-modal-title').text(title);
            
            // Update content
            this.currentModal.find('.lcd-modal-body').html(content);
            
            // Update buttons
            var $footer = this.currentModal.find('.lcd-modal-footer');
            if (buttons.length > 0) {
                if ($footer.length === 0) {
                    this.currentModal.find('.lcd-modal-content').append('<div class="lcd-modal-footer"></div>');
                    $footer = this.currentModal.find('.lcd-modal-footer');
                }
                
                var buttonsHtml = '';
                buttons.forEach(function(button) {
                    var btnClass = 'lcd-modal-btn ' + (button.className || 'lcd-btn-secondary');
                    var btnAttrs = button.attributes ? ' ' + button.attributes : '';
                    buttonsHtml += `<button type="button" class="${btnClass}" data-action="${button.action || ''}"${btnAttrs}>${button.text}</button>`;
                });
                $footer.html(buttonsHtml);
                
                // Rebind button events
                $footer.find('.lcd-modal-btn').off('click').on('click', function(e) {
                    var action = $(this).data('action');
                    var buttonConfig = buttons.find(btn => btn.action === action);
                    
                    if (buttonConfig && typeof buttonConfig.callback === 'function') {
                        buttonConfig.callback.call(this, e, volunteerSignup.currentModal);
                    }
                });
            } else {
                $footer.remove();
            }
        },

        showLoadingState: function(message = 'Processing...') {
            if (!this.currentModal) return;
            
            this.currentModal.find('.lcd-modal-body').html(`
                <div class="loading-content">
                    <div class="loading-spinner"></div>
                    <p>${message}</p>
                </div>
            `);
            
            // Remove footer buttons during loading
            this.currentModal.find('.lcd-modal-footer').remove();
        },

        handleGuestSignup: function(e) {
            e.preventDefault();
            
            var formData = {
                method: 'guest',
                event_id: volunteerSignup.currentShiftData.eventId,
                shift_index: volunteerSignup.currentShiftData.shiftIndex,
                shift_title: volunteerSignup.currentShiftData.shiftTitle,
                first_name: $('#guest-first-name').val(),
                last_name: $('#guest-last-name').val(),
                email: $('#guest-email').val(),
                phone: $('#guest-phone').val(),
                notes: $('#guest-notes').val()
            };
            
            volunteerSignup.submitSignup(formData);
        },

        handleAccountSignup: function(e) {
            e.preventDefault();
            
            var formData = {
                method: 'account',
                event_id: volunteerSignup.currentShiftData.eventId,
                shift_index: volunteerSignup.currentShiftData.shiftIndex,
                shift_title: volunteerSignup.currentShiftData.shiftTitle,
                first_name: $('#account-first-name').val(),
                last_name: $('#account-last-name').val(),
                email: $('#account-email').val(),
                phone: $('#account-phone').val(),
                notes: $('#account-notes').val(),
                create_account: $('#create-account-checkbox').is(':checked')
            };
            
            volunteerSignup.submitSignup(formData);
        },

        submitSignup: function(formData) {
            this.showLoadingState();
            
            $.ajax({
                url: lcdVolunteerData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'lcd_volunteer_opportunity_signup',
                    nonce: lcdVolunteerData.nonce,
                    ...formData
                },
                success: function(response) {
                    if (response.success) {
                        volunteerSignup.showSuccess(response.data);
                        volunteerSignup.updateButtonState();
                    } else {
                        volunteerSignup.showError(response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    volunteerSignup.showError('Network error. Please try again.');
                }
            });
        },

        showSuccess: function(data) {
            var message = data.message;
            if (data.account_info) {
                message += '<br><br>' + data.account_info;
            }
            
            // Close current modal first
            if (this.currentModal) {
                LCDModal.close(this.currentModal);
            }
            
            // Show success modal
            LCDModal.alert({
                title: 'Signup Successful!',
                content: `<div class="success-content" style="text-align: center;">
                    <div class="success-icon" style="color: #28a745; font-size: 48px; margin-bottom: 16px;">
                        <span class="dashicons dashicons-yes-alt"></span>
                    </div>
                    <div>${message}</div>
                </div>`,
                afterClose: function() {
                    volunteerSignup.currentModal = null;
                }
            });
        },

        showError: function(message) {
            // Close current modal first
            if (this.currentModal) {
                LCDModal.close(this.currentModal);
            }
            
            // Show error modal
            LCDModal.alert({
                title: 'Signup Failed',
                content: `<div class="error-content" style="text-align: center;">
                    <div class="error-icon" style="color: #dc3545; font-size: 48px; margin-bottom: 16px;">
                        <span class="dashicons dashicons-warning"></span>
                    </div>
                    <div>${message}</div>
                </div>`,
                buttons: [
                    {
                        text: 'Try Again',
                        action: 'retry',
                        className: 'lcd-btn-secondary',
                        callback: function() {
                            volunteerSignup.openSignupOptionsModal();
                            return false;
                        }
                    },
                    {
                        text: 'Close',
                        action: 'close',
                        className: 'lcd-btn-primary'
                    }
                ],
                afterClose: function() {
                    volunteerSignup.currentModal = null;
                }
            });
        },

        updateButtonState: function() {
            // Update the signup button to reflect the new state
            var $button = this.currentShiftData.button;
            $button.removeClass('btn-primary').addClass('btn-success');
            $button.html('<span class="dashicons dashicons-yes"></span> Signed Up');
            $button.prop('disabled', true);
            
            // Update capacity count in the row
            var $row = $button.closest('.opportunity-row');
            var $currentCount = $row.find('.current-count');
            if ($currentCount.length) {
                var currentCount = parseInt($currentCount.text()) + 1;
                $currentCount.text(currentCount);
                
                // Update spots remaining if there's a max
                var $spotsRemaining = $row.find('.spots-remaining');
                if ($spotsRemaining.length) {
                    var spotsText = $spotsRemaining.text();
                    var currentSpots = parseInt(spotsText.match(/\d+/)[0]);
                    if (currentSpots > 1) {
                        $spotsRemaining.text(spotsText.replace(/\d+/, currentSpots - 1));
                    } else {
                        $spotsRemaining.text('Full');
                        $row.addClass('row-full').removeClass('row-available');
                        $row.find('.status-badge').removeClass('status-available').addClass('status-full')
                            .html('<span class="dashicons dashicons-no"></span> Full');
                    }
                }
            }
        },

        // Legacy methods removed - using LCDModal system now

        handleLoginSubmit: function(e) {
            e.preventDefault();
            
            var username = $('#login-username').val();
            var password = $('#login-password').val();
            var remember = $('#login-remember').is(':checked');
            
            if (!username || !password) {
                volunteerSignup.showError('Please enter both username/email and password.');
                return;
            }
            
            // Show loading state
            volunteerSignup.showLoadingState();
            
            $.ajax({
                url: lcdVolunteerData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'lcd_volunteer_login',
                    nonce: lcdVolunteerData.nonce,
                    username: username,
                    password: password,
                    remember: remember
                },
                success: function(response) {
                    if (response.success) {
                        // Login successful - proceed to logged-in signup
                        $('#signup-loading .loading-content p').text('Login successful! Proceeding to signup...');
                        
                        // Update the global logged-in state
                        lcdVolunteerData.isLoggedIn = true;
                        
                        // Auto-proceed to logged-in signup after brief delay
                        setTimeout(function() {
                            volunteerSignup.openLoggedInModal();
                        }, 1000);
                    } else {
                        volunteerSignup.showError(response.data.message || 'Login failed. Please check your credentials and try again.');
                    }
                },
                error: function(xhr, status, error) {
                    volunteerSignup.showError('Network error during login. Please try again.');
                }
            });
        },

        submitLoggedInSignup: function() {
            // Show loading state
            this.showLoadingState();
            
            // Make completely fresh AJAX request - no nonce from frontend, let backend generate fresh one
            $.ajax({
                url: lcdVolunteerData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'lcd_volunteer_opportunity_signup',
                    // No nonce - let the backend generate a fresh one for the logged-in user
                    method: 'logged_in',
                    event_id: this.currentShiftData.eventId,
                    shift_index: this.currentShiftData.shiftIndex,
                    shift_title: this.currentShiftData.shiftTitle
                },
                success: function(response) {
                    if (response.success) {
                        volunteerSignup.showSuccess(response.data);
                        volunteerSignup.updateButtonState();
                    } else {
                        volunteerSignup.showError(response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    volunteerSignup.showError('Network error. Please try again.');
                }
            });
        }
    };

    $(document).ready(function() {
        eventFilter.init();
        volunteerSignup.init();
        
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
        
        // Auto-refresh page data every 5 minutes to keep volunteer counts current
        if ($('.volunteer-opportunities-table').length > 0) {
            setInterval(function() {
                // TODO: Implement AJAX refresh of volunteer counts
                console.log('Auto-refresh volunteer counts (placeholder)');
            }, 300000); // 5 minutes
        }
    });

})(jQuery); 