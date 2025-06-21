<?php
/**
 * Plugin Name: LCD Events
 * Plugin URI: https://lewiscountydemocrats.org/
 * Description: A custom events management plugin for Lewis County Democrats
 * Version: 1.0.0
 * Author: Lewis County Democrats
 * Author URI: https://lewiscountydemocrats.org/
 * Text Domain: lcd-events
 * Domain Path: /languages
 */

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

// Define plugin constants
define('LCD_EVENTS_VERSION', '1.0.0');
define('LCD_EVENTS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LCD_EVENTS_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Flush rewrite rules on plugin activation
 */
function lcd_events_activate() {
    // Register post type first
    lcd_register_events_post_type();
    
    // Create volunteer signups table
    lcd_create_volunteer_signups_table();
    
    // Then flush rewrite rules
    flush_rewrite_rules();
    
    // Set transient for admin notice
    set_transient('lcd_events_activated', true, 5);
}
register_activation_hook(__FILE__, 'lcd_events_activate');

/**
 * Create volunteer signups table
 */
function lcd_create_volunteer_signups_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'lcd_volunteer_signups';
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (\n        id mediumint(9) NOT NULL AUTO_INCREMENT,\n        event_id bigint(20) NOT NULL,\n        shift_index int(11) NOT NULL,\n        shift_title varchar(255) NOT NULL,\n        volunteer_name varchar(255) NOT NULL,\n        volunteer_email varchar(255) NOT NULL,\n        volunteer_phone varchar(50) DEFAULT '',\n        volunteer_notes text,\n        user_id bigint(20) DEFAULT NULL,\n        signup_date datetime DEFAULT CURRENT_TIMESTAMP,\n        status varchar(20) DEFAULT 'confirmed',\n        person_id bigint(20) DEFAULT NULL,\n        PRIMARY KEY (id),\n        KEY idx_event_id (event_id),\n        KEY idx_user_id (user_id),\n        KEY idx_person_id (person_id),\n        KEY idx_status (status)\n    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Flush rewrite rules on plugin deactivation
 */
function lcd_events_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'lcd_events_deactivate');

/**
 * Display admin notice after plugin activation
 */
function lcd_events_admin_notice() {
    // Check transient
    if (get_transient('lcd_events_activated')) {
        ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('LCD Events plugin has been activated. If you experience 404 errors, please visit the <a href="edit.php?post_type=event">Events</a> page and then try accessing the events archive page again.', 'lcd-events'); ?></p>
        </div>
        <?php
        // Delete transient
        delete_transient('lcd_events_activated');
    }
}
add_action('admin_notices', 'lcd_events_admin_notice');

/**
 * Register Events Post Type
 */
function lcd_register_events_post_type() {
    $labels = array(
        'name'                  => _x('Events', 'Post type general name', 'lcd-events'),
        'singular_name'         => _x('Event', 'Post type singular name', 'lcd-events'),
        'menu_name'            => _x('Events', 'Admin Menu text', 'lcd-events'),
        'add_new'              => __('Add New', 'lcd-events'),
        'add_new_item'         => __('Add New Event', 'lcd-events'),
        'edit_item'            => __('Edit Event', 'lcd-events'),
        'new_item'             => __('New Event', 'lcd-events'),
        'view_item'            => __('View Event', 'lcd-events'),
        'search_items'         => __('Search Events', 'lcd-events'),
        'not_found'            => __('No events found', 'lcd-events'),
        'not_found_in_trash'   => __('No events found in Trash', 'lcd-events'),
        'all_items'            => __('All Events', 'lcd-events'),
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'query_var'          => true,
        'rewrite'            => array('slug' => 'events'),
        'capability_type'    => 'post',
        'has_archive'        => true,
        'hierarchical'       => false,
        'menu_position'      => 5,
        'menu_icon'          => 'dashicons-calendar-alt',
        'supports'           => array('title', 'editor', 'thumbnail', 'excerpt'),
        'show_in_rest'       => true,
    );

    register_post_type('event', $args);
}
add_action('init', 'lcd_register_events_post_type');

/**
 * Register Event Type Taxonomy
 */
function lcd_register_event_type_taxonomy() {
    $labels = array(
        'name'              => _x('Event Types', 'taxonomy general name', 'lcd-events'),
        'singular_name'     => _x('Event Type', 'taxonomy singular name', 'lcd-events'),
        'search_items'      => __('Search Event Types', 'lcd-events'),
        'all_items'         => __('All Event Types', 'lcd-events'),
        'parent_item'       => __('Parent Event Type', 'lcd-events'),
        'parent_item_colon' => __('Parent Event Type:', 'lcd-events'),
        'edit_item'         => __('Edit Event Type', 'lcd-events'),
        'update_item'       => __('Update Event Type', 'lcd-events'),
        'add_new_item'      => __('Add New Event Type', 'lcd-events'),
        'new_item_name'     => __('New Event Type Name', 'lcd-events'),
        'menu_name'         => __('Event Types', 'lcd-events'),
    );

    $args = array(
        'hierarchical'      => true,
        'labels'            => $labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array('slug' => 'event-type'),
        'show_in_rest'      => true,
    );

    register_taxonomy('event_type', array('event'), $args);
}
add_action('init', 'lcd_register_event_type_taxonomy');

/**
 * Add Event Meta Boxes
 */
function lcd_add_event_meta_boxes() {
    add_meta_box(
        'event_details',
        __('Event Details', 'lcd-events'),
        'lcd_event_details_callback',
        'event',
        'normal',
        'high'
    );
    
    add_meta_box(
        'volunteer_shifts',
        __('Volunteer Shifts', 'lcd-events'),
        'lcd_volunteer_shifts_callback',
        'event',
        'normal',
        'default'
    );

    add_meta_box(
        'additional_buttons',
        __('Additional Buttons', 'lcd-events'),
        'lcd_additional_buttons_callback',
        'event',
        'normal',
        'default'
    );
}
add_action('add_meta_boxes', 'lcd_add_event_meta_boxes');

/**
 * Event Details Meta Box Callback
 */
function lcd_event_details_callback($post) {
    wp_nonce_field('lcd_event_details', 'lcd_event_details_nonce');

    $event_date = get_post_meta($post->ID, '_event_date', true);
    $event_time = get_post_meta($post->ID, '_event_time', true);
    $event_end_time = get_post_meta($post->ID, '_event_end_time', true);
    $event_location = get_post_meta($post->ID, '_event_location', true);
    $event_address = get_post_meta($post->ID, '_event_address', true);
    $event_map_link = get_post_meta($post->ID, '_event_map_link', true);
    $event_registration_url = get_post_meta($post->ID, '_event_registration_url', true);
    $event_capacity = get_post_meta($post->ID, '_event_capacity', true);
    $event_cost = get_post_meta($post->ID, '_event_cost', true);
    $event_poster_id = get_post_meta($post->ID, '_event_poster', true);
    $event_button_text = get_post_meta($post->ID, '_event_button_text', true);
    $event_ticketing_notes = get_post_meta($post->ID, '_event_ticketing_notes', true);
    ?>
    <div class="lcd-event-meta">
        <p>
            <label for="event_date"><?php _e('Event Date:', 'lcd-events'); ?></label><br>
            <input type="date" id="event_date" name="event_date" value="<?php echo esc_attr($event_date); ?>" class="widefat">
        </p>
        <p>
            <label for="event_time"><?php _e('Event Time:', 'lcd-events'); ?></label><br>
            <input type="time" id="event_time" name="event_time" value="<?php echo esc_attr($event_time); ?>" class="widefat">
        </p>
        <p>
            <label for="event_end_time"><?php _e('End Time (optional):', 'lcd-events'); ?></label><br>
            <input type="time" id="event_end_time" name="event_end_time" value="<?php echo esc_attr($event_end_time); ?>" class="widefat">
        </p>
        <p>
            <label for="event_location"><?php _e('Location:', 'lcd-events'); ?></label><br>
            <input type="text" id="event_location" name="event_location" value="<?php echo esc_attr($event_location); ?>" class="widefat">
        </p>
        <p>
            <label for="event_address"><?php _e('Address:', 'lcd-events'); ?></label><br>
            <textarea id="event_address" name="event_address" rows="3" class="widefat"><?php echo esc_textarea($event_address); ?></textarea>
        </p>
        <p>
            <label for="event_map_link"><?php _e('Map Link:', 'lcd-events'); ?></label><br>
            <input type="url" id="event_map_link" name="event_map_link" value="<?php echo esc_attr($event_map_link); ?>" class="widefat" placeholder="https://maps.google.com/...">
        </p>
        <p>
            <label for="event_registration_url"><?php _e('Registration URL:', 'lcd-events'); ?></label><br>
            <input type="url" id="event_registration_url" name="event_registration_url" value="<?php echo esc_attr($event_registration_url); ?>" class="widefat">
        </p>
        <p>
            <label for="event_capacity"><?php _e('Capacity (optional):', 'lcd-events'); ?></label><br>
            <input type="number" id="event_capacity" name="event_capacity" value="<?php echo esc_attr($event_capacity); ?>" class="widefat">
        </p>
        <p>
            <label for="event_cost"><?php _e('Cost:', 'lcd-events'); ?></label><br>
            <input type="text" id="event_cost" name="event_cost" value="<?php echo esc_attr($event_cost); ?>" class="widefat" placeholder="Free, $10, $5-$15, etc.">
        </p>
        <p>
            <label for="event_button_text"><?php _e('Registration Button Text:', 'lcd-events'); ?></label><br>
            <input type="text" id="event_button_text" name="event_button_text" value="<?php echo esc_attr($event_button_text ?: 'Register Now'); ?>" class="widefat">
        </p>
        <p>
            <label for="event_ticketing_notes"><?php _e('Ticketing Notes (optional):', 'lcd-events'); ?></label><br>
            <textarea id="event_ticketing_notes" name="event_ticketing_notes" rows="2" class="widefat" placeholder="<?php _e('E.g., Early bird pricing until...', 'lcd-events'); ?>"><?php echo esc_textarea($event_ticketing_notes); ?></textarea>
        </p>
        <p>
            <label for="event_poster"><?php _e('Event Poster:', 'lcd-events'); ?></label><br>
            <input type="hidden" id="event_poster" name="event_poster" value="<?php echo esc_attr($event_poster_id); ?>">
            <button type="button" class="button event-poster-upload"><?php _e('Select/Upload Poster', 'lcd-events'); ?></button>
            <div class="event-poster-preview" style="margin-top: 10px;">
                <?php if ($event_poster_id) : ?>
                    <?php echo wp_get_attachment_image($event_poster_id, 'medium', false, array('style' => 'max-width: 100%; height: auto;')); ?>
                <?php endif; ?>
            </div>
            <?php if ($event_poster_id) : ?>
                <button type="button" class="button event-poster-remove"><?php _e('Remove Poster', 'lcd-events'); ?></button>
            <?php endif; ?>
        </p>
    </div>
    <?php
}

/**
 * Volunteer Shifts Meta Box Callback
 */
function lcd_volunteer_shifts_callback($post) {
    wp_nonce_field('lcd_volunteer_shifts', 'lcd_volunteer_shifts_nonce');

    $volunteer_shifts = get_post_meta($post->ID, '_volunteer_shifts', true);
    if (!is_array($volunteer_shifts)) {
        $volunteer_shifts = array();
    }
    
    // Get all signups for this event
    $all_signups = lcd_get_volunteer_signups($post->ID);
    $signups_by_shift = array();
    foreach ($all_signups as $signup) {
        $signups_by_shift[$signup->shift_index][] = $signup;
    }
    
    // Show export options if there are signups
    $total_signups = count($all_signups);
    ?>
    <div class="lcd-volunteer-shifts-meta">
        <?php if ($total_signups > 0) : ?>
            <div class="volunteer-export-section">
                <h4><?php _e('Export Volunteer List', 'lcd-events'); ?></h4>
                <p><?php printf(__('Total volunteers: %d', 'lcd-events'), $total_signups); ?></p>
                <div class="export-buttons">
                    <button type="button" class="button button-secondary export-volunteers-csv" data-event-id="<?php echo $post->ID; ?>">
                        <span class="dashicons dashicons-media-spreadsheet"></span>
                        <?php _e('Export to CSV', 'lcd-events'); ?>
                    </button>
                    <button type="button" class="button button-secondary export-volunteers-pdf" data-event-id="<?php echo $post->ID; ?>">
                        <span class="dashicons dashicons-pdf"></span>
                        <?php _e('Export to PDF', 'lcd-events'); ?>
                    </button>
                </div>
            </div>
        <?php endif; ?>
        <div id="volunteer-shifts-container">
            <?php if (!empty($volunteer_shifts)) : ?>
                <?php foreach ($volunteer_shifts as $index => $shift) : ?>
                    <?php 
                    $shift_signups = $signups_by_shift[$index] ?? array();
                    $signup_count = count($shift_signups);
                    $max_volunteers = intval($shift['max_volunteers'] ?? 0);
                    
                    // Format date and time for display
                    $formatted_date = !empty($shift['date']) ? date_i18n('M j, Y', strtotime($shift['date'])) : '';
                    $formatted_time = '';
                    if (!empty($shift['start_time']) && !empty($shift['end_time'])) {
                        $formatted_time = date_i18n('g:i A', strtotime($shift['date'] . ' ' . $shift['start_time'])) . ' - ' . 
                                         date_i18n('g:i A', strtotime($shift['date'] . ' ' . $shift['end_time']));
                    } elseif (!empty($shift['start_time'])) {
                        $formatted_time = date_i18n('g:i A', strtotime($shift['date'] . ' ' . $shift['start_time']));
                    }
                    ?>
                    <div class="volunteer-shift-item" data-index="<?php echo $index; ?>">
                        <div class="shift-summary" data-shift="<?php echo $index; ?>">
                            <div class="shift-summary-content">
                                <div class="shift-title-summary">
                                    <strong><?php echo esc_html($shift['title'] ?: __('Untitled Shift', 'lcd-events')); ?></strong>
                                </div>
                                <div class="shift-meta-summary">
                                    <?php if ($formatted_date) : ?>
                                        <span class="shift-date-summary"><?php echo esc_html($formatted_date); ?></span>
                                    <?php endif; ?>
                                    <?php if ($formatted_time) : ?>
                                        <span class="shift-time-summary"><?php echo esc_html($formatted_time); ?></span>
                                    <?php endif; ?>
                                    <?php if ($signup_count > 0) : ?>
                                        <span class="shift-signups-summary">
                                            <?php 
                                            if ($max_volunteers > 0) {
                                                printf(__('%d / %d volunteers', 'lcd-events'), $signup_count, $max_volunteers);
                                            } else {
                                                printf(__('%d volunteers', 'lcd-events'), $signup_count);
                                            }
                                            ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="shift-summary-actions">
                                <button type="button" class="button button-small toggle-shift-details" data-expanded="false">
                                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                                    <?php _e('Details', 'lcd-events'); ?>
                                </button>
                                <button type="button" class="button button-small remove-shift">
                                    <span class="dashicons dashicons-trash"></span>
                                    <?php _e('Remove', 'lcd-events'); ?>
                                </button>
                            </div>
                        </div>
                        
                        <div class="shift-details" style="display: none;">
                            <div class="shift-form-row">
                                <div class="shift-form-col">
                                    <label for="volunteer_shifts_<?php echo $index; ?>_title"><?php _e('Shift Title:', 'lcd-events'); ?></label>
                                    <input type="text" 
                                           id="volunteer_shifts_<?php echo $index; ?>_title" 
                                           name="volunteer_shifts[<?php echo $index; ?>][title]" 
                                           value="<?php echo esc_attr($shift['title'] ?? ''); ?>" 
                                           placeholder="<?php _e('e.g., Event Setup Crew', 'lcd-events'); ?>">
                                </div>
                                <div class="shift-form-col">
                                    <label for="volunteer_shifts_<?php echo $index; ?>_max_volunteers"><?php _e('Max Volunteers:', 'lcd-events'); ?></label>
                                    <input type="number" 
                                           id="volunteer_shifts_<?php echo $index; ?>_max_volunteers" 
                                           name="volunteer_shifts[<?php echo $index; ?>][max_volunteers]" 
                                           value="<?php echo esc_attr($shift['max_volunteers'] ?? ''); ?>" 
                                           placeholder="<?php _e('Unlimited', 'lcd-events'); ?>">
                                </div>
                            </div>
                            
                            <div class="shift-form-row">
                                <div class="shift-form-col">
                                    <label for="volunteer_shifts_<?php echo $index; ?>_description"><?php _e('Description:', 'lcd-events'); ?></label>
                                    <textarea id="volunteer_shifts_<?php echo $index; ?>_description" 
                                              name="volunteer_shifts[<?php echo $index; ?>][description]" 
                                              rows="2" 
                                              placeholder="<?php _e('What will volunteers be doing?', 'lcd-events'); ?>"><?php echo esc_textarea($shift['description'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            
                            <div class="shift-form-row shift-datetime-row">
                                <div class="shift-form-col">
                                    <label for="volunteer_shifts_<?php echo $index; ?>_date"><?php _e('Date:', 'lcd-events'); ?></label>
                                    <input type="date" 
                                           id="volunteer_shifts_<?php echo $index; ?>_date" 
                                           name="volunteer_shifts[<?php echo $index; ?>][date]" 
                                           value="<?php echo esc_attr($shift['date'] ?? ''); ?>" 
                                           class="shift-date">
                                </div>
                                <div class="shift-form-col">
                                    <label for="volunteer_shifts_<?php echo $index; ?>_start_time"><?php _e('Start Time:', 'lcd-events'); ?></label>
                                    <input type="time" 
                                           id="volunteer_shifts_<?php echo $index; ?>_start_time" 
                                           name="volunteer_shifts[<?php echo $index; ?>][start_time]" 
                                           value="<?php echo esc_attr($shift['start_time'] ?? ''); ?>" 
                                           class="shift-time">
                                </div>
                                <div class="shift-form-col">
                                    <label for="volunteer_shifts_<?php echo $index; ?>_end_time"><?php _e('End Time:', 'lcd-events'); ?></label>
                                    <input type="time" 
                                           id="volunteer_shifts_<?php echo $index; ?>_end_time" 
                                           name="volunteer_shifts[<?php echo $index; ?>][end_time]" 
                                           value="<?php echo esc_attr($shift['end_time'] ?? ''); ?>" 
                                           class="shift-time">
                                </div>
                            </div>
                            
                            <?php if (!empty($shift_signups)) : ?>
                                <div class="shift-signups">
                                    <h5>
                                        <span class="dashicons dashicons-groups"></span>
                                        <?php _e('Registered Volunteers:', 'lcd-events'); ?>
                                        <span class="signups-count">(<?php echo count($shift_signups); ?>)</span>
                                    </h5>
                                    <div class="signups-list">
                                        <?php foreach ($shift_signups as $signup) : ?>
                                            <?php 
                                            $person_name = esc_html($signup->volunteer_name);
                                            $person_email = esc_html($signup->volunteer_email);
                                            $person_phone = esc_html($signup->volunteer_phone ?? '');
                                            $person_notes = esc_html($signup->volunteer_notes ?? '');
                                            $person_id_attr = isset($signup->person_id) && $signup->person_id ? 'data-person-id="' . esc_attr($signup->person_id) . '"' : '';
                                            $signup_id_attr = 'data-signup-id="' . esc_attr($signup->id) . '"';
                                            
                                            // If linked to an lcd_person, fetch their latest details
                                            if (isset($signup->person_id) && $signup->person_id) {
                                                $person_post = get_post($signup->person_id);
                                                if ($person_post) {
                                                    $person_name = esc_html($person_post->post_title);
                                                    $person_email = esc_html(get_post_meta($signup->person_id, '_lcd_person_email', true));
                                                    $person_phone = esc_html(get_post_meta($signup->person_id, '_lcd_person_phone', true));
                                                    // Notes are from the signup itself, not the person profile generally.
                                                }
                                            }
                                            ?>
                                            <div class="signup-item-compact status-<?php echo esc_attr($signup->status); ?>" <?php echo $person_id_attr; ?> <?php echo $signup_id_attr; ?>>
                                                <div class="signup-header">
                                                    <div class="signup-primary">
                                                        <strong><?php echo $person_name; ?></strong>
                                                        <span class="signup-contact">
                                                            <?php echo $person_email; ?>
                                                            <?php if (!empty($person_phone)) : ?>
                                                                • <?php echo $person_phone; ?>
                                                            <?php endif; ?>
                                                        </span>
                                                    </div>
                                                    <div class="signup-date"><?php echo date_i18n('M j, Y \a\t g:i A', strtotime($signup->signup_date)); ?></div>
                                                </div>
                                                <?php /* Show notes for any signup that has them - both guest signups and assignment notes */
                                                if (!empty($signup->volunteer_notes)) : ?>
                                                    <div class="signup-notes">
                                                        <div class="signup-notes-display">
                                                            <span class="notes-text"><?php echo esc_html($signup->volunteer_notes); ?></span>
                                                            <button type="button" class="button-link edit-notes" title="<?php esc_attr_e('Edit notes', 'lcd-events'); ?>">
                                                                <span class="dashicons dashicons-edit"></span>
                                                            </button>
                                                        </div>
                                                        <div class="signup-notes-edit" style="display: none;">
                                                            <textarea class="notes-edit-field" rows="2"><?php echo esc_textarea($signup->volunteer_notes); ?></textarea>
                                                            <div class="notes-edit-actions">
                                                                <button type="button" class="button button-small save-notes"><?php _e('Save', 'lcd-events'); ?></button>
                                                                <button type="button" class="button button-small cancel-notes"><?php _e('Cancel', 'lcd-events'); ?></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php else : ?>
                                                    <div class="signup-notes">
                                                        <div class="signup-notes-display">
                                                            <span class="notes-text no-notes"><?php _e('No notes', 'lcd-events'); ?></span>
                                                            <button type="button" class="button-link edit-notes" title="<?php esc_attr_e('Add notes', 'lcd-events'); ?>">
                                                                <span class="dashicons dashicons-edit"></span>
                                                            </button>
                                                        </div>
                                                        <div class="signup-notes-edit" style="display: none;">
                                                            <textarea class="notes-edit-field" rows="2" placeholder="<?php esc_attr_e('Add notes for this assignment...', 'lcd-events'); ?>"></textarea>
                                                            <div class="notes-edit-actions">
                                                                <button type="button" class="button button-small save-notes"><?php _e('Save', 'lcd-events'); ?></button>
                                                                <button type="button" class="button button-small cancel-notes"><?php _e('Cancel', 'lcd-events'); ?></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="signup-status-section">
                                                    <div class="signup-status-display">
                                                        <span class="signup-status-label"><?php _e('Status:', 'lcd-events'); ?></span>
                                                        <span class="signup-status-value <?php echo esc_attr($signup->status); ?>">
                                                            <?php echo ucfirst($signup->status); ?>
                                                        </span>
                                                    </div>
                                                    <div class="signup-actions">
                                                        <button type="button" class="button button-small toggle-confirmed <?php echo esc_attr($signup->status); ?>" 
                                                                data-confirmed="<?php echo esc_attr($signup->status === 'confirmed' ? '1' : '0'); ?>"
                                                                title="<?php echo esc_attr($signup->status === 'confirmed' ? __('Mark as unconfirmed', 'lcd-events') : __('Mark as confirmed', 'lcd-events')); ?>">
                                                            <?php echo $signup->status === 'confirmed' ? __('Unconfirm', 'lcd-events') : __('Confirm', 'lcd-events'); ?>
                                                        </button>
                                                        <button type="button" class="button-link button-link-delete unassign-volunteer" title="<?php esc_attr_e('Remove from shift', 'lcd-events'); ?>">
                                                            <span class="dashicons dashicons-no-alt"></span>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="assign-volunteer-section">
                                <h6 class="assign-volunteer-title"><?php _e('Assign Person to Shift:', 'lcd-events'); ?></h6>
                                <div class="assign-volunteer-controls">
                                    <select class="lcd-person-search-select" data-shift-index="<?php echo $index; ?>" style="width: 100%; margin-bottom: 5px;">
                                        <option></option> <?php // Required for placeholder to work with allowClear ?>
                                    </select>
                                    <textarea class="shift-assignment-notes" 
                                              placeholder="<?php _e('Optional notes for this assignment (e.g., specific tasks, time constraints, etc.)', 'lcd-events'); ?>" 
                                              rows="2" 
                                              style="width: 100%; margin-top: 5px; resize: vertical;"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="add-shift-container">
            <button type="button" class="button button-primary" id="add-volunteer-shift">
                <span class="dashicons dashicons-plus-alt"></span>
                <?php _e('Add Volunteer Shift', 'lcd-events'); ?>
            </button>
        </div>
        
        <div id="volunteer-shift-template" style="display: none;">
            <div class="volunteer-shift-item" data-index="__INDEX__">
                <div class="shift-summary" data-shift="__INDEX__">
                    <div class="shift-summary-content">
                        <div class="shift-title-summary">
                            <strong><?php _e('New Shift', 'lcd-events'); ?></strong>
                        </div>
                        <div class="shift-meta-summary">
                            <span class="shift-placeholder"><?php _e('Click Details to configure', 'lcd-events'); ?></span>
                        </div>
                    </div>
                    <div class="shift-summary-actions">
                        <button type="button" class="button button-small toggle-shift-details" data-expanded="true">
                            <span class="dashicons dashicons-arrow-up-alt2"></span>
                            <?php _e('Details', 'lcd-events'); ?>
                        </button>
                        <button type="button" class="button button-small remove-shift">
                            <span class="dashicons dashicons-trash"></span>
                            <?php _e('Remove', 'lcd-events'); ?>
                        </button>
                    </div>
                </div>
                
                <div class="shift-details">
                    <div class="shift-form-row">
                        <div class="shift-form-col">
                            <label for="volunteer_shifts___INDEX___title"><?php _e('Shift Title:', 'lcd-events'); ?></label>
                            <input type="text" 
                                   id="volunteer_shifts___INDEX___title" 
                                   name="volunteer_shifts[__INDEX__][title]" 
                                   value="" 
                                   placeholder="<?php _e('e.g., Event Setup Crew', 'lcd-events'); ?>">
                        </div>
                        <div class="shift-form-col">
                            <label for="volunteer_shifts___INDEX___max_volunteers"><?php _e('Max Volunteers:', 'lcd-events'); ?></label>
                            <input type="number" 
                                   id="volunteer_shifts___INDEX___max_volunteers" 
                                   name="volunteer_shifts[__INDEX__][max_volunteers]" 
                                   value="" 
                                   placeholder="<?php _e('Unlimited', 'lcd-events'); ?>">
                        </div>
                    </div>
                    
                    <div class="shift-form-row">
                        <div class="shift-form-col">
                            <label for="volunteer_shifts___INDEX___description"><?php _e('Description:', 'lcd-events'); ?></label>
                            <textarea id="volunteer_shifts___INDEX___description" 
                                      name="volunteer_shifts[__INDEX__][description]" 
                                      rows="2" 
                                      placeholder="<?php _e('What will volunteers be doing?', 'lcd-events'); ?>"></textarea>
                        </div>
                    </div>
                    
                    <div class="shift-form-row shift-datetime-row">
                        <div class="shift-form-col">
                            <label for="volunteer_shifts___INDEX___date"><?php _e('Date:', 'lcd-events'); ?></label>
                            <input type="date" 
                                   id="volunteer_shifts___INDEX___date" 
                                   name="volunteer_shifts[__INDEX__][date]" 
                                   value="" 
                                   class="shift-date">
                        </div>
                        <div class="shift-form-col">
                            <label for="volunteer_shifts___INDEX___start_time"><?php _e('Start Time:', 'lcd-events'); ?></label>
                            <input type="time" 
                                   id="volunteer_shifts___INDEX___start_time" 
                                   name="volunteer_shifts[__INDEX__][start_time]" 
                                   value="" 
                                   class="shift-time">
                        </div>
                        <div class="shift-form-col">
                            <label for="volunteer_shifts___INDEX___end_time"><?php _e('End Time:', 'lcd-events'); ?></label>
                            <input type="time" 
                                   id="volunteer_shifts___INDEX___end_time" 
                                   name="volunteer_shifts[__INDEX__][end_time]" 
                                   value="" 
                                   class="shift-time">
                        </div>
                    </div>

                    <div class="assign-volunteer-section">
                        <h6 class="assign-volunteer-title"><?php _e('Assign Person to Shift:', 'lcd-events'); ?></h6>
                        <div class="assign-volunteer-controls">
                            <select class="lcd-person-search-select" data-shift-index="__INDEX__" style="width: 100%; margin-bottom: 5px;">
                                <option></option> <?php // Required for placeholder to work with allowClear ?>
                            </select>
                            <textarea class="shift-assignment-notes" 
                                      placeholder="<?php _e('Optional notes for this assignment (e.g., specific tasks, time constraints, etc.)', 'lcd-events'); ?>" 
                                      rows="2" 
                                      style="width: 100%; margin-top: 5px; resize: vertical;"></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Additional Buttons Meta Box Callback
 */
function lcd_additional_buttons_callback($post) {
    wp_nonce_field('lcd_additional_buttons', 'lcd_additional_buttons_nonce');
    
    $additional_buttons = get_post_meta($post->ID, '_additional_buttons', true);
    if (!is_array($additional_buttons)) {
        $additional_buttons = array();
    }
    ?>
    <div class="lcd-additional-buttons-meta">
        <div id="additional-buttons-container">
            <?php 
            if (!empty($additional_buttons)) {
                foreach ($additional_buttons as $index => $button) {
                    ?>
                    <div class="additional-button-item">
                        <h4><?php _e('Button', 'lcd-events'); ?> <span class="button-number"><?php echo $index + 1; ?></span></h4>
                        <p>
                            <label for="additional_button_text_<?php echo $index; ?>"><?php _e('Button Text:', 'lcd-events'); ?></label><br>
                            <input type="text" id="additional_button_text_<?php echo $index; ?>" 
                                   name="additional_buttons[<?php echo $index; ?>][text]" 
                                   value="<?php echo esc_attr($button['text']); ?>" class="widefat">
                        </p>
                        <p>
                            <label for="additional_button_url_<?php echo $index; ?>"><?php _e('Button URL:', 'lcd-events'); ?></label><br>
                            <input type="url" id="additional_button_url_<?php echo $index; ?>" 
                                   name="additional_buttons[<?php echo $index; ?>][url]" 
                                   value="<?php echo esc_url($button['url']); ?>" class="widefat">
                        </p>
                        <button type="button" class="button remove-additional-button"><?php _e('Remove Button', 'lcd-events'); ?></button>
                    </div>
                    <?php
                }
            }
            ?>
        </div>
        <p>
            <button type="button" id="add-additional-button" class="button button-secondary">
                <?php _e('Add Button', 'lcd-events'); ?>
            </button>
        </p>
    </div>

    <script type="text/html" id="additional-button-template">
        <div class="additional-button-item">
            <h4><?php _e('Button', 'lcd-events'); ?> <span class="button-number">__INDEX__</span></h4>
            <p>
                <label for="additional_button_text___INDEX__"><?php _e('Button Text:', 'lcd-events'); ?></label><br>
                <input type="text" id="additional_button_text___INDEX__" 
                       name="additional_buttons[__INDEX__][text]" 
                       value="" class="widefat">
            </p>
            <p>
                <label for="additional_button_url___INDEX__"><?php _e('Button URL:', 'lcd-events'); ?></label><br>
                <input type="url" id="additional_button_url___INDEX__" 
                       name="additional_buttons[__INDEX__][url]" 
                       value="" class="widefat">
            </p>
            <button type="button" class="button remove-additional-button"><?php _e('Remove Button', 'lcd-events'); ?></button>
        </div>
    </script>
    <?php
}

/**
 * Save Event Meta Box Data
 */
function lcd_save_event_meta($post_id) {
    // Check if this is an autosave or if user can't edit
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Save event details
    if (isset($_POST['lcd_event_details_nonce']) && wp_verify_nonce($_POST['lcd_event_details_nonce'], 'lcd_event_details')) {
    $fields = array(
        'event_date',
        'event_time',
        'event_end_time',
        'event_location',
        'event_address',
        'event_map_link',
        'event_registration_url',
        'event_capacity',
        'event_cost',
        'event_poster',
        'event_button_text',
        'event_ticketing_notes'
    );

    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            $value = sanitize_text_field($_POST[$field]);
            update_post_meta($post_id, '_' . $field, $value);
        }
        }
    }

    // Save volunteer shifts
    if (isset($_POST['lcd_volunteer_shifts_nonce']) && wp_verify_nonce($_POST['lcd_volunteer_shifts_nonce'], 'lcd_volunteer_shifts')) {
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
        
        update_post_meta($post_id, '_volunteer_shifts', $volunteer_shifts);
    }

    // Save additional buttons
    if (isset($_POST['lcd_additional_buttons_nonce']) && wp_verify_nonce($_POST['lcd_additional_buttons_nonce'], 'lcd_additional_buttons')) {
        $additional_buttons = array();
        if (isset($_POST['additional_buttons']) && is_array($_POST['additional_buttons'])) {
            foreach ($_POST['additional_buttons'] as $button) {
                if (!empty($button['text']) && !empty($button['url'])) {
                    $additional_buttons[] = array(
                        'text' => sanitize_text_field($button['text']),
                        'url' => esc_url_raw($button['url'])
                    );
                }
            }
        }
        update_post_meta($post_id, '_additional_buttons', $additional_buttons);
    }
}
add_action('save_post_event', 'lcd_save_event_meta');

/**
 * Get volunteer signups for an event
 */
function lcd_get_volunteer_signups($event_id) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'lcd_volunteer_signups';
    
    $signups = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name WHERE event_id = %d ORDER BY shift_index ASC, signup_date ASC",
        $event_id
    ));
    
    return $signups;
}

