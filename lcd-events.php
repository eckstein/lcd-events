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

// Include volunteer shifts class
require_once LCD_EVENTS_PLUGIN_DIR . 'includes/class-lcd-volunteer-shifts.php';
LCD_Volunteer_Shifts::get_instance();

// Load admin functionality
require_once LCD_EVENTS_PLUGIN_DIR . 'admin/class-admin-loader.php';
LCD_Events_Admin_Loader::get_instance();

/**
 * Flush rewrite rules on plugin activation
 */
function lcd_events_activate() {
    // Register post type first
    lcd_register_events_post_type();
    
    // Create volunteer signups table
    LCD_Volunteer_Shifts::get_instance()->create_volunteer_signups_table();
    
    // Then flush rewrite rules
    flush_rewrite_rules();
    
    // Set transient for admin notice
    set_transient('lcd_events_activated', true, 5);
}
register_activation_hook(__FILE__, 'lcd_events_activate');

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
        
        // Check both slug and template assignment
        $is_volunteer_opportunities = false;
        
        if ($post) {
            // Check by slug
            if ($post->post_name === 'volunteer-opportunities') {
                $is_volunteer_opportunities = true;
            }
            
            // Check by template assignment
            if (get_page_template_slug($post->ID) === 'page-volunteer-opportunities.php') {
                $is_volunteer_opportunities = true;
            }
        }
        
        // Also check if we can find the page by path (handles query parameters better)
        if (!$is_volunteer_opportunities) {
            $volunteer_page = get_page_by_path('volunteer-opportunities');
            if ($volunteer_page && is_page($volunteer_page->ID)) {
                $is_volunteer_opportunities = true;
            }
        }
        
        if ($is_volunteer_opportunities) {
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
            array('jquery', 'select2', 'lcd-modal-system-admin'),
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
        
        // Check if this is the volunteer opportunities page (using same logic as template loading)
        $is_volunteer_opportunities = false;
        
        if ($post) {
            // Check by slug
            if ($post->post_name === 'volunteer-opportunities') {
                $is_volunteer_opportunities = true;
            }
            
            // Check by template assignment
            if (get_page_template_slug($post->ID) === 'page-volunteer-opportunities.php') {
                $is_volunteer_opportunities = true;
            }
        }
        
        // Also check if we can find the page by path (handles query parameters better)
        if (!$is_volunteer_opportunities) {
            $volunteer_page = get_page_by_path('volunteer-opportunities');
            if ($volunteer_page && is_page($volunteer_page->ID)) {
                $is_volunteer_opportunities = true;
            }
        }
        
        if ($is_volunteer_opportunities) {
            wp_enqueue_script(
                'lcd-volunteer-opportunities',
                LCD_EVENTS_PLUGIN_URL . 'js/volunteer-opportunities.js',
                array('jquery'),
                LCD_EVENTS_VERSION,
                true
            );
            
            // Localize script with AJAX data for volunteer signup functionality
            wp_localize_script('lcd-volunteer-opportunities', 'lcdVolunteerData', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('lcd_volunteer_opportunities'),
                'loginUrl' => wp_login_url(get_permalink()),
                'lostPasswordUrl' => wp_lostpassword_url(),
                'isLoggedIn' => is_user_logged_in(),
                'text' => array(
                    'signing_up' => __('Signing up...', 'lcd-events'),
                    'sign_up' => __('Sign Up', 'lcd-events'),
                    'error_signup' => __('Error signing up. Please try again.', 'lcd-events'),
                    'success_signup' => __('Successfully signed up!', 'lcd-events'),
                    'confirm_signup' => __('Are you sure you want to sign up for this volunteer shift?', 'lcd-events'),
                    'processing' => __('Processing...', 'lcd-events'),
                    'guest_signup' => __('Sign up as guest', 'lcd-events'),
                    'login_signup' => __('Login and sign up', 'lcd-events'),
                    'create_account_signup' => __('Create account and sign up', 'lcd-events'),
                    'name_required' => __('Name is required', 'lcd-events'),
                    'email_required' => __('Email is required', 'lcd-events'),
                    'invalid_email' => __('Please enter a valid email address', 'lcd-events'),
                    'phone_optional' => __('Phone number (optional)', 'lcd-events'),
                    'additional_notes' => __('Additional notes (optional)', 'lcd-events'),
                    'back' => __('Back', 'lcd-events'),
                    'submit' => __('Submit', 'lcd-events'),
                    'cancel' => __('Cancel', 'lcd-events'),
                    'close' => __('Close', 'lcd-events'),
                    'volunteer_signup_title' => __('Volunteer Sign-up', 'lcd-events'),
                    'how_signup' => __('How would you like to sign up?', 'lcd-events'),
                    'guest_signup_desc' => __('Quick signup without creating an account', 'lcd-events'),
                    'login_signup_desc' => __('Use your existing account', 'lcd-events'),
                    'create_account_desc' => __('Create an account for future signups', 'lcd-events'),
                    'first_name_required' => __('First Name *', 'lcd-events'),
                    'last_name_required' => __('Last Name *', 'lcd-events'),
                    'signup_success' => __('Signup Successful!', 'lcd-events'),
                    'signup_error' => __('Signup Error', 'lcd-events'),
                    'unexpected_error' => __('An unexpected error occurred. Please try again.', 'lcd-events'),
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
 * Wrapper function for backward compatibility
 * Get formatted volunteer shifts for an event (for frontend use)
 */
function lcd_get_event_volunteer_shifts($event_id) {
    return LCD_Volunteer_Shifts::get_instance()->get_event_volunteer_shifts($event_id);
}

/**
 * Wrapper function for backward compatibility
 * Get volunteer signups for an event
 */
function lcd_get_volunteer_signups($event_id) {
    return LCD_Volunteer_Shifts::get_instance()->get_volunteer_signups($event_id);
}

/**
 * Get volunteer signups for a specific user
 */
function lcd_get_user_volunteer_signups($user_id = null, $email = null) {
    return LCD_Volunteer_Shifts::get_instance()->get_user_volunteer_signups($user_id, $email);
}

/**
 * Check if a user is signed up for a specific shift
 */
function lcd_is_user_signed_up_for_shift($event_id, $shift_index, $user_id = null, $email = null) {
    return LCD_Volunteer_Shifts::get_instance()->is_user_signed_up_for_shift($event_id, $shift_index, $user_id, $email);
}




