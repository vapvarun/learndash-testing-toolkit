<?php

/**
 * Main LDTT Core Class
 * 
 * @package LearnDash_Testing_Toolkit
 * @version 1.2.0
 * @since 1.0.0
 */
class LDTT_Core {
    
    /**
     * Plugin version
     */
    const VERSION = '1.2.0';
    
    /**
     * Minimum PHP version
     */
    const MIN_PHP_VERSION = '7.4';
    
    /**
     * Plugin instance
     * 
     * @var LDTT_Core|null
     */
    private static $instance = null;
    
    /**
     * Plugin components
     * 
     * @var array
     */
    private $components = array();
    
    /**
     * Plugin initialization status
     * 
     * @var bool
     */
    private $initialized = false;
    
    /**
     * Get plugin instance (Singleton)
     * 
     * @return LDTT_Core
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct() {
        $this->define_constants();
        $this->init_hooks();
    }
    
    /**
     * Define plugin constants
     */
    private function define_constants() {
        if ( ! defined( 'LDTT_VERSION' ) ) {
            define( 'LDTT_VERSION', self::VERSION );
        }
        
        if ( ! defined( 'LDTT_MIN_PHP_VERSION' ) ) {
            define( 'LDTT_MIN_PHP_VERSION', self::MIN_PHP_VERSION );
        }
        
        if ( ! defined( 'LDTT_PLUGIN_FILE' ) ) {
            define( 'LDTT_PLUGIN_FILE', LDTT_PLUGIN_DIR . 'learndash-testing-toolkit.php' );
        }
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action( 'plugins_loaded', array( $this, 'init' ), 10 );
        add_action( 'admin_init', array( $this, 'admin_init' ) );
        add_action( 'admin_notices', array( $this, 'admin_notices' ) );
        
        // Handle AJAX requests
        add_action( 'wp_ajax_ldtt_handle_action', array( $this, 'handle_ajax_action' ) );
    }
    