/**
 * Get signup count for a specific shift
 */
function lcd_get_shift_signup_count($event_id, $shift_index) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'lcd_volunteer_signups';
    
    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE event_id = %d AND shift_index = %d AND status = 'confirmed'",
        $event_id,
        $shift_index
    ));
    
    return intval($count);
}

/**
 * Get formatted volunteer shifts for an event (for frontend use)
 */
function lcd_get_event_volunteer_shifts($event_id) {
    $volunteer_shifts = get_post_meta($event_id, '_volunteer_shifts', true);
    
    if (empty($volunteer_shifts) || !is_array($volunteer_shifts)) {
        return array();
    }
    
    $formatted_shifts = array();
    
    foreach ($volunteer_shifts as $index => $shift) {
        $signup_count = lcd_get_shift_signup_count($event_id, $index);
        $max_volunteers = intval($shift['max_volunteers'] ?? 0);
        $is_full = $max_volunteers > 0 && $signup_count >= $max_volunteers;
        
        $formatted_shifts[] = array(
            'index' => $index,
            'title' => $shift['title'],
            'description' => $shift['description'] ?? '',
            'date' => $shift['date'] ?? '',
            'start_time' => $shift['start_time'] ?? '',
            'end_time' => $shift['end_time'] ?? '',
            'max_volunteers' => $max_volunteers,
            'signup_count' => $signup_count,
            'spots_remaining' => $max_volunteers > 0 ? max(0, $max_volunteers - $signup_count) : -1, // -1 means unlimited
            'is_full' => $is_full,
            'formatted_date' => !empty($shift['date']) ? date_i18n(get_option('date_format'), strtotime($shift['date'])) : '',
            'formatted_start_time' => !empty($shift['start_time']) ? date_i18n(get_option('time_format'), strtotime($shift['date'] . ' ' . $shift['start_time'])) : '',
            'formatted_end_time' => !empty($shift['end_time']) ? date_i18n(get_option('time_format'), strtotime($shift['date'] . ' ' . $shift['end_time'])) : ''
        );
    }
    
    return $formatted_shifts;
}

