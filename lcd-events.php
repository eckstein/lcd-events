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
            <label for="event_time"><?php _e('Start Time:', 'lcd-events'); ?></label><br>
            <input type="time" id="event_time" name="event_time" value="<?php echo esc_attr($event_time); ?>" class="widefat">
        </p>
        <p>
            <label for="event_end_time"><?php _e('End Time:', 'lcd-events'); ?></label><br>
            <input type="time" id="event_end_time" name="event_end_time" value="<?php echo esc_attr($event_end_time); ?>" class="widefat">
        </p>
        <p>
            <label for="event_location"><?php _e('Location Name:', 'lcd-events'); ?></label><br>
            <input type="text" id="event_location" name="event_location" value="<?php echo esc_attr($event_location); ?>" class="widefat">
        </p>
        <p>
            <label for="event_address"><?php _e('Address:', 'lcd-events'); ?></label><br>
            <textarea id="event_address" name="event_address" class="widefat" rows="3"><?php echo esc_textarea($event_address); ?></textarea>
        </p>
        <p>
            <label for="event_map_link"><?php _e('Map Link:', 'lcd-events'); ?></label><br>
            <input type="url" id="event_map_link" name="event_map_link" value="<?php echo esc_url($event_map_link); ?>" class="widefat">
        </p>
        <p>
            <label for="event_registration_url"><?php _e('Registration URL:', 'lcd-events'); ?></label><br>
            <input type="url" id="event_registration_url" name="event_registration_url" value="<?php echo esc_url($event_registration_url); ?>" class="widefat">
        </p>
        <p>
            <label for="event_button_text"><?php _e('Registration Button Text:', 'lcd-events'); ?></label><br>
            <input type="text" id="event_button_text" name="event_button_text" value="<?php echo esc_attr($event_button_text); ?>" class="widefat" placeholder="Register Now">
        </p>
        <p>
            <label for="event_ticketing_notes"><?php _e('Ticketing Notes:', 'lcd-events'); ?></label><br>
            <textarea id="event_ticketing_notes" name="event_ticketing_notes" class="widefat" rows="3" placeholder="Enter any additional notes about registration or ticketing"><?php echo esc_textarea($event_ticketing_notes); ?></textarea>
            <span class="description"><?php _e('This text will appear below the registration button.', 'lcd-events'); ?></span>
        </p>
        <p>
            <label for="event_capacity"><?php _e('Capacity:', 'lcd-events'); ?></label><br>
            <input type="number" id="event_capacity" name="event_capacity" value="<?php echo esc_attr($event_capacity); ?>" class="widefat">
        </p>
        <p>
            <label for="event_cost"><?php _e('Event Cost:', 'lcd-events'); ?></label><br>
            <input type="text" id="event_cost" name="event_cost" value="<?php echo esc_attr($event_cost); ?>" class="widefat" placeholder="Free or enter amount">
        </p>
        <p>
            <label><?php _e('Event Poster:', 'lcd-events'); ?></label><br>
            <div class="event-poster-preview" style="margin-bottom: 10px;">
                <?php if ($event_poster_id) : ?>
                    <?php echo wp_get_attachment_image($event_poster_id, 'medium'); ?>
                <?php endif; ?>
            </div>
            <input type="hidden" id="event_poster" name="event_poster" value="<?php echo esc_attr($event_poster_id); ?>">
            <button type="button" class="button event-poster-upload"><?php _e('Upload Poster', 'lcd-events'); ?></button>
            <?php if ($event_poster_id) : ?>
                <button type="button" class="button event-poster-remove"><?php _e('Remove Poster', 'lcd-events'); ?></button>
            <?php endif; ?>
        </p>
    </div>

    <script>
    jQuery(document).ready(function($) {
        var frame;
        $('.event-poster-upload').on('click', function(e) {
            e.preventDefault();

            if (frame) {
                frame.open();
                return;
            }

            frame = wp.media({
                title: '<?php _e('Select or Upload Event Poster', 'lcd-events'); ?>',
                button: {
                    text: '<?php _e('Use this image', 'lcd-events'); ?>'
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
    });
    </script>
    <?php
}

/**
 * Save Event Meta Box Data
 */
function lcd_save_event_meta($post_id) {
    if (!isset($_POST['lcd_event_details_nonce'])) {
        return;
    }

    if (!wp_verify_nonce($_POST['lcd_event_details_nonce'], 'lcd_event_details')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

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
function lcd_events_admin_scripts() {
    wp_enqueue_media();
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