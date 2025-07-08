<?php
/**
 * Plugin Name: LearnDash Testing Toolkit
 * Plugin URI: https://github.com/vapvarun/learndash-testing-toolkit
 * Description: A comprehensive toolkit for testing LearnDash courses, lessons, topics, quizzes, and user management with realistic data distribution and progress tracking.
 * Version: 1.2.0
 * Author: vapvarun
 * Author URI: https://github.com/vapvarun
 * Text Domain: learndash-testing-toolkit
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'LDTT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'LDTT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'LDTT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main Plugin Class
 */
class LearnDash_Testing_Toolkit {
    
    /**
     * Plugin version
     */
    const VERSION = '1.2.0';
    
    /**
     * Plugin instance
     * 
     * @var LearnDash_Testing_Toolkit|null
     */
    private static $instance = null;
    
    /**
     * Core instance
     * 
     * @var LDTT_Core|null
     */
    private $core = null;
    
    /**
     * Get plugin instance
     * 
     * @return LearnDash_Testing_Toolkit
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->define_constants();
        $this->load_core_files();
        $this->init_hooks();
        $this->init_core();
    }
    
    /**
     * Define additional constants
     */
    private function define_constants() {
        if ( ! defined( 'LDTT_VERSION' ) ) {
            define( 'LDTT_VERSION', self::VERSION );
        }
    }
    