/**
 * Add custom columns to events list
 */
function lcd_event_columns($columns) {
    $new_columns = array();
    $new_columns['cb'] = $columns['cb'];
    $new_columns['title'] = $columns['title'];
    $new_columns['event_date'] = __('Event Date', 'lcd-events');
    $new_columns['event_location'] = __('Location', 'lcd-events');
    $new_columns['volunteer_shifts'] = __('Volunteer Shifts', 'lcd-events');
    $new_columns['event_cost'] = __('Cost', 'lcd-events');
    $new_columns['date'] = $columns['date'];
    return $new_columns;
}
add_filter('manage_event_posts_columns', 'lcd_event_columns');

/**
 * Add content to custom columns
 */
function lcd_event_column_content($column, $post_id) {
    switch ($column) {
        case 'event_date':
            $date = get_post_meta($post_id, '_event_date', true);
            echo $date ? date_i18n(get_option('date_format'), strtotime($date)) : '—';
            break;
        case 'event_location':
            echo get_post_meta($post_id, '_event_location', true) ?: '—';
            break;
        case 'volunteer_shifts':
            $volunteer_shifts = get_post_meta($post_id, '_volunteer_shifts', true);
            if (!empty($volunteer_shifts) && is_array($volunteer_shifts)) {
                $shift_count = count($volunteer_shifts);
                $total_signups = 0;
                for ($i = 0; $i < $shift_count; $i++) {
                    $total_signups += lcd_get_shift_signup_count($post_id, $i);
                }
                printf(__('%d shifts (%d signups)', 'lcd-events'), $shift_count, $total_signups);
            } else {
                echo '—';
            }
            break;
        case 'event_cost':
            echo get_post_meta($post_id, '_event_cost', true) ?: '—';
            break;
    }
}
add_action('manage_event_posts_custom_column', 'lcd_event_column_content', 10, 2);

