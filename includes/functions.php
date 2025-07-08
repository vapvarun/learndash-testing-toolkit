<?php
/**
 * Global Functions for LDTT
 * 
 * @package LearnDash_Testing_Toolkit
 * @since 1.2.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Get LDTT plugin instance
 * 
 * @return LearnDash_Testing_Toolkit|null
 */
if ( ! function_exists( 'ldtt' ) ) {
    function ldtt() {
        return LearnDash_Testing_Toolkit::get_instance();
    }
}

/**
 * Get LDTT core instance
 * 
 * @return LDTT_Core|null
 */
if ( ! function_exists( 'ldtt_core' ) ) {
    function ldtt_core() {
        $plugin = ldtt();
        return $plugin ? $plugin->get_core() : null;
    }
}

/**
 * Get LDTT component
 * 
 * @param string $component_name
 * @return object|null
 */
if ( ! function_exists( 'ldtt_get_component' ) ) {
    function ldtt_get_component( $component_name ) {
        $core = ldtt_core();
        return $core ? $core->get_component( $component_name ) : null;
    }
}

/**
 * Check if LearnDash is available
 * 
 * @return bool
 */
if ( ! function_exists( 'ldtt_is_learndash_available' ) ) {
    function ldtt_is_learndash_available() {
        $detector = ldtt_get_component( 'learndash_detector' );
        return $detector ? $detector->is_learndash_available() : false;
    }
}

/**
 * Execute LDTT command
 * 
 * @param string $command
 * @param array $args
 * @param array $assoc_args
 * @return array
 */
if ( ! function_exists( 'ldtt_execute_command' ) ) {
    function ldtt_execute_command( $command, $args = array(), $assoc_args = array() ) {
        $command_factory = ldtt_get_component( 'command_factory' );
        if ( ! $command_factory ) {
            return array( 'success' => false, 'message' => 'Command factory not available' );
        }
        
        return $command_factory->execute_command( $command, $args, $assoc_args );
    }
}

/**
 * Get test data statistics
 * 
 * @return array
 */
if ( ! function_exists( 'ldtt_get_test_statistics' ) ) {
    function ldtt_get_test_statistics() {
        $data_manager = ldtt_get_component( 'data_manager' );
        return $data_manager ? $data_manager->get_test_data_statistics() : array();
    }
}

/**
 * Create admin notice
 * 
 * @param string $message
 * @param string $type
 * @param bool $dismissible
 */
if ( ! function_exists( 'ldtt_add_admin_notice' ) ) {
    function ldtt_add_admin_notice( $message, $type = 'info', $dismissible = true ) {
        add_action( 'admin_notices', function() use ( $message, $type, $dismissible ) {
            $dismissible_class = $dismissible ? 'is-dismissible' : '';
            printf(
                '<div class="notice notice-%s %s"><p>%s</p></div>',
                esc_attr( $type ),
                esc_attr( $dismissible_class ),
                esc_html( $message )
            );
        } );
    }
}

/**
 * Log LDTT message
 * 
 * @param string $message
 * @param string $level
 * @param array $context
 */
if ( ! function_exists( 'ldtt_log' ) ) {
    function ldtt_log( $message, $level = 'info', $context = array() ) {
        if ( ! class_exists( 'LDTT_Logger' ) ) {
            return;
        }
        
        switch ( strtolower( $level ) ) {
            case 'error':
                LDTT_Logger::error( $message, $context );
                break;
            case 'warning':
                LDTT_Logger::warning( $message, $context );
                break;
            case 'debug':
                LDTT_Logger::debug( $message, $context );
                break;
            default:
                LDTT_Logger::info( $message, $context );
        }
    }
}

/**
 * Get plugin version
 * 
 * @return string
 */
function ldtt_get_version() {
    return defined( 'LDTT_VERSION' ) ? LDTT_VERSION : '1.2.0';
}

/**
 * Check if plugin is in development mode
 * 
 * @return bool
 */
