<?php
/**
 * Template for displaying single event
 *
 * @package LCD_Events
 */

get_header(); 

// Get event meta values
$event_date = get_post_meta(get_the_ID(), '_event_date', true);
$event_time = get_post_meta(get_the_ID(), '_event_time', true);
$event_end_time = get_post_meta(get_the_ID(), '_event_end_time', true);
$event_location = get_post_meta(get_the_ID(), '_event_location', true);
$event_address = get_post_meta(get_the_ID(), '_event_address', true);
$event_map_link = get_post_meta(get_the_ID(), '_event_map_link', true);
$event_registration_url = get_post_meta(get_the_ID(), '_event_registration_url', true);
$event_capacity = get_post_meta(get_the_ID(), '_event_capacity', true);
$event_cost = get_post_meta(get_the_ID(), '_event_cost', true);
$event_poster_id = get_post_meta(get_the_ID(), '_event_poster', true);
$event_button_text = get_post_meta(get_the_ID(), '_event_button_text', true) ?: __('Register Now', 'lcd-events');
$event_ticketing_notes = get_post_meta(get_the_ID(), '_event_ticketing_notes', true);

// Format date and time
$formatted_date = '';
if ($event_date) {
    $formatted_date = date_i18n(get_option('date_format'), strtotime($event_date));
}

$formatted_time = '';
if ($event_time) {
    $formatted_time = date_i18n(get_option('time_format'), strtotime($event_date . ' ' . $event_time));
    
    if ($event_end_time) {
        $formatted_time .= ' - ' . date_i18n(get_option('time_format'), strtotime($event_date . ' ' . $event_end_time));
    }
}

// Check if post has featured image
$has_featured_image = has_post_thumbnail();
?>

<main id="primary" class="site-main">
    <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
        <header class="entry-header<?php echo $has_featured_image ? ' has-featured-image' : ''; ?>">
            <?php if ($has_featured_image) : ?>
            <div class="featured-image">
                <?php the_post_thumbnail('full'); ?>
            </div>
            <?php endif; ?>
            
            <div class="container">
                <div class="entry-header-content">
                    <?php 
                    // Display event type flag above title
                    $event_types = get_the_terms(get_the_ID(), 'event_type');
                    if (!empty($event_types) && !is_wp_error($event_types)) : ?>
                        <div class="event-type-flag">
                            <?php echo esc_html($event_types[0]->name); ?>
                        </div>
                    <?php endif; ?>
                    
                    <h1 class="entry-title"><?php the_title(); ?></h1>
                    
                    <?php if ($formatted_date || $formatted_time || $event_location) : ?>
                    <div class="event-meta-header">
                        <?php if ($formatted_date || $formatted_time) : ?>
                        <div class="meta-item">
                            <i class="dashicons dashicons-calendar-alt"></i>
                            <div class="meta-content">
                                <?php if ($formatted_date) : ?>
                                    <span class="event-date"><?php echo esc_html($formatted_date); ?></span>
                                <?php endif; ?>
                                
                                <?php if ($formatted_time) : ?>
                                    <span class="event-time"><?php echo esc_html($formatted_time); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($event_location) : ?>
                        <div class="meta-item">
                            <i class="dashicons dashicons-location"></i>
                            <div class="meta-content">
                                <span><?php echo esc_html($event_location); ?></span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <div class="entry-content">
            <div class="event-content-wrapper">
                <div class="event-main-content">
                    <?php if ($event_poster_id) : ?>
                        <div class="event-poster">
                            <?php echo wp_get_attachment_image($event_poster_id, 'large', false, array('class' => 'event-poster-image')); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php the_content(); ?>
                </div>
                
                <div class="event-sidebar">
                    <div class="event-info-box">
                        <h3><?php _e('Event Details', 'lcd-events'); ?></h3>
                        
                        <?php if ($formatted_date) : ?>
                            <div class="event-info-item">
                                <strong><?php _e('Date:', 'lcd-events'); ?></strong>
                                <span><?php echo esc_html($formatted_date); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($formatted_time) : ?>
                            <div class="event-info-item">
                                <strong><?php _e('Time:', 'lcd-events'); ?></strong>
                                <span><?php echo esc_html($formatted_time); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($event_location) : ?>
                            <div class="event-info-item">
                                <strong><?php _e('Location:', 'lcd-events'); ?></strong>
                                <span><?php echo esc_html($event_location); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($event_address) : ?>
                            <div class="event-info-item">
                                <strong><?php _e('Address:', 'lcd-events'); ?></strong>
                                <span><?php echo nl2br(esc_html($event_address)); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($event_map_link) : ?>
                            <div class="event-info-item">
                                <a href="<?php echo esc_url($event_map_link); ?>" class="event-map-link" target="_blank">
                                    <i class="dashicons dashicons-location-alt"></i>
                                    <?php _e('View Map', 'lcd-events'); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($event_cost) : ?>
                            <div class="event-info-item">
                                <strong><?php _e('Cost:', 'lcd-events'); ?></strong>
                                <span><?php echo esc_html($event_cost); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($event_capacity) : ?>
                            <div class="event-info-item">
                                <strong><?php _e('Capacity:', 'lcd-events'); ?></strong>
                                <span><?php echo esc_html($event_capacity); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($event_registration_url) : ?>
                            <div class="event-registration">
                                <a href="<?php echo esc_url($event_registration_url); ?>" class="button event-register-button" target="_blank">
                                    <?php echo esc_html($event_button_text); ?>
                                </a>
                                
                                <?php if ($event_ticketing_notes) : ?>
                                    <div class="event-ticketing-notes">
                                        <?php echo wp_kses_post($event_ticketing_notes); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </article>
</main>

<?php get_footer(); ?> 