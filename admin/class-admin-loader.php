<?php
/**
 * Admin Loader
 * 
 * Loads and initializes all admin-related functionality for the LCD Events plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class LCD_Events_Admin_Loader {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Only load admin functionality in admin area
        if (is_admin()) {
            $this->load_admin_classes();
            $this->init_admin_classes();
        }
    }
    
    /**
     * Load admin class files
     */
    private function load_admin_classes() {
        require_once plugin_dir_path(__FILE__) . 'class-volunteer-email-admin.php';
        require_once plugin_dir_path(__FILE__) . 'class-volunteer-shifts-admin.php';
        // Future admin classes will be loaded here
        // require_once plugin_dir_path(__FILE__) . 'class-event-meta-admin.php';
    }
    
    /**
     * Initialize admin classes
     */
    private function init_admin_classes() {
        // Initialize email admin
        LCD_Volunteer_Email_Admin::get_instance();
        
        // Initialize volunteer shifts admin
        LCD_Volunteer_Shifts_Admin::get_instance();
        
        // Future admin classes will be initialized here
        // LCD_Event_Meta_Admin::get_instance();
    }
} 