    /**
     * Load core files
     */
    private function load_core_files() {
        // Load security and utility functions first
        require_once LDTT_PLUGIN_DIR . 'includes/functions.php';
        
        // Load loader class
        require_once LDTT_PLUGIN_DIR . 'includes/class-ldtt-loader.php';
        
        // Initialize the loader
        LDTT_Loader::init();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
        register_uninstall_hook( __FILE__, array( __CLASS__, 'uninstall' ) );
        
        add_action( 'init', array( $this, 'load_textdomain' ) );
        add_action( 'admin_init', array( $this, 'check_version' ) );
        
        // Add plugin action links
        add_filter( 'plugin_action_links_' . LDTT_PLUGIN_BASENAME, array( $this, 'plugin_action_links' ) );
        add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );
    }
    
    /**
     * Initialize core
     */
    private function init_core() {
        if ( class_exists( 'LDTT_Core' ) ) {
            $this->core = LDTT_Core::get_instance();
        }
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Check system requirements
        $this->check_system_requirements();
        
        // Create database tables if needed
        $this->create_database_tables();
        
        // Set activation options
        $this->set_activation_options();
        
        // Schedule cleanup tasks
        $this->schedule_cleanup_tasks();
        
        // Log activation
        if ( class_exists( 'LDTT_Logger' ) ) {
            LDTT_Logger::info( 'Plugin activated successfully' );
        }
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled tasks
        $this->clear_scheduled_tasks();
        
        // Log deactivation
        if ( class_exists( 'LDTT_Logger' ) ) {
            LDTT_Logger::info( 'Plugin deactivated' );
        }
    }
    
    /**
     * Plugin uninstall
     */
    public static function uninstall() {
        // Only run if user has proper permissions
        if ( ! current_user_can( 'activate_plugins' ) ) {
            return;
        }
        
        // Check if we should clean up data
        $cleanup_on_uninstall = get_option( 'ldtt_cleanup_on_uninstall', false );
        
        if ( $cleanup_on_uninstall ) {
            self::cleanup_all_data();
        }
        
        // Remove plugin options
        self::remove_plugin_options();
    }
    
    /**
     * Check system requirements
     */
    private function check_system_requirements() {
        // Check PHP version
        if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
            deactivate_plugins( LDTT_PLUGIN_BASENAME );
            wp_die(
                sprintf(
                    __( 'LearnDash Testing Toolkit requires PHP 7.4 or higher. You are running PHP %s.', 'learndash-testing-toolkit' ),
                    PHP_VERSION
                ),
                __( 'Plugin Activation Error', 'learndash-testing-toolkit' ),
                array( 'back_link' => true )
            );
        }
        
        // Check WordPress version
        global $wp_version;
        if ( version_compare( $wp_version, '5.0', '<' ) ) {
            deactivate_plugins( LDTT_PLUGIN_BASENAME );
            wp_die(
                sprintf(
                    __( 'LearnDash Testing Toolkit requires WordPress 5.0 or higher. You are running WordPress %s.', 'learndash-testing-toolkit' ),
                    $wp_version
                ),
                __( 'Plugin Activation Error', 'learndash-testing-toolkit' ),
                array( 'back_link' => true )
            );
        }
    }
    
    /**
     * Create database tables
     */
    private function create_database_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Create logs table
        $logs_table = $wpdb->prefix . 'ldtt_logs';
        $logs_sql = "CREATE TABLE IF NOT EXISTS {$logs_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            level varchar(20) NOT NULL DEFAULT 'INFO',
            message text NOT NULL,
            context longtext,
            user_id bigint(20) unsigned DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY level (level),
            KEY timestamp (timestamp),
            KEY user_id (user_id)
        ) {$charset_collate};";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $logs_sql );
    }
    
    /**
     * Set activation options
     */
    private function set_activation_options() {
        add_option( 'ldtt_version', self::VERSION );
        add_option( 'ldtt_activation_time', current_time( 'timestamp' ) );
        add_option( 'ldtt_settings', $this->get_default_settings() );
        
        // Set flag for activation redirect
        set_transient( 'ldtt_activation_redirect', true, 30 );
    }
    
    /**
     * Get default settings
     * 
     * @return array
     */
    private function get_default_settings() {
        return array(
            'log_level' => 'INFO',
            'cleanup_on_uninstall' => false,
            'auto_cleanup_days' => 30,
            'max_test_posts' => 10000,
            'max_test_users' => 1000,
            'enable_debug_mode' => false,
        );
    }
    
    /**
     * Schedule cleanup tasks
     */
    private function schedule_cleanup_tasks() {
        if ( ! wp_next_scheduled( 'ldtt_daily_cleanup' ) ) {
            wp_schedule_event( time(), 'daily', 'ldtt_daily_cleanup' );
        }
    }
    
    /**
     * Clear scheduled tasks
     */
    private function clear_scheduled_tasks() {
        wp_clear_scheduled_hook( 'ldtt_daily_cleanup' );
    }
    
    /**
     * Clean up all plugin data
     */
    private static function cleanup_all_data() {
        global $wpdb;
        
        // Delete test posts
        $test_posts = get_posts( array(
            'post_type' => 'any',
            'numberposts' => -1,
            'meta_key' => '_ldtt_test_data',
            'post_status' => 'any',
        ) );
        
        foreach ( $test_posts as $post ) {
            wp_delete_post( $post->ID, true );
        }
        
        // Delete test users
        $test_users = get_users( array(
            'meta_key' => '_ldtt_test_user',
            'meta_value' => true,
        ) );
        
        foreach ( $test_users as $user ) {
            if ( ! in_array( 'administrator', $user->roles, true ) ) {
                wp_delete_user( $user->ID );
            }
        }
        
        // Drop custom tables
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}ldtt_logs" );
    }
    
    /**
     * Remove plugin options
     */
    private static function remove_plugin_options() {
        $options = array(
            'ldtt_version',
            'ldtt_activation_time',
            'ldtt_settings',
            'ldtt_bypass_learndash_check',
            'ldtt_logs',
            'ldtt_log_level',
        );
        
        foreach ( $options as $option ) {
            delete_option( $option );
        }
    }
    
    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'learndash-testing-toolkit',
            false,
            dirname( LDTT_PLUGIN_BASENAME ) . '/languages'
        );
    }
    
    /**
     * Check version and run updates if needed
     */
    public function check_version() {
        $installed_version = get_option( 'ldtt_version', '1.0.0' );
        
        if ( version_compare( $installed_version, self::VERSION, '<' ) ) {
            $this->run_update( $installed_version );
        }
    }
    
    /**
     * Run plugin update
     * 
     * @param string $from_version
     */
    private function run_update( $from_version ) {
        // Update version
        update_option( 'ldtt_version', self::VERSION );
        
        // Run version-specific updates
        if ( version_compare( $from_version, '1.2.0', '<' ) ) {
            $this->update_to_1_2_0();
        }
        
        if ( class_exists( 'LDTT_Logger' ) ) {
            LDTT_Logger::info( "Plugin updated from {$from_version} to " . self::VERSION );
        }
    }
    
    /**
     * Update to version 1.2.0
     */
    private function update_to_1_2_0() {
        // Create new database tables
        $this->create_database_tables();
        
        // Migrate old settings
        $old_settings = get_option( 'ldtt_settings', array() );
        $new_settings = wp_parse_args( $old_settings, $this->get_default_settings() );
        update_option( 'ldtt_settings', $new_settings );
    }
    
    /**
     * Add plugin action links
     * 
     * @param array $links
     * @return array
     */
    public function plugin_action_links( $links ) {
        $action_links = array(
            'settings' => sprintf(
                '<a href="%s">%s</a>',
                admin_url( 'admin.php?page=ldtt-cli-commands' ),
                __( 'Settings', 'learndash-testing-toolkit' )
            ),
        );
        
        return array_merge( $action_links, $links );
    }
    
    /**
     * Add plugin row meta
     * 
     * @param array  $links
     * @param string $file
     * @return array
     */
    public function plugin_row_meta( $links, $file ) {
        if ( LDTT_PLUGIN_BASENAME !== $file ) {
            return $links;
        }
        
        $row_meta = array(
            'docs' => sprintf(
                '<a href="%s" target="_blank">%s</a>',
                'https://github.com/vapvarun/learndash-testing-toolkit/wiki',
                __( 'Documentation', 'learndash-testing-toolkit' )
            ),
            'support' => sprintf(
                '<a href="%s" target="_blank">%s</a>',
                'https://github.com/vapvarun/learndash-testing-toolkit/issues',
                __( 'Support', 'learndash-testing-toolkit' )
            ),
        );
        
        return array_merge( $links, $row_meta );
    }
    
    /**
     * Get core instance
     * 
     * @return LDTT_Core|null
     */
    public function get_core() {
        return $this->core;
    }
    
    /**
     * Get plugin info
     * 
     * @return array
     */
    public function get_plugin_info() {
        return array(
            'name' => 'LearnDash Testing Toolkit',
            'version' => self::VERSION,
            'file' => __FILE__,
            'dir' => LDTT_PLUGIN_DIR,
            'url' => LDTT_PLUGIN_URL,
        );
    }
}

/**
 * Initialize the plugin
 */
function ldtt_init() {
    return LearnDash_Testing_Toolkit::get_instance();
}

// Start the plugin
add_action( 'plugins_loaded', 'ldtt_init', 1 );

// Global functions are now loaded from includes/functions.php