/**
 * Make event date column sortable
 */
function lcd_event_sortable_columns($columns) {
    $columns['event_date'] = 'event_date';
    return $columns;
}
add_filter('manage_edit-event_sortable_columns', 'lcd_event_sortable_columns');

/**
 * Sort events by date
 */
function lcd_event_sort_by_date($query) {
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }

    if ($query->get('post_type') === 'event' && $query->get('orderby') === 'event_date') {
        $query->set('meta_key', '_event_date');
        $query->set('orderby', 'meta_value');
        $query->set('order', 'ASC');
    }
}
add_action('pre_get_posts', 'lcd_event_sort_by_date');

/**
 * Add a shortcode to display upcoming events
 * 
 * Usage: [lcd_events limit="3" show_past="false"]
 */
function lcd_events_shortcode($atts) {
    $atts = shortcode_atts(
        array(
            'limit' => 3,
            'show_past' => 'false',
            'show_title' => 'true',
            'title' => __('Upcoming Events', 'lcd-events'),
        ),
        $atts,
        'lcd_events'
    );
    
    // Convert string to boolean
    $show_past = filter_var($atts['show_past'], FILTER_VALIDATE_BOOLEAN);
    $show_title = filter_var($atts['show_title'], FILTER_VALIDATE_BOOLEAN);
    
    // Get current date
    $today = date('Y-m-d');
    
    // Setup query args
    $args = array(
        'post_type' => 'event',
        'posts_per_page' => intval($atts['limit']),
        'meta_key' => '_event_date',
        'orderby' => 'meta_value',
        'order' => 'ASC',
        'meta_query' => array(
            array(
                'key' => '_event_date',
                'value' => $today,
                'compare' => $show_past ? '<=' : '>=',
                'type' => 'DATE'
            )
        )
    );
    
    if ($show_past) {
        $args['order'] = 'DESC'; // Show most recent past events first
    }
    
    $events_query = new WP_Query($args);
    
    ob_start();
    
    if ($events_query->have_posts()) :
        ?>
        <div class="lcd-events-shortcode">
            <?php if ($show_title && !empty($atts['title'])) : ?>
                <h2 class="lcd-events-title"><?php echo esc_html($atts['title']); ?></h2>
            <?php endif; ?>
            
            <div class="lcd-events-list">
                <?php while ($events_query->have_posts()) : $events_query->the_post();
                    // Get event meta
                    $event_date = get_post_meta(get_the_ID(), '_event_date', true);
                    $event_time = get_post_meta(get_the_ID(), '_event_time', true);
                    $event_location = get_post_meta(get_the_ID(), '_event_location', true);
                    
                    // Format date
                    $formatted_date = '';
                    if ($event_date) {
                        $formatted_date = date_i18n(get_option('date_format'), strtotime($event_date));
                    }
                    
                    // Format time
                    $formatted_time = '';
                    if ($event_time) {
                        $formatted_time = date_i18n(get_option('time_format'), strtotime($event_date . ' ' . $event_time));
                    }
                    ?>
                    
                    <article id="post-<?php the_ID(); ?>" <?php post_class('event-card'); ?>>
                        <?php if (has_post_thumbnail()) : ?>
                            <div class="event-thumbnail">
                                <a href="<?php the_permalink(); ?>">
                                    <?php the_post_thumbnail('medium'); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <div class="event-card-content">
                            <h3 class="event-title">
                                <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                            </h3>
                            
                            <?php if ($formatted_date || $formatted_time) : ?>
                                <div class="event-date-time">
                                    <?php if ($formatted_date) : ?>
                                        <span class="event-date"><?php echo esc_html($formatted_date); ?></span>
                                    <?php endif; ?>
                                    
                                    <?php if ($formatted_time) : ?>
                                        <span class="event-time"><?php echo esc_html($formatted_time); ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($event_location) : ?>
                                <div class="event-location">
                                    <span class="event-location-label"><?php _e('Location:', 'lcd-events'); ?></span>
                                    <span class="event-location-value"><?php echo esc_html($event_location); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php 
                            // Display event type
                            $event_types = get_the_terms(get_the_ID(), 'event_type');
                            if (!empty($event_types) && !is_wp_error($event_types)) : ?>
                                <div class="event-type">
                                    <span class="event-type-label"><?php _e('Event Type:', 'lcd-events'); ?></span>
                                    <span class="event-type-value">
                                        <?php 
                                        $type_names = array();
                                        foreach ($event_types as $type) {
                                            $type_names[] = '<a href="' . esc_url(get_term_link($type)) . '">' . esc_html($type->name) . '</a>';
                                        }
                                        echo implode(', ', $type_names);
                                        ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="event-excerpt">
                                <?php the_excerpt(); ?>
                            </div>
                            
                            <a href="<?php the_permalink(); ?>" class="event-more-link">
                                <?php _e('View Event Details', 'lcd-events'); ?>
                            </a>
                        </div>
                    </article>
                <?php endwhile; ?>
            </div>
            
            <div class="lcd-events-footer">
                <a href="<?php echo esc_url(get_post_type_archive_link('event')); ?>" class="all-events-link">
                    <?php _e('View All Events', 'lcd-events'); ?>
                </a>
            </div>
        </div>
        <?php
    else :
        ?>
        <div class="lcd-events-shortcode">
            <?php if ($show_title && !empty($atts['title'])) : ?>
                <h2 class="lcd-events-title"><?php echo esc_html($atts['title']); ?></h2>
            <?php endif; ?>
            
            <p class="no-events-message">
                <?php 
                if ($show_past) {
                    _e('There are no past events to display.', 'lcd-events');
                } else {
                    _e('There are no upcoming events at this time. Please check back later.', 'lcd-events');
                }
                ?>
            </p>
        </div>
        <?php
    endif;
    
    wp_reset_postdata();
    
    $output = ob_get_clean();
    return $output;
}
add_shortcode('lcd_events', 'lcd_events_shortcode');

/**
 * Load custom templates for events
 */
function lcd_event_template_include($template) {
    if (is_singular('event')) {
        $custom_template = LCD_EVENTS_PLUGIN_DIR . 'templates/single-event.php';
        if (file_exists($custom_template)) {
            return $custom_template;
        }
    }

    if (is_post_type_archive('event')) {
        $custom_template = LCD_EVENTS_PLUGIN_DIR . 'templates/archive-event.php';
        if (file_exists($custom_template)) {
            return $custom_template;
        }
    }

    // Check for volunteer opportunities page (slug-based only, template selection handled by page_template filter)
    if (is_page()) {
        global $post;
        if ($post && $post->post_name === 'volunteer-opportunities') {
            $custom_template = LCD_EVENTS_PLUGIN_DIR . 'templates/page-volunteer-opportunities.php';
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }
    }

    return $template;
}
add_filter('template_include', 'lcd_event_template_include');

/**
 * Enqueue plugin styles
 */
function lcd_events_enqueue_styles() {
    // Enqueue dashicons (for event icons)
    wp_enqueue_style('dashicons');
    
    // Enqueue variables first
    wp_enqueue_style(
        'lcd-events-variables',
        LCD_EVENTS_PLUGIN_URL . 'css/variables.css',
        array(),
        LCD_EVENTS_VERSION
    );
    
    // Then enqueue main styles
    wp_enqueue_style(
        'lcd-events-styles',
        LCD_EVENTS_PLUGIN_URL . 'css/lcd-events.css',
        array('lcd-events-variables', 'dashicons'),
        LCD_EVENTS_VERSION
    );
}
add_action('wp_enqueue_scripts', 'lcd_events_enqueue_styles');

/**
 * Enqueue admin scripts
 */
function lcd_events_admin_scripts($hook) {
    wp_enqueue_media(); // For the media uploader in event details

    // Only load specific assets on event edit pages
    if (('post.php' === $hook || 'post-new.php' === $hook) && isset($_GET['post_type']) && $_GET['post_type'] === 'event' ||
        ('post.php' === $hook && isset($_GET['post']) && get_post_type($_GET['post']) === 'event')) {
        
        wp_enqueue_style(
            'lcd-events-admin-styles',
            LCD_EVENTS_PLUGIN_URL . 'css/lcd-events.css',
            array(), 
            LCD_EVENTS_VERSION
        );

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

        // Enqueue the admin events JavaScript
        wp_enqueue_script(
            'lcd-events-admin',
            LCD_EVENTS_PLUGIN_URL . 'js/admin-events.js',
            array('jquery', 'select2'),
            LCD_EVENTS_VERSION,
            true
        );

        // Get current post ID for admin
        $post_id = 0;
        if (isset($_GET['post'])) {
            $post_id = intval($_GET['post']);
        }

        // Get volunteer shifts count for initial value
        $volunteer_shifts = get_post_meta($post_id, '_volunteer_shifts', true);
        $initial_shift_count = is_array($volunteer_shifts) ? count($volunteer_shifts) : 0;
        
        wp_localize_script('lcd-events-admin', 'lcdEventsAdmin', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'event_id' => $post_id,
            'initial_shift_count' => $initial_shift_count,
            'search_people_nonce' => wp_create_nonce('lcd_event_shifts_people_search'),
            'assign_person_nonce' => wp_create_nonce('lcd_event_assign_person_to_shift'),
            'unassign_person_nonce' => wp_create_nonce('lcd_event_unassign_person_from_shift'),
            'edit_notes_nonce' => wp_create_nonce('lcd_event_edit_volunteer_notes'),
            'toggle_confirmed_nonce' => wp_create_nonce('lcd_event_toggle_volunteer_confirmed'),
            'export_csv_nonce' => wp_create_nonce('lcd_export_volunteers_csv'),
            'export_pdf_nonce' => wp_create_nonce('lcd_export_volunteers_pdf'),
            'text' => [
                'select_upload_poster' => __('Select or Upload Event Poster', 'lcd-events'),
                'use_image' => __('Use this image', 'lcd-events'),
                'confirm_remove_shift' => __('This shift has registered volunteers. Removing the shift will NOT remove their signups from the database but they will no longer be visible here. Are you sure you want to remove this shift definition?', 'lcd-events'),
                'untitled_shift' => __('Untitled Shift', 'lcd-events'),
                'enter_details' => __('Enter details below', 'lcd-events'),
                'shift' => __('Shift', 'lcd-events'),
                'registered_volunteers' => __('Registered Volunteers:', 'lcd-events'),
                'search_placeholder' => __('Search by name or email...', 'lcd-events'),
                'input_too_short' => __('Please enter 2 or more characters', 'lcd-events'),
                'confirm_unassign' => __('Are you sure you want to remove this volunteer from this shift?', 'lcd-events'),
                'error_assigning' => __('Could not assign volunteer. Please try again.', 'lcd-events'),
                'error_unassigning' => __('Could not remove volunteer. Please try again.', 'lcd-events'),
                'error_editing_notes' => __('Could not save notes. Please try again.', 'lcd-events'),
                'error_toggle_confirmed' => __('Could not update confirmation status. Please try again.', 'lcd-events'),
                'searching' => __('Searching...', 'lcd-events'),
                'no_results' => __('No people found matching your search.', 'lcd-events'),
                'error_loading' => __('Could not load search results.', 'lcd-events'),
                'edit_notes' => __('Edit notes', 'lcd-events'),
                'add_notes' => __('Add notes', 'lcd-events'),
                'no_notes' => __('No notes', 'lcd-events'),
                'export_error' => __('Error exporting volunteer list. Please try again.', 'lcd-events'),
            ]
        ]);

        // Keep backward compatibility for any remaining references to lcdShiftsData
        wp_localize_script('lcd-events-admin', 'lcdShiftsData', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'search_people_nonce' => wp_create_nonce('lcd_event_shifts_people_search'),
            'assign_person_nonce' => wp_create_nonce('lcd_event_assign_person_to_shift'),
            'unassign_person_nonce' => wp_create_nonce('lcd_event_unassign_person_from_shift'),
            'edit_notes_nonce' => wp_create_nonce('lcd_event_edit_volunteer_notes'),
            'toggle_confirmed_nonce' => wp_create_nonce('lcd_event_toggle_volunteer_confirmed'),
            'text' => [
                'confirm_unassign' => __('Are you sure you want to remove this volunteer from this shift?', 'lcd-events'),
                'error_assigning' => __('Could not assign volunteer. Please try again.', 'lcd-events'),
                'error_unassigning' => __('Could not remove volunteer. Please try again.', 'lcd-events'),
                'error_editing_notes' => __('Could not save notes. Please try again.', 'lcd-events'),
                'searching' => __('Searching...', 'lcd-events'),
                'no_results' => __('No people found matching your search.', 'lcd-events'),
                'error_loading' => __('Could not load search results.', 'lcd-events'),
                'edit_notes' => __('Edit notes', 'lcd-events'),
                'add_notes' => __('Add notes', 'lcd-events'),
                'no_notes' => __('No notes', 'lcd-events'),
            ]
        ]);
    }
}
add_action('admin_enqueue_scripts', 'lcd_events_admin_scripts');

