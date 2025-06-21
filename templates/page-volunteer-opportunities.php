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

<main id="primary" class="site-main volunteer-opportunities">
    <header class="page-header">
        <div class="container">
            <div class="header-icon">
                <span class="dashicons dashicons-groups"></span>
            </div>
            <h1 class="page-title"><?php _e('Volunteer Opportunities', 'lcd-events'); ?></h1>
            <p class="page-description"><?php _e('Join us in making a difference! Discover and sign up for upcoming volunteer shifts.', 'lcd-events'); ?></p>
        </div>
    </header>

    <div class="container">
        <?php
        // Get current date and time
        $today = date('Y-m-d');
        $current_datetime = current_time('mysql');
        
        // Query for upcoming events that have volunteer shifts
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
        
        $events_query = new WP_Query($args);
        
        // Collect events with their volunteer shifts
        $events_with_shifts = array();
        
        if ($events_query->have_posts()) {
            while ($events_query->have_posts()) {
                $events_query->the_post();
                $event_id = get_the_ID();
                $shifts = lcd_get_event_volunteer_shifts($event_id);
                
                if (!empty($shifts)) {
                    // Filter to only future shifts
                    $future_shifts = array();
                    foreach ($shifts as $shift) {
                        $shift_datetime = $shift['date'] . ' ' . ($shift['start_time'] ?: '00:00:00');
                        if (strtotime($shift_datetime) >= strtotime($current_datetime)) {
                            $future_shifts[] = $shift;
                        }
                    }
                    
                    if (!empty($future_shifts)) {
                        $event_date = get_post_meta($event_id, '_event_date', true);
                        $events_with_shifts[] = array(
                            'event_id' => $event_id,
                            'event_title' => get_the_title(),
                            'event_permalink' => get_permalink(),
                            'event_date' => $event_date,
                            'event_location' => get_post_meta($event_id, '_event_location', true),
                            'shifts' => $future_shifts
                        );
                    }
                }
            }
            wp_reset_postdata();
        }
        
        // Sort events by date
        usort($events_with_shifts, function($a, $b) {
            return strtotime($a['event_date']) - strtotime($b['event_date']);
        });
        ?>

        <?php if (!empty($events_with_shifts)) : ?>
            <div class="volunteer-events-container">
                <?php foreach ($events_with_shifts as $event) : 
                    $formatted_event_date = !empty($event['event_date']) ? date_i18n(get_option('date_format'), strtotime($event['event_date'])) : '';
                    ?>
                    <section class="event-volunteer-section">
                        <header class="event-section-header">
                            <h2 class="event-section-title">
                                <a href="<?php echo esc_url($event['event_permalink']); ?>" class="event-title-link">
                                    <?php echo esc_html($event['event_title']); ?>
                                </a>
                            </h2>
                            <div class="event-section-meta">
                                <?php if ($formatted_event_date) : ?>
                                    <span class="event-section-date">
                                        <span class="dashicons dashicons-calendar-alt"></span>
                                        <?php echo esc_html($formatted_event_date); ?>
                                    </span>
                                <?php endif; ?>
                                <?php if (!empty($event['event_location'])) : ?>
                                    <span class="event-section-location">
                                        <span class="dashicons dashicons-location"></span>
                                        <?php echo esc_html($event['event_location']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </header>

                        <div class="volunteer-shifts-container">
                            <?php foreach ($event['shifts'] as $shift) : ?>
                                <article class="volunteer-shift-card <?php echo $shift['is_full'] ? 'shift-full' : 'shift-available'; ?>">
                                    <div class="shift-header">
                                        <h3 class="shift-title"><?php echo esc_html($shift['title']); ?></h3>
                                        <div class="shift-status">
                                            <?php if ($shift['is_full']) : ?>
                                                <span class="status-badge status-full"><?php _e('Full', 'lcd-events'); ?></span>
                                            <?php else : ?>
                                                <span class="status-badge status-available"><?php _e('Available', 'lcd-events'); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="shift-details">
                                        <?php if (!empty($shift['formatted_date'])) : ?>
                                            <div class="shift-date">
                                                <span class="detail-label"><?php _e('Date:', 'lcd-events'); ?></span>
                                                <span class="detail-value"><?php echo esc_html($shift['formatted_date']); ?></span>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($shift['formatted_start_time']) || !empty($shift['formatted_end_time'])) : ?>
                                            <div class="shift-time">
                                                <span class="detail-label"><?php _e('Time:', 'lcd-events'); ?></span>
                                                <span class="detail-value">
                                                    <?php 
                                                    if (!empty($shift['formatted_start_time']) && !empty($shift['formatted_end_time'])) {
                                                        echo esc_html($shift['formatted_start_time']) . ' - ' . esc_html($shift['formatted_end_time']);
                                                    } elseif (!empty($shift['formatted_start_time'])) {
                                                        echo esc_html($shift['formatted_start_time']);
                                                    } elseif (!empty($shift['formatted_end_time'])) {
                                                        echo __('Until', 'lcd-events') . ' ' . esc_html($shift['formatted_end_time']);
                                                    }
                                                    ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($shift['description'])) : ?>
                                            <div class="shift-description">
                                                <span class="detail-label"><?php _e('Description:', 'lcd-events'); ?></span>
                                                <span class="detail-value"><?php echo esc_html($shift['description']); ?></span>
                                            </div>
                                        <?php endif; ?>

                                        <div class="shift-capacity">
                                            <span class="detail-label"><?php _e('Volunteers:', 'lcd-events'); ?></span>
                                            <span class="detail-value">
                                                <?php 
                                                if ($shift['max_volunteers'] > 0) {
                                                    printf(
                                                        __('%d of %d signed up', 'lcd-events'),
                                                        $shift['signup_count'],
                                                        $shift['max_volunteers']
                                                    );
                                                    if ($shift['spots_remaining'] > 0) {
                                                        echo ' (' . sprintf(_n('%d spot remaining', '%d spots remaining', $shift['spots_remaining'], 'lcd-events'), $shift['spots_remaining']) . ')';
                                                    }
                                                } else {
                                                    printf(
                                                        __('%d signed up (unlimited)', 'lcd-events'),
                                                        $shift['signup_count']
                                                    );
                                                }
                                                ?>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="shift-actions">
                                        <?php if ($shift['is_full']) : ?>
                                            <button class="volunteer-signup-btn btn-disabled" disabled>
                                                <span class="dashicons dashicons-no"></span>
                                                <?php _e('Shift Full', 'lcd-events'); ?>
                                            </button>
                                        <?php else : ?>
                                            <button class="volunteer-signup-btn btn-primary" 
                                                    data-event-id="<?php echo esc_attr($event['event_id']); ?>"
                                                    data-shift-index="<?php echo esc_attr($shift['index']); ?>"
                                                    data-shift-title="<?php echo esc_attr($shift['title']); ?>">
                                                <span class="dashicons dashicons-plus"></span>
                                                <?php _e('Sign Up', 'lcd-events'); ?>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <a href="<?php echo esc_url($event['event_permalink']); ?>" class="event-details-btn btn-secondary">
                                            <span class="dashicons dashicons-info"></span>
                                            <?php _e('Event Details', 'lcd-events'); ?>
                                        </a>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <div class="no-opportunities-message">
                <div class="message-icon">
                    <span class="dashicons dashicons-calendar-alt"></span>
                </div>
                <h3><?php _e('No Volunteer Opportunities Available', 'lcd-events'); ?></h3>
                <p><?php _e('There are currently no upcoming volunteer opportunities. Please check back later or', 'lcd-events'); ?> 
                   <a href="<?php echo get_post_type_archive_link('event'); ?>"><?php _e('view our upcoming events', 'lcd-events'); ?></a>.
                </p>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php
get_footer();
?> 