<?php
// Exit if accessed directly
if (!defined('ABSPATH')) exit;

/**
 * Class LCD_Volunteer_Shifts
 *
 * Handles all functionality related to volunteer shifts for events.
 */
class LCD_Volunteer_Shifts {

    private static $instance;
    private $last_email_error = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('add_meta_boxes', [$this, 'add_volunteer_shifts_meta_box']);
        add_action('save_post_event', [$this, 'save_volunteer_shifts_meta']);
        add_action('manage_event_posts_custom_column', [$this, 'render_volunteer_shifts_column'], 10, 2);
        add_action('wp_ajax_lcd_search_people_for_shifts', [$this, 'ajax_search_people_for_shifts']);
        add_action('wp_ajax_lcd_assign_person_to_shift', [$this, 'ajax_assign_person_to_shift']);
        add_action('wp_ajax_lcd_unassign_person_from_shift', [$this, 'ajax_unassign_person_from_shift']);
        add_action('wp_ajax_lcd_edit_volunteer_notes', [$this, 'ajax_edit_volunteer_notes']);
        add_action('wp_ajax_lcd_toggle_volunteer_confirmed', [$this, 'ajax_toggle_volunteer_confirmed']);
        add_action('wp_ajax_lcd_export_volunteers_csv', [$this, 'ajax_export_volunteers_csv']);
        add_action('wp_ajax_lcd_export_volunteers_pdf', [$this, 'ajax_export_volunteers_pdf']);
        // Frontend volunteer opportunity signup
        add_action('wp_ajax_lcd_volunteer_opportunity_signup', [$this, 'ajax_volunteer_opportunity_signup']);
        add_action('wp_ajax_nopriv_lcd_volunteer_opportunity_signup', [$this, 'ajax_volunteer_opportunity_signup']);
        
        // Frontend modal login
        add_action('wp_ajax_lcd_volunteer_login', [$this, 'ajax_volunteer_login']);
        add_action('wp_ajax_nopriv_lcd_volunteer_login', [$this, 'ajax_volunteer_login']);



        
        // Individual shift saving
        add_action('wp_ajax_lcd_save_individual_shift', [$this, 'ajax_save_individual_shift']);
        