    /**
     * Initialize the plugin
     */
    public function init() {
        if ( $this->initialized ) {
            return;
        }
        
        try {
            // Check system requirements
            if ( ! $this->check_requirements() ) {
                return;
            }
            
            // Initialize components
            $this->init_components();
            
            // Load CLI commands if WP-CLI is available
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                $this->init_cli_commands();
            }
            
            $this->initialized = true;
            
            LDTT_Logger::info( 'LDTT Core initialized successfully' );
            
        } catch ( Exception $e ) {
            LDTT_Logger::error( 'Failed to initialize LDTT Core: ' . $e->getMessage() );
            $this->handle_initialization_error( $e );
        }
    }
    
    /**
     * Admin initialization
     */
    public function admin_init() {
        if ( ! $this->initialized ) {
            return;
        }
        
        // Handle admin actions
        $this->handle_admin_actions();
        
        // Enqueue admin assets
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
    }
    
    /**
     * Check system requirements
     * 
     * @return bool
     */
    private function check_requirements() {
        $requirements = new LDTT_Requirements_Checker();
        return $requirements->check_all();
    }
    
    /**
     * Initialize plugin components
     */
    private function init_components() {
        $this->components = array(
            'learndash_detector' => new LDTT_LearnDash_Detector(),
            'admin_interface'    => new LDTT_Admin_Interface(),
            'command_factory'    => new LDTT_Command_Factory(),
            'data_manager'       => new LDTT_Data_Manager(),
            'progress_manager'   => new LDTT_Progress_Manager(),
        );
        
        // Initialize each component
        foreach ( $this->components as $component ) {
            if ( method_exists( $component, 'init' ) ) {
                $component->init();
            }
        }
    }
    
    /**
     * Initialize CLI commands
     */
    private function init_cli_commands() {
        if ( ! isset( $this->components['command_factory'] ) ) {
            return;
        }
        
        $this->components['command_factory']->register_cli_commands();
    }
    
    /**
     * Get component instance
     * 
     * @param string $component_name
     * @return object|null
     */
    public function get_component( $component_name ) {
        return isset( $this->components[ $component_name ] ) ? $this->components[ $component_name ] : null;
    }
    
    /**
     * Handle admin actions
     */
    private function handle_admin_actions() {
        if ( ! isset( $_GET['ldtt_action'] ) || ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        $action = sanitize_text_field( $_GET['ldtt_action'] );
        $nonce = isset( $_GET['nonce'] ) ? sanitize_text_field( $_GET['nonce'] ) : '';
        
        if ( ! wp_verify_nonce( $nonce, 'ldtt_action_' . $action ) ) {
            wp_die( __( 'Security check failed.', 'learndash-testing-toolkit' ) );
        }
        
        switch ( $action ) {
            case 'bypass_learndash_check':
                $this->enable_bypass_mode();
                break;
                
            case 'disable_bypass':
                $this->disable_bypass_mode();
                break;
                
            case 'reset_plugin':
                $this->reset_plugin_settings();
                break;
        }
    }
    
    /**
     * Enable bypass mode
     */
    private function enable_bypass_mode() {
        update_option( 'ldtt_bypass_learndash_check', true );
        
        // Initialize fallback functions
        if ( isset( $this->components['learndash_detector'] ) ) {
            $this->components['learndash_detector']->init_fallback_functions();
        }
        
        LDTT_Logger::warning( 'LearnDash bypass mode enabled' );
        
        wp_safe_redirect( admin_url( 'admin.php?page=ldtt-cli-commands&ldtt_message=bypass_enabled' ) );
        exit;
    }
    
    /**
     * Disable bypass mode
     */
    private function disable_bypass_mode() {
        delete_option( 'ldtt_bypass_learndash_check' );
        
        LDTT_Logger::info( 'LearnDash bypass mode disabled' );
        
        wp_safe_redirect( admin_url( 'admin.php?page=ldtt-cli-commands&ldtt_message=bypass_disabled' ) );
        exit;
    }
    
    /**
     * Reset plugin settings
     */
    private function reset_plugin_settings() {
        $options_to_delete = array(
            'ldtt_bypass_learndash_check',
            'ldtt_version',
            'ldtt_activation_time',
            'ldtt_settings',
        );
        
        foreach ( $options_to_delete as $option ) {
            delete_option( $option );
        }
        
        LDTT_Logger::info( 'Plugin settings reset' );
        
        wp_safe_redirect( admin_url( 'admin.php?page=ldtt-cli-commands&ldtt_message=settings_reset' ) );
        exit;
    }
    
    /**
     * Handle AJAX actions
     */
    public function handle_ajax_action() {
        check_ajax_referer( 'ldtt_ajax_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Insufficient permissions.', 'learndash-testing-toolkit' ) );
        }
        
        $action = isset( $_POST['action_type'] ) ? sanitize_text_field( $_POST['action_type'] ) : '';
        
        $response = array( 'success' => false, 'message' => 'Unknown action' );
        
        try {
            switch ( $action ) {
                case 'test_learndash_connection':
                    $response = $this->test_learndash_connection();
                    break;
                    
                case 'validate_settings':
                    $response = $this->validate_settings();
                    break;
                    
                default:
                    $response['message'] = 'Invalid action specified';
            }
        } catch ( Exception $e ) {
            $response = array(
                'success' => false,
                'message' => $e->getMessage(),
            );
            
            LDTT_Logger::error( 'AJAX action failed: ' . $e->getMessage() );
        }
        
        wp_send_json( $response );
    }
    
    /**
     * Test LearnDash connection
     * 
     * @return array
     */
    private function test_learndash_connection() {
        if ( ! isset( $this->components['learndash_detector'] ) ) {
            return array( 'success' => false, 'message' => 'LearnDash detector not available' );
        }
        
        $detector = $this->components['learndash_detector'];
        $status = $detector->get_detailed_status();
        
        return array(
            'success' => $status['is_active'],
            'message' => $status['message'],
            'details' => $status,
        );
    }
    
    /**
     * Validate settings
     * 
     * @return array
     */
    private function validate_settings() {
        $settings = isset( $_POST['settings'] ) ? $_POST['settings'] : array();
        $errors = array();
        
        // Validate total users
        if ( isset( $settings['total_users'] ) ) {
            $total_users = intval( $settings['total_users'] );
            if ( $total_users < 1 || $total_users > 1000 ) {
                $errors[] = 'Total users must be between 1 and 1000';
            }
        }
        
        // Validate percentages
        $percentage_fields = array( 'group_leaders', 'group_members', 'course_enrolled' );
        $total_percentage = 0;
        
        foreach ( $percentage_fields as $field ) {
            if ( isset( $settings[ $field ] ) ) {
                $percentage = floatval( $settings[ $field ] );
                if ( $percentage < 0 || $percentage > 100 ) {
                    $errors[] = ucfirst( str_replace( '_', ' ', $field ) ) . ' percentage must be between 0 and 100';
                }
                $total_percentage += $percentage;
            }
        }
        
        if ( $total_percentage > 100 ) {
            $errors[] = 'Total percentage cannot exceed 100%';
        }
        
        return array(
            'success' => empty( $errors ),
            'message' => empty( $errors ) ? 'Settings are valid' : 'Validation errors found',
            'errors'  => $errors,
        );
    }
    
    /**
     * Display admin notices
     */
    public function admin_notices() {
        // Show messages based on URL parameters
        if ( isset( $_GET['ldtt_message'] ) ) {
            $message_type = sanitize_text_field( $_GET['ldtt_message'] );
            $this->show_admin_message( $message_type );
        }
        
        // Show requirement notices
        if ( isset( $this->components['learndash_detector'] ) ) {
            $this->components['learndash_detector']->show_admin_notices();
        }
    }
    
    /**
     * Show admin message
     * 
     * @param string $message_type
     */
    private function show_admin_message( $message_type ) {
        $messages = array(
            'bypass_enabled'  => array( 'type' => 'warning', 'text' => 'LearnDash bypass mode has been enabled.' ),
            'bypass_disabled' => array( 'type' => 'success', 'text' => 'LearnDash bypass mode has been disabled.' ),
            'settings_reset'  => array( 'type' => 'success', 'text' => 'Plugin settings have been reset.' ),
        );
        
        if ( isset( $messages[ $message_type ] ) ) {
            $message = $messages[ $message_type ];
            printf(
                '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                esc_attr( $message['type'] ),
                esc_html( $message['text'] )
            );
        }
    }
    
    /**
     * Enqueue admin assets
     * 
     * @param string $hook
     */
    public function enqueue_admin_assets( $hook ) {
        // Only load on our admin pages
        if ( strpos( $hook, 'ldtt' ) === false ) {
            return;
        }
        
        wp_enqueue_style(
            'ldtt-admin-style',
            LDTT_PLUGIN_URL . 'includes/assets/css/admin-style.css',
            array(),
            LDTT_VERSION
        );
        
        wp_enqueue_script(
            'ldtt-admin-script',
            LDTT_PLUGIN_URL . 'includes/assets/js/admin-script.js',
            array( 'jquery' ),
            LDTT_VERSION,
            true
        );
        
        wp_localize_script( 'ldtt-admin-script', 'ldtt_ajax', array(
            'url'   => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'ldtt_ajax_nonce' ),
        ) );
    }
    
    /**
     * Handle initialization error
     * 
     * @param Exception $e
     */
    private function handle_initialization_error( Exception $e ) {
        add_action( 'admin_notices', function() use ( $e ) {
            printf(
                '<div class="notice notice-error"><p><strong>LearnDash Testing Toolkit Error:</strong> %s</p></div>',
                esc_html( $e->getMessage() )
            );
        } );
    }
    
    /**
     * Get plugin status information
     * 
     * @return array
     */
    public function get_status() {
        return array(
            'version'     => LDTT_VERSION,
            'initialized' => $this->initialized,
            'components'  => array_keys( $this->components ),
            'php_version' => PHP_VERSION,
            'wp_version'  => get_bloginfo( 'version' ),
        );
    }
}

