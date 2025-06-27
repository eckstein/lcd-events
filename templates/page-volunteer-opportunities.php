<?php
/**
 * Template for displaying volunteer opportunities page
 * 
 * This template can be used by creating a page with the slug "volunteer-opportunities"
 * or by assigning this template to any page.
 *
 * @package LCD_Events
 */

get_header();
?>

<main id="primary" class="site-main page-template volunteer-opportunities">
    <?php 
    // Get the current page if we're on a page with this template
    $current_page = null;
    if (is_page()) {
        $current_page = get_queried_object();
    } else {
        // Try to find a page with volunteer-opportunities slug
        $current_page = get_page_by_path('volunteer-opportunities');
    }
    
    // Set up post data for the page if we found one
    if ($current_page) {
        $GLOBALS['post'] = $current_page;
        setup_postdata($current_page);
    }
    ?>
    
    <article id="post-<?php echo $current_page ? $current_page->ID : 'volunteer-opportunities'; ?>" class="page volunteer-opportunities-page">
        <header class="entry-header<?php echo ($current_page && has_post_thumbnail($current_page->ID)) ? ' has-featured-image' : ''; ?>">
            <?php if ($current_page && has_post_thumbnail($current_page->ID)) : ?>
                <div class="featured-image">
                    <?php echo get_the_post_thumbnail($current_page->ID, 'full'); ?>
                </div>
            <?php endif; ?>
            
            <div class="entry-header-content">
                <?php if (!is_front_page()) : ?>
                    <div class="breadcrumbs">
                        <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'lcd-theme'); ?></a>
                        <span class="separator"> › </span>
                        <?php
                        if ($current_page && $current_page->post_parent) {
                            $ancestors = get_post_ancestors($current_page->ID);
                            $ancestors = array_reverse($ancestors);
                            foreach ($ancestors as $ancestor) {
                                $ancestor_post = get_post($ancestor);
                                echo '<a href="' . get_permalink($ancestor) . '">' . esc_html($ancestor_post->post_title) . '</a>';
                                echo '<span class="separator"> › </span>';
                            }
                        }
                        ?>
                        <span class="current">
                            <?php 
                            if ($current_page) {
                                echo esc_html($current_page->post_title);
                            } else {
                                _e('Volunteer Opportunities', 'lcd-events');
                            }
                            ?>
                        </span>
                    </div>
                <?php endif; ?>
                
                <h1 class="entry-title">
                    <?php 
                    if ($current_page) {
                        echo esc_html($current_page->post_title);
                    } else {
                        _e('Volunteer Opportunities', 'lcd-events');
                    }
                    ?>
                </h1>
                
               
            </div>
        </header>

        <div class="content-wrapper full-width-content">
            <div class="entry-content">
                <?php
                // Display page content if available
                if ($current_page && $current_page->post_content) {
                    echo apply_filters('the_content', $current_page->post_content);
                }
                
                // Get current date and time
                $today = date('Y-m-d');
                $current_datetime = current_time('mysql');
                
                // Get the selected event filter from URL parameter
                $selected_event_id = isset($_GET['event_filter']) ? intval($_GET['event_filter']) : 0;
                // Get current user info for signup checking
                $current_user_id = get_current_user_id();
                $current_user_email = null;
                if ($current_user_id) {
                    $current_user = wp_get_current_user();
                    $current_user_email = $current_user->user_email;
                }
                
                // Single query for events with volunteer shifts
                $args = array(
                    'post_type' => 'event',
                    'posts_per_page' => -1,
                    'meta_key' => '_event_date',
                    'orderby' => 'meta_value',
                    'order' => 'ASC',
                    'meta_query' => array(
                        'relation' => 'AND',
                        array(
                            'key' => '_event_date',
                            'value' => $today,
                            'compare' => '>=',
                            'type' => 'DATE'
                        ),
                        array(
                            'key' => '_volunteer_shifts',
                            'compare' => 'EXISTS'
                        )
                    )
                );
                
                // If a specific event is selected, add it to the query
                if ($selected_event_id > 0) {
                    $args['p'] = $selected_event_id;
                }
                
                $events_query = new WP_Query($args);
                
                // Process events for both filter dropdown and opportunities
                $available_events = array();
                $all_opportunities = array();
                
                if ($events_query->have_posts()) {
                    while ($events_query->have_posts()) {
                        $events_query->the_post();
                        $event_id = get_the_ID();
                        $shifts = lcd_get_event_volunteer_shifts($event_id);
                        
                        if (!empty($shifts)) {
                            $event_date = get_post_meta($event_id, '_event_date', true);
                            $event_location = get_post_meta($event_id, '_event_location', true);
                            $event_address = get_post_meta($event_id, '_event_address', true);
                            
                            // Track if this event has upcoming shifts for filter dropdown
                            $has_upcoming_shifts = false;
                            
                            foreach ($shifts as $shift) {
                                $shift_datetime = $shift['date'] . ' ' . ($shift['start_time'] ?: '00:00:00');
                                if (strtotime($shift_datetime) >= strtotime($current_datetime)) {
                                    $has_upcoming_shifts = true;
                                    
                                    // Check if current user is already signed up for this shift
                                    $user_is_signed_up = false;
                                    if ($current_user_id || $current_user_email) {
                                        $user_is_signed_up = lcd_is_user_signed_up_for_shift(
                                            $event_id, 
                                            $shift['index'], 
                                            $current_user_id, 
                                            $current_user_email
                                        );
                                    }
                                    
                                    $all_opportunities[] = array(
                                        'event_id' => $event_id,
                                        'event_title' => get_the_title(),
                                        'event_permalink' => get_permalink(),
                                        'event_date' => $event_date,
                                        'event_location' => $event_location,
                                        'event_address' => $event_address,
                                        'shift_index' => $shift['index'],
                                        'shift_title' => $shift['title'],
                                        'shift_description' => $shift['description'],
                                        'shift_date' => $shift['date'],
                                        'shift_start_time' => $shift['start_time'],
                                        'shift_end_time' => $shift['end_time'],
                                        'shift_formatted_date' => $shift['formatted_date'],
                                        'shift_formatted_start_time' => $shift['formatted_start_time'],
                                        'shift_formatted_end_time' => $shift['formatted_end_time'],
                                        'max_volunteers' => $shift['max_volunteers'],
                                        'signup_count' => $shift['signup_count'],
                                        'spots_remaining' => $shift['spots_remaining'],
                                        'is_full' => $shift['is_full'],
                                        'shift_datetime' => $shift_datetime,
                                        'user_is_signed_up' => $user_is_signed_up
                                    );
                                }
                            }
                            
                            // Add to available events for filter dropdown (only if no specific event selected)
                            if ($selected_event_id == 0 && $has_upcoming_shifts) {
                                $available_events[] = array(
                                    'id' => $event_id,
                                    'title' => get_the_title(),
                                    'date' => $event_date
                                );
                            }
                        }
                    }
                    wp_reset_postdata();
                }
                
                // If filtering by specific event, we need to get all events for the dropdown
                if ($selected_event_id > 0) {
                    $all_events_args = array(
                        'post_type' => 'event',
                        'posts_per_page' => -1,
                        'meta_key' => '_event_date',
                        'orderby' => 'meta_value',
                        'order' => 'ASC',
                        'meta_query' => array(
                            'relation' => 'AND',
                            array(
                                'key' => '_event_date',
                                'value' => $today,
                                'compare' => '>=',
                                'type' => 'DATE'
                            ),
                            array(
                                'key' => '_volunteer_shifts',
                                'compare' => 'EXISTS'
                            )
                        )
                    );
                    
                    $all_events_query = new WP_Query($all_events_args);
                    if ($all_events_query->have_posts()) {
                        while ($all_events_query->have_posts()) {
                            $all_events_query->the_post();
                            $event_id = get_the_ID();
                            $shifts = lcd_get_event_volunteer_shifts($event_id);
                            
                            if (!empty($shifts)) {
                                // Check if this event has any upcoming shifts
                                $has_upcoming_shifts = false;
                                foreach ($shifts as $shift) {
                                    $shift_datetime = $shift['date'] . ' ' . ($shift['start_time'] ?: '00:00:00');
                                    if (strtotime($shift_datetime) >= strtotime($current_datetime)) {
                                        $has_upcoming_shifts = true;
                                        break;
                                    }
                                }
                                
                                if ($has_upcoming_shifts) {
                                    $available_events[] = array(
                                        'id' => $event_id,
                                        'title' => get_the_title(),
                                        'date' => get_post_meta($event_id, '_event_date', true)
                                    );
                                }
                            }
                        }
                        wp_reset_postdata();
                    }
                }
                
                // Sort all opportunities by shift date/time
                usort($all_opportunities, function($a, $b) {
                    return strtotime($a['shift_datetime']) - strtotime($b['shift_datetime']);
                });
                ?>

                <?php if (!empty($available_events)) : ?>
                    <div class="volunteer-opportunities-table-container">
                        <div class="opportunities-filter">
                            <form method="GET" class="event-filter-form">
                                <div class="filter-group">
                                    <label for="event-filter" class="filter-label">
                                        <span class="dashicons dashicons-filter"></span>
                                        <?php _e('Filter by Event:', 'lcd-events'); ?>
                                    </label>
                                    <select id="event-filter" name="event_filter" class="event-filter-select">
                                        <option value=""><?php _e('All Events', 'lcd-events'); ?></option>
                                        <?php foreach ($available_events as $event) : ?>
                                            <option value="<?php echo esc_attr($event['id']); ?>" <?php selected($selected_event_id, $event['id']); ?>>
                                                <?php 
                                                echo esc_html($event['title']);
                                                if (!empty($event['date'])) {
                                                    echo ' - ' . date_i18n(get_option('date_format'), strtotime($event['date']));
                                                }
                                                ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <?php if (!empty($all_opportunities)) : ?>
                                    <div class="opportunities-count">
                                        <?php 
                                        if ($selected_event_id > 0) {
                                            // Get the selected event title for display
                                            $selected_event_title = '';
                                            foreach ($available_events as $event) {
                                                if ($event['id'] == $selected_event_id) {
                                                    $selected_event_title = $event['title'];
                                                    break;
                                                }
                                            }
                                            if ($selected_event_title) {
                                                printf(
                                                    _n('%d volunteer opportunity for <strong>%s</strong>', '%d volunteer opportunities for <strong>%s</strong>', count($all_opportunities), 'lcd-events'),
                                                    count($all_opportunities),
                                                    esc_html($selected_event_title)
                                                );
                                            } else {
                                                printf(
                                                    _n('%d volunteer opportunity found', '%d volunteer opportunities found', count($all_opportunities), 'lcd-events'),
                                                    count($all_opportunities)
                                                );
                                            }
                                        } else {
                                            printf(
                                                _n('%d volunteer opportunity available', '%d volunteer opportunities available', count($all_opportunities), 'lcd-events'),
                                                count($all_opportunities)
                                            );
                                        }
                                        ?>
                                    </div>
                                <?php endif; ?>
                            </form>
                        </div>
                        
                        <?php if (!empty($all_opportunities)) : ?>
                        <div class="table-responsive">
                            <table class="volunteer-opportunities-table">
                                <thead>
                                    <tr>
                                        <th class="col-event"><?php _e('Event', 'lcd-events'); ?></th>
                                        <th class="col-shift"><?php _e('Volunteer Role', 'lcd-events'); ?></th>
                                        <th class="col-date"><?php _e('Date & Time', 'lcd-events'); ?></th>
                                        <th class="col-location"><?php _e('Location', 'lcd-events'); ?></th>
                                        <th class="col-capacity"><?php _e('Capacity', 'lcd-events'); ?></th>
                                        <th class="col-status"><?php _e('Status', 'lcd-events'); ?></th>
                                        <th class="col-actions"><?php _e('Actions', 'lcd-events'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($all_opportunities as $opportunity) : ?>
                                        <?php 
                                        $row_classes = ['opportunity-row'];
                                        if ($opportunity['user_is_signed_up']) {
                                            $row_classes[] = 'row-signed-up';
                                        } elseif ($opportunity['is_full']) {
                                            $row_classes[] = 'row-full';
                                        } else {
                                            $row_classes[] = 'row-available';
                                        }
                                        ?>
                                        <tr class="<?php echo implode(' ', $row_classes); ?>">
                                            <td class="col-event">
                                                <div class="event-info">
                                                    <h4 class="event-title">
                                                        <a href="<?php echo esc_url($opportunity['event_permalink']); ?>" class="event-link">
                                                            <?php echo esc_html($opportunity['event_title']); ?>
                                                        </a>
                                                    </h4>
                                                    <div class="event-date">
                                                        <?php 
                                                        if (!empty($opportunity['event_date'])) {
                                                            echo date_i18n(get_option('date_format'), strtotime($opportunity['event_date']));
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                            </td>
                                            
                                            <td class="col-shift">
                                                <div class="shift-info">
                                                    <h5 class="shift-title"><?php echo esc_html($opportunity['shift_title']); ?></h5>
                                                    <?php if (!empty($opportunity['shift_description'])) : ?>
                                                        <div class="shift-description" title="<?php echo esc_attr($opportunity['shift_description']); ?>">
                                                            <?php 
                                                            $description = $opportunity['shift_description'];
                                                            echo esc_html(strlen($description) > 80 ? substr($description, 0, 77) . '...' : $description);
                                                            ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            
                                            <td class="col-date">
                                                <div class="datetime-info">
                                                    <div class="shift-date">
                                                        <?php echo esc_html($opportunity['shift_formatted_date']); ?>
                                                    </div>
                                                    <?php if (!empty($opportunity['shift_formatted_start_time'])) : ?>
                                                        <div class="shift-time">
                                                            <?php 
                                                            if (!empty($opportunity['shift_formatted_end_time'])) {
                                                                echo esc_html($opportunity['shift_formatted_start_time']) . ' - ' . esc_html($opportunity['shift_formatted_end_time']);
                                                            } else {
                                                                echo esc_html($opportunity['shift_formatted_start_time']);
                                                            }
                                                            ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            
                                            <td class="col-location">
                                                <?php if (!empty($opportunity['event_location']) || !empty($opportunity['event_address'])) : ?>
                                                    <?php 
                                                    // Use address for map query if available, otherwise use location name
                                                    $map_query = !empty($opportunity['event_address']) ? $opportunity['event_address'] : $opportunity['event_location'];
                                                    $display_location = $opportunity['event_location'];
                                                    $display_address = $opportunity['event_address'];
                                                    ?>
                                                    <a href="https://www.google.com/maps/search/?api=1&query=<?php echo urlencode($map_query); ?>" 
                                                       target="_blank" 
                                                       rel="noopener noreferrer" 
                                                       class="location-link"
                                                       title="<?php printf(__('View on map: %s', 'lcd-events'), esc_attr($map_query)); ?>">
                                                        <span class="dashicons dashicons-location-alt"></span>
                                                        <div class="location-info">
                                                            <?php if (!empty($display_location)) : ?>
                                                                <span class="location-name"><?php echo esc_html($display_location); ?></span>
                                                            <?php endif; ?>
                                                            <?php if (!empty($display_address) && $display_address !== $display_location) : ?>
                                                                <span class="location-address"><?php echo esc_html($display_address); ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </a>
                                                <?php else : ?>
                                                    <span class="location-tbd"><?php _e('TBD', 'lcd-events'); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <td class="col-capacity">
                                                <div class="capacity-info">
                                                    <?php if ($opportunity['max_volunteers'] > 0) : ?>
                                                        <div class="capacity-numbers">
                                                            <span class="current-count"><?php echo $opportunity['signup_count']; ?></span>
                                                            <span class="capacity-separator">/</span>
                                                            <span class="max-count"><?php echo $opportunity['max_volunteers']; ?></span>
                                                        </div>
                                                        <?php if ($opportunity['spots_remaining'] > 0) : ?>
                                                            <div class="spots-remaining">
                                                                <?php printf(_n('%d spot left', '%d spots left', $opportunity['spots_remaining'], 'lcd-events'), $opportunity['spots_remaining']); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    <?php else : ?>
                                                        <div class="capacity-unlimited">
                                                            <span class="current-count"><?php echo $opportunity['signup_count']; ?></span>
                                                            <span class="unlimited-label"><?php _e('(unlimited)', 'lcd-events'); ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            
                                            <td class="col-status">
                                                <?php if ($opportunity['is_full']) : ?>
                                                    <span class="status-badge status-full">
                                                        <span class="dashicons dashicons-no"></span>
                                                        <?php _e('Full', 'lcd-events'); ?>
                                                    </span>
                                                <?php else : ?>
                                                    <span class="status-badge status-available">
                                                        <span class="dashicons dashicons-yes"></span>
                                                        <?php _e('Available', 'lcd-events'); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <td class="col-actions">
                                                <div class="action-buttons">
                                                    <?php if ($opportunity['user_is_signed_up']) : ?>
                                                        <button class="btn btn-success" disabled>
                                                            <span class="dashicons dashicons-yes"></span>
                                                            <?php _e('Signed Up', 'lcd-events'); ?>
                                                        </button>
                                                    <?php elseif ($opportunity['is_full']) : ?>
                                                        <button class="btn btn-disabled" disabled>
                                                            <span class="dashicons dashicons-no"></span>
                                                            <?php _e('Full', 'lcd-events'); ?>
                                                        </button>
                                                    <?php else : ?>
                                                        <button class="btn btn-primary volunteer-signup-btn" 
                                                                data-event-id="<?php echo esc_attr($opportunity['event_id']); ?>"
                                                                data-shift-index="<?php echo esc_attr($opportunity['shift_index']); ?>"
                                                                data-shift-title="<?php echo esc_attr($opportunity['shift_title']); ?>">
                                                            <span class="dashicons dashicons-plus"></span>
                                                            <?php _e('Sign Up', 'lcd-events'); ?>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php else : ?>
                    <div class="no-opportunities-message">
                        <div class="message-icon">
                            <span class="dashicons dashicons-calendar-alt"></span>
                        </div>
                        <?php if ($selected_event_id > 0) : ?>
                            <?php 
                            // Get the selected event title for a more specific message
                            $selected_event_title = '';
                            foreach ($available_events as $event) {
                                if ($event['id'] == $selected_event_id) {
                                    $selected_event_title = $event['title'];
                                    break;
                                }
                            }
                            if (!$selected_event_title && $selected_event_id > 0) {
                                $selected_event_post = get_post($selected_event_id);
                                if ($selected_event_post) {
                                    $selected_event_title = $selected_event_post->post_title;
                                }
                            }
                            ?>
                            <h3><?php _e('No Volunteer Opportunities for This Event', 'lcd-events'); ?></h3>
                            <p>
                                <?php if ($selected_event_title) : ?>
                                    <?php printf(__('"%s" currently has no upcoming volunteer opportunities.', 'lcd-events'), esc_html($selected_event_title)); ?>
                                <?php else : ?>
                                    <?php _e('This event currently has no upcoming volunteer opportunities.', 'lcd-events'); ?>
                                <?php endif; ?>
                                <br><br>
                                <a href="<?php echo esc_url(remove_query_arg('event_filter')); ?>"><?php _e('View all volunteer opportunities', 'lcd-events'); ?></a>
                                <?php _e(' or ', 'lcd-events'); ?>
                                <a href="<?php echo get_post_type_archive_link('event'); ?>"><?php _e('view our upcoming events', 'lcd-events'); ?></a>.
                            </p>
                        <?php else : ?>
                            <h3><?php _e('No Volunteer Opportunities Available', 'lcd-events'); ?></h3>
                            <p><?php _e('There are currently no upcoming volunteer opportunities. Please check back later or', 'lcd-events'); ?> 
                               <a href="<?php echo get_post_type_archive_link('event'); ?>"><?php _e('view our upcoming events', 'lcd-events'); ?></a>.
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </article>
    
    <?php
    // Reset post data if we set it up
    if ($current_page) {
        wp_reset_postdata();
    }
    ?>
</main>

<?php
get_footer();
?> 