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
$additional_buttons = get_post_meta(get_the_ID(), '_additional_buttons', true);

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
        <!-- Simplified Header with centered, contained featured image -->
        <?php if ($has_featured_image) : ?>
        <header class="entry-header event-header-minimal">
            <div class="event-featured-image-container">
                <?php the_post_thumbnail('full', array('class' => 'event-featured-image')); ?>
            </div>
        </header>
        <?php endif; ?>

        <!-- Mobile Registration Button (shows on scroll) -->
        <?php if ($event_registration_url) : ?>
            <div class="event-registration-mobile-sticky" id="mobile-registration-sticky">
                <div class="mobile-sticky-content">
                    <a href="<?php echo esc_url($event_registration_url); ?>" class="mobile-registration-button" target="_blank">
                        <i class="dashicons dashicons-tickets-alt"></i>
                        <?php echo esc_html($event_button_text); ?>
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <div class="entry-content">
            <div class="event-content-wrapper">
                <div class="event-main-content">
                    <!-- Event Title and Meta -->
                    <div class="event-header-content">
                        <?php 
                        // Display event type flag above title
                        $event_types = get_the_terms(get_the_ID(), 'event_type');
                        if (!empty($event_types) && !is_wp_error($event_types)) : ?>
                            <div class="event-type-flag">
                                <?php echo esc_html($event_types[0]->name); ?>
                            </div>
                        <?php endif; ?>
                        
                        <h1 class="entry-title"><?php the_title(); ?></h1>
                        
                        <!-- Compact Event Details -->
                        <div class="event-details-compact">
                            <?php if ($formatted_date || $formatted_time) : ?>
                            <div class="detail-item">
                                <i class="dashicons dashicons-calendar-alt"></i>
                                <div class="detail-content">
                                    <?php if ($formatted_date) : ?>
                                        <span class="detail-primary"><?php echo esc_html($formatted_date); ?></span>
                                    <?php endif; ?>
                                    <?php if ($formatted_time) : ?>
                                        <span class="detail-secondary"><?php echo esc_html($formatted_time); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($event_location || $event_address) : ?>
                            <div class="detail-item">
                                <i class="dashicons dashicons-location"></i>
                                <div class="detail-content">
                                    <?php if ($event_location) : ?>
                                        <span class="detail-primary"><?php echo esc_html($event_location); ?></span>
                                    <?php endif; ?>
                                    <?php if ($event_address) : ?>
                                        <span class="detail-secondary"><?php echo nl2br(esc_html($event_address)); ?></span>
                                        <?php if ($event_map_link) : ?>
                                            <a href="<?php echo esc_url($event_map_link); ?>" class="detail-link" target="_blank">
                                                <i class="dashicons dashicons-location-alt"></i>
                                                <?php _e('View Map', 'lcd-events'); ?>
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if ($event_cost || $event_capacity) : ?>
                            <div class="detail-item">
                                <i class="dashicons dashicons-tickets-alt"></i>
                                <div class="detail-content">
                                    <?php if ($event_cost) : ?>
                                        <span class="detail-primary"><?php echo esc_html($event_cost); ?></span>
                                    <?php endif; ?>
                                    <?php if ($event_capacity) : ?>
                                        <span class="detail-secondary"><?php echo esc_html($event_capacity); ?> <?php _e('people', 'lcd-events'); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Event Poster and Content -->
                    <?php if ($event_poster_id) : ?>
                        <div class="event-poster">
                            <?php echo wp_get_attachment_image($event_poster_id, 'large', false, array('class' => 'event-poster-image')); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="event-description">
                        <?php the_content(); ?>
                    </div>
                </div>
                
                <div class="event-sidebar">
                    <!-- Registration Section - Only content in sidebar now -->
                    <?php if ($event_registration_url) : ?>
                        <div class="event-registration-card priority-card">
                            <h3><?php _e('Register for this Event', 'lcd-events'); ?></h3>
                            
                            
                            
                            <a href="<?php echo esc_url($event_registration_url); ?>" class="button event-register-button primary-cta" target="_blank">
                                <i class="dashicons dashicons-tickets-alt"></i>
                                <?php echo esc_html($event_button_text); ?>
                            </a>
                            
                            <?php if ($event_ticketing_notes) : ?>
                                <div class="event-ticketing-notes">
                                    <?php echo wp_kses_post($event_ticketing_notes); ?>
                                </div>
                            <?php endif; ?>

                            <div class="registration-highlight">
                                <?php if ($event_cost) : ?>
                                    <div class="event-cost-display">
                                        <span class="cost-label"><?php _e('Price:', 'lcd-events'); ?></span>
                                        <span class="cost-value"><?php echo esc_html($event_cost); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($event_capacity) : ?>
                                    <div class="event-capacity-display">
                                        <span class="capacity-label"><?php _e('Capacity:', 'lcd-events'); ?></span>
                                        <span class="capacity-value"><?php echo esc_html($event_capacity); ?> <?php _e('people', 'lcd-events'); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($additional_buttons) && is_array($additional_buttons)) : ?>
                                <div class="additional-event-buttons" style="margin-top: 15px;">
                                    <?php foreach ($additional_buttons as $button) : ?>
                                        <?php if (!empty($button['text']) && !empty($button['url'])) : ?>
                                            <a href="<?php echo esc_url($button['url']); ?>" class="button additional-event-button" target="_blank" style="margin-bottom: 10px; display: block; text-align: center;">
                                                <?php echo esc_html($button['text']); ?>
                                            </a>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </article>
</main>

<?php get_footer(); ?> 