        // Setup reminder cron job
        add_action('init', [$this, 'setup_reminder_cron']);
    }

    /**
     * Save volunteer shifts meta data.
     * 
     * Note: This method no longer handles meta box form data since the meta box
     * is now read-only. Volunteer shifts are managed through the admin page interface.
     *
     * @param int $post_id The ID of the post being saved.
     */
    public function save_volunteer_shifts_meta($post_id) {
        // The meta box is now read-only, so we don't process any form data here.
        // All volunteer shift management happens through the dedicated admin page.
        return;
    }

    /**
     * Create volunteer signups table
     */
    public function create_volunteer_signups_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'lcd_volunteer_signups';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        event_id bigint(20) NOT NULL,
        shift_index int(11) NOT NULL,
        shift_title varchar(255) NOT NULL,
        volunteer_name varchar(255) NOT NULL,
        volunteer_email varchar(255) NOT NULL,
        volunteer_phone varchar(50) DEFAULT '',
        volunteer_notes text,
        user_id bigint(20) DEFAULT NULL,
        signup_date datetime DEFAULT CURRENT_TIMESTAMP,
        status varchar(20) DEFAULT 'confirmed',
        person_id bigint(20) DEFAULT NULL,
        PRIMARY KEY (id),
        KEY idx_event_id (event_id),
        KEY idx_user_id (user_id),
        KEY idx_person_id (person_id),
        KEY idx_status (status)
    ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function add_volunteer_shifts_meta_box() {
        add_meta_box(
            'volunteer_shifts',
            __('Volunteer Shifts', 'lcd-events'),
            [$this, 'volunteer_shifts_callback'],
            'event',
            'normal',
            'default'
        );
    }

    /**
     * Volunteer Shifts Meta Box Callback (Read-Only Summary)
     */
    public function volunteer_shifts_callback($post) {
        $volunteer_shifts = get_post_meta($post->ID, '_volunteer_shifts', true);
        if (!is_array($volunteer_shifts)) {
            $volunteer_shifts = array();
        }
        
        // Get all signups for this event
        $all_signups = $this->get_volunteer_signups($post->ID);
        $signups_by_shift = array();
        foreach ($all_signups as $signup) {
            $signups_by_shift[$signup->shift_index][] = $signup;
        }
        
        $total_signups = count($all_signups);
        ?>
        <div class="lcd-volunteer-shifts-meta lcd-volunteer-shifts-readonly">
            <div class="volunteer-shifts-summary-header">
                <a href="<?php echo admin_url('edit.php?post_type=event&page=volunteer-shifts#event-' . $post->ID); ?>" 
                   class="button button-primary">
                    <?php _e('Manage Volunteer Shifts', 'lcd-events'); ?>
                </a>
                <?php if ($total_signups > 0) : ?>
                    <button type="button" class="button button-secondary export-volunteers-csv" data-event-id="<?php echo $post->ID; ?>">
                        <?php _e('Export Volunteers', 'lcd-events'); ?>
                    </button>
                <?php endif; ?>
            </div>
            <?php if (!empty($volunteer_shifts)) : ?>
                <ul class="volunteer-shifts-list">
                    <?php foreach ($volunteer_shifts as $index => $shift) : ?>
                        <?php 
                        $shift_signups = $signups_by_shift[$index] ?? array();
                        $signup_count = count($shift_signups);
                        $max_volunteers = intval($shift['max_volunteers'] ?? 0);
                        
                        // Format basic info
                        $shift_info = array();
                        if (!empty($shift['date'])) {
                            $shift_info[] = date_i18n('M j', strtotime($shift['date']));
                        }
                        if (!empty($shift['start_time'])) {
                            $shift_info[] = date_i18n('g:i A', strtotime($shift['date'] . ' ' . $shift['start_time']));
                        }
                        
                        $volunteer_count = '';
                        if ($max_volunteers > 0) {
                            $volunteer_count = "({$signup_count}/{$max_volunteers} volunteers)";
                        } else {
                            $volunteer_count = "({$signup_count} volunteers)";
                        }
                        ?>
                        <li>
                            <strong><?php echo esc_html($shift['title'] ?: __('Untitled Shift', 'lcd-events')); ?></strong>
                            <?php if (!empty($shift_info)) : ?>
                                - <?php echo esc_html(implode(' at ', $shift_info)); ?>
                            <?php endif; ?>
                            <span class="volunteer-count"><?php echo esc_html($volunteer_count); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <div class="no-shifts-message">
                    <p><?php _e('No volunteer shifts have been created for this event yet.', 'lcd-events'); ?></p>
                    <p><?php _e('Use the "Manage Volunteer Shifts" button above to create and manage shifts.', 'lcd-events'); ?></p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Get volunteer signups for an event
     */
    public function get_volunteer_signups($event_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'lcd_volunteer_signups';
        
        $signups = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE event_id = %d ORDER BY shift_index ASC, signup_date ASC",
            $event_id
        ));
        
        return $signups;
    }

    /**
     * Get volunteer signups for a specific user (across all events)
     * Can look up by user_id or by email if user is not logged in
     */
    public function get_user_volunteer_signups($user_id = null, $email = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'lcd_volunteer_signups';
        
        if ($user_id) {
            // Get signups by user_id
            $signups = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table_name WHERE user_id = %d ORDER BY signup_date DESC",
                $user_id
            ));
        } elseif ($email) {
            // Get signups by email
            $signups = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table_name WHERE volunteer_email = %s ORDER BY signup_date DESC",
                $email
            ));
        } else {
            return array();
        }
        
        return $signups;
    }

    /**
     * Check if a user is signed up for a specific shift
     */
    public function is_user_signed_up_for_shift($event_id, $shift_index, $user_id = null, $email = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'lcd_volunteer_signups';
        
        if ($user_id) {
            // Check by user_id
            $signup = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM $table_name WHERE event_id = %d AND shift_index = %d AND user_id = %d AND status = 'confirmed'",
                $event_id,
                $shift_index,
                $user_id
            ));
        } elseif ($email) {
            // Check by email
            $signup = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM $table_name WHERE event_id = %d AND shift_index = %d AND volunteer_email = %s AND status = 'confirmed'",
                $event_id,
                $shift_index,
                $email
            ));
        } else {
            return false;
        }
        
        return !empty($signup);
    }

    /**
     * Get signup count for a specific shift
     */
    public function get_shift_signup_count($event_id, $shift_index) {
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
    public function get_event_volunteer_shifts($event_id) {
        $volunteer_shifts = get_post_meta($event_id, '_volunteer_shifts', true);
        
        if (empty($volunteer_shifts) || !is_array($volunteer_shifts)) {
            return array();
        }
        
        $formatted_shifts = array();
        
        foreach ($volunteer_shifts as $index => $shift) {
            $signup_count = $this->get_shift_signup_count($event_id, $index);
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
     * Render the content for the volunteer shifts admin column.
     *
     * @param string $column  The name of the column.
     * @param int    $post_id The ID of the post.
     */
    public function render_volunteer_shifts_column($column, $post_id) {
        if ($column === 'volunteer_shifts') {
            $volunteer_shifts = get_post_meta($post_id, '_volunteer_shifts', true);
            if (!empty($volunteer_shifts) && is_array($volunteer_shifts)) {
                $shift_count = count($volunteer_shifts);
                $total_signups = 0;
                for ($i = 0; $i < $shift_count; $i++) {
                    $total_signups += $this->get_shift_signup_count($post_id, $i);
                }
                printf(__('%d shifts (%d signups)', 'lcd-events'), $shift_count, $total_signups);
            } else {
                echo '—';
            }
        }
    }

    /**
     * AJAX handler for searching people (lcd_person CPT)
     */
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

    /**
     * AJAX handler for assigning a person to a shift
     */
    public function ajax_assign_person_to_shift() {
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
        $send_email = isset($_POST['send_email']) ? ($_POST['send_email'] === 'true' || $_POST['send_email'] === true) : true; // Default to true for backward compatibility
        $additional_message = isset($_POST['additional_message']) ? sanitize_textarea_field($_POST['additional_message']) : '';

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
            
            // Send confirmation email only if requested
            if ($send_email) {
                $volunteer_shifts = get_post_meta($event_id, '_volunteer_shifts', true);
                $shift_details = $volunteer_shifts[$shift_index] ?? [];
                
                $volunteer_email_data = [
                    'name' => $volunteer_name,
                    'email' => $volunteer_email,
                    'phone' => $volunteer_phone
                ];
                
                $this->send_volunteer_confirmation_email($event_id, $shift_details, $volunteer_email_data, $additional_message);
            }
            // Prepare HTML for the new signup item to send back to JS
            ob_start();
            ?>
            <tr class="signup-row status-confirmed" data-person-id="<?php echo esc_attr($person_id); ?>" data-signup-id="<?php echo esc_attr($signup_id); ?>">
                <td class="signup-name-cell">
                    <div class="signup-name"><?php echo esc_html($volunteer_name); ?></div>
                </td>
                <td class="signup-contact-cell">
                    <div class="signup-contact">
                        <div class="email"><?php echo esc_html($volunteer_email); ?></div>
                        <?php if (!empty($volunteer_phone)) : ?>
                            <div class="phone"><?php echo esc_html($volunteer_phone); ?></div>
                        <?php endif; ?>
                    </div>
                </td>
                <td class="signup-notes-cell">
                    <div class="signup-notes">
                        <div class="signup-notes-display">
                            <span class="signup-notes-text <?php echo empty($assignment_notes) ? 'no-notes' : ''; ?>">
                                <?php echo !empty($assignment_notes) ? esc_html($assignment_notes) : __('No notes', 'lcd-events'); ?>
                            </span>
                            <a class="signup-notes-edit-btn edit-notes" title="<?php esc_attr_e('Edit notes', 'lcd-events'); ?>">
                                <span class="dashicons dashicons-edit"></span>
                            </a>
                        </div>
                        <div class="signup-notes-edit" style="display: none;">
                            <textarea class="notes-edit-field" rows="2" placeholder="<?php esc_attr_e('Add notes for this assignment...', 'lcd-events'); ?>"><?php echo esc_textarea($assignment_notes ?? ''); ?></textarea>
                            <div class="notes-edit-actions">
                                <button type="button" class="button button-small save-notes"><?php _e('Save', 'lcd-events'); ?></button>
                                <button type="button" class="button button-small cancel-notes"><?php _e('Cancel', 'lcd-events'); ?></button>
                            </div>
                        </div>
                    </div>
                </td>
                <td class="signup-status-cell">
                    <span class="signup-status confirmed"><?php _e('Confirmed', 'lcd-events'); ?></span>
                </td>
                <td class="signup-date-cell">
                    <?php echo date_i18n('M j, Y', strtotime(current_time('mysql'))); ?>
                    <br>
                    <small><?php echo date_i18n('g:i A', strtotime(current_time('mysql'))); ?></small>
                </td>
                <td class="signup-actions-cell">
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
                </td>
            </tr>
            <?php
            $new_signup_html = ob_get_clean();
            wp_send_json_success(['message' => __('Volunteer assigned.', 'lcd-events'), 'new_signup_html' => $new_signup_html, 'signup_id' => $signup_id]);
        } else {
            wp_send_json_error(['message' => __('Could not save signup.', 'lcd-events'), 'db_error' => $wpdb->last_error], 500);
        }
    }

    /**
     * AJAX handler for unassigning a person from a shift
     */
    public function ajax_unassign_person_from_shift() {
        check_ajax_referer('lcd_event_unassign_person_from_shift', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Permission denied.', 'lcd-events')], 403);
            return;
        }

        $signup_id = isset($_POST['signup_id']) ? intval($_POST['signup_id']) : 0;
        $send_email = isset($_POST['send_email']) ? ($_POST['send_email'] === 'true' || $_POST['send_email'] === true) : true; // Default to true for backward compatibility
        $additional_message = isset($_POST['additional_message']) ? sanitize_textarea_field($_POST['additional_message']) : '';

        if (!$signup_id) {
            wp_send_json_error(['message' => __('Missing signup ID.', 'lcd-events')], 400);
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'lcd_volunteer_signups';

        // Get signup data before deleting for email
        $signup = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $signup_id
        ));

        $deleted = $wpdb->delete($table_name, ['id' => $signup_id], ['%d']);

        if ($deleted !== false) {
            // Send cancellation email if signup was found and email is requested
            if ($signup && $send_email) {
                $volunteer_shifts = get_post_meta($signup->event_id, '_volunteer_shifts', true);
                $shift_details = $volunteer_shifts[$signup->shift_index] ?? [];
                
                $volunteer_email_data = [
                    'name' => $signup->volunteer_name,
                    'email' => $signup->volunteer_email,
                    'phone' => $signup->volunteer_phone
                ];
                
                $this->send_volunteer_cancellation_email($signup->event_id, $shift_details, $volunteer_email_data, $additional_message);
            }
            
            wp_send_json_success(['message' => __('Volunteer unassigned.', 'lcd-events')]);
        } else {
            wp_send_json_error(['message' => __('Could not remove signup.', 'lcd-events'), 'db_error' => $wpdb->last_error], 500);
        }
    }

    /**
     * AJAX handler for editing volunteer notes
     */
    public function ajax_edit_volunteer_notes() {
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
    public function ajax_toggle_volunteer_confirmed() {
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
        $send_email = isset($_POST['send_email']) ? ($_POST['send_email'] === 'true' || $_POST['send_email'] === true) : false; // Default to false for confirmation toggle
        $additional_message = isset($_POST['additional_message']) ? sanitize_textarea_field($_POST['additional_message']) : '';
        
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
        
        // Send email if requested (only when confirming)
        if ($send_email && $confirmed) {
            $volunteer_shifts = get_post_meta($signup->event_id, '_volunteer_shifts', true);
            $shift_details = $volunteer_shifts[$signup->shift_index] ?? [];
            
            $volunteer_email_data = [
                'name' => $signup->volunteer_name,
                'email' => $signup->volunteer_email,
                'phone' => $signup->volunteer_phone
            ];
            
            $this->send_volunteer_confirmation_email($signup->event_id, $shift_details, $volunteer_email_data, $additional_message);
        }
        
        wp_send_json_success(array(
            'signup_id' => $signup_id,
            'confirmed' => $confirmed,
            'status' => $new_status,
            'message' => $confirmed ? __('Volunteer confirmed', 'lcd-events') : __('Volunteer unconfirmed', 'lcd-events')
        ));
    }

    /**
     * AJAX handler for exporting volunteers to CSV
     */
    public function ajax_export_volunteers_csv() {
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
        $all_signups = $this->get_volunteer_signups($event_id);
        
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

    /**
     * AJAX handler for exporting volunteers to PDF
     */
    public function ajax_export_volunteers_pdf() {
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
        $all_signups = $this->get_volunteer_signups($event_id);
        
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
     * Send confirmation email when volunteer is assigned
     */
    public function send_volunteer_confirmation_email($event_id, $shift_data, $volunteer_data, $additional_message = '') {
        $event = get_post($event_id);
        if (!$event) return false;
        
        $event_data = [
            'title' => $event->post_title,
            'date' => get_post_meta($event_id, '_event_date', true),
            'time' => get_post_meta($event_id, '_event_time', true),
            'location' => get_post_meta($event_id, '_event_location', true),
            'address' => get_post_meta($event_id, '_event_address', true),
            'url' => get_permalink($event_id)
        ];
        
        $additional_data = [
            'additional_message' => $additional_message
        ];
        
        // Delegate to email admin class
        if (class_exists('LCD_Volunteer_Email_Admin')) {
            $email_admin = LCD_Volunteer_Email_Admin::get_instance();
            return $email_admin->send_volunteer_email('volunteer_confirmation', $volunteer_data, $event_data, $shift_data, $additional_data);
        }
        return false;
    }

    /**
     * Send cancellation email when volunteer is removed
     */
    public function send_volunteer_cancellation_email($event_id, $shift_data, $volunteer_data, $additional_message = '') {
        $event = get_post($event_id);
        if (!$event) return false;
        
        $event_data = [
            'title' => $event->post_title,
            'date' => get_post_meta($event_id, '_event_date', true),
            'time' => get_post_meta($event_id, '_event_time', true),
            'location' => get_post_meta($event_id, '_event_location', true),
            'address' => get_post_meta($event_id, '_event_address', true),
            'url' => get_permalink($event_id)
        ];
        
        $additional_data = [
            'additional_message' => $additional_message
        ];
        
        // Delegate to email admin class
        if (class_exists('LCD_Volunteer_Email_Admin')) {
            $email_admin = LCD_Volunteer_Email_Admin::get_instance();
            return $email_admin->send_volunteer_email('volunteer_cancellation', $volunteer_data, $event_data, $shift_data, $additional_data);
        }
        return false;
    }

    /**
     * Send request received email when volunteer request is submitted
     */
    public function send_volunteer_request_received_email($event_id, $shift_data, $volunteer_data) {
        $event = get_post($event_id);
        if (!$event) return false;
        
        $event_data = [
            'title' => $event->post_title,
            'date' => get_post_meta($event_id, '_event_date', true),
            'time' => get_post_meta($event_id, '_event_time', true),
            'location' => get_post_meta($event_id, '_event_location', true),
            'address' => get_post_meta($event_id, '_event_address', true),
            'url' => get_permalink($event_id)
        ];
        
        // Delegate to email admin class
        if (class_exists('LCD_Volunteer_Email_Admin')) {
            $email_admin = LCD_Volunteer_Email_Admin::get_instance();
            return $email_admin->send_volunteer_email('volunteer_request_received', $volunteer_data, $event_data, $shift_data);
        }
        return false;
    }

    /**
     * Send request denied email when volunteer request is denied
     *
     * @param int $event_id Event ID
     * @param array $shift_data Shift information
     * @param array $volunteer_data Volunteer information
     * @param string $additional_message Custom message explaining why the request was denied
     */
    public function send_volunteer_request_denied_email($event_id, $shift_data, $volunteer_data, $additional_message = '') {
        $event = get_post($event_id);
        if (!$event) return false;
        
        $event_data = [
            'title' => $event->post_title,
            'date' => get_post_meta($event_id, '_event_date', true),
            'time' => get_post_meta($event_id, '_event_time', true),
            'location' => get_post_meta($event_id, '_event_location', true),
            'address' => get_post_meta($event_id, '_event_address', true),
            'url' => get_permalink($event_id)
        ];
        
        $additional_data = [
            'additional_message' => $additional_message ?: __('Unfortunately, the shift is already full or no longer available.', 'lcd-events')
        ];
        
        // Delegate to email admin class
        if (class_exists('LCD_Volunteer_Email_Admin')) {
            $email_admin = LCD_Volunteer_Email_Admin::get_instance();
            return $email_admin->send_volunteer_email('volunteer_request_denied', $volunteer_data, $event_data, $shift_data, $additional_data);
        }
        return false;
    }

    /**
     * Send reminder emails for volunteers with upcoming shifts
     */
    public function send_volunteer_reminders() {
        global $wpdb;
        
        $settings = get_option('lcd_volunteer_email_settings', []);
        $reminder_value = $settings['reminder_timing_value'] ?? 1;
        $reminder_unit = $settings['reminder_timing_unit'] ?? 'days';
        
        // Calculate the target date for reminders
        if ($reminder_unit === 'hours') {
            $target_datetime = date('Y-m-d H:i:s', strtotime("+{$reminder_value} hours"));
            $target_date = date('Y-m-d', strtotime("+{$reminder_value} hours"));
        } else {
            $target_datetime = date('Y-m-d H:i:s', strtotime("+{$reminder_value} days"));
            $target_date = date('Y-m-d', strtotime("+{$reminder_value} days"));
        }
        
        $table_name = $wpdb->prefix . 'lcd_volunteer_signups';
        
        // Get all volunteer shifts for the target date
        $shifts_for_target_date = $wpdb->get_results($wpdb->prepare(
            "SELECT vs.*, p.post_title as event_title 
             FROM $table_name vs 
             LEFT JOIN {$wpdb->posts} p ON vs.event_id = p.ID 
             WHERE vs.status = 'confirmed' 
             AND p.post_status = 'publish'
             AND EXISTS (
                 SELECT 1 FROM {$wpdb->postmeta} pm 
                 WHERE pm.post_id = vs.event_id 
                 AND pm.meta_key = '_volunteer_shifts'
                 AND pm.meta_value LIKE %s
             )",
            '%"date":"' . $target_date . '"%'
        ));
        
        $sent_count = 0;
        $failed_count = 0;
        
        foreach ($shifts_for_target_date as $signup) {
            $volunteer_shifts = get_post_meta($signup->event_id, '_volunteer_shifts', true);
            $shift_details = $volunteer_shifts[$signup->shift_index] ?? [];
            
            // Only send if this shift is actually on the target date
            if (($shift_details['date'] ?? '') === $target_date) {
                $volunteer_data = [
                    'name' => $signup->volunteer_name,
                    'email' => $signup->volunteer_email,
                    'phone' => $signup->volunteer_phone
                ];
                
                $event_data = [
                    'title' => $signup->event_title,
                    'date' => get_post_meta($signup->event_id, '_event_date', true),
                    'time' => get_post_meta($signup->event_id, '_event_time', true),
                    'location' => get_post_meta($signup->event_id, '_event_location', true),
                    'address' => get_post_meta($signup->event_id, '_event_address', true),
                    'url' => get_permalink($signup->event_id)
                ];
                
                // Delegate to email admin class
                $email_sent = false;
                if (class_exists('LCD_Volunteer_Email_Admin')) {
                    $email_admin = LCD_Volunteer_Email_Admin::get_instance();
                    $email_sent = $email_admin->send_volunteer_email('volunteer_reminder', $volunteer_data, $event_data, $shift_details);
                }
                
                if ($email_sent) {
                    $sent_count++;
                } else {
                    $failed_count++;
                }
            }
        }
        
        return [
            'sent' => $sent_count,
            'failed' => $failed_count,
            'total' => count($shifts_for_target_date)
        ];
    }



    /**
     * Setup cron job for sending reminder emails
     */
    public function setup_reminder_cron() {
        if (!wp_next_scheduled('lcd_send_volunteer_reminders')) {
            wp_schedule_event(strtotime('18:00:00'), 'daily', 'lcd_send_volunteer_reminders');
        }
        
        add_action('lcd_send_volunteer_reminders', [$this, 'send_volunteer_reminders']);
    }

    /**
     * Clear cron job for reminder emails
     */
    public function clear_reminder_cron() {
        wp_clear_scheduled_hook('lcd_send_volunteer_reminders');
    }

    /**
     * Get email sending statistics
     */
    public function get_email_stats() {
        $stats = get_option('lcd_volunteer_email_stats', [
            'confirmation_emails_sent' => 0,
            'cancellation_emails_sent' => 0,
            'reminder_emails_sent' => 0,
            'last_reminder_run' => null
        ]);
        
        return $stats;
    }

    /**
     * Update email statistics
     */
    private function update_email_stats($email_type, $increment = 1) {
        $stats = $this->get_email_stats();
        $stat_key = $email_type . '_emails_sent';
        
        if (isset($stats[$stat_key])) {
            $stats[$stat_key] += $increment;
            update_option('lcd_volunteer_email_stats', $stats);
        }
    }

    /**
     * AJAX handler for volunteer opportunity signup from frontend
     */
    public function ajax_volunteer_opportunity_signup() {
        $method = isset($_POST['method']) ? sanitize_text_field($_POST['method']) : '';
        
        // For logged-in users who came through the login flow, skip nonce check 
        // and rely on WordPress's built-in logged-in user verification
        if ($method === 'logged_in' && is_user_logged_in()) {
            // Additional security: verify the user actually just logged in by checking user capabilities
            if (!current_user_can('read')) {
                wp_send_json_error(['message' => __('Security check failed.', 'lcd-events')], 403);
                return;
            }
        } else {
            // For guest and account signups, verify nonce
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'lcd_volunteer_opportunities')) {
                wp_send_json_error(['message' => __('Security check failed.', 'lcd-events')], 403);
                return;
            }
        }

        $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
        $shift_index = isset($_POST['shift_index']) ? intval($_POST['shift_index']) : -1;
        $shift_title = isset($_POST['shift_title']) ? sanitize_text_field($_POST['shift_title']) : '';
        
        // Basic validation
        if (!$event_id || $shift_index < 0 || empty($method)) {
            wp_send_json_error(['message' => __('Missing required data.', 'lcd-events')], 400);
            return;
        }

        // Verify event and shift exist
        $event_post = get_post($event_id);
        if (!$event_post || $event_post->post_type !== 'event') {
            wp_send_json_error(['message' => __('Event not found.', 'lcd-events')], 404);
            return;
        }

        $volunteer_shifts = get_post_meta($event_id, '_volunteer_shifts', true);
        if (!is_array($volunteer_shifts) || !isset($volunteer_shifts[$shift_index])) {
            wp_send_json_error(['message' => __('Volunteer shift not found.', 'lcd-events')], 404);
            return;
        }

        $shift_details = $volunteer_shifts[$shift_index];

        // Check if shift is full
        $current_signups = $this->get_shift_signup_count($event_id, $shift_index);
        $max_volunteers = intval($shift_details['max_volunteers'] ?? 0);
        if ($max_volunteers > 0 && $current_signups >= $max_volunteers) {
            wp_send_json_error(['message' => __('This volunteer shift is full.', 'lcd-events')], 409);
            return;
        }

        // Handle different signup methods
        switch ($method) {
            case 'logged_in':
                $result = $this->handle_logged_in_signup($event_id, $shift_index, $shift_title, $shift_details);
                break;
                
            case 'guest':
                $result = $this->handle_guest_signup($event_id, $shift_index, $shift_title, $shift_details);
                break;
                
            case 'account':
                $result = $this->handle_account_signup($event_id, $shift_index, $shift_title, $shift_details);
                break;
                
            default:
                wp_send_json_error(['message' => __('Invalid signup method.', 'lcd-events')], 400);
                return;
        }

        if ($result['success']) {
            wp_send_json_success($result['data']);
        } else {
            wp_send_json_error($result['data']);
        }
    }

    /**
     * Handle signup for logged-in users
     */
    private function handle_logged_in_signup($event_id, $shift_index, $shift_title, $shift_details) {
        $user = wp_get_current_user();
        if (!$user || !$user->ID) {
            return [
                'success' => false,
                'data' => ['message' => __('You must be logged in to use this signup method.', 'lcd-events')]
            ];
        }

        // Try to find associated person record
        $person_id = null;
        $person_query = new WP_Query([
            'post_type' => 'lcd_person',
            'meta_query' => [
                [
                    'key' => '_lcd_person_user_id',
                    'value' => $user->ID,
                    'compare' => '='
                ]
            ],
            'posts_per_page' => 1
        ]);

        if ($person_query->have_posts()) {
            $person_post = $person_query->posts[0];
            $person_id = $person_post->ID;
            $volunteer_name = $person_post->post_title;
            $volunteer_email = get_post_meta($person_id, '_lcd_person_email', true) ?: $user->user_email;
            $volunteer_phone = get_post_meta($person_id, '_lcd_person_phone', true) ?: '';
        } else {
            // No person record found, use user data
            $volunteer_name = $user->display_name ?: $user->user_login;
            $volunteer_email = $user->user_email;
            $volunteer_phone = '';
        }

        wp_reset_postdata();

        // Check for duplicate signup
        if ($this->check_duplicate_signup($event_id, $shift_index, $volunteer_email, $person_id)) {
            return [
                'success' => false,
                'data' => ['message' => __('You are already signed up for this shift.', 'lcd-events')]
            ];
        }

        // Create signup record (unconfirmed - requires admin approval)
        $signup_result = $this->create_volunteer_signup([
            'event_id' => $event_id,
            'shift_index' => $shift_index,
            'shift_title' => $shift_title,
            'volunteer_name' => $volunteer_name,
            'volunteer_email' => $volunteer_email,
            'volunteer_phone' => $volunteer_phone,
            'volunteer_notes' => '',
            'user_id' => $user->ID,
            'person_id' => $person_id,
            'status' => 'unconfirmed'
        ]);

        if (!$signup_result) {
            return [
                'success' => false,
                'data' => ['message' => __('Failed to create signup record.', 'lcd-events')]
            ];
        }

        // Send request received email (not confirmation)
        $this->send_volunteer_request_received_email($event_id, $shift_details, [
            'name' => $volunteer_name,
            'email' => $volunteer_email,
            'phone' => $volunteer_phone
        ]);

        return [
            'success' => true,
            'data' => [
                'message' => __('Thank you for your volunteer request! We\'ve sent a confirmation to your email and will review your request shortly.', 'lcd-events')
            ]
        ];
    }

    /**
     * Handle guest signup (no account creation)
     */
    private function handle_guest_signup($event_id, $shift_index, $shift_title, $shift_details) {
        $first_name = sanitize_text_field($_POST['first_name'] ?? '');
        $last_name = sanitize_text_field($_POST['last_name'] ?? '');
        $volunteer_email = sanitize_email($_POST['email'] ?? '');
        $volunteer_phone = sanitize_text_field($_POST['phone'] ?? '');
        $volunteer_notes = sanitize_textarea_field($_POST['notes'] ?? '');

        // Combine first and last name for display
        $volunteer_name = trim($first_name . ' ' . $last_name);

        // Validation
        if (empty($first_name) || empty($last_name) || empty($volunteer_email)) {
            return [
                'success' => false,
                'data' => ['message' => __('First name, last name, and email are required.', 'lcd-events')]
            ];
        }

        if (!is_email($volunteer_email)) {
            return [
                'success' => false,
                'data' => ['message' => __('Please enter a valid email address.', 'lcd-events')]
            ];
        }

        // Check for duplicate signup
        if ($this->check_duplicate_signup($event_id, $shift_index, $volunteer_email)) {
            return [
                'success' => false,
                'data' => ['message' => __('This email address is already signed up for this shift.', 'lcd-events')]
            ];
        }

        // Create or update person record
        $person_id = $this->create_or_update_person_record($volunteer_name, $volunteer_email, $volunteer_phone, $first_name, $last_name);

        // Create signup record (unconfirmed - requires admin approval)
        $signup_result = $this->create_volunteer_signup([
            'event_id' => $event_id,
            'shift_index' => $shift_index,
            'shift_title' => $shift_title,
            'volunteer_name' => $volunteer_name,
            'volunteer_email' => $volunteer_email,
            'volunteer_phone' => $volunteer_phone,
            'volunteer_notes' => $volunteer_notes,
            'user_id' => null,
            'person_id' => $person_id,
            'status' => 'unconfirmed'
        ]);

        if (!$signup_result) {
            return [
                'success' => false,
                'data' => ['message' => __('Failed to create signup record.', 'lcd-events')]
            ];
        }

        // Send request received email (not confirmation)
        $this->send_volunteer_request_received_email($event_id, $shift_details, [
            'name' => $volunteer_name,
            'email' => $volunteer_email,
            'phone' => $volunteer_phone
        ]);

        return [
            'success' => true,
            'data' => [
                'message' => __('Thank you for your volunteer request! We\'ve sent a confirmation to your email and will review your request shortly.', 'lcd-events')
            ]
        ];
    }

    /**
     * Handle signup with account creation option
     */
    private function handle_account_signup($event_id, $shift_index, $shift_title, $shift_details) {
        $first_name = sanitize_text_field($_POST['first_name'] ?? '');
        $last_name = sanitize_text_field($_POST['last_name'] ?? '');
        $volunteer_email = sanitize_email($_POST['email'] ?? '');
        $volunteer_phone = sanitize_text_field($_POST['phone'] ?? '');
        $volunteer_notes = sanitize_textarea_field($_POST['notes'] ?? '');
        $create_account = isset($_POST['create_account']) && $_POST['create_account'];

        // Combine first and last name for display
        $volunteer_name = trim($first_name . ' ' . $last_name);

        // Validation
        if (empty($first_name) || empty($last_name) || empty($volunteer_email)) {
            return [
                'success' => false,
                'data' => ['message' => __('First name, last name, and email are required.', 'lcd-events')]
            ];
        }

        if (!is_email($volunteer_email)) {
            return [
                'success' => false,
                'data' => ['message' => __('Please enter a valid email address.', 'lcd-events')]
            ];
        }

        // Check for duplicate signup
        if ($this->check_duplicate_signup($event_id, $shift_index, $volunteer_email)) {
            return [
                'success' => false,
                'data' => ['message' => __('This email address is already signed up for this shift.', 'lcd-events')]
            ];
        }

        // Create or update person record
        $person_id = $this->create_or_update_person_record($volunteer_name, $volunteer_email, $volunteer_phone, $first_name, $last_name);

        // Create signup record (unconfirmed - requires admin approval)
        $signup_result = $this->create_volunteer_signup([
            'event_id' => $event_id,
            'shift_index' => $shift_index,
            'shift_title' => $shift_title,
            'volunteer_name' => $volunteer_name,
            'volunteer_email' => $volunteer_email,
            'volunteer_phone' => $volunteer_phone,
            'volunteer_notes' => $volunteer_notes,
            'user_id' => null,
            'person_id' => $person_id,
            'status' => 'unconfirmed'
        ]);

        if (!$signup_result) {
            return [
                'success' => false,
                'data' => ['message' => __('Failed to create signup record.', 'lcd-events')]
            ];
        }

        // Send request received email (not confirmation)
        $this->send_volunteer_request_received_email($event_id, $shift_details, [
            'name' => $volunteer_name,
            'email' => $volunteer_email,
            'phone' => $volunteer_phone
        ]);

        $response_data = [
            'message' => __('Thank you for your volunteer request! We\'ve sent a confirmation to your email and will review your request shortly.', 'lcd-events')
        ];

        // Handle account creation if requested
        if ($create_account && $person_id) {
            $account_result = $this->handle_account_creation_request($volunteer_email, $person_id);
            if ($account_result['account_info']) {
                $response_data['account_info'] = $account_result['account_info'];
            }
        }

        return [
            'success' => true,
            'data' => $response_data
        ];
    }

    /**
     * Check if user/email is already signed up for this shift
     */
    private function check_duplicate_signup($event_id, $shift_index, $email, $person_id = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'lcd_volunteer_signups';

        // Check by email first
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table_name WHERE event_id = %d AND shift_index = %d AND volunteer_email = %s",
            $event_id, $shift_index, $email
        ));

        if ($existing) {
            return true;
        }

        // If person_id is provided, also check by person_id
        if ($person_id) {
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM $table_name WHERE event_id = %d AND shift_index = %d AND person_id = %d",
                $event_id, $shift_index, $person_id
            ));

            if ($existing) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create or update person record
     */
    private function create_or_update_person_record($name, $email, $phone = '', $first_name = '', $last_name = '') {
        // Check if person already exists by email
        $existing_person = get_posts([
            'post_type' => 'lcd_person',
            'meta_query' => [
                [
                    'key' => '_lcd_person_email',
                    'value' => $email,
                    'compare' => '='
                ]
            ],
            'posts_per_page' => 1
        ]);

        if (!empty($existing_person)) {
            $person_id = $existing_person[0]->ID;
            
            // Update phone if provided and not already set
            $existing_phone = get_post_meta($person_id, '_lcd_person_phone', true);
            if (!empty($phone) && empty($existing_phone)) {
                update_post_meta($person_id, '_lcd_person_phone', $phone);
            }
            
            // Update first/last names if provided and not already set
            if (!empty($first_name)) {
                $existing_first_name = get_post_meta($person_id, '_lcd_person_first_name', true);
                if (empty($existing_first_name)) {
                    update_post_meta($person_id, '_lcd_person_first_name', $first_name);
                }
            }
            
            if (!empty($last_name)) {
                $existing_last_name = get_post_meta($person_id, '_lcd_person_last_name', true);
                if (empty($existing_last_name)) {
                    update_post_meta($person_id, '_lcd_person_last_name', $last_name);
                }
            }
            
            return $person_id;
        }

        // Create new person record
        $meta_input = [
            '_lcd_person_email' => $email,
            '_lcd_person_phone' => $phone,
            '_lcd_person_membership_status' => 'supporter'
        ];
        
        // Add first and last name if provided
        if (!empty($first_name)) {
            $meta_input['_lcd_person_first_name'] = $first_name;
        }
        if (!empty($last_name)) {
            $meta_input['_lcd_person_last_name'] = $last_name;
        }
        
        $person_data = [
            'post_title' => $name,
            'post_type' => 'lcd_person',
            'post_status' => 'publish',
            'meta_input' => $meta_input
        ];

        $person_id = wp_insert_post($person_data);
        
        return $person_id ?: null;
    }

    /**
     * Create volunteer signup record
     */
    private function create_volunteer_signup($data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'lcd_volunteer_signups';

        $signup_data = [
            'event_id' => $data['event_id'],
            'shift_index' => $data['shift_index'],
            'shift_title' => $data['shift_title'],
            'volunteer_name' => $data['volunteer_name'],
            'volunteer_email' => $data['volunteer_email'],
            'volunteer_phone' => $data['volunteer_phone'],
            'volunteer_notes' => $data['volunteer_notes'],
            'user_id' => $data['user_id'],
            'person_id' => $data['person_id'],
            'signup_date' => current_time('mysql'),
            'status' => $data['status']
        ];

        $format = ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s'];
        
        $result = $wpdb->insert($table_name, $signup_data, $format);
        
        return $result !== false;
    }

    /**
     * Handle account creation request - create account directly during volunteer signup
     */
    private function handle_account_creation_request($email, $person_id) {
        // Check if user already exists
        $existing_user = get_user_by('email', $email);
        
        if ($existing_user) {
            return [
                'account_info' => sprintf(
                    __('An account with this email already exists. You can <a href="%s">log in here</a>.', 'lcd-events'),
                    wp_login_url()
                )
            ];
        }

        // Check if the LCD People plugin is available
        if (!class_exists('LCD_People')) {
            return [
                'account_info' => __('Account creation is temporarily unavailable. Please contact us to set up your account.', 'lcd-events')
            ];
        }

        // Get the LCD People instance to check for existing person records
        $people_instance = null;
        if (method_exists('LCD_People', 'get_instance')) {
            $people_instance = call_user_func(array('LCD_People', 'get_instance'));
        }

        if (!$people_instance) {
            return [
                'account_info' => __('Account creation is temporarily unavailable. Please contact us to set up your account.', 'lcd-events')
            ];
        }

        // Get person data from the person record we created
        $person_post = get_post($person_id);
        if (!$person_post) {
            return [
                'account_info' => __('There was an issue with your information. Please try again.', 'lcd-events')
            ];
        }

        // Get person metadata
        $first_name = get_post_meta($person_id, '_lcd_person_first_name', true);
        $last_name = get_post_meta($person_id, '_lcd_person_last_name', true);
        $display_name = trim($first_name . ' ' . $last_name);
        
        if (empty($display_name)) {
            $display_name = $person_post->post_title;
        }

        // Generate a temporary password
        $temp_password = wp_generate_password(12, false);

        // Create user account
        $user_data = array(
            'user_login' => $email,
            'user_email' => $email,
            'user_pass' => $temp_password,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'display_name' => $display_name,
            'role' => 'subscriber'
        );

        $user_id = wp_insert_user($user_data);

        if (is_wp_error($user_id)) {
            return [
                'account_info' => __('Failed to create user account. Please contact us for assistance.', 'lcd-events')
            ];
        }

        // Connect user to person record using LCD People's method
        if (method_exists($people_instance, 'connect_user_to_person')) {
            $people_instance->connect_user_to_person($user_id, $person_id);
        } else {
            // Fallback: connect manually
            update_post_meta($person_id, '_lcd_person_user_id', $user_id);
            update_user_meta($user_id, 'lcd_person_id', $person_id);
        }

        // Set registration date if not already set
        $registration_date = get_post_meta($person_id, '_lcd_person_registration_date', true);
        if (empty($registration_date)) {
            update_post_meta($person_id, '_lcd_person_registration_date', current_time('Y-m-d'));
        }

        // Send password reset email instead of storing/sending temp password
        $reset_key = get_password_reset_key(get_user_by('ID', $user_id));
        if (!is_wp_error($reset_key)) {
            $reset_url = network_site_url("wp-login.php?action=rp&key=$reset_key&login=" . rawurlencode($email), 'login');
            
            // Send custom email with account creation info
            $site_name = get_bloginfo('name');
            $subject = sprintf(__('Your new account at %s', 'lcd-events'), $site_name);
            
            $message = sprintf(
                __('Hello %s!

Your account has been created at %s as part of your volunteer signup.

Username: %s

To set your password and access your account, please click the link below:
%s

This link will expire in 24 hours. If you need a new password reset link, you can request one at:
%s

Thank you for volunteering!

- %s', 'lcd-events'),
                $display_name,
                $site_name,
                $email,
                $reset_url,
                wp_lostpassword_url(),
                $site_name
            );

            wp_mail($email, $subject, $message);
        }

        return [
            'account_info' => sprintf(
                __('Your account has been created! We\'ve sent password setup instructions to %s. Please check your email to complete your account setup.', 'lcd-events'),
                $email
            )
        ];
    }

    /**
     * AJAX handler for modal login
     */
    public function ajax_volunteer_login() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'lcd_volunteer_opportunities')) {
            wp_send_json_error(['message' => __('Security check failed.', 'lcd-events')], 403);
            return;
        }

        $username = sanitize_text_field($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']) && $_POST['remember'];

        if (empty($username) || empty($password)) {
            wp_send_json_error(['message' => __('Please enter both username/email and password.', 'lcd-events')]);
            return;
        }

        // Attempt to log in the user
        $credentials = [
            'user_login' => $username,
            'user_password' => $password,
            'remember' => $remember
        ];

        $user = wp_signon($credentials, false);

        if (is_wp_error($user)) {
            $error_message = $user->get_error_message();
            
            // Customize common error messages for better UX
            if (strpos($error_message, 'Invalid username') !== false) {
                $error_message = __('Invalid email or username. Please check your credentials and try again.', 'lcd-events');
            } elseif (strpos($error_message, 'incorrect password') !== false) {
                $error_message = __('Incorrect password. Please try again.', 'lcd-events');
            }
            
            wp_send_json_error(['message' => $error_message]);
            return;
        }

        // Login successful
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, $remember);

        wp_send_json_success([
            'message' => sprintf(__('Welcome back, %s!', 'lcd-events'), $user->display_name),
            'user_id' => $user->ID,
            'user_name' => $user->display_name
        ]);
    }

    /**
     * AJAX handler for saving individual shift
     */
    public function ajax_save_individual_shift() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'lcd_save_individual_shift')) {
            wp_send_json_error(['message' => __('Security check failed.', 'lcd-events')], 403);
            return;
        }

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Permission denied.', 'lcd-events')], 403);
            return;
        }

        $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
        $shift_index = isset($_POST['shift_index']) ? intval($_POST['shift_index']) : -1;
        $shift_data = isset($_POST['shift_data']) ? $_POST['shift_data'] : array();

        if (!$event_id || $shift_index < 0) {
            wp_send_json_error(['message' => __('Missing required data.', 'lcd-events')], 400);
            return;
        }

        // Verify event exists and user can edit it
        $event_post = get_post($event_id);
        if (!$event_post || $event_post->post_type !== 'event') {
            wp_send_json_error(['message' => __('Event not found.', 'lcd-events')], 404);
            return;
        }

        if (!current_user_can('edit_post', $event_id)) {
            wp_send_json_error(['message' => __('Permission denied.', 'lcd-events')], 403);
            return;
        }

        // Get existing volunteer shifts
        $volunteer_shifts = get_post_meta($event_id, '_volunteer_shifts', true);
        if (!is_array($volunteer_shifts)) {
            $volunteer_shifts = array();
        }

        // Sanitize shift data
        if (!is_array($shift_data)) {
            wp_send_json_error(['message' => __('Invalid shift data format.', 'lcd-events')], 400);
            return;
        }

        $sanitized_shift = array(
            'title' => sanitize_text_field($shift_data['title'] ?? ''),
            'description' => sanitize_textarea_field($shift_data['description'] ?? ''),
            'date' => sanitize_text_field($shift_data['date'] ?? ''),
            'start_time' => sanitize_text_field($shift_data['start_time'] ?? ''),
            'end_time' => sanitize_text_field($shift_data['end_time'] ?? ''),
            'max_volunteers' => max(0, intval($shift_data['max_volunteers'] ?? 0))
        );

        // Validation
        if (empty($sanitized_shift['title'])) {
            wp_send_json_error(['message' => __('Shift title is required.', 'lcd-events')], 400);
            return;
        }

        // Update or add the shift
        $volunteer_shifts[$shift_index] = $sanitized_shift;

        // Save to database
        $updated = update_post_meta($event_id, '_volunteer_shifts', $volunteer_shifts);

        if ($updated !== false) {
            wp_send_json_success([
                'message' => __('Shift saved successfully.', 'lcd-events'),
                'shift_data' => $sanitized_shift
            ]);
        } else {
            // Check if the meta key exists and verify the data
            $existing_meta = get_post_meta($event_id, '_volunteer_shifts', true);
            
            // Check if our shift was actually saved (sometimes update_post_meta returns false when data is unchanged)
            if (is_array($existing_meta) && isset($existing_meta[$shift_index])) {
                $saved_shift = $existing_meta[$shift_index];
                if ($saved_shift['title'] === $sanitized_shift['title'] && 
                    $saved_shift['date'] === $sanitized_shift['date'] &&
                    $saved_shift['start_time'] === $sanitized_shift['start_time']) {
                    // Data was actually saved successfully
                    wp_send_json_success([
                        'message' => __('Shift saved successfully.', 'lcd-events'),
                        'shift_data' => $sanitized_shift
                    ]);
                    return;
                }
            }
            
            // Try a direct database approach as a fallback
            delete_post_meta($event_id, '_volunteer_shifts');
            $direct_update = add_post_meta($event_id, '_volunteer_shifts', $volunteer_shifts, true);
            
            if ($direct_update !== false) {
                wp_send_json_success([
                    'message' => __('Shift saved successfully.', 'lcd-events'),
                    'shift_data' => $sanitized_shift
                ]);
            } else {
                wp_send_json_error([
                    'message' => __('Could not save shift. Please try again.', 'lcd-events')
                ], 500);
            }
        }
    }
}