// AJAX handler for searching people (lcd_person CPT)
add_action('wp_ajax_lcd_search_people_for_shifts', 'lcd_ajax_search_people_for_shifts');

function lcd_ajax_search_people_for_shifts() {
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

// AJAX handler for assigning a person to a shift
add_action('wp_ajax_lcd_assign_person_to_shift', 'lcd_ajax_assign_person_to_shift');
function lcd_ajax_assign_person_to_shift() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'lcd_event_assign_person_to_shift')) {
        wp_send_json_error(['message' => __('Security check failed.', 'lcd-events')], 403);
        return;
    }

    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => __('Permission denied.', 'lcd-events')], 403);
        return;
    }

    $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
    $shift_index = isset($_POST['shift_index']) ? intval($_POST['shift_index']) : -1;
    $shift_title = isset($_POST['shift_title']) ? sanitize_text_field($_POST['shift_title']) : __('Untitled Shift', 'lcd-events');
    $person_id = isset($_POST['person_id']) ? intval($_POST['person_id']) : 0;
    $assignment_notes = isset($_POST['assignment_notes']) ? sanitize_textarea_field($_POST['assignment_notes']) : '';

    if (!$event_id || $shift_index < 0 || !$person_id) {
        wp_send_json_error(['message' => __('Missing required data.', 'lcd-events')], 400);
        return;
    }

    $person_post = get_post($person_id);
    if (!$person_post || $person_post->post_type !== 'lcd_person') {
        wp_send_json_error(['message' => __('Invalid person selected.', 'lcd-events')], 400);
        return;
    }

    $volunteer_name = $person_post->post_title;
    $volunteer_email = get_post_meta($person_id, '_lcd_person_email', true);
    $volunteer_phone = get_post_meta($person_id, '_lcd_person_phone', true);
    $user_id = get_post_meta($person_id, '_lcd_person_user_id', true); // Check if person is linked to a WP user

    if (empty($volunteer_email)) {
        // Provide a more helpful error message that includes the person's name
        wp_send_json_error([
            'message' => sprintf(
                __('%s does not have an email address in their profile. Please add an email address to their profile before assigning them to a volunteer shift.', 'lcd-events'),
                esc_html($volunteer_name)
            )
        ], 400);
        return;
    }
    
    // Check if already assigned to this specific shift to prevent duplicates
    global $wpdb;
    $table_name = $wpdb->prefix . 'lcd_volunteer_signups';
    $existing_signup = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM $table_name WHERE event_id = %d AND shift_index = %d AND person_id = %d",
        $event_id, $shift_index, $person_id
    ));

    if ($existing_signup) {
        wp_send_json_error(['message' => __('This person is already assigned to this shift.', 'lcd-events')], 409); // 409 Conflict
        return;
    }

    $signup_data = [
        'event_id' => $event_id,
        'shift_index' => $shift_index,
        'shift_title' => $shift_title,
        'volunteer_name' => $volunteer_name, // This will be from lcd_person title
        'volunteer_email' => $volunteer_email,
        'volunteer_phone' => $volunteer_phone,
        'volunteer_notes' => $assignment_notes, // Save the assignment notes
        'user_id' => $user_id ? intval($user_id) : null,
        'signup_date' => current_time('mysql'),
        'status' => 'confirmed',
        'person_id' => $person_id, // Link to the lcd_person post
    ];

    $format = ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d'];
    
    $inserted = $wpdb->insert($table_name, $signup_data, $format);

    if ($inserted) {
        $signup_id = $wpdb->insert_id;
        // Prepare HTML for the new signup item to send back to JS
        ob_start();
        ?>
        <div class="signup-item-compact status-confirmed" data-person-id="<?php echo esc_attr($person_id); ?>" data-signup-id="<?php echo esc_attr($signup_id); ?>">
            <div class="signup-header">
                <div class="signup-primary">
                    <strong><?php echo esc_html($volunteer_name); ?></strong>
                    <span class="signup-contact">
                        <?php echo esc_html($volunteer_email); ?>
                        <?php if (!empty($volunteer_phone)) : ?>
                            • <?php echo esc_html($volunteer_phone); ?>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="signup-date"><?php echo date_i18n('M j, Y \a\t g:i A', strtotime(current_time('mysql'))); ?></div>
            </div>
            <?php if (!empty($assignment_notes)) : ?>
                <div class="signup-notes">
                    <div class="signup-notes-display">
                        <span class="notes-text"><?php echo esc_html($assignment_notes); ?></span>
                        <button type="button" class="button-link edit-notes" title="<?php esc_attr_e('Edit notes', 'lcd-events'); ?>">
                            <span class="dashicons dashicons-edit"></span>
                        </button>
                    </div>
                    <div class="signup-notes-edit" style="display: none;">
                        <textarea class="notes-edit-field" rows="2"><?php echo esc_textarea($assignment_notes); ?></textarea>
                        <div class="notes-edit-actions">
                            <button type="button" class="button button-small save-notes"><?php _e('Save', 'lcd-events'); ?></button>
                            <button type="button" class="button button-small cancel-notes"><?php _e('Cancel', 'lcd-events'); ?></button>
                        </div>
                    </div>
                </div>
            <?php else : ?>
                <div class="signup-notes">
                    <div class="signup-notes-display">
                        <span class="notes-text no-notes"><?php _e('No notes', 'lcd-events'); ?></span>
                        <button type="button" class="button-link edit-notes" title="<?php esc_attr_e('Add notes', 'lcd-events'); ?>">
                            <span class="dashicons dashicons-edit"></span>
                        </button>
                    </div>
                    <div class="signup-notes-edit" style="display: none;">
                        <textarea class="notes-edit-field" rows="2" placeholder="<?php esc_attr_e('Add notes for this assignment...', 'lcd-events'); ?>"></textarea>
                        <div class="notes-edit-actions">
                            <button type="button" class="button button-small save-notes"><?php _e('Save', 'lcd-events'); ?></button>
                            <button type="button" class="button button-small cancel-notes"><?php _e('Cancel', 'lcd-events'); ?></button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            <div class="signup-status-section">
                <div class="signup-status-display">
                    <span class="signup-status-label"><?php _e('Status:', 'lcd-events'); ?></span>
                    <span class="signup-status-value confirmed">
                        <?php _e('Confirmed', 'lcd-events'); ?>
                    </span>
                </div>
                <div class="signup-actions">
                    <button type="button" class="button button-small toggle-confirmed confirmed" 
                            data-confirmed="1"
                            title="<?php esc_attr_e('Mark as unconfirmed', 'lcd-events'); ?>">
                        <?php _e('Unconfirm', 'lcd-events'); ?>
                    </button>
                    <button type="button" class="button-link button-link-delete unassign-volunteer" title="<?php esc_attr_e('Remove from shift', 'lcd-events'); ?>">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
            </div>
        </div>
        <?php
        $new_signup_html = ob_get_clean();
        wp_send_json_success(['message' => __('Volunteer assigned.', 'lcd-events'), 'new_signup_html' => $new_signup_html, 'signup_id' => $signup_id]);
    } else {
        wp_send_json_error(['message' => __('Could not save signup.', 'lcd-events'), 'db_error' => $wpdb->last_error], 500);
    }
}

// AJAX handler for unassigning a person from a shift
add_action('wp_ajax_lcd_unassign_person_from_shift', 'lcd_ajax_unassign_person_from_shift');
function lcd_ajax_unassign_person_from_shift() {
    check_ajax_referer('lcd_event_unassign_person_from_shift', 'nonce');

    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => __('Permission denied.', 'lcd-events')], 403);
        return;
    }

    $signup_id = isset($_POST['signup_id']) ? intval($_POST['signup_id']) : 0;

    if (!$signup_id) {
        wp_send_json_error(['message' => __('Missing signup ID.', 'lcd-events')], 400);
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'lcd_volunteer_signups';

    $deleted = $wpdb->delete($table_name, ['id' => $signup_id], ['%d']);

    if ($deleted !== false) {
        wp_send_json_success(['message' => __('Volunteer unassigned.', 'lcd-events')]);
    } else {
        wp_send_json_error(['message' => __('Could not remove signup.', 'lcd-events'), 'db_error' => $wpdb->last_error], 500);
    }
}

// AJAX handler for editing volunteer notes
add_action('wp_ajax_lcd_edit_volunteer_notes', 'lcd_ajax_edit_volunteer_notes');
function lcd_ajax_edit_volunteer_notes() {
    check_ajax_referer('lcd_event_edit_volunteer_notes', 'nonce');

    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => __('Permission denied.', 'lcd-events')], 403);
        return;
    }

    $signup_id = isset($_POST['signup_id']) ? intval($_POST['signup_id']) : 0;
    $notes = isset($_POST['notes']) ? sanitize_textarea_field($_POST['notes']) : '';

    if (!$signup_id) {
        wp_send_json_error(['message' => __('Missing signup ID.', 'lcd-events')], 400);
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'lcd_volunteer_signups';

    // Verify signup exists
    $signup = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM $table_name WHERE id = %d",
        $signup_id
    ));

    if (!$signup) {
        wp_send_json_error(['message' => __('Signup not found.', 'lcd-events')], 404);
        return;
    }

    $updated = $wpdb->update(
        $table_name,
        ['volunteer_notes' => $notes],
        ['id' => $signup_id],
        ['%s'],
        ['%d']
    );

    if ($updated !== false) {
        wp_send_json_success(['message' => __('Notes updated successfully.', 'lcd-events')]);
    } else {
        wp_send_json_error(['message' => __('Could not update notes.', 'lcd-events'), 'db_error' => $wpdb->last_error], 500);
    }
}

/**
 * Toggle volunteer confirmation status via AJAX
 */
