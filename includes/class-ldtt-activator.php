<?php

class LDTT_Activator {

    public static function activate() {
        // Ensure required dependencies are installed.
        self::check_dependencies();

        // Set default options or settings if necessary.
        self::set_default_options();

        // Log activation if needed or perform any initial setup tasks.
        self::log_activation();
    }

    private static function check_dependencies() {
        // Check for required plugins or PHP version.
        if ( ! function_exists( 'learndash_get_post_type_slug' ) && ! class_exists( 'LDLMS_Post_Types' ) ) {
            deactivate_plugins( plugin_basename( LDTT_PLUGIN_DIR . 'learndash-testing-toolkit.php' ) );
            wp_die( 
                __( 'This plugin requires LearnDash LMS to be installed and activated.', 'learndash-testing-toolkit' ),
                __( 'Plugin Activation Error', 'learndash-testing-toolkit' ),
                array( 'back_link' => true )
            );
        }

        // Check PHP version
        if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
            deactivate_plugins( plugin_basename( LDTT_PLUGIN_DIR . 'learndash-testing-toolkit.php' ) );
            wp_die( 
                __( 'This plugin requires PHP 7.4 or higher.', 'learndash-testing-toolkit' ),
                __( 'Plugin Activation Error', 'learndash-testing-toolkit' ),
                array( 'back_link' => true )
            );
        }
    }

    private static function set_default_options() {
        // Set default options if they don't exist.
        add_option( 'ldtt_version', LDTT_VERSION );
        add_option( 'ldtt_activation_time', current_time( 'timestamp' ) );
    }

    private static function log_activation() {
        // Log plugin activation
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[LDTT] LearnDash Testing Toolkit activated successfully.' );
        }
    }
}