/**
 * Requirements Checker Class
 */
class LDTT_Requirements_Checker {
    
    /**
     * Check all requirements
     * 
     * @return bool
     */
    public function check_all() {
        $checks = array(
            'php_version' => $this->check_php_version(),
            'wp_version'  => $this->check_wp_version(),
            'functions'   => $this->check_required_functions(),
        );
        
        foreach ( $checks as $check => $result ) {
            if ( ! $result['passed'] ) {
                LDTT_Logger::error( "Requirement check failed: {$check} - {$result['message']}" );
                $this->show_requirement_error( $result['message'] );
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Check PHP version
     * 
     * @return array
     */
    private function check_php_version() {
        $passed = version_compare( PHP_VERSION, LDTT_MIN_PHP_VERSION, '>=' );
        
        return array(
            'passed'  => $passed,
            'message' => $passed ? 'PHP version OK' : sprintf( 'PHP %s or higher required. Current: %s', LDTT_MIN_PHP_VERSION, PHP_VERSION ),
        );
    }
    
    /**
     * Check WordPress version
     * 
     * @return array
     */
    private function check_wp_version() {
        global $wp_version;
        $min_wp_version = '5.0';
        $passed = version_compare( $wp_version, $min_wp_version, '>=' );
        
        return array(
            'passed'  => $passed,
            'message' => $passed ? 'WordPress version OK' : sprintf( 'WordPress %s or higher required. Current: %s', $min_wp_version, $wp_version ),
        );
    }
    
    /**
     * Check required functions
     * 
     * @return array
     */
    private function check_required_functions() {
        $required_functions = array( 'wp_insert_post', 'get_users', 'update_user_meta' );
        $missing = array();
        
        foreach ( $required_functions as $function ) {
            if ( ! function_exists( $function ) ) {
                $missing[] = $function;
            }
        }
        
        $passed = empty( $missing );
        
        return array(
            'passed'  => $passed,
            'message' => $passed ? 'All required functions available' : 'Missing functions: ' . implode( ', ', $missing ),
        );
    }
    
    /**
     * Show requirement error
     * 
     * @param string $message
     */
    private function show_requirement_error( $message ) {
        add_action( 'admin_notices', function() use ( $message ) {
            printf(
                '<div class="notice notice-error"><p><strong>LearnDash Testing Toolkit:</strong> %s</p></div>',
                esc_html( $message )
            );
        } );
    }
}

/**
 * Logger Class
 */
class LDTT_Logger {
    
    /**
     * Log levels
     */
    const LEVEL_ERROR   = 'ERROR';
    const LEVEL_WARNING = 'WARNING';
    const LEVEL_INFO    = 'INFO';
    const LEVEL_DEBUG   = 'DEBUG';
    
    /**
     * Log message
     * 
     * @param string $message
     * @param string $level
     * @param array  $context
     */
    private static function log( $message, $level = self::LEVEL_INFO, $context = array() ) {
        if ( ! self::should_log( $level ) ) {
            return;
        }
        
        $log_entry = sprintf(
            '[%s] [LDTT] [%s] %s',
            current_time( 'Y-m-d H:i:s' ),
            $level,
            $message
        );
        
        if ( ! empty( $context ) ) {
            $log_entry .= ' | Context: ' . wp_json_encode( $context );
        }
        
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( $log_entry );
        }
        
        // Store in database for admin viewing
        self::store_log_entry( $message, $level, $context );
    }
    
    /**
     * Log error
     * 
     * @param string $message
     * @param array  $context
     */
    public static function error( $message, $context = array() ) {
        self::log( $message, self::LEVEL_ERROR, $context );
    }
    
    /**
     * Log warning
     * 
     * @param string $message
     * @param array  $context
     */
    public static function warning( $message, $context = array() ) {
        self::log( $message, self::LEVEL_WARNING, $context );
    }
    
    /**
     * Log info
     * 
     * @param string $message
     * @param array  $context
     */
    public static function info( $message, $context = array() ) {
        self::log( $message, self::LEVEL_INFO, $context );
    }
    
    /**
     * Log debug
     * 
     * @param string $message
     * @param array  $context
     */
    public static function debug( $message, $context = array() ) {
        self::log( $message, self::LEVEL_DEBUG, $context );
    }
    
    /**
     * Check if should log based on level
     * 
     * @param string $level
     * @return bool
     */
    private static function should_log( $level ) {
        $log_level = get_option( 'ldtt_log_level', self::LEVEL_INFO );
        
        $levels = array(
            self::LEVEL_ERROR   => 1,
            self::LEVEL_WARNING => 2,
            self::LEVEL_INFO    => 3,
            self::LEVEL_DEBUG   => 4,
        );
        
        return isset( $levels[ $level ] ) && isset( $levels[ $log_level ] ) && $levels[ $level ] <= $levels[ $log_level ];
    }
    
    /**
     * Store log entry in database
     * 
     * @param string $message
     * @param string $level
     * @param array  $context
     */
    private static function store_log_entry( $message, $level, $context ) {
        $logs = get_option( 'ldtt_logs', array() );
        
        // Keep only last 100 entries
        if ( count( $logs ) >= 100 ) {
            $logs = array_slice( $logs, -99 );
        }
        
        $logs[] = array(
            'timestamp' => current_time( 'timestamp' ),
            'level'     => $level,
            'message'   => $message,
            'context'   => $context,
        );
        
        update_option( 'ldtt_logs', $logs );
    }
    
    /**
     * Get recent logs
     * 
     * @param int $limit
     * @return array
     */
    public static function get_recent_logs( $limit = 50 ) {
        $logs = get_option( 'ldtt_logs', array() );
        return array_slice( array_reverse( $logs ), 0, $limit );
    }
    
    /**
     * Clear logs
     */
    public static function clear_logs() {
        delete_option( 'ldtt_logs' );
    }
}