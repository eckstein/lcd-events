<?php
/**
 * Volunteer Shifts Administration
 * 
 * Handles volunteer shifts admin page, shift management, and admin-only shift functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class LCD_Volunteer_Shifts_Admin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', [$this, 'add_volunteer_shifts_page']);
        add_action('admin_enqueue_scripts', [$this, 'volunteer_shifts_admin_styles']);
        add_action('admin_init', [$this, 'handle_admin_shift_actions']);
        
        // Admin-only AJAX handlers
        add_action('wp_ajax_lcd_search_people_for_shifts', [$this, 'ajax_search_people_for_shifts']);
        add_action('wp_ajax_assign_person_to_shift', [$this, 'ajax_assign_person_to_shift']);
        add_action('wp_ajax_unassign_person_from_shift', [$this, 'ajax_unassign_person_from_shift']);
        add_action('wp_ajax_edit_volunteer_notes', [$this, 'ajax_edit_volunteer_notes']);
        add_action('wp_ajax_toggle_volunteer_confirmed', [$this, 'ajax_toggle_volunteer_confirmed']);
        add_action('wp_ajax_export_volunteers_csv', [$this, 'ajax_export_volunteers_csv']);
        add_action('wp_ajax_export_volunteers_pdf', [$this, 'ajax_export_volunteers_pdf']);
        add_action('wp_ajax_save_individual_shift', [$this, 'ajax_save_individual_shift']);
    }

    /**
     * Add volunteer shifts admin page
     */
    public function add_volunteer_shifts_page() {
        add_submenu_page(
            'edit.php?post_type=event',
            __('Volunteer Shifts', 'lcd-events'),
            __('Volunteer Shifts', 'lcd-events'),
            'edit_posts',
            'volunteer-shifts',
            [$this, 'volunteer_shifts_page_callback']
        );
    }

    /**
     * Volunteer shifts page callback
     */
    public function volunteer_shifts_page_callback() {
        // Get all upcoming events (including those without shifts for adding new ones)
        $events = get_posts(array(
            'post_type' => 'event',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_event_date',
                    'value' => date('Y-m-d'),
                    'compare' => '>=',
                    'type' => 'DATE'
                )
            ),
            'orderby' => 'meta_value',
            'meta_key' => '_event_date',
            'order' => 'ASC'
        ));

        ?>
        <div class="wrap">
            <h1><?php _e('Volunteer Shifts Management', 'lcd-events'); ?></h1>
            
            <?php if (empty($events)) : ?>
                <div class="notice notice-info">
                    <p><?php _e('No upcoming events found.', 'lcd-events'); ?></p>
                </div>
            <?php else : ?>
                <div class="lcd-volunteer-shifts-overview">
                    <?php foreach ($events as $event) : 
                        $this->render_event_section($event);
                    endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render individual event section
     */
    private function render_event_section($event) {
        $volunteer_shifts = get_post_meta($event->ID, '_volunteer_shifts', true);
        if (!is_array($volunteer_shifts)) {
            $volunteer_shifts = array();
        }
        
        $event_date = get_post_meta($event->ID, '_event_date', true);
        $formatted_date = date_i18n(get_option('date_format'), strtotime($event_date));
        
        // Get all signups for this event
        $volunteer_shifts_instance = LCD_Volunteer_Shifts::get_instance();
        $all_signups = $volunteer_shifts_instance->get_volunteer_signups($event->ID);
        $signups_by_shift = array();
        foreach ($all_signups as $signup) {
            $signups_by_shift[$signup->shift_index][] = $signup;
        }
        
        ?>
        <div class="lcd-event-shifts-section" data-event-id="<?php echo $event->ID; ?>">
            <div class="event-header">
                <div class="event-header-content">
                    <h2 class="event-title">
                        <a href="<?php echo get_edit_post_link($event->ID); ?>">
                            <?php echo esc_html($event->post_title); ?>
                        </a>
                    </h2>
                    <div class="event-date"><?php echo esc_html($formatted_date); ?></div>
                </div>
                <div class="event-actions">
                    <button type="button" class="button button-primary add-new-shift" data-event-id="<?php echo $event->ID; ?>">
                        <span class="dashicons dashicons-plus-alt"></span>
                        <?php _e('Add Shift', 'lcd-events'); ?>
                    </button>
                </div>
            </div>
            
            <?php 
            $total_event_signups = count($all_signups);
            if ($total_event_signups > 0) : ?>
                <div class="event-export-header">
                    <div class="event-export-info">
                        <span><?php printf(__('Total volunteers: %d', 'lcd-events'), $total_event_signups); ?></span>
                    </div>
                    <div class="event-export-buttons">
                        <button type="button" class="button button-secondary export-volunteers-csv" data-event-id="<?php echo $event->ID; ?>">
                            <span class="dashicons dashicons-media-spreadsheet"></span>
                            <?php _e('Export CSV', 'lcd-events'); ?>
                        </button>
                        <button type="button" class="button button-secondary export-volunteers-pdf" data-event-id="<?php echo $event->ID; ?>">
                            <span class="dashicons dashicons-pdf"></span>
                            <?php _e('Export PDF', 'lcd-events'); ?>
                        </button>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (empty($volunteer_shifts)) : ?>
                <div class="no-shifts-message">
                    <p><?php _e('No volunteer shifts created for this event yet.', 'lcd-events'); ?></p>
                </div>
            <?php endif; ?>
            
            <form method="post" class="shift-management-form" data-event-id="<?php echo $event->ID; ?>">
                <?php wp_nonce_field('lcd_volunteer_shifts_admin', 'lcd_volunteer_shifts_admin_nonce'); ?>
                <input type="hidden" name="action" value="save_shifts">
                <input type="hidden" name="event_id" value="<?php echo $event->ID; ?>">
                
                <div id="volunteer-shifts-container-<?php echo $event->ID; ?>" class="volunteer-shifts-container">
                    <?php foreach ($volunteer_shifts as $index => $shift) : 
                        $this->render_shift_item($event->ID, $index, $shift, $signups_by_shift[$index] ?? array());
                    endforeach; ?>
                </div>
            </form>
        </div>
        <?php
    }

    /**
     * Render individual shift item
     */
    private function render_shift_item($event_id, $index, $shift, $shift_signups) {
        $signup_count = count($shift_signups);
        $max_volunteers = !empty($shift['max_volunteers']) ? $shift['max_volunteers'] : '∞';
        
        $time_string = '';
        if (!empty($shift['start_time']) && !empty($shift['date'])) {
            $time_string = date_i18n(get_option('time_format'), strtotime($shift['date'] . ' ' . $shift['start_time']));
            if (!empty($shift['end_time'])) {
                $time_string .= ' - ' . date_i18n(get_option('time_format'), strtotime($shift['date'] . ' ' . $shift['end_time']));
            }
        }
        
        ?>
        <div class="volunteer-shift-item" data-index="<?php echo $index; ?>" data-event-id="<?php echo $event_id; ?>">
            <div class="shift-summary" data-shift="<?php echo $index; ?>">
                <div class="shift-summary-content">
                    <div class="shift-title-summary">
                        <strong><?php echo esc_html($shift['title']); ?></strong>
                    </div>
                    <div class="shift-meta-summary">
                        <?php if (!empty($shift['date'])) : ?>
                            <span class="shift-date-summary">
                                <?php echo date_i18n(get_option('date_format'), strtotime($shift['date'])); ?>
                            </span>
                        <?php endif; ?>
                        
                        <?php if ($time_string) : ?>
                            <span class="shift-time-summary"><?php echo esc_html($time_string); ?></span>
                        <?php endif; ?>
                        
                        <span class="shift-signups-summary">
                            <?php 
                            if ($max_volunteers !== '∞') {
                                printf(__('%d / %s volunteers', 'lcd-events'), $signup_count, $max_volunteers);
                            } else {
                                printf(__('%d volunteers', 'lcd-events'), $signup_count);
                            }
                            ?>
                        </span>
                    </div>
                </div>
                <div class="shift-summary-actions">
                    <button type="button" class="button button-small toggle-shift-details" data-expanded="false">
                        <span class="dashicons dashicons-arrow-down-alt2"></span>
                        <?php _e('Edit', 'lcd-events'); ?>
                    </button>
                    <button type="button" class="button button-small button-link-delete remove-shift">
                        <span class="dashicons dashicons-trash"></span>
                        <?php _e('Delete', 'lcd-events'); ?>
                    </button>
                </div>
            </div>
        
            <div class="shift-details" style="display: none;">
                <?php $this->render_shift_form($event_id, $index, $shift); ?>
                
                <!-- Volunteer Signups Section -->
                <?php if (!empty($shift_signups)) : ?>
                    <?php $this->render_shift_signups($event_id, $index, $shift_signups); ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render shift form fields
     */
    private function render_shift_form($event_id, $index, $shift) {
        ?>
        <div class="shift-form-row">
            <div class="shift-form-col">
                <label for="volunteer_shifts_<?php echo $event_id; ?>_<?php echo $index; ?>_title"><?php _e('Shift Title:', 'lcd-events'); ?></label>
                <input type="text" 
                       id="volunteer_shifts_<?php echo $event_id; ?>_<?php echo $index; ?>_title" 
                       name="volunteer_shifts[<?php echo $index; ?>][title]" 
                       value="<?php echo esc_attr($shift['title'] ?? ''); ?>" 
                       placeholder="<?php _e('e.g., Event Setup Crew', 'lcd-events'); ?>"
                       class="regular-text">
            </div>
            <div class="shift-form-col">
                <label for="volunteer_shifts_<?php echo $event_id; ?>_<?php echo $index; ?>_max_volunteers"><?php _e('Max Volunteers:', 'lcd-events'); ?></label>
                <input type="number" 
                       id="volunteer_shifts_<?php echo $event_id; ?>_<?php echo $index; ?>_max_volunteers" 
                       name="volunteer_shifts[<?php echo $index; ?>][max_volunteers]" 
                       value="<?php echo esc_attr($shift['max_volunteers'] ?? ''); ?>" 
                       placeholder="<?php _e('Unlimited', 'lcd-events'); ?>"
                       class="small-text">
            </div>
        </div>
        
        <div class="shift-form-row">
            <div class="shift-form-col shift-form-col-full">
                <label for="volunteer_shifts_<?php echo $event_id; ?>_<?php echo $index; ?>_description"><?php _e('Description:', 'lcd-events'); ?></label>
                <textarea id="volunteer_shifts_<?php echo $event_id; ?>_<?php echo $index; ?>_description" 
                          name="volunteer_shifts[<?php echo $index; ?>][description]" 
                          rows="3" 
                          class="large-text"
                          placeholder="<?php _e('What will volunteers be doing?', 'lcd-events'); ?>"><?php echo esc_textarea($shift['description'] ?? ''); ?></textarea>
            </div>
        </div>

        <div class="shift-form-row shift-datetime-row">
            <div class="shift-form-col">
                <label for="volunteer_shifts_<?php echo $event_id; ?>_<?php echo $index; ?>_date"><?php _e('Date:', 'lcd-events'); ?></label>
                <input type="date" 
                       id="volunteer_shifts_<?php echo $event_id; ?>_<?php echo $index; ?>_date" 
                       name="volunteer_shifts[<?php echo $index; ?>][date]" 
                       value="<?php echo esc_attr($shift['date'] ?? ''); ?>">
            </div>
            <div class="shift-form-col">
                <label for="volunteer_shifts_<?php echo $event_id; ?>_<?php echo $index; ?>_start_time"><?php _e('Start Time:', 'lcd-events'); ?></label>
                <input type="time" 
                       id="volunteer_shifts_<?php echo $event_id; ?>_<?php echo $index; ?>_start_time" 
                       name="volunteer_shifts[<?php echo $index; ?>][start_time]" 
                       value="<?php echo esc_attr($shift['start_time'] ?? ''); ?>">
            </div>
            <div class="shift-form-col">
                <label for="volunteer_shifts_<?php echo $event_id; ?>_<?php echo $index; ?>_end_time"><?php _e('End Time:', 'lcd-events'); ?></label>
                <input type="time" 
                       id="volunteer_shifts_<?php echo $event_id; ?>_<?php echo $index; ?>_end_time" 
                       name="volunteer_shifts[<?php echo $index; ?>][end_time]" 
                       value="<?php echo esc_attr($shift['end_time'] ?? ''); ?>">
            </div>
        </div>

        <!-- Volunteer Assignment Section -->
        <div class="assign-volunteer-section">
            <h4 class="assign-volunteer-title"><?php _e('Assign Volunteer', 'lcd-events'); ?></h4>
            <div class="assign-volunteer-controls">
                <select id="assign_volunteer_<?php echo $event_id; ?>_<?php echo $index; ?>" class="assign-volunteer-select" data-event-id="<?php echo $event_id; ?>" data-shift-index="<?php echo $index; ?>" data-shift-title="<?php echo esc_attr($shift['title'] ?? ''); ?>">
                    <option value=""><?php _e('Search for a person to assign...', 'lcd-events'); ?></option>
                </select>
            </div>
        </div>
        <?php
    }

    /**
     * Render shift signups section
     */
    private function render_shift_signups($event_id, $shift_index, $signups) {
        ?>
        <div class="shift-signups">
            <div class="shift-signups-header">
                <span class="dashicons dashicons-groups"></span>
                <?php _e('Volunteer Signups', 'lcd-events'); ?>
                <span class="signups-count">(<?php echo count($signups); ?>)</span>
            </div>
            
            <div class="signups-list">
                <table class="signups-table">
                    <thead>
                        <tr>
                            <th><?php _e('Volunteer', 'lcd-events'); ?></th>
                            <th><?php _e('Contact', 'lcd-events'); ?></th>
                            <th><?php _e('Notes', 'lcd-events'); ?></th>
                            <th><?php _e('Status', 'lcd-events'); ?></th>
                            <th><?php _e('Actions', 'lcd-events'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($signups as $signup) : ?>
                            <?php $this->render_signup_row($signup); ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * Render individual signup row
     */
    private function render_signup_row($signup) {
        $status_class = $signup->status === 'confirmed' ? 'status-confirmed' : 'status-unconfirmed';
        ?>
        <tr class="signup-row <?php echo esc_attr($status_class); ?>" data-person-id="<?php echo esc_attr($signup->person_id ?? ''); ?>" data-signup-id="<?php echo esc_attr($signup->id); ?>">
            <td class="signup-name-cell">
                <div class="signup-name"><?php echo esc_html($signup->volunteer_name); ?></div>
            </td>
            <td class="signup-contact-cell">
                <div class="signup-contact">
                    <div class="email"><?php echo esc_html($signup->volunteer_email); ?></div>
                    <?php if (!empty($signup->volunteer_phone)) : ?>
                        <div class="phone"><?php echo esc_html($signup->volunteer_phone); ?></div>
                    <?php endif; ?>
                </div>
            </td>
            <td class="signup-notes-cell">
                <div class="signup-notes">
                    <div class="signup-notes-display">
                        <span class="signup-notes-text <?php echo empty($signup->volunteer_notes) ? 'no-notes' : ''; ?>">
                            <?php echo !empty($signup->volunteer_notes) ? esc_html($signup->volunteer_notes) : __('No notes', 'lcd-events'); ?>
                        </span>
                        <a class="signup-notes-edit-btn edit-notes" title="<?php esc_attr_e('Edit notes', 'lcd-events'); ?>">
                            <span class="dashicons dashicons-edit"></span>
                        </a>
                    </div>
                    <div class="signup-notes-edit" style="display: none;">
                        <textarea class="notes-edit-field" rows="2" placeholder="<?php esc_attr_e('Add notes for this assignment...', 'lcd-events'); ?>"><?php echo esc_textarea($signup->volunteer_notes ?? ''); ?></textarea>
                        <div class="notes-edit-actions">
                            <button type="button" class="button button-small save-notes"><?php _e('Save', 'lcd-events'); ?></button>
                            <button type="button" class="button button-small cancel-notes"><?php _e('Cancel', 'lcd-events'); ?></button>
                        </div>
                    </div>
                </div>
            </td>
            <td class="signup-status-cell">
                <button type="button" class="button button-small toggle-confirmed <?php echo $signup->status === 'confirmed' ? 'confirmed' : 'unconfirmed'; ?>" 
                        data-signup-id="<?php echo esc_attr($signup->id); ?>" 
                        data-confirmed="<?php echo $signup->status === 'confirmed' ? '1' : '0'; ?>">
                    <?php echo $signup->status === 'confirmed' ? __('Unconfirm', 'lcd-events') : __('Confirm', 'lcd-events'); ?>
                </button>
            </td>
            <td class="signup-actions-cell">
                <button type="button" class="button-link button-link-delete remove-volunteer" 
                        data-signup-id="<?php echo esc_attr($signup->id); ?>">
                    <span class="dashicons dashicons-trash"></span>
                    <?php _e('Remove', 'lcd-events'); ?>
                </button>
            </td>
        </tr>
        <?php
    }

    /**
     * Handle admin shift actions
     */
    public function handle_admin_shift_actions() {
        // Only handle form submission on the volunteer shifts admin page
        if (!isset($_GET['page']) || $_GET['page'] !== 'volunteer-shifts') {
            return;
        }

        // Check if required fields exist
        if (!isset($_POST['lcd_volunteer_shifts_admin_nonce']) || !isset($_POST['event_id']) || !isset($_POST['action'])) {
            return; // No form submission to handle
        }

        // Verify nonce
        if (!wp_verify_nonce($_POST['lcd_volunteer_shifts_admin_nonce'], 'lcd_volunteer_shifts_admin')) {
            wp_die(__('Security check failed.', 'lcd-events'));
            return;
        }

        $event_id = intval($_POST['event_id']);
        $action = sanitize_text_field($_POST['action']);

        if (!current_user_can('edit_post', $event_id)) {
            wp_die(__('You do not have permission to edit this event.', 'lcd-events'));
            return;
        }

        switch ($action) {
            case 'save_shifts':
                $this->save_admin_shifts($event_id);
                break;
        }
    }

    /**
     * Save shifts from admin page
     */
    private function save_admin_shifts($event_id) {
        $volunteer_shifts = array();
        
        if (isset($_POST['volunteer_shifts']) && is_array($_POST['volunteer_shifts'])) {
            foreach ($_POST['volunteer_shifts'] as $index => $shift) {
                if (!empty($shift['title'])) { // Only save shifts with a title
                    $volunteer_shifts[] = array(
                        'title' => sanitize_text_field($shift['title']),
                        'description' => sanitize_textarea_field($shift['description'] ?? ''),
                        'date' => sanitize_text_field($shift['date'] ?? ''),
                        'start_time' => sanitize_text_field($shift['start_time'] ?? ''),
                        'end_time' => sanitize_text_field($shift['end_time'] ?? ''),
                        'max_volunteers' => intval($shift['max_volunteers'] ?? 0)
                    );
                }
            }
        }
        
        update_post_meta($event_id, '_volunteer_shifts', $volunteer_shifts);
        
        // Add admin notice
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Volunteer shifts saved successfully.', 'lcd-events') . '</p></div>';
        });
    }

    /**
     * Load admin styles and scripts
     */
    public function volunteer_shifts_admin_styles($hook) {
        $screen = get_current_screen();
        
        // Load styles for volunteer shifts overview page and admin pages
        if (in_array($screen->id, ['event_page_volunteer-shifts', 'event_page_volunteer-email-settings', 'event_page_volunteer-email-templates'])) {
            // Enqueue the volunteer shifts admin stylesheet
            wp_enqueue_style(
                'lcd-volunteer-shifts-admin-styles',
                plugin_dir_url(dirname(__FILE__)) . 'css/admin-volunteer-shifts.css',
                array(),
                '1.0.0'
            );
        }
        
        // Load styles for email pages
        if (in_array($screen->id, ['event_page_volunteer-email-settings', 'event_page_volunteer-email-templates'])) {
            // Enqueue the email templates admin stylesheet
            wp_enqueue_style(
                'lcd-email-templates-admin-styles',
                plugin_dir_url(dirname(__FILE__)) . 'css/email-templates-admin.css',
                array(),
                '1.0.0'
            );
        }
        
        // Enqueue JavaScript and dependencies only for volunteer shifts admin page
        if ($screen->id === 'event_page_volunteer-shifts') {
            // Enqueue the main admin CSS file as well
            wp_enqueue_style(
                'lcd-events-admin-styles',
                plugin_dir_url(dirname(__FILE__)) . 'css/lcd-events.css',
                array(), 
                '1.0.0'
            );
            
            // Enqueue Select2
            if (!wp_style_is('select2', 'enqueued')) {
                wp_enqueue_style(
                    'select2',
                    'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
                    array(),
                    '4.1.0-rc.0'
                );
            }
            if (!wp_script_is('select2', 'enqueued')) {
                wp_enqueue_script(
                    'select2',
                    'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
                    array('jquery'),
                    '4.1.0-rc.0',
                    true
                );
            }

            // Enqueue our admin events JavaScript
            wp_enqueue_script(
                'lcd-events-admin',
                plugin_dir_url(dirname(__FILE__)) . 'js/admin-events.js',
                array('jquery', 'select2'),
                '1.0.0',
                true
            );

            // Localize script for the volunteer shifts overview page
            wp_localize_script('lcd-events-admin', 'lcdEventsAdmin', [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'search_people_nonce' => wp_create_nonce('lcd_event_shifts_people_search'),
                'assign_person_nonce' => wp_create_nonce('lcd_event_assign_person_to_shift'),
                'unassign_person_nonce' => wp_create_nonce('lcd_event_unassign_person_from_shift'),
                'edit_notes_nonce' => wp_create_nonce('lcd_event_edit_volunteer_notes'),
                'toggle_confirmed_nonce' => wp_create_nonce('lcd_event_toggle_volunteer_confirmed'),
                'save_shift_nonce' => wp_create_nonce('lcd_save_individual_shift'),
                'export_csv_nonce' => wp_create_nonce('lcd_export_volunteers_csv'),
                'export_pdf_nonce' => wp_create_nonce('lcd_export_volunteers_pdf'),
                'text' => [
                    'confirm_unassign' => __('Are you sure you want to remove this volunteer from this shift?', 'lcd-events'),
                    'confirm_remove_shift' => __('This shift has volunteers assigned. Removing it will unassign all volunteers. Are you sure you want to continue?', 'lcd-events'),
                    'error_assigning' => __('Could not assign volunteer. Please try again.', 'lcd-events'),
                    'error_unassigning' => __('Could not remove volunteer. Please try again.', 'lcd-events'),
                    'error_editing_notes' => __('Could not save notes. Please try again.', 'lcd-events'),
                    'searching' => __('Searching...', 'lcd-events'),
                    'no_results' => __('No people found matching your search.', 'lcd-events'),
                    'error_loading' => __('Could not load search results.', 'lcd-events'),
                    'edit_notes' => __('Edit notes', 'lcd-events'),
                    'add_notes' => __('Add notes', 'lcd-events'),
                    'no_notes' => __('No notes', 'lcd-events'),
                    'search_placeholder' => __('Search by name or email...', 'lcd-events'),
                    'input_too_short' => __('Please enter 2 or more characters', 'lcd-events'),
                    'export_error' => __('Error exporting volunteer list. Please try again.', 'lcd-events'),
                    'registered_volunteers' => __('Registered Volunteers:', 'lcd-events'),
                    'untitled_shift' => __('Untitled Shift', 'lcd-events'),
                    'enter_details' => __('Click Edit to configure', 'lcd-events'),
                    'new_shift' => __('New Shift', 'lcd-events'),
                ]
            ]);
        }
        
        // For event edit pages, just enqueue basic admin JavaScript for meta box export functionality
        if ($screen->id === 'event') {
            wp_enqueue_script(
                'lcd-events-admin',
                plugin_dir_url(dirname(__FILE__)) . 'js/admin-events.js',
                array('jquery'),
                '1.0.0',
                true
            );
            
            // Minimal localization for meta box
            wp_localize_script('lcd-events-admin', 'lcdEventsAdmin', [
                'ajaxurl' => admin_url('admin-ajax.php')
            ]);
        }
    }

    // AJAX Handlers - Implementation will be added separately due to length
    public function ajax_search_people_for_shifts() {
        check_ajax_referer('lcd_event_shifts_people_search', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Permission denied.', 'lcd-events')], 403);
            return;
        }

        $search_term = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
        $results = [];

        if (empty($search_term)) {
            wp_send_json_success(['items' => $results]);
            return;
        }

        $args = [
            'post_type' => 'lcd_person',
            'posts_per_page' => 20,
            's' => $search_term,
            'post_status' => 'publish',
        ];

        $people_query_by_title = new WP_Query($args);

        if ($people_query_by_title->have_posts()) {
            while ($people_query_by_title->have_posts()) {
                $people_query_by_title->the_post();
                $person_id = get_the_ID();
                $email = get_post_meta($person_id, '_lcd_person_email', true);
                $text = get_the_title();
                if ($email) {
                    $text .= ' (' . esc_html($email) . ')';
                }
                $results[$person_id] = ['id' => $person_id, 'text' => $text];
            }
        }
        wp_reset_postdata();

        // Also search by email meta if search term contains '@'
        if (strpos($search_term, '@') !== false) {
            $args_email = [
                'post_type' => 'lcd_person',
                'posts_per_page' => 20,
                'post_status' => 'publish',
                'meta_query' => [
                    [
                        'key' => '_lcd_person_email',
                        'value' => $search_term,
                        'compare' => 'LIKE',
                    ],
                ],
            ];
            $people_query_by_email = new WP_Query($args_email);
            if ($people_query_by_email->have_posts()) {
                while ($people_query_by_email->have_posts()) {
                    $people_query_by_email->the_post();
                    $person_id = get_the_ID();
                    // Avoid duplicates if already found by title
                    if (!isset($results[$person_id])) {
                        $email = get_post_meta($person_id, '_lcd_person_email', true);
                        $text = get_the_title();
                         if ($email) {
                            $text .= ' (' . esc_html($email) . ')';
                        }
                        $results[$person_id] = ['id' => $person_id, 'text' => $text];
                    }
                }
            }
            wp_reset_postdata();
        }
        
        // Convert associative array to indexed for Select2
        wp_send_json_success(['items' => array_values($results)]);
    }

    public function ajax_assign_person_to_shift() {
        // Implementation will be moved from main class - stub for now
        $volunteer_shifts = LCD_Volunteer_Shifts::get_instance();
        return $volunteer_shifts->ajax_assign_person_to_shift();
    }

    public function ajax_unassign_person_from_shift() {
        // Implementation will be moved from main class - stub for now
        $volunteer_shifts = LCD_Volunteer_Shifts::get_instance();
        return $volunteer_shifts->ajax_unassign_person_from_shift();
    }

    public function ajax_edit_volunteer_notes() {
        // Implementation will be moved from main class - stub for now
        $volunteer_shifts = LCD_Volunteer_Shifts::get_instance();
        return $volunteer_shifts->ajax_edit_volunteer_notes();
    }

    public function ajax_toggle_volunteer_confirmed() {
        // Implementation will be moved from main class - stub for now
        $volunteer_shifts = LCD_Volunteer_Shifts::get_instance();
        return $volunteer_shifts->ajax_toggle_volunteer_confirmed();
    }

    public function ajax_export_volunteers_csv() {
        // Implementation will be moved from main class - stub for now
        $volunteer_shifts = LCD_Volunteer_Shifts::get_instance();
        return $volunteer_shifts->ajax_export_volunteers_csv();
    }

    public function ajax_export_volunteers_pdf() {
        // Implementation will be moved from main class - stub for now
        $volunteer_shifts = LCD_Volunteer_Shifts::get_instance();
        return $volunteer_shifts->ajax_export_volunteers_pdf();
    }

    public function ajax_save_individual_shift() {
        // Implementation will be moved from main class - stub for now
        $volunteer_shifts = LCD_Volunteer_Shifts::get_instance();
        return $volunteer_shifts->ajax_save_individual_shift();
    }
} 