function lcd_ajax_toggle_volunteer_confirmed() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'lcd_event_toggle_volunteer_confirmed')) {
        wp_die('Security check failed');
    }
    
    // Check user permissions
    if (!current_user_can('edit_posts')) {
        wp_die('Insufficient permissions');
    }
    
    $signup_id = intval($_POST['signup_id']);
    $confirmed = $_POST['confirmed'] === '1' ? 1 : 0;
    
    if ($signup_id <= 0) {
        wp_send_json_error('Invalid signup ID');
        return;
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'lcd_volunteer_signups';
    
    // Get current signup
    $signup = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE id = %d",
        $signup_id
    ));
    
    if (!$signup) {
        wp_send_json_error('Signup not found');
        return;
    }
    
    // Toggle the status
    $new_status = $confirmed ? 'confirmed' : 'unconfirmed';
    
    $updated = $wpdb->update(
        $table_name,
        array('status' => $new_status),
        array('id' => $signup_id),
        array('%s'),
        array('%d')
    );
    
    if ($updated === false) {
        wp_send_json_error('Failed to update confirmation status');
        return;
    }
    
    wp_send_json_success(array(
        'signup_id' => $signup_id,
        'confirmed' => $confirmed,
        'status' => $new_status,
        'message' => $confirmed ? __('Volunteer confirmed', 'lcd-events') : __('Volunteer unconfirmed', 'lcd-events')
    ));
}
add_action('wp_ajax_lcd_toggle_volunteer_confirmed', 'lcd_ajax_toggle_volunteer_confirmed');

// AJAX handler for exporting volunteers to CSV
add_action('wp_ajax_lcd_export_volunteers_csv', 'lcd_ajax_export_volunteers_csv');
function lcd_ajax_export_volunteers_csv() {
    if (!current_user_can('edit_posts')) {
        wp_die(__('Permission denied.', 'lcd-events'));
        return;
    }

    $event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
    
    if (!$event_id) {
        wp_die(__('Invalid event ID.', 'lcd-events'));
        return;
    }

    $event = get_post($event_id);
    if (!$event || $event->post_type !== 'event') {
        wp_die(__('Event not found.', 'lcd-events'));
        return;
    }

    // Get volunteer shifts and signups
    $volunteer_shifts = get_post_meta($event_id, '_volunteer_shifts', true);
    $all_signups = lcd_get_volunteer_signups($event_id);
    
    if (empty($all_signups)) {
        wp_die(__('No volunteers found for this event.', 'lcd-events'));
        return;
    }

    // Prepare filename
    $event_date = get_post_meta($event_id, '_event_date', true);
    $date_part = $event_date ? date('Y-m-d', strtotime($event_date)) : date('Y-m-d');
    $filename = sanitize_file_name($event->post_title . '_volunteers_' . $date_part . '.csv');

    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Create output handle
    $output = fopen('php://output', 'w');

    // Add BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // CSV headers
    $headers = [
        __('Volunteer Name', 'lcd-events'),
        __('Email', 'lcd-events'),
        __('Phone', 'lcd-events'),
        __('Shift Title', 'lcd-events'),
        __('Shift Date', 'lcd-events'),
        __('Shift Start Time', 'lcd-events'),
        __('Shift End Time', 'lcd-events'),
        __('Notes', 'lcd-events'),
        __('Signup Date', 'lcd-events'),
        __('Status', 'lcd-events')
    ];
    
    fputcsv($output, $headers);

    // Process each signup
    foreach ($all_signups as $signup) {
        // Get shift details
        $shift = isset($volunteer_shifts[$signup->shift_index]) ? $volunteer_shifts[$signup->shift_index] : null;
        
        $volunteer_name = $signup->volunteer_name;
        $volunteer_email = $signup->volunteer_email;
        $volunteer_phone = $signup->volunteer_phone;
        
        // If linked to an lcd_person, get updated details
        if ($signup->person_id) {
            $person_post = get_post($signup->person_id);
            if ($person_post) {
                $volunteer_name = $person_post->post_title;
                $volunteer_email = get_post_meta($signup->person_id, '_lcd_person_email', true) ?: $volunteer_email;
                $volunteer_phone = get_post_meta($signup->person_id, '_lcd_person_phone', true) ?: $volunteer_phone;
            }
        }
        
        $shift_date = $shift ? ($shift['date'] ?? '') : '';
        $shift_start_time = $shift ? ($shift['start_time'] ?? '') : '';
        $shift_end_time = $shift ? ($shift['end_time'] ?? '') : '';
        
        // Format dates and times for CSV
        $formatted_shift_date = $shift_date ? date_i18n(get_option('date_format'), strtotime($shift_date)) : '';
        $formatted_start_time = ($shift_date && $shift_start_time) ? date_i18n(get_option('time_format'), strtotime($shift_date . ' ' . $shift_start_time)) : '';
        $formatted_end_time = ($shift_date && $shift_end_time) ? date_i18n(get_option('time_format'), strtotime($shift_date . ' ' . $shift_end_time)) : '';
        $formatted_signup_date = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($signup->signup_date));

        $row = [
            $volunteer_name,
            $volunteer_email,
            $volunteer_phone,
            $signup->shift_title,
            $formatted_shift_date,
            $formatted_start_time,
            $formatted_end_time,
            $signup->volunteer_notes ?? '',
            $formatted_signup_date,
            $signup->status
        ];
        
        fputcsv($output, $row);
    }

    fclose($output);
    exit;
}

