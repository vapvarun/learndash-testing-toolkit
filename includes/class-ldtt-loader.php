<?php

/**
 * LDTT Loader Class - Updated for Production
 * 
 * @package LearnDash_Testing_Toolkit
 * @since 1.2.0
 */
class LDTT_Loader {

    /**
     * Initialize the plugin by loading necessary files and CLI commands.
     */
    public static function init() {
        // Load core classes first
        self::load_core_classes();
        
        // Check if LearnDash is active with better detection
        if ( ! self::is_learndash_active() ) {
            add_action( 'admin_notices', array( __CLASS__, 'learndash_missing_notice' ) );
            // Don't return - let the detector handle this gracefully
        }

        // Load helper classes
        self::load_helper_classes();

        // Include CLI command files
        self::include_command_files();

        // Load CLI commands if WP-CLI is available
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            self::load_cli_commands();
        }

        // Initialize hooks
        self::init_hooks();
    }

    /**
     * Load core classes in proper order
     */
    private static function load_core_classes() {
        $core_classes = array(
            'includes/class-ldtt-core.php',
            'includes/class-ldtt-learndash-detector.php',
            'includes/class-ldtt-command-factory.php',
            'includes/class-ldtt-admin-interface.php',
        );

        foreach ( $core_classes as $file ) {
            $file_path = LDTT_PLUGIN_DIR . $file;
            if ( file_exists( $file_path ) ) {
                require_once $file_path;
            } else {
                error_log( "[LDTT] Core file missing: {$file}" );
            }
        }
    }

    /**
     * Load helper classes
     */
    private static function load_helper_classes() {
        $helper_files = array(
            'includes/helpers/class-ldtt-helper.php',
            'includes/helpers/class-ldtt-sample-data.php',
            'includes/helpers/class-ldtt-progress-manager.php',
            'includes/functions.php', // Global functions
        );

        foreach ( $helper_files as $file ) {
            $file_path = LDTT_PLUGIN_DIR . $file;
            if ( file_exists( $file_path ) ) {
                require_once $file_path;
            }
        }
    }

    /**
     * Check if LearnDash is active using the detector if available
     */
    private static function is_learndash_active() {
        // If detector class is available, use it
        if ( class_exists( 'LDTT_LearnDash_Detector' ) ) {
            $detector = new LDTT_LearnDash_Detector();
            $status = $detector->get_detection_status();
            return $status['is_active'] || get_option( 'ldtt_bypass_learndash_check', false );
        }

        // Fallback detection
        return self::basic_learndash_check();
    }

    /**
     * Basic LearnDash detection fallback
     */
    private static function basic_learndash_check() {
        // Check for LearnDash functions
        if ( function_exists( 'learndash_get_post_type_slug' ) ) {
            return true;
        }

        // Check for LearnDash classes
        if ( class_exists( 'LDLMS_Post_Types' ) || class_exists( 'LearnDash_Settings_Section' ) ) {
            return true;
        }

        // Check active plugins
        $active_plugins = get_option( 'active_plugins', array() );
        foreach ( $active_plugins as $plugin ) {
            if ( strpos( $plugin, 'sfwd-lms' ) !== false || strpos( $plugin, 'learndash' ) !== false ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Display notice when LearnDash is not active.
     */
    public static function learndash_missing_notice() {
        // Only show if detector isn't handling it
        if ( class_exists( 'LDTT_LearnDash_Detector' ) ) {
            return; // Let the detector handle the notice
        }

        echo '<div class="notice notice-error"><p>';
        esc_html_e( 'LearnDash Testing Toolkit requires LearnDash LMS to be installed and activated.', 'learndash-testing-toolkit' );
        echo '</p></div>';
    }

    /**
     * Include all CLI command files.
     */
    private static function include_command_files() {
        $command_files = array(
            'includes/cli-commands/class-ldtt-create-courses.php',
            'includes/cli-commands/class-ldtt-create-lessons.php',
            'includes/cli-commands/class-ldtt-create-topics.php',
            'includes/cli-commands/class-ldtt-create-quizzes.php',
            'includes/cli-commands/class-ldtt-create-questions.php',
            'includes/cli-commands/class-ldtt-enrollment.php',
            'includes/cli-commands/class-ldtt-course-groups.php',
            'includes/cli-commands/class-ldtt-group-leaders.php',
            'includes/cli-commands/class-ldtt-group-enrollment.php',
            'includes/cli-commands/class-ldtt-delete-items.php',
            'includes/cli-commands/class-ldtt-enhanced-user-distribution.php',
            'includes/cli-commands/class-ldtt-user-specific-commands.php',
        );

        foreach ( $command_files as $file ) {
            $file_path = LDTT_PLUGIN_DIR . $file;
            if ( file_exists( $file_path ) ) {
                require_once $file_path;
            } else {
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( "[LDTT] Command file missing: {$file}" );
                }
            }
        }
    }

    /**
     * Load CLI command classes and register them with WP-CLI.
     */
    private static function load_cli_commands() {
        $commands = array(
            'create-courses' => 'LDTT_Create_Courses',
            'create-lessons' => 'LDTT_Create_Lessons',
            'create-topics' => 'LDTT_Create_Topics',
            'create-quizzes' => 'LDTT_Create_Quizzes',
            'create-questions' => 'LDTT_Create_Questions',
            'delete-items' => 'LDTT_Delete_Items',
            'enrollment' => 'LDTT_Enrollment',
            'course-groups' => 'LDTT_Course_Groups',
            'group-leaders' => 'LDTT_Group_Leaders',
            'group-enrollment' => 'LDTT_Group_Enrollment',
            'enhanced-user-distribution' => 'LDTT_Enhanced_User_Distribution',
            'assign-progress' => array( 'LDTT_Enhanced_User_Distribution', 'assign_progress_to_enrolled' ), // FIX: Use array format
        );
    
        foreach ( $commands as $command_name => $class_info ) {
            if ( is_array( $class_info ) ) {
                // Handle array format for custom methods
                $class_name = $class_info[0];
                $method_name = $class_info[1];
                
                if ( class_exists( $class_name ) && method_exists( $class_name, $method_name ) ) {
                    WP_CLI::add_command( 'ldtt ' . $command_name, array( $class_name, $method_name ) );
                    
                    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                        error_log( "[LDTT] Registered CLI command: ldtt {$command_name} -> {$class_name}::{$method_name}" );
                    }
                }
            } else {
                // Handle single class format
                $class_name = $class_info;
                
                if ( class_exists( $class_name ) ) {
                    WP_CLI::add_command( 'ldtt ' . $command_name, array( $class_name, 'handle' ) );
                    
                    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                        error_log( "[LDTT] Registered CLI command: ldtt {$command_name}" );
                    }
                } else {
                    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                        error_log( "[LDTT] Command class not found: {$class_name}" );
                    }
                }
            }
        }
    }
    /**
     * Initialize hooks
     */
    private static function init_hooks() {
        // Hook for admin initialization
        add_action( 'admin_init', array( __CLASS__, 'admin_init' ) );
        
        // Hook for plugin upgrades
        add_action( 'upgrader_process_complete', array( __CLASS__, 'plugin_updated' ), 10, 2 );
    }

    /**
     * Admin initialization
     */
    public static function admin_init() {
        // Check if plugin needs database updates
        self::maybe_upgrade_database();
        
        // Load admin-specific functionality
        if ( is_admin() && ! wp_doing_ajax() ) {
            self::load_admin_components();
        }
    }

    /**
     * Load admin-specific components
     */
    private static function load_admin_components() {
        // Admin interface is already loaded in core classes
        // Add any additional admin-only functionality here
    }

    /**
     * Check if database needs upgrade
     */
    private static function maybe_upgrade_database() {
        $db_version = get_option( 'ldtt_db_version', '1.0.0' );
        $plugin_version = defined( 'LDTT_VERSION' ) ? LDTT_VERSION : '1.2.0';
        
        if ( version_compare( $db_version, $plugin_version, '<' ) ) {
            self::upgrade_database( $db_version, $plugin_version );
        }
    }

    /**
     * Upgrade database if needed
     */
    private static function upgrade_database( $from_version, $to_version ) {
        // Create any new database tables or update existing ones
        if ( version_compare( $from_version, '1.2.0', '<' ) ) {
            self::create_120_tables();
        }
        
        // Update database version
        update_option( 'ldtt_db_version', $to_version );
        
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( "[LDTT] Database upgraded from {$from_version} to {$to_version}" );
        }
    }

    /**
     * Create database tables for version 1.2.0
     */
    private static function create_120_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Create logs table if it doesn't exist
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
     * Handle plugin updates
     */
    public static function plugin_updated( $upgrader_object, $options ) {
        if ( $options['action'] === 'update' && $options['type'] === 'plugin' ) {
            if ( isset( $options['plugins'] ) ) {
                foreach ( $options['plugins'] as $plugin ) {
                    if ( strpos( $plugin, 'learndash-testing-toolkit' ) !== false ) {
                        // Plugin was updated
                        self::handle_plugin_update();
                        break;
                    }
                }
            }
        }
    }

    /**
     * Handle plugin update
     */
    private static function handle_plugin_update() {
        // Clear any caches
        wp_cache_delete( 'ldtt_settings' );
        
        // Log the update
        if ( class_exists( 'LDTT_Logger' ) ) {
            LDTT_Logger::info( 'Plugin updated successfully' );
        }
        
        // Set transient to show update notice
        set_transient( 'ldtt_updated', true, 30 );
    }

    /**
     * Get loader status for debugging
     */
    public static function get_status() {
        return array(
            'learndash_active' => self::is_learndash_active(),
            'cli_available' => defined( 'WP_CLI' ) && WP_CLI,
            'core_classes_loaded' => class_exists( 'LDTT_Core' ),
            'admin_interface_loaded' => class_exists( 'LDTT_Admin_Interface' ),
            'detector_loaded' => class_exists( 'LDTT_LearnDash_Detector' ),
            'commands_loaded' => class_exists( 'LDTT_Create_Courses' ),
        );
    }
}