<?php
/**
 * Template for displaying event archives
 *
 * @package LCD_Events
 */

get_header();
?>

<main id="primary" class="site-main events-archive">
    <header class="archive-header">
        <div class="container">
            <div class="header-icon">
                <span class="dashicons dashicons-calendar-alt"></span>
            </div>
            <h1 class="page-title"><?php _e('Upcoming Events', 'lcd-events'); ?></h1>
            
        </div>
    </header>

    <div class="container">
        <?php
        // Get current date
        $today = date('Y-m-d');
        
        // Query for upcoming events
        $upcoming_args = array(
            'post_type' => 'event',
            'posts_per_page' => -1,
            'meta_key' => '_event_date',
            'orderby' => 'meta_value',
            'order' => 'ASC',
            'meta_query' => array(
                array(
                    'key' => '_event_date',
                    'value' => $today,
                    'compare' => '>=',
                    'type' => 'DATE'
                )
            )
        );
        
        $upcoming_events = new WP_Query($upcoming_args);
        
        // Query for past events
        $past_args = array(
            'post_type' => 'event',
            'posts_per_page' => 10,
            'meta_key' => '_event_date',
            'orderby' => 'meta_value',
            'order' => 'DESC',
            'meta_query' => array(
                array(
                    'key' => '_event_date',
                    'value' => $today,
                    'compare' => '<',
                    'type' => 'DATE'
                )
            )
        );
        
        $past_events = new WP_Query($past_args);
        
        // Count upcoming events
        $upcoming_count = $upcoming_events->post_count;
        ?>

       

        <div class="events-container">
            <?php if ($upcoming_events->have_posts()) : ?>
                <section class="upcoming-events">
                    <h2><?php _e('Upcoming Events', 'lcd-events'); ?></h2>
                    
                    <div class="events-grid">
                        <?php while ($upcoming_events->have_posts()) : $upcoming_events->the_post(); ?>
                            <?php
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
                                        <?php 
                                        // Display event type overlay on thumbnail
                                        $event_types = get_the_terms(get_the_ID(), 'event_type');
                                        if (!empty($event_types) && !is_wp_error($event_types)) : ?>
                                            <div class="event-type-flag">
                                                <?php echo esc_html($event_types[0]->name); ?>
                                            </div>
                                        <?php endif; ?>
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
                </section>
            <?php else : ?>
                <div class="no-events-message">
                    <p><?php _e('There are no upcoming events at this time. Please check back later.', 'lcd-events'); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if ($past_events->have_posts()) : ?>
                <section class="past-events">
                    <h2><?php _e('Past Events', 'lcd-events'); ?></h2>
                    
                    <div class="events-list">
                        <?php while ($past_events->have_posts()) : $past_events->the_post(); ?>
                            <?php
                            // Get event meta
                            $event_date = get_post_meta(get_the_ID(), '_event_date', true);
                            
                            // Format date
                            $formatted_date = '';
                            if ($event_date) {
                                $formatted_date = date_i18n(get_option('date_format'), strtotime($event_date));
                            }
                            ?>
                            
                            <article id="post-<?php the_ID(); ?>" <?php post_class('event-list-item'); ?>>
                                <?php if ($formatted_date) : ?>
                                    <span class="event-date"><?php echo esc_html($formatted_date); ?></span>
                                <?php endif; ?>
                                
                                <h3 class="event-title">
                                    <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                                </h3>
                            </article>
                        <?php endwhile; ?>
                    </div>
                </section>
            <?php endif; ?>
            
            <?php wp_reset_postdata(); ?>
        </div>
    </div>
</main>

<?php get_footer(); ?> 