function ldtt_is_dev_mode() {
    return defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'LDTT_DEV_MODE' ) && LDTT_DEV_MODE;
}

/**
 * Get admin URL for LDTT pages
 * 
 * @param string $page
 * @param array $args
 * @return string
 */
function ldtt_admin_url( $page = 'ldtt-cli-commands', $args = array() ) {
    $args['page'] = $page;
    return add_query_arg( $args, admin_url( 'admin.php' ) );
}

/**
 * Clean all test data
 * 
 * @param array $options
 * @return array
 */
function ldtt_clean_test_data( $options = array() ) {
    $data_manager = ldtt_get_component( 'data_manager' );
    if ( ! $data_manager ) {
        return array( 'success' => false, 'message' => 'Data manager not available' );
    }
    
    return $data_manager->clean_all_test_data( $options );
}

/**
 * Create backup of test data
 * 
 * @return array|WP_Error
 */
function ldtt_create_backup() {
    if ( ! class_exists( 'LDTT_Backup' ) ) {
        return new WP_Error( 'backup_class_missing', 'Backup class not available' );
    }
    
    return LDTT_Backup::create_backup();
}

/**
 * Format large numbers for display
 * 
 * @param int $number
 * @return string
 */
function ldtt_format_number( $number ) {
    if ( $number >= 1000000 ) {
        return round( $number / 1000000, 1 ) . 'M';
    } elseif ( $number >= 1000 ) {
        return round( $number / 1000, 1 ) . 'K';
    }
    return number_format( $number );
}

/**
 * Check if CLI is available
 * 
 * @return bool
 */
function ldtt_is_cli() {
    return defined( 'WP_CLI' ) && WP_CLI;
}

/**
 * Get current user timezone
 * 
 * @return string
 */
function ldtt_get_timezone() {
    return get_option( 'timezone_string' ) ?: 'UTC';
}

/**
 * Convert bytes to human readable format
 * 
 * @param int $bytes
 * @return string
 */
function ldtt_format_bytes( $bytes ) {
    $units = array( 'B', 'KB', 'MB', 'GB', 'TB' );
    $bytes = max( $bytes, 0 );
    $pow = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
    $pow = min( $pow, count( $units ) - 1 );
    
    $bytes /= pow( 1024, $pow );
    
    return round( $bytes, 2 ) . ' ' . $units[ $pow ];
}

/**
 * Check if operation should be batched
 * 
 * @param int $count
 * @param int $batch_size
 * @return bool
 */
function ldtt_should_batch( $count, $batch_size = 100 ) {
    return $count > $batch_size;
}

/**
 * Validate user capabilities for LDTT operations
 * 
 * @param string $capability
 * @return bool
 */
function ldtt_user_can( $capability = 'manage_options' ) {
    return current_user_can( $capability );
}

/**
 * Get LDTT settings
 * 
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function ldtt_get_setting( $key = null, $default = null ) {
    $settings = get_option( 'ldtt_settings', array() );
    
    if ( null === $key ) {
        return $settings;
    }
    
    return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
}

/**
 * Update LDTT setting
 * 
 * @param string $key
 * @param mixed $value
 * @return bool
 */
function ldtt_update_setting( $key, $value ) {
    $settings = get_option( 'ldtt_settings', array() );
    $settings[ $key ] = $value;
    return update_option( 'ldtt_settings', $settings );
}

/**
 * Security function to verify nonce and capabilities
 * 
 * @param string $action
 * @param string $capability
 * @return bool
 */
function ldtt_verify_request( $action, $capability = 'manage_options' ) {
    if ( class_exists( 'LDTT_Security' ) ) {
        return LDTT_Security::verify_request( $action, $capability );
    }
    
    // Fallback verification
    return current_user_can( $capability ) && wp_verify_nonce( 
        $_POST[ 'ldtt_' . $action . '_nonce' ] ?? $_GET['nonce'] ?? '', 
        'ldtt_' . $action . '_action' 
    );
}