// AJAX handler for exporting volunteers to PDF
add_action('wp_ajax_lcd_export_volunteers_pdf', 'lcd_ajax_export_volunteers_pdf');
function lcd_ajax_export_volunteers_pdf() {
    if (!current_user_can('edit_posts')) {
        wp_die(__('Permission denied.', 'lcd-events'));
        return;
    }

    $event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
    
    if (!$event_id) {
        wp_die(__('Invalid event ID.', 'lcd-events'));
        return;
    }

    $event = get_post($event_id);
    if (!$event || $event->post_type !== 'event') {
        wp_die(__('Event not found.', 'lcd-events'));
        return;
    }

    // Get event details
    $event_date = get_post_meta($event_id, '_event_date', true);
    $event_time = get_post_meta($event_id, '_event_time', true);
    $event_location = get_post_meta($event_id, '_event_location', true);
    
    // Get volunteer shifts and signups
    $volunteer_shifts = get_post_meta($event_id, '_volunteer_shifts', true);
    $all_signups = lcd_get_volunteer_signups($event_id);
    
    if (empty($all_signups)) {
        wp_die(__('No volunteers found for this event.', 'lcd-events'));
        return;
    }

    // Group signups by shift
    $signups_by_shift = array();
    foreach ($all_signups as $signup) {
        $signups_by_shift[$signup->shift_index][] = $signup;
    }

    // Prepare filename
    $date_part = $event_date ? date('Y-m-d', strtotime($event_date)) : date('Y-m-d');
    $filename = sanitize_file_name($event->post_title . '_volunteers_' . $date_part . '.pdf');

    // Create PDF content using basic HTML
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title><?php echo esc_html($event->post_title); ?> - <?php _e('Volunteer List', 'lcd-events'); ?></title>
        <style>
            body { font-family: Arial, sans-serif; font-size: 12px; line-height: 1.4; margin: 20px; }
            .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 15px; }
            .event-title { font-size: 18px; font-weight: bold; margin-bottom: 5px; }
            .event-details { font-size: 14px; color: #666; }
            .shift-section { margin-bottom: 30px; page-break-inside: avoid; }
            .shift-header { background: #f5f5f5; padding: 12px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 4px; }
            .shift-title { font-size: 16px; font-weight: bold; margin-bottom: 8px; color: #333; }
            .shift-meta { font-size: 12px; color: #666; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 8px; }
            .shift-meta-item { white-space: nowrap; }
            .shift-status-summary { margin-top: 8px; font-size: 11px; color: #555; }
            .volunteers-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; font-size: 11px; }
            .volunteers-table th, .volunteers-table td { border: 1px solid #ddd; padding: 6px; text-align: left; vertical-align: top; }
            .volunteers-table th { background: #f9f9f9; font-weight: bold; color: #333; }
            .name-col { width: 18%; }
            .email-col { width: 22%; }
            .phone-col { width: 15%; }
            .status-col { width: 12%; text-align: center; }
            .notes-col { width: 20%; word-wrap: break-word; }
            .signup-date-col { width: 13%; }
            .status-confirmed { color: #0d7377; font-weight: bold; }
            .status-unconfirmed { color: #dc3545; font-weight: bold; }
            .summary { margin-top: 30px; border-top: 2px solid #333; padding-top: 15px; }
            .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 15px; }
            .summary-item { text-align: center; }
            .summary-number { font-size: 18px; font-weight: bold; color: #333; }
            .summary-label { font-size: 12px; color: #666; }
            .generation-info { font-size: 10px; color: #666; text-align: center; margin-top: 15px; }
        </style>
    </head>
    <body>
        <div class="header">
            <div class="event-title"><?php echo esc_html($event->post_title); ?></div>
            <div class="event-details">
                <?php if ($event_date) : ?>
                    <strong><?php _e('Date:', 'lcd-events'); ?></strong> <?php echo date_i18n(get_option('date_format'), strtotime($event_date)); ?>
                <?php endif; ?>
                <?php if ($event_time) : ?>
                    | <strong><?php _e('Time:', 'lcd-events'); ?></strong> <?php echo date_i18n(get_option('time_format'), strtotime($event_date . ' ' . $event_time)); ?>
                <?php endif; ?>
                <?php if ($event_location) : ?>
                    | <strong><?php _e('Location:', 'lcd-events'); ?></strong> <?php echo esc_html($event_location); ?>
                <?php endif; ?>
            </div>
        </div>

        <?php 
        $total_confirmed = 0;
        $total_unconfirmed = 0;
        
        foreach ($volunteer_shifts as $shift_index => $shift) : 
            if (empty($signups_by_shift[$shift_index])) continue;
            $shift_signups = $signups_by_shift[$shift_index];
            
            // Count status for this shift
            $shift_confirmed = 0;
            $shift_unconfirmed = 0;
            foreach ($shift_signups as $signup) {
                if ($signup->status === 'confirmed') {
                    $shift_confirmed++;
                    $total_confirmed++;
                } else {
                    $shift_unconfirmed++;
                    $total_unconfirmed++;
                }
            }
            
            $shift_time_string = '';
            if (!empty($shift['start_time'])) {
                $shift_time_string = date_i18n(get_option('time_format'), strtotime($shift['date'] . ' ' . $shift['start_time']));
                if (!empty($shift['end_time'])) {
                    $shift_time_string .= ' - ' . date_i18n(get_option('time_format'), strtotime($shift['date'] . ' ' . $shift['end_time']));
                }
            }
            ?>
            
            <div class="shift-section">
                <div class="shift-header">
                    <div class="shift-title"><?php echo esc_html($shift['title']); ?></div>
                    <div class="shift-meta">
                        <?php if (!empty($shift['date'])) : ?>
                            <div class="shift-meta-item">
                                <strong><?php _e('Date:', 'lcd-events'); ?></strong> <?php echo date_i18n(get_option('date_format'), strtotime($shift['date'])); ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($shift_time_string) : ?>
                            <div class="shift-meta-item">
                                <strong><?php _e('Time:', 'lcd-events'); ?></strong> <?php echo esc_html($shift_time_string); ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($shift['max_volunteers'])) : ?>
                            <div class="shift-meta-item">
                                <strong><?php _e('Max Volunteers:', 'lcd-events'); ?></strong> <?php echo esc_html($shift['max_volunteers']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($shift['description'])) : ?>
                        <div class="shift-meta-item" style="margin-top: 8px;">
                            <strong><?php _e('Description:', 'lcd-events'); ?></strong> <?php echo esc_html($shift['description']); ?>
                        </div>
                    <?php endif; ?>
                    <div class="shift-status-summary">
                        <strong><?php _e('Volunteers:', 'lcd-events'); ?></strong> 
                        <?php printf(__('%d total', 'lcd-events'), count($shift_signups)); ?>
                        <?php if ($shift_confirmed > 0) : ?>
                            | <span class="status-confirmed"><?php printf(__('%d confirmed', 'lcd-events'), $shift_confirmed); ?></span>
                        <?php endif; ?>
                        <?php if ($shift_unconfirmed > 0) : ?>
                            | <span class="status-unconfirmed"><?php printf(__('%d unconfirmed', 'lcd-events'), $shift_unconfirmed); ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <table class="volunteers-table">
                    <thead>
                        <tr>
                            <th class="name-col"><?php _e('Name', 'lcd-events'); ?></th>
                            <th class="email-col"><?php _e('Email', 'lcd-events'); ?></th>
                            <th class="phone-col"><?php _e('Phone', 'lcd-events'); ?></th>
                            <th class="status-col"><?php _e('Status', 'lcd-events'); ?></th>
                            <th class="notes-col"><?php _e('Notes', 'lcd-events'); ?></th>
                            <th class="signup-date-col"><?php _e('Signup Date', 'lcd-events'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($shift_signups as $signup) : 
                            $volunteer_name = $signup->volunteer_name;
                            $volunteer_email = $signup->volunteer_email;
                            $volunteer_phone = $signup->volunteer_phone;
                            
                            // If linked to an lcd_person, get updated details
                            if ($signup->person_id) {
                                $person_post = get_post($signup->person_id);
                                if ($person_post) {
                                    $volunteer_name = $person_post->post_title;
                                    $volunteer_email = get_post_meta($signup->person_id, '_lcd_person_email', true) ?: $volunteer_email;
                                    $volunteer_phone = get_post_meta($signup->person_id, '_lcd_person_phone', true) ?: $volunteer_phone;
                                }
                            }
                            
                            $status_class = $signup->status === 'confirmed' ? 'status-confirmed' : 'status-unconfirmed';
                            $status_text = $signup->status === 'confirmed' ? __('Confirmed', 'lcd-events') : __('Unconfirmed', 'lcd-events');
                            ?>
                            <tr>
                                <td class="name-col"><?php echo esc_html($volunteer_name); ?></td>
                                <td class="email-col"><?php echo esc_html($volunteer_email); ?></td>
                                <td class="phone-col"><?php echo esc_html($volunteer_phone); ?></td>
                                <td class="status-col"><span class="<?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_text); ?></span></td>
                                <td class="notes-col"><?php echo esc_html($signup->volunteer_notes ?? ''); ?></td>
                                <td class="signup-date-col"><?php echo date_i18n(get_option('date_format'), strtotime($signup->signup_date)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>

        <div class="summary">
            <div class="summary-grid">
                <div class="summary-item">
                    <div class="summary-number"><?php echo count($all_signups); ?></div>
                    <div class="summary-label"><?php _e('Total Volunteers', 'lcd-events'); ?></div>
                </div>
                <div class="summary-item">
                    <div class="summary-number" style="color: #0d7377;"><?php echo $total_confirmed; ?></div>
                    <div class="summary-label"><?php _e('Confirmed', 'lcd-events'); ?></div>
                </div>
                <div class="summary-item">
                    <div class="summary-number" style="color: #dc3545;"><?php echo $total_unconfirmed; ?></div>
                    <div class="summary-label"><?php _e('Unconfirmed', 'lcd-events'); ?></div>
                </div>
                <div class="summary-item">
                    <div class="summary-number"><?php echo count($volunteer_shifts); ?></div>
                    <div class="summary-label"><?php _e('Volunteer Shifts', 'lcd-events'); ?></div>
                </div>
            </div>
            <div class="generation-info">
                <?php printf(__('Generated on %s', 'lcd-events'), date_i18n(get_option('date_format') . ' ' . get_option('time_format'))); ?>
            </div>
        </div>
    </body>
    </html>
    <?php
    $html = ob_get_clean();

    // Check if DomPDF is available (you might want to include it as a dependency)
    // For now, we'll use a simple HTML to PDF conversion via the browser
    // In a production environment, you might want to use a proper PDF library
    
    // Set headers for PDF download
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // For basic PDF generation, we'll use wkhtmltopdf if available, or fall back to HTML
    // This is a simplified approach - in production you might want to use a proper PDF library
    
    // Try to use wkhtmltopdf if available
    $wkhtmltopdf_path = '/usr/bin/wkhtmltopdf'; // Adjust path as needed
    if (file_exists($wkhtmltopdf_path) && is_executable($wkhtmltopdf_path)) {
        $temp_html = tempnam(sys_get_temp_dir(), 'lcd_volunteers_') . '.html';
        $temp_pdf = tempnam(sys_get_temp_dir(), 'lcd_volunteers_') . '.pdf';
        
        file_put_contents($temp_html, $html);
        
        $command = escapeshellcmd($wkhtmltopdf_path) . ' --page-size A4 --orientation Portrait --margin-top 1cm --margin-right 1cm --margin-bottom 1cm --margin-left 1cm ' . 
                   escapeshellarg($temp_html) . ' ' . escapeshellarg($temp_pdf);
        
        exec($command, $output, $return_var);
        
        if ($return_var === 0 && file_exists($temp_pdf)) {
            readfile($temp_pdf);
            unlink($temp_html);
            unlink($temp_pdf);
            exit;
        }
        
        // Clean up temp files if command failed
        if (file_exists($temp_html)) unlink($temp_html);
        if (file_exists($temp_pdf)) unlink($temp_pdf);
    }
    
    // Fallback: serve as HTML with PDF headers (browser will handle PDF conversion)
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: inline; filename="' . str_replace('.pdf', '.html', $filename) . '"');
    echo $html;
    exit;
}

/**
 * Function to display event type in templates
 */
function lcd_display_event_type($post_id = null) {
    if (!$post_id) {
        $post_id = get_the_ID();
    }
    
    $event_types = get_the_terms($post_id, 'event_type');
    if (!empty($event_types) && !is_wp_error($event_types)) {
        echo '<div class="event-type">';
        echo '<span class="event-type-label">' . __('Event Type:', 'lcd-events') . '</span> ';
        echo '<span class="event-type-value">';
        
        $type_names = array();
        foreach ($event_types as $type) {
            $type_names[] = '<a href="' . esc_url(get_term_link($type)) . '">' . esc_html($type->name) . '</a>';
        }
        echo implode(', ', $type_names);
        
        echo '</span>';
        echo '</div>';
    }
}

/**
 * Enqueue front-end scripts
 */
function lcd_events_frontend_scripts() {
    // Load on single event pages
    if (is_singular('event')) {
        wp_enqueue_script(
            'lcd-events-frontend',
            LCD_EVENTS_PLUGIN_URL . 'js/front-end-events.js',
            array(),
            LCD_EVENTS_VERSION,
            true
        );
    }
    
    // Load on volunteer opportunities page
    if (is_page()) {
        global $post;
        if ($post && ($post->post_name === 'volunteer-opportunities' || 
                     get_page_template_slug($post->ID) === 'page-volunteer-opportunities.php')) {
            wp_enqueue_script(
                'lcd-volunteer-opportunities',
                LCD_EVENTS_PLUGIN_URL . 'js/volunteer-opportunities.js',
                array('jquery'),
                LCD_EVENTS_VERSION,
                true
            );
            
            // Localize script with AJAX data for future sign-up functionality
            wp_localize_script('lcd-volunteer-opportunities', 'lcdVolunteerData', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('lcd_volunteer_signup'),
                'text' => array(
                    'signing_up' => __('Signing up...', 'lcd-events'),
                    'sign_up' => __('Sign Up', 'lcd-events'),
                    'error_signup' => __('Error signing up. Please try again.', 'lcd-events'),
                    'success_signup' => __('Successfully signed up!', 'lcd-events'),
                    'confirm_signup' => __('Are you sure you want to sign up for this volunteer shift?', 'lcd-events'),
                )
            ));
        }
    }
}
add_action('wp_enqueue_scripts', 'lcd_events_frontend_scripts');

/**
 * Register custom page templates
 */
function lcd_register_page_templates($templates) {
    $templates['page-volunteer-opportunities.php'] = __('Volunteer Opportunities', 'lcd-events');
    return $templates;
}
add_filter('theme_page_templates', 'lcd_register_page_templates');

/**
 * Load custom page template from plugin
 */
function lcd_load_page_template($template) {
    global $post;
    
    if ($post && get_page_template_slug($post->ID) === 'page-volunteer-opportunities.php') {
        $plugin_template = LCD_EVENTS_PLUGIN_DIR . 'templates/page-volunteer-opportunities.php';
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }
    }
    
    return $template;
}
add_filter('page_template', 'lcd_load_page_template');

/**
 * Add Volunteer Shifts Admin Page
 */
function lcd_add_volunteer_shifts_page() {
    add_submenu_page(
        'edit.php?post_type=event',           // Parent slug (Events menu)
        __('Volunteer Shifts', 'lcd-events'), // Page title
        __('Volunteer Shifts', 'lcd-events'), // Menu title
        'edit_posts',                         // Capability required
        'volunteer-shifts',                   // Menu slug
        'lcd_volunteer_shifts_page_callback'  // Callback function
    );
}
add_action('admin_menu', 'lcd_add_volunteer_shifts_page');

/**
 * Volunteer Shifts Admin Page Callback
 */
function lcd_volunteer_shifts_page_callback() {
    // Get all upcoming events with volunteer shifts
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
        <h1><?php _e('Volunteer Shifts', 'lcd-events'); ?></h1>
        
        <?php if (empty($events)) : ?>
            <div class="notice notice-info">
                <p><?php _e('No upcoming events with volunteer shifts found.', 'lcd-events'); ?></p>
            </div>
        <?php else : ?>
            <div class="lcd-volunteer-shifts-overview">
                <?php foreach ($events as $event) : 
                    $shifts = lcd_get_event_volunteer_shifts($event->ID);
                    if (empty($shifts)) continue;
                    
                    $event_date = get_post_meta($event->ID, '_event_date', true);
                    $formatted_date = date_i18n(get_option('date_format'), strtotime($event_date));
                    
                    // Get all signups for this event
                    $all_signups = lcd_get_volunteer_signups($event->ID);
                    $signups_by_shift = array();
                    foreach ($all_signups as $signup) {
                        $signups_by_shift[$signup->shift_index][] = $signup;
                    }
                    ?>
                    
                    <div class="lcd-event-shifts-section">
                        <h2>
                            <a href="<?php echo get_edit_post_link($event->ID); ?>">
                                <?php echo esc_html($event->post_title); ?>
                            </a>
                            <span class="event-date"><?php echo esc_html($formatted_date); ?></span>
                        </h2>
                        
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
                        
                        <div id="volunteer-shifts-container-<?php echo $event->ID; ?>" class="volunteer-shifts-container">
                            <?php foreach ($shifts as $index => $shift) : 
                                $shift_signups = $signups_by_shift[$index] ?? array();
                                $signup_count = count($shift_signups);
                                $max_volunteers = !empty($shift['max_volunteers']) ? $shift['max_volunteers'] : '∞';
                                
                                $time_string = '';
                                if (!empty($shift['start_time'])) {
                                    $time_string = date_i18n(get_option('time_format'), strtotime($shift['start_time']));
                                    if (!empty($shift['end_time'])) {
                                        $time_string .= ' - ' . date_i18n(get_option('time_format'), strtotime($shift['end_time']));
                                    }
                                }
                                ?>
                                
                                <div class="volunteer-shift-item" data-index="<?php echo $index; ?>" data-event-id="<?php echo $event->ID; ?>">
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
                                                <?php _e('Details', 'lcd-events'); ?>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="shift-details" style="display: none;">
                                        <?php 
                                        // Display shift date and time prominently in details
                                        if (!empty($shift['date']) || $time_string) : ?>
                                            <div class="shift-datetime-info">
                                                <?php if (!empty($shift['date'])) : ?>
                                                    <div class="shift-date-info">
                                                        <strong><?php _e('Date:', 'lcd-events'); ?></strong>
                                                        <span><?php echo date_i18n(get_option('date_format'), strtotime($shift['date'])); ?></span>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if ($time_string) : ?>
                                                    <div class="shift-time-info">
                                                        <strong><?php _e('Time:', 'lcd-events'); ?></strong>
                                                        <span><?php echo esc_html($time_string); ?></span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($shift['description'])) : ?>
                                            <div class="shift-description">
                                                <strong><?php _e('Description:', 'lcd-events'); ?></strong>
                                                <p><?php echo esc_html($shift['description']); ?></p>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($shift_signups)) : ?>
                                            <div class="shift-signups">
                                                <h5>
                                                    <span class="dashicons dashicons-groups"></span>
                                                    <?php _e('Registered Volunteers:', 'lcd-events'); ?>
                                                    <span class="signups-count">(<?php echo count($shift_signups); ?>)</span>
                                                </h5>
                                                <div class="signups-list">
                                                    <?php foreach ($shift_signups as $signup) : 
                                                        $person_name = esc_html($signup->volunteer_name);
                                                        $person_email = esc_html($signup->volunteer_email);
                                                        $person_phone = esc_html($signup->volunteer_phone ?? '');
                                                        $person_notes = esc_html($signup->volunteer_notes ?? '');
                                                        $person_id_attr = isset($signup->person_id) && $signup->person_id ? 'data-person-id="' . esc_attr($signup->person_id) . '"' : '';
                                                        $signup_id_attr = 'data-signup-id="' . esc_attr($signup->id) . '"';
                                                        
                                                        // If linked to an lcd_person, fetch their latest details
                                                        if (isset($signup->person_id) && $signup->person_id) {
                                                            $person_post = get_post($signup->person_id);
                                                            if ($person_post) {
                                                                $person_name = esc_html($person_post->post_title);
                                                                $person_email = esc_html(get_post_meta($signup->person_id, '_lcd_person_email', true));
                                                                $person_phone = esc_html(get_post_meta($signup->person_id, '_lcd_person_phone', true));
                                                            }
                                                        }
                                                        ?>
                                                        <div class="signup-item-compact status-<?php echo esc_attr($signup->status); ?>" <?php echo $person_id_attr; ?> <?php echo $signup_id_attr; ?>>
                                                            <div class="signup-header">
                                                                <div class="signup-primary">
                                                                    <strong><?php echo $person_name; ?></strong>
                                                                    <span class="signup-contact">
                                                                        <?php echo $person_email; ?>
                                                                        <?php if (!empty($person_phone)) : ?>
                                                                            • <?php echo $person_phone; ?>
                                                                        <?php endif; ?>
                                                                    </span>
                                                                </div>
                                                                <div class="signup-date"><?php echo date_i18n('M j, Y \a\t g:i A', strtotime($signup->signup_date)); ?></div>
                                                            </div>
                                                            <?php if (!empty($signup->volunteer_notes)) : ?>
                                                                <div class="signup-notes">
                                                                    <div class="signup-notes-display">
                                                                        <span class="notes-text"><?php echo esc_html($signup->volunteer_notes); ?></span>
                                                                        <button type="button" class="button-link edit-notes" title="<?php esc_attr_e('Edit notes', 'lcd-events'); ?>">
                                                                            <span class="dashicons dashicons-edit"></span>
                                                                        </button>
                                                                    </div>
                                                                    <div class="signup-notes-edit" style="display: none;">
                                                                        <textarea class="notes-edit-field" rows="2"><?php echo esc_textarea($signup->volunteer_notes); ?></textarea>
                                                                        <div class="notes-edit-actions">
                                                                            <button type="button" class="button button-small save-notes"><?php _e('Save', 'lcd-events'); ?></button>
                                                                            <button type="button" class="button button-small cancel-notes"><?php _e('Cancel', 'lcd-events'); ?></button>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            <?php else : ?>
                                                                <div class="signup-notes">
                                                                    <div class="signup-notes-display">
                                                                        <span class="notes-text no-notes"><?php _e('No notes', 'lcd-events'); ?></span>
                                                                        <button type="button" class="button-link edit-notes" title="<?php esc_attr_e('Add notes', 'lcd-events'); ?>">
                                                                            <span class="dashicons dashicons-edit"></span>
                                                                        </button>
                                                                    </div>
                                                                    <div class="signup-notes-edit" style="display: none;">
                                                                        <textarea class="notes-edit-field" rows="2" placeholder="<?php esc_attr_e('Add notes for this assignment...', 'lcd-events'); ?>"></textarea>
                                                                        <div class="notes-edit-actions">
                                                                            <button type="button" class="button button-small save-notes"><?php _e('Save', 'lcd-events'); ?></button>
                                                                            <button type="button" class="button button-small cancel-notes"><?php _e('Cancel', 'lcd-events'); ?></button>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            <?php endif; ?>
                                                            <div class="signup-status-section">
                                                                <div class="signup-status-display">
                                                                    <span class="signup-status-label"><?php _e('Status:', 'lcd-events'); ?></span>
                                                                    <span class="signup-status-value <?php echo esc_attr($signup->status); ?>">
                                                                        <?php echo ucfirst($signup->status); ?>
                                                                    </span>
                                                                </div>
                                                                <div class="signup-actions">
                                                                    <button type="button" class="button button-small toggle-confirmed <?php echo esc_attr($signup->status); ?>" 
                                                                            data-confirmed="<?php echo esc_attr($signup->status === 'confirmed' ? '1' : '0'); ?>"
                                                                            title="<?php echo esc_attr($signup->status === 'confirmed' ? __('Mark as unconfirmed', 'lcd-events') : __('Mark as confirmed', 'lcd-events')); ?>">
                                                                        <?php echo $signup->status === 'confirmed' ? __('Unconfirm', 'lcd-events') : __('Confirm', 'lcd-events'); ?>
                                                                    </button>
                                                                    <button type="button" class="button-link button-link-delete unassign-volunteer" title="<?php esc_attr_e('Remove from shift', 'lcd-events'); ?>">
                                                                        <span class="dashicons dashicons-no-alt"></span>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <div class="assign-volunteer-section">
                                            <h6 class="assign-volunteer-title"><?php _e('Assign Person to Shift:', 'lcd-events'); ?></h6>
                                            <div class="assign-volunteer-controls">
                                                <select class="lcd-person-search-select" data-shift-index="<?php echo $index; ?>" data-event-id="<?php echo $event->ID; ?>" style="width: 100%; margin-bottom: 5px;">
                                                    <option></option>
                                                </select>
                                                <textarea class="shift-assignment-notes" 
                                                          placeholder="<?php _e('Optional notes for this assignment (e.g., specific tasks, time constraints, etc.)', 'lcd-events'); ?>" 
                                                          rows="2" 
                                                          style="width: 100%; margin-top: 5px; resize: vertical;"></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

// Add styles for the volunteer shifts overview page
function lcd_volunteer_shifts_admin_styles() {
    $screen = get_current_screen();
    if ($screen->id !== 'event_page_volunteer-shifts') return;
    
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
        LCD_EVENTS_PLUGIN_URL . 'js/admin-events.js',
        array('jquery', 'select2'),
        LCD_EVENTS_VERSION,
        true
    );

    // Localize script for the volunteer shifts overview page (different from event edit page)
    wp_localize_script('lcd-events-admin', 'lcdEventsAdmin', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'search_people_nonce' => wp_create_nonce('lcd_event_shifts_people_search'),
        'assign_person_nonce' => wp_create_nonce('lcd_event_assign_person_to_shift'),
        'unassign_person_nonce' => wp_create_nonce('lcd_event_unassign_person_from_shift'),
        'edit_notes_nonce' => wp_create_nonce('lcd_event_edit_volunteer_notes'),
        'toggle_confirmed_nonce' => wp_create_nonce('lcd_event_toggle_volunteer_confirmed'),
        'export_csv_nonce' => wp_create_nonce('lcd_export_volunteers_csv'),
        'export_pdf_nonce' => wp_create_nonce('lcd_export_volunteers_pdf'),
        'text' => [
            'confirm_unassign' => __('Are you sure you want to remove this volunteer from this shift?', 'lcd-events'),
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
            'registered_volunteers' => __('Registered Volunteers:', 'lcd-events'),
            'export_error' => __('Error exporting volunteer list. Please try again.', 'lcd-events'),
        ]
    ]);
    
    // Enqueue the main CSS file
    wp_enqueue_style(
        'lcd-events-admin-styles',
        LCD_EVENTS_PLUGIN_URL . 'css/lcd-events.css',
        array(), 
        LCD_EVENTS_VERSION
    );
    
    ?>
    <style>
        .lcd-volunteer-shifts-overview {
            margin-top: 20px;
        }
        
        .lcd-event-shifts-section {
            background: #fff;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
            margin-bottom: 20px;
            padding: 0;
        }
        
        .lcd-event-shifts-section h2 {
            border-bottom: 1px solid #ccd0d4;
            margin: 0;
            padding: 15px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .lcd-event-shifts-section .event-date {
            color: #666;
            font-size: 14px;
            font-weight: normal;
            margin-left: 10px;
        }

        .volunteer-shifts-container {
            padding: 15px;
        }
        
        /* Ensure proper styling for shift description */
        .shift-description {
            margin-bottom: 15px;
            padding: 10px;
            background: #f9f9f9;
            border-left: 3px solid #0073aa;
            border-radius: 3px;
        }
        
        .shift-description strong {
            display: block;
            margin-bottom: 5px;
            color: #23282d;
        }
        
        .shift-description p {
            margin: 0;
            color: #666;
        }
        
        /* Styling for shift date/time info */
        .shift-datetime-info {
            margin-bottom: 15px;
            padding: 12px;
            background: #e8f4f8;
            border-left: 3px solid #00a0d2;
            border-radius: 3px;
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .shift-date-info,
        .shift-time-info {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .shift-date-info strong,
        .shift-time-info strong {
            color: #00a0d2;
            font-weight: 600;
            margin: 0;
        }
        
        .shift-date-info span,
        .shift-time-info span {
            color: #23282d;
            font-weight: 500;
        }
        
        @media (max-width: 768px) {
            .shift-datetime-info {
                flex-direction: column;
                gap: 8px;
            }
        }
        
        /* Override any conflicting styles for the overview page */
        .lcd-volunteer-shifts-overview .volunteer-shift-item {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 15px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .lcd-volunteer-shifts-overview .shift-summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
            background: #fafafa;
        }
        
        .lcd-volunteer-shifts-overview .shift-summary:hover {
            background: #f0f0f0;
        }
        
        .lcd-volunteer-shifts-overview .shift-summary-content {
            flex: 1;
        }
        
        .lcd-volunteer-shifts-overview .shift-title-summary strong {
            font-size: 16px;
            color: #23282d;
            margin-bottom: 5px;
            display: block;
        }
        
        .lcd-volunteer-shifts-overview .shift-meta-summary {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            font-size: 13px;
            color: #666;
        }
        
        .lcd-volunteer-shifts-overview .shift-date-summary,
        .lcd-volunteer-shifts-overview .shift-time-summary,
        .lcd-volunteer-shifts-overview .shift-signups-summary {
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .lcd-volunteer-shifts-overview .shift-signups-summary {
            background: #0073aa;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .lcd-volunteer-shifts-overview .shift-summary-actions {
            display: flex;
            gap: 5px;
        }
        
        .lcd-volunteer-shifts-overview .shift-details {
            padding: 15px;
            border-top: 1px solid #eee;
        }
        
        /* Fix signup item styling */
        .lcd-volunteer-shifts-overview .signup-item-compact {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 8px;
            background: #fff;
            position: relative;
        }
        
        .lcd-volunteer-shifts-overview .signup-actions {
            position: absolute;
            top: 8px;
            right: 8px;
        }
        
        .lcd-volunteer-shifts-overview .signup-primary {
            flex: 1;
            margin-right: 30px; /* Make room for the action button */
        }
        
        .lcd-volunteer-shifts-overview .signup-primary strong {
            display: block;
            margin-bottom: 3px;
            color: #23282d;
        }
        
        .lcd-volunteer-shifts-overview .signup-contact {
            font-size: 13px;
            color: #666;
        }
        
        .lcd-volunteer-shifts-overview .signup-notes {
            margin-top: 8px;
            font-size: 13px;
        }
        
        .lcd-volunteer-shifts-overview .signup-date {
            position: absolute;
            bottom: 8px;
            right: 8px;
            font-size: 11px;
            color: #999;
        }
        
        /* Button styling */
        .lcd-volunteer-shifts-overview .button-link-delete {
            color: #a00;
            text-decoration: none;
            padding: 2px;
            border-radius: 2px;
        }
        
        .lcd-volunteer-shifts-overview .button-link-delete:hover {
            color: #dc3232;
            background: #f0f0f0;
        }
        
        .lcd-volunteer-shifts-overview .button-link-delete .dashicons {
            font-size: 16px;
            width: 16px;
            height: 16px;
        }
        
        /* Select2 styling adjustments */
        .lcd-volunteer-shifts-overview .select2-container {
            margin-bottom: 10px;
        }
        
        .lcd-volunteer-shifts-overview .assign-volunteer-section {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        
        .lcd-volunteer-shifts-overview .assign-volunteer-title {
            margin: 0 0 10px 0;
            font-size: 14px;
            font-weight: 600;
            color: #23282d;
        }
    </style>
    <?php
}
add_action('admin_head', 'lcd_volunteer_shifts_admin_styles');