<?php
/**
 * Volunteer Email Administration
 * 
 * Handles email settings pages, template configuration, and email-related admin functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class LCD_Volunteer_Email_Admin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', [$this, 'add_email_pages']);
        add_action('admin_init', [$this, 'register_email_settings']);
        
        // AJAX handlers for email testing
        add_action('wp_ajax_test_volunteer_template_email', [$this, 'ajax_test_template_email']);
        add_action('wp_ajax_test_volunteer_wpmail_template', [$this, 'ajax_test_wpmail_template']);
    }

    /**
     * Add Email Admin Pages
     */
    public function add_email_pages() {
        // Email Settings Page
        add_submenu_page(
            'edit.php?post_type=event',           // Parent slug (Events menu)
            __('Email Settings', 'lcd-events'),   // Page title
            __('Email Settings', 'lcd-events'),   // Menu title
            'manage_options',                     // Capability required
            'volunteer-email-settings',           // Menu slug
            [$this, 'email_settings_page_callback']  // Callback function
        );
        
        // Email Templates Page
        add_submenu_page(
            'edit.php?post_type=event',           // Parent slug (Events menu)
            __('Email Templates', 'lcd-events'),  // Page title
            __('Email Templates', 'lcd-events'),  // Menu title
            'manage_options',                     // Capability required
            'volunteer-email-templates',          // Menu slug
            [$this, 'email_templates_page_callback']  // Callback function
        );
    }

    /**
     * Register email settings
     */
    public function register_email_settings() {
        // Register settings group for both pages
        register_setting('lcd_volunteer_email_settings', 'lcd_volunteer_email_settings', [
            'sanitize_callback' => [$this, 'sanitize_email_settings']
        ]);

        // Email Settings Page Sections
        add_settings_section(
            'zeptomail_integration',
            __('Zeptomail Integration', 'lcd-events'),
            [$this, 'zeptomail_integration_section_callback'],
            'lcd_volunteer_email_settings'
        );
        
        add_settings_section(
            'email_controls',
            __('Email Controls', 'lcd-events'),
            [$this, 'email_controls_section_callback'],
            'lcd_volunteer_email_settings'
        );

        // Email Templates Page Sections - conditionally register based on centralized Zeptomail setting
        $zeptomail_enabled = false;
        if (class_exists('LCD_Zeptomail')) {
            $zeptomail_enabled = LCD_Zeptomail::get_instance()->is_enabled();
        }
        
        if ($zeptomail_enabled) {
            // Zeptomail Template Configuration
            add_settings_section(
                'zeptomail_templates',
                __('Zeptomail Template Configuration', 'lcd-events'),
                [$this, 'zeptomail_templates_section_callback'],
                'lcd_volunteer_email_templates'
            );
        } else {
            // WordPress wp_mail Template Editor
            add_settings_section(
                'wpmail_templates',
                __('Email Template Editor', 'lcd-events'),
                [$this, 'wpmail_templates_section_callback'],
                'lcd_volunteer_email_templates'
            );
        }

        // Email Controls fields
        add_settings_field(
            'reminder_timing',
            __('Reminder Timing', 'lcd-events'),
            [$this, 'reminder_timing_field_callback'],
            'lcd_volunteer_email_settings',
            'email_controls'
        );

        // Email template activation fields
        $email_types = $this->get_email_types();
        foreach ($email_types as $type => $label) {
            add_settings_field(
                $type . '_enabled',
                sprintf(__('Enable %s', 'lcd-events'), $label),
                [$this, 'email_template_enabled_field_callback'],
                'lcd_volunteer_email_settings',
                'email_controls',
                ['type' => $type, 'label' => $label]
            );
        }

        // Add template fields conditionally based on centralized Zeptomail setting
        $email_types = $this->get_email_types();
        
        if ($zeptomail_enabled) {
            // Zeptomail template key fields
            foreach ($email_types as $type => $label) {
                add_settings_field(
                    $type . '_zeptomail_template',
                    sprintf(__('%s Template', 'lcd-events'), $label),
                    [$this, 'zeptomail_template_field_callback'],
                    'lcd_volunteer_email_templates',
                    'zeptomail_templates',
                    ['type' => $type, 'label' => $label]
                );
            }
        } else {
            // WordPress wp_mail template fields
            foreach ($email_types as $type => $label) {
                add_settings_field(
                    $type . '_wpmail_template',
                    sprintf(__('%s Email Template', 'lcd-events'), $label),
                    [$this, 'wpmail_template_field_callback'],
                    'lcd_volunteer_email_templates',
                    'wpmail_templates',
                    ['type' => $type, 'label' => $label]
                );
            }
        }
    }

    /**
     * Get available email types
     */
    public function get_email_types() {
        return [
            'volunteer_confirmation' => __('Volunteer Assignment Confirmation', 'lcd-events'),
            'volunteer_cancellation' => __('Volunteer Assignment Cancellation', 'lcd-events'),
            'volunteer_reminder' => __('Volunteer Shift Reminder', 'lcd-events'),
            'volunteer_request_received' => __('Volunteer Request Received', 'lcd-events'),
            'volunteer_request_denied' => __('Volunteer Request Denied', 'lcd-events'),
        ];
    }

    /**
     * Get email type descriptions
     */
    public function get_email_type_descriptions() {
        return [
            'volunteer_confirmation' => __('Sent when a volunteer is successfully assigned to a shift and their assignment is confirmed.', 'lcd-events'),
            'volunteer_cancellation' => __('Sent when a volunteer is removed from a shift or their assignment is cancelled.', 'lcd-events'),
            'volunteer_reminder' => __('Sent automatically before the shift date to remind volunteers of their upcoming assignment.', 'lcd-events'),
            'volunteer_request_received' => __('Sent immediately when someone submits a volunteer request that requires review before confirmation.', 'lcd-events'),
            'volunteer_request_denied' => __('Sent when a volunteer request cannot be approved (e.g., shift is full, doesn\'t meet requirements, etc.).', 'lcd-events'),
        ];
    }

    /**
     * Email Settings Page Callback
     */
    public function email_settings_page_callback() {
        ?>
        <div class="wrap">
            <h1><?php _e('Volunteer Email Settings', 'lcd-events'); ?></h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('lcd_volunteer_email_settings');
                ?>
                <input type="hidden" name="lcd_volunteer_email_settings[form_page]" value="email_settings">
                <?php
                do_settings_sections('lcd_volunteer_email_settings');
                submit_button();
                ?>
            </form>

            <?php
            // Only show connection test if centralized Zeptomail plugin is available
            if (class_exists('LCD_Zeptomail')) : ?>
                <div class="lcd-connection-test">
                    <h3><?php _e('Connection Test', 'lcd-events'); ?></h3>
                    <p><?php printf(__('Test your Zeptomail connection from the <a href="%s" target="_blank">centralized Zeptomail settings page</a>.', 'lcd-events'), admin_url('options-general.php?page=lcd-zeptomail-settings')); ?></p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Email Templates Page Callback
     */
    public function email_templates_page_callback() {
        ?>
        <div class="wrap">
            <h1><?php _e('Volunteer Email Templates', 'lcd-events'); ?></h1>
            
            <?php 
            // Show email method status notice
            if (class_exists('LCD_Zeptomail') && LCD_Zeptomail::get_instance()->is_enabled()) : ?>
                <div class="notice notice-info">
                    <p><strong><?php _e('✓ Using Zeptomail', 'lcd-events'); ?></strong> - <?php _e('Volunteer emails will be sent via Zeptomail using the template keys you configure below.', 'lcd-events'); ?></p>
                </div>
            <?php else : ?>
                <div class="notice notice-warning">
                    <p><strong><?php _e('Using WordPress wp_mail()', 'lcd-events'); ?></strong> - <?php _e('Volunteer emails will be sent using WordPress built-in email functionality. For better deliverability, consider configuring the', 'lcd-events'); ?> <a href="<?php echo admin_url('options-general.php?page=lcd-zeptomail-settings'); ?>"><?php _e('centralized Zeptomail integration', 'lcd-events'); ?></a>.</p>
                </div>
            <?php endif; ?>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('lcd_volunteer_email_settings');
                ?>
                <input type="hidden" name="lcd_volunteer_email_settings[form_page]" value="email_templates">
                <?php
                do_settings_sections('lcd_volunteer_email_templates');
                submit_button();
                ?>
            </form>

            <?php $this->render_email_template_help(); ?>
        </div>
        <?php
    }

    // Section callbacks will be added in next part...
    
    /**
     * Zeptomail integration section callback
     */
    public function zeptomail_integration_section_callback() {
        if (class_exists('LCD_Zeptomail')) {
            $zeptomail_enabled = LCD_Zeptomail::get_instance()->is_enabled();
            if ($zeptomail_enabled) {
                echo '<p>' . __('Zeptomail is properly configured and enabled for this site. Volunteer emails will be sent via Zeptomail.', 'lcd-events') . '</p>';
            } else {
                echo '<p>' . sprintf(__('Zeptomail plugin is available but not enabled. <a href="%s" target="_blank">Configure Zeptomail settings</a> to enable enhanced email delivery.', 'lcd-events'), admin_url('options-general.php?page=lcd-zeptomail-settings')) . '</p>';
            }
        } else {
            echo '<p>' . __('Zeptomail plugin is not installed. Volunteer emails will use WordPress built-in wp_mail() function.', 'lcd-events') . '</p>';
        }
    }

    /**
     * Email controls section callback
     */
    public function email_controls_section_callback() {
        echo '<p>' . __('Configure when and which volunteer emails are sent automatically.', 'lcd-events') . '</p>';
    }

    /**
     * Zeptomail templates section callback
     */
    public function zeptomail_templates_section_callback() {
        echo '<p>' . __('Configure Zeptomail template keys for each type of volunteer email. Templates should be created in your Zeptomail dashboard first.', 'lcd-events') . '</p>';
    }

    /**
     * WP Mail templates section callback
     */
    public function wpmail_templates_section_callback() {
        echo '<p>' . __('Configure email templates that will be sent using WordPress built-in email functionality.', 'lcd-events') . '</p>';
    }

    // Field callbacks and other methods will continue in the next part...

    /**
     * Reminder timing field callback
     */
    public function reminder_timing_field_callback() {
        $options = get_option('lcd_volunteer_email_settings');
        $value = isset($options['reminder_timing']) ? $options['reminder_timing'] : '24';
        ?>
        <input type="number" id="reminder_timing" name="lcd_volunteer_email_settings[reminder_timing]" value="<?php echo esc_attr($value); ?>" min="1" max="168" />
        <span class="description"><?php _e('Hours before the shift to send reminder emails (1-168 hours)', 'lcd-events'); ?></span>
        <?php
    }

    /**
     * Email template enabled field callback
     */
    public function email_template_enabled_field_callback($args) {
        $options = get_option('lcd_volunteer_email_settings');
        $value = isset($options[$args['type'] . '_enabled']) ? $options[$args['type'] . '_enabled'] : '0';
        $description = $this->get_email_type_descriptions()[$args['type']] ?? '';
        ?>
        <label>
            <input type="checkbox" id="<?php echo esc_attr($args['type']); ?>_enabled" name="lcd_volunteer_email_settings[<?php echo esc_attr($args['type']); ?>_enabled]" value="1" <?php checked('1', $value); ?> />
            <?php printf(__('Enable %s emails', 'lcd-events'), $args['label']); ?>
        </label>
        <?php if ($description) : ?>
            <p class="description"><?php echo esc_html($description); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Zeptomail template field callback
     */
    public function zeptomail_template_field_callback($args) {
        $options = get_option('lcd_volunteer_email_settings', []);
        $template_key = $options['template_mapping'][$args['type']] ?? '';
        $email_enabled = $options[$args['type'] . '_enabled'] ?? 1;
        $descriptions = $this->get_email_type_descriptions();
        
        // Only show template mapping if the email type is enabled
        if (!$email_enabled) {
            echo '<p class="description" style="color: #666; font-style: italic;">' . 
                 sprintf(__('%s emails are disabled. Enable them in Email Settings to configure templates.', 'lcd-events'), $args['label']) . 
                 '</p>';
            return;
        }
        
        echo '<div class="template-mapping-row" data-email-type="' . esc_attr($args['type']) . '">';
        
        // Show email type description
        if (isset($descriptions[$args['type']])) {
            echo '<p class="description" style="margin-bottom: 10px; padding: 8px; background: #f9f9f9; border-left: 4px solid #72aee6;">';
            echo '<strong>' . esc_html($args['label']) . ':</strong> ' . esc_html($descriptions[$args['type']]);
            echo '</p>';
        }
        
        echo '<div class="template-input-group">';
        echo '<input type="text" name="lcd_volunteer_email_settings[template_mapping][' . esc_attr($args['type']) . ']" value="' . esc_attr($template_key) . '" class="regular-text template-key-input" data-email-type="' . esc_attr($args['type']) . '" placeholder="' . esc_attr__('Enter template key (e.g., 2d6f.117fe5b8.k1.f38e8b50-1e7f-11e8-9b33-5254004d4f8f.178b1ae70a4)', 'lcd-events') . '">';
        
        if (!empty($template_key)) {
            echo '<button type="button" class="button button-secondary test-template-btn" data-email-type="' . esc_attr($args['type']) . '" data-template-key="' . esc_attr($template_key) . '">' . __('Test Template', 'lcd-events') . '</button>';
        } else {
            echo '<button type="button" class="button button-secondary test-template-btn" data-email-type="' . esc_attr($args['type']) . '" style="display: none;">' . __('Test Template', 'lcd-events') . '</button>';
        }
        
        echo '</div>';
        echo '<p class="description">' . sprintf(__('Enter the ZeptoMail template key for %s emails. Find this in your ZeptoMail dashboard under Templates.', 'lcd-events'), $args['label']) . '</p>';
        echo '<div class="template-test-result" id="test-result-' . esc_attr($args['type']) . '" style="display: none;"></div>';
        echo '</div>';
    }

    /**
     * WP Mail template field callback
     */
    public function wpmail_template_field_callback($args) {
        $options = get_option('lcd_volunteer_email_settings', []);
        $email_enabled = $options[$args['type'] . '_enabled'] ?? 1;
        $subject = $options['wpmail_templates'][$args['type']]['subject'] ?? '';
        $content = $options['wpmail_templates'][$args['type']]['content'] ?? '';
        $descriptions = $this->get_email_type_descriptions();
        
        // Only show wp_mail template fields if email type is enabled
        if (!$email_enabled) {
            echo '<p class="description" style="color: #666; font-style: italic;">' . 
                 sprintf(__('%s emails are disabled. Enable them in Email Settings to configure templates.', 'lcd-events'), $args['label']) . 
                 '</p>';
            return;
        }
        
        echo '<div class="wpmail-template-section">';
        
        // Show email type description
        if (isset($descriptions[$args['type']])) {
            echo '<h3>' . esc_html($args['label']) . '</h3>';
            echo '<p class="description" style="margin-bottom: 15px; padding: 8px; background: #f9f9f9; border-left: 4px solid #72aee6;">';
            echo '<strong>' . esc_html($args['label']) . ':</strong> ' . esc_html($descriptions[$args['type']]);
            echo '</p>';
        }
        
        // Subject field
        echo '<table class="form-table" role="presentation">';
        echo '<tr>';
        echo '<th scope="row"><label for="wpmail_subject_' . esc_attr($args['type']) . '">' . __('Subject Line', 'lcd-events') . '</label></th>';
        echo '<td>';
        echo '<input type="text" id="wpmail_subject_' . esc_attr($args['type']) . '" name="lcd_volunteer_email_settings[wpmail_templates][' . esc_attr($args['type']) . '][subject]" value="' . esc_attr($subject) . '" class="large-text" placeholder="' . esc_attr__('Email subject line...', 'lcd-events') . '">';
        echo '<p class="description">' . __('Subject line for the email. You can use merge variables like {volunteer_name}, {event_title}, etc.', 'lcd-events') . '</p>';
        echo '</td>';
        echo '</tr>';
        echo '</table>';
        
        // Content field
        echo '<h4>' . __('Email Content', 'lcd-events') . '</h4>';
        $editor_id = 'wpmail_content_' . $args['type'];
        $settings = [
            'textarea_name' => 'lcd_volunteer_email_settings[wpmail_templates][' . $args['type'] . '][content]',
            'textarea_rows' => 15,
            'media_buttons' => false,
            'teeny' => true,
            'quicktags' => true,
            'tinymce' => [
                'toolbar1' => 'formatselect,bold,italic,underline,|,bullist,numlist,|,link,unlink,|,undo,redo',
                'toolbar2' => '',
                'resize' => true,
                'paste_auto_cleanup_on_paste' => true,
                'paste_remove_styles' => true
            ]
        ];
        
        wp_editor($content, $editor_id, $settings);
        echo '<p class="description">' . __('Email content. You can use HTML formatting and merge variables like {volunteer_name}, {event_title}, {shift_title}, etc.', 'lcd-events') . '</p>';
        
        // Add test button for wp_mail templates
        echo '<div style="margin-top: 10px;">';
        echo '<button type="button" class="button button-secondary test-wpmail-template-btn" data-email-type="' . esc_attr($args['type']) . '">' . __('Test Template', 'lcd-events') . '</button>';
        echo '<div class="template-test-result" id="test-wpmail-result-' . esc_attr($args['type']) . '" style="display: none; margin-top: 10px;"></div>';
        echo '</div>';
        
        echo '</div>';
        echo '<hr style="margin: 30px 0;">';
    }

    /**
     * Sanitize email settings
     */
    public function sanitize_email_settings($input) {
        // Get existing settings to preserve data from other pages
        $existing_settings = get_option('lcd_volunteer_email_settings', []);
        $sanitized = $existing_settings;
        
        // Determine which page we're on by checking the hidden form field
        $form_page = $input['form_page'] ?? '';
        $is_email_settings_page = ($form_page === 'email_settings');
        $is_email_templates_page = ($form_page === 'email_templates');
        
        // Sanitize reminder timing settings (if present in input)
        if (isset($input['reminder_timing'])) {
            $sanitized['reminder_timing'] = max(1, min(168, intval($input['reminder_timing'])));
        }
        
        // Handle email enabled settings checkboxes properly
        if ($is_email_settings_page) {
            $email_types = array_keys($this->get_email_types());
            
            // When on email settings page, process all email type checkboxes
            // (unchecked checkboxes won't be in $input, so we need to set them to 0)
            foreach ($email_types as $type) {
                $field_name = $type . '_enabled';
                $sanitized[$field_name] = isset($input[$field_name]) ? 1 : 0;
            }
        }
        
        // Sanitize template mappings (if present in input)
        if (isset($input['template_mapping']) && is_array($input['template_mapping'])) {
            // Initialize template_mapping array if it doesn't exist
            if (!isset($sanitized['template_mapping'])) {
                $sanitized['template_mapping'] = [];
            }
            foreach ($input['template_mapping'] as $type => $template_key) {
                $sanitized['template_mapping'][$type] = sanitize_text_field($template_key);
            }
        }
        
        // Sanitize wp_mail templates (if present in input)
        if (isset($input['wpmail_templates']) && is_array($input['wpmail_templates'])) {
            // Initialize wpmail_templates array if it doesn't exist
            if (!isset($sanitized['wpmail_templates'])) {
                $sanitized['wpmail_templates'] = [];
            }
            foreach ($input['wpmail_templates'] as $type => $template) {
                if (is_array($template)) {
                    $sanitized['wpmail_templates'][$type] = [
                        'subject' => sanitize_text_field($template['subject'] ?? ''),
                        'content' => wp_kses_post($template['content'] ?? '')
                    ];
                }
            }
        }
        
        // Remove the form_page field from being saved to the database
        unset($sanitized['form_page']);
        
        return $sanitized;
    }

    // Helper methods for rendering sections
    private function render_email_template_help() {
        ?>
        <div class="lcd-email-help">
            <h3><?php _e('Available Merge Variables', 'lcd-events'); ?></h3>
            <div class="lcd-template-variables">
                <div class="variable-group">
                    <h4><?php _e('Volunteer Information', 'lcd-events'); ?></h4>
                    <ul>
                        <li><code>{volunteer_name}</code> - <?php _e('Full name of the volunteer', 'lcd-events'); ?></li>
                        <li><code>{volunteer_email}</code> - <?php _e('Email address of the volunteer', 'lcd-events'); ?></li>
                        <li><code>{volunteer_phone}</code> - <?php _e('Phone number of the volunteer', 'lcd-events'); ?></li>
                    </ul>
                </div>
                
                <div class="variable-group">
                    <h4><?php _e('Event Information', 'lcd-events'); ?></h4>
                    <ul>
                        <li><code>{event_title}</code> - <?php _e('Title of the event', 'lcd-events'); ?></li>
                        <li><code>{event_date}</code> - <?php _e('Event date (formatted)', 'lcd-events'); ?></li>
                        <li><code>{event_time}</code> - <?php _e('Event start time', 'lcd-events'); ?></li>
                        <li><code>{event_location}</code> - <?php _e('Event location name', 'lcd-events'); ?></li>
                        <li><code>{event_address}</code> - <?php _e('Event address', 'lcd-events'); ?></li>
                        <li><code>{event_url}</code> - <?php _e('Link to the event page', 'lcd-events'); ?></li>
                    </ul>
                </div>
                
                <div class="variable-group">
                    <h4><?php _e('Shift Information', 'lcd-events'); ?></h4>
                    <ul>
                        <li><code>{shift_title}</code> - <?php _e('Title of the volunteer shift', 'lcd-events'); ?></li>
                        <li><code>{shift_description}</code> - <?php _e('Description of the shift', 'lcd-events'); ?></li>
                        <li><code>{shift_date}</code> - <?php _e('Shift date (if different from event)', 'lcd-events'); ?></li>
                        <li><code>{shift_start_time}</code> - <?php _e('Shift start time', 'lcd-events'); ?></li>
                        <li><code>{shift_end_time}</code> - <?php _e('Shift end time', 'lcd-events'); ?></li>
                    </ul>
                </div>
                
                <div class="variable-group">
                    <h4><?php _e('Additional Variables', 'lcd-events'); ?></h4>
                    <ul>
                        <li><code>{additional_message}</code> - <?php _e('Custom message (for certain email types)', 'lcd-events'); ?></li>
                        <li><code>{site_name}</code> - <?php _e('Your website name', 'lcd-events'); ?></li>
                        <li><code>{site_url}</code> - <?php _e('Your website URL', 'lcd-events'); ?></li>
                    </ul>
                </div>
            </div>
        </div>
        <?php
    }



    // AJAX handlers
    public function ajax_test_template_email() {
        check_ajax_referer('lcd_test_template_email', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'lcd-events')]);
            return;
        }
        
        $email_type = sanitize_text_field($_POST['email_type'] ?? '');
        $template_key = sanitize_text_field($_POST['template_key'] ?? '');
        $test_email = sanitize_email($_POST['test_email'] ?? '');
        
        if (empty($email_type) || empty($template_key) || empty($test_email)) {
            wp_send_json_error(['message' => __('Missing required fields.', 'lcd-events')]);
            return;
        }
        
        // Get volunteer shifts instance for email sending
        $volunteer_shifts = LCD_Volunteer_Shifts::get_instance();
        
        // Prepare test data
        $volunteer_data = [
            'name' => 'John Doe',
            'email' => $test_email,
            'phone' => '(555) 123-4567'
        ];
        
        $event_data = [
            'title' => 'Test Event - Democratic Rally',
            'date' => date('Y-m-d', strtotime('+1 week')),
            'time' => '18:00',
            'location' => 'Community Center',
            'address' => '123 Main Street, Centralia, WA 98531',
            'url' => home_url('/events/test-event/')
        ];
        
        $shift_data = [
            'title' => 'Event Setup Crew',
            'description' => 'Help set up chairs, tables, and audio equipment before the event.',
            'date' => date('Y-m-d', strtotime('+1 week')),
            'start_time' => '16:00',
            'end_time' => '18:00'
        ];
        
        $additional_data = [];
        
        // Test the specific template by temporarily overriding the template mapping
        $settings = get_option('lcd_volunteer_email_settings', []);
        $original_mapping = $settings['template_mapping'][$email_type] ?? '';
        
        // Temporarily set the template key for testing
        $settings['template_mapping'][$email_type] = $template_key;
        update_option('lcd_volunteer_email_settings', $settings);
        
        // Send test email
        $sent = $volunteer_shifts->send_volunteer_email($email_type, $volunteer_data, $event_data, $shift_data, $additional_data);
        
        // Restore original mapping
        $settings['template_mapping'][$email_type] = $original_mapping;
        update_option('lcd_volunteer_email_settings', $settings);
        
        if ($sent) {
            wp_send_json_success([
                'message' => sprintf(__('Test %s email sent successfully to %s using template key: %s', 'lcd-events'), 
                    $this->get_email_types()[$email_type] ?? $email_type, 
                    $test_email, 
                    $template_key
                )
            ]);
        } else {
            wp_send_json_error(['message' => __('Failed to send test email. Please check your template key and email settings.', 'lcd-events')]);
        }
    }

    public function ajax_test_wpmail_template() {
        check_ajax_referer('lcd_test_wpmail_template', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'lcd-events')]);
            return;
        }
        
        $email_type = sanitize_text_field($_POST['email_type'] ?? '');
        $test_email = sanitize_email($_POST['test_email'] ?? '');
        $subject = sanitize_text_field($_POST['subject'] ?? '');
        $content = wp_kses_post($_POST['content'] ?? '');
        
        if (empty($email_type) || empty($test_email) || empty($subject) || empty($content)) {
            wp_send_json_error(['message' => __('Missing required fields.', 'lcd-events')]);
            return;
        }
        
        // Get volunteer shifts instance for helper methods
        $volunteer_shifts = LCD_Volunteer_Shifts::get_instance();
        
        // Prepare test data
        $volunteer_data = [
            'name' => 'John Doe',
            'email' => $test_email,
            'phone' => '(555) 123-4567'
        ];
        
        $event_data = [
            'title' => 'Test Event - Democratic Rally',
            'date' => date('Y-m-d', strtotime('+1 week')),
            'time' => '18:00',
            'location' => 'Community Center',
            'address' => '123 Main Street, Centralia, WA 98531',
            'url' => home_url('/events/test-event/')
        ];
        
        $shift_data = [
            'title' => 'Event Setup Crew',
            'description' => 'Help set up chairs, tables, and audio equipment before the event.',
            'date' => date('Y-m-d', strtotime('+1 week')),
            'start_time' => '16:00',
            'end_time' => '18:00'
        ];
        
        $additional_data = [];
        if ($email_type === 'volunteer_request_denied') {
            $additional_data['additional_message'] = 'The shift you requested is currently at capacity. However, we will keep your information on file and contact you if any spots become available or if we have other similar volunteer opportunities.';
        }
        
        // Use reflection to access private methods from volunteer shifts class
        $reflection = new ReflectionClass($volunteer_shifts);
        $prepare_variables_method = $reflection->getMethod('prepare_email_variables');
        $prepare_variables_method->setAccessible(true);
        $replace_variables_method = $reflection->getMethod('replace_email_variables');
        $replace_variables_method->setAccessible(true);
        
        // Prepare merge variables
        $merge_data = $prepare_variables_method->invoke($volunteer_shifts, $volunteer_data, $event_data, $shift_data, $additional_data);
        
        // Replace variables in subject and content
        $final_subject = $replace_variables_method->invoke($volunteer_shifts, $subject, $merge_data);
        $final_content = $replace_variables_method->invoke($volunteer_shifts, $content, $merge_data);
        
        // Get email settings
        $settings = get_option('lcd_volunteer_email_settings', []);
        $from_name = $settings['from_name'] ?? get_bloginfo('name');
        $from_email = $settings['from_email'] ?? get_option('admin_email');
        $reply_to = $settings['reply_to'] ?? $from_email;
        
        // Prepare email headers
        $headers = [];
        $headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
        $headers[] = 'Reply-To: ' . $reply_to;
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        
        // Send test email
        $sent = wp_mail($test_email, $final_subject, $final_content, $headers);
        
        if ($sent) {
            wp_send_json_success([
                'message' => sprintf(__('Test %s email sent successfully to %s using wp_mail template', 'lcd-events'), 
                    $this->get_email_types()[$email_type] ?? $email_type, 
                    $test_email
                )
            ]);
        } else {
            wp_send_json_error(['message' => __('Failed to send test email. Please check your wp_mail configuration.', 'lcd-events')]);
        }
    }

    /**
     * Send volunteer email using configured method (Zeptomail or wp_mail)
     *
     * @param string $email_type Type of email (volunteer_confirmation, volunteer_cancellation, etc.)
     * @param array $volunteer_data Volunteer information
     * @param array $event_data Event information
     * @param array $shift_data Shift information
     * @param array $additional_data Additional data like custom messages
     * @return bool Success/failure
     */
    public function send_volunteer_email($email_type, $volunteer_data, $event_data, $shift_data, $additional_data = []) {
        // Get email settings
        $settings = get_option('lcd_volunteer_email_settings', []);
        
        // Check if this email type is enabled
        if (empty($settings[$email_type . '_enabled'])) {
            return false; // Email type is disabled
        }
        
        // Determine if we should use Zeptomail or wp_mail
        $use_zeptomail = false;
        if (class_exists('LCD_Zeptomail')) {
            $zeptomail = LCD_Zeptomail::get_instance();
            $use_zeptomail = $zeptomail->is_enabled();
        }
        
        if ($use_zeptomail) {
            return $this->send_zeptomail_template_email($email_type, $volunteer_data, $event_data, $shift_data, $additional_data, $settings);
        } else {
            return $this->send_wpmail_template_email($email_type, $volunteer_data, $event_data, $shift_data, $additional_data, $settings);
        }
    }
    
    /**
     * Send email using Zeptomail template
     */
    private function send_zeptomail_template_email($email_type, $volunteer_data, $event_data, $shift_data, $additional_data, $settings) {
        // Get template key for this email type
        $template_key = $settings['template_mapping'][$email_type] ?? '';
        if (empty($template_key)) {
            return false; // No template configured
        }
        
        if (!class_exists('LCD_Zeptomail')) {
            return false;
        }
        
        $zeptomail = LCD_Zeptomail::get_instance();
        $zeptomail_settings = $zeptomail->get_settings();
        
        // Prepare merge data for Zeptomail template
        $merge_data = $this->prepare_zeptomail_merge_data($volunteer_data, $event_data, $shift_data, $additional_data);
        
        // Use configured email settings or defaults
        $from_name = $zeptomail_settings['from_name'] ?? get_bloginfo('name');
        $from_email = $zeptomail_settings['from_email'] ?? get_option('admin_email');
        $reply_to = $zeptomail_settings['reply_to'] ?? $from_email;
        
        return $zeptomail->send_template_email(
            $volunteer_data['email'],
            $template_key,
            $merge_data,
            $from_name,
            $from_email,
            $reply_to
        );
    }
    
    /**
     * Send email using WordPress wp_mail with configured templates
     */
    private function send_wpmail_template_email($email_type, $volunteer_data, $event_data, $shift_data, $additional_data, $settings) {
        // Get template for this email type
        $template = $settings['wpmail_templates'][$email_type] ?? [];
        $subject = $template['subject'] ?? '';
        $content = $template['content'] ?? '';
        
        if (empty($subject) || empty($content)) {
            return false; // No template configured
        }
        
        // Prepare merge variables
        $merge_variables = $this->prepare_email_variables($volunteer_data, $event_data, $shift_data, $additional_data);
        
        // Replace variables in subject and content
        $final_subject = $this->replace_email_variables($subject, $merge_variables);
        $final_content = $this->replace_email_variables($content, $merge_variables);
        
        // Get email settings
        $zeptomail_settings = [];
        if (class_exists('LCD_Zeptomail')) {
            $zeptomail = LCD_Zeptomail::get_instance();
            $zeptomail_settings = $zeptomail->get_settings();
        }
        
        $from_name = $zeptomail_settings['from_name'] ?? get_bloginfo('name');
        $from_email = $zeptomail_settings['from_email'] ?? get_option('admin_email');
        $reply_to = $zeptomail_settings['reply_to'] ?? $from_email;
        
        // Prepare email headers
        $headers = [];
        $headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
        $headers[] = 'Reply-To: ' . $reply_to;
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        
        // Send email
        return wp_mail($volunteer_data['email'], $final_subject, $final_content, $headers);
    }
    
    /**
     * Prepare merge data for Zeptomail templates
     */
    private function prepare_zeptomail_merge_data($volunteer_data, $event_data, $shift_data, $additional_data) {
        return [
            'volunteer_name' => $volunteer_data['name'] ?? '',
            'volunteer_email' => $volunteer_data['email'] ?? '',
            'volunteer_phone' => $volunteer_data['phone'] ?? '',
            'event_title' => $event_data['title'] ?? '',
            'event_date' => !empty($event_data['date']) ? date_i18n(get_option('date_format'), strtotime($event_data['date'])) : '',
            'event_time' => $event_data['time'] ?? '',
            'event_location' => $event_data['location'] ?? '',
            'event_address' => $event_data['address'] ?? '',
            'event_url' => $event_data['url'] ?? '',
            'shift_title' => $shift_data['title'] ?? '',
            'shift_description' => $shift_data['description'] ?? '',
            'shift_date' => !empty($shift_data['date']) ? date_i18n(get_option('date_format'), strtotime($shift_data['date'])) : '',
            'shift_start_time' => !empty($shift_data['start_time']) ? date_i18n(get_option('time_format'), strtotime($shift_data['start_time'])) : '',
            'shift_end_time' => !empty($shift_data['end_time']) ? date_i18n(get_option('time_format'), strtotime($shift_data['end_time'])) : '',
            'additional_message' => $additional_data['additional_message'] ?? '',
            'site_name' => get_bloginfo('name'),
            'site_url' => home_url(),
            'recipient_name' => $volunteer_data['name'] ?? ''
        ];
    }
    
    /**
     * Prepare email variables for wp_mail templates
     */
    private function prepare_email_variables($volunteer_data, $event_data, $shift_data, $additional_data) {
        return [
            '{volunteer_name}' => $volunteer_data['name'] ?? '',
            '{volunteer_email}' => $volunteer_data['email'] ?? '',
            '{volunteer_phone}' => $volunteer_data['phone'] ?? '',
            '{event_title}' => $event_data['title'] ?? '',
            '{event_date}' => !empty($event_data['date']) ? date_i18n(get_option('date_format'), strtotime($event_data['date'])) : '',
            '{event_time}' => $event_data['time'] ?? '',
            '{event_location}' => $event_data['location'] ?? '',
            '{event_address}' => $event_data['address'] ?? '',
            '{event_url}' => $event_data['url'] ?? '',
            '{shift_title}' => $shift_data['title'] ?? '',
            '{shift_description}' => $shift_data['description'] ?? '',
            '{shift_date}' => !empty($shift_data['date']) ? date_i18n(get_option('date_format'), strtotime($shift_data['date'])) : '',
            '{shift_start_time}' => !empty($shift_data['start_time']) ? date_i18n(get_option('time_format'), strtotime($shift_data['start_time'])) : '',
            '{shift_end_time}' => !empty($shift_data['end_time']) ? date_i18n(get_option('time_format'), strtotime($shift_data['end_time'])) : '',
            '{additional_message}' => $additional_data['additional_message'] ?? '',
            '{site_name}' => get_bloginfo('name'),
            '{site_url}' => home_url()
        ];
    }
    
    /**
     * Replace variables in email content
     */
    private function replace_email_variables($content, $variables) {
        return str_replace(array_keys($variables), array_values($variables), $content);
    }
} 