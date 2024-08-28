<?php

class LDTT_Activator {

    public static function activate() {
        // Ensure required dependencies are installed.
        self::check_dependencies();

        // Set default options or settings if necessary.
        self::set_default_options();

        // Schedule any recurring events, if needed.
        self::schedule_events();

        // Optionally, create custom database tables.
        // self::create_custom_tables();

        // Log activation if needed or perform any initial setup tasks.
        // self::log_activation();
    }

    private static function check_dependencies() {
        // Check for required plugins or PHP version.
        if ( ! class_exists( 'LearnDash_Settings_Section' ) ) {
            deactivate_plugins( plugin_basename( __FILE__ ) );
            wp_die( __( 'This plugin requires LearnDash to be installed and activated.', 'learndash-testing-toolkit' ) );
        }
    }

    private static function set_default_options() {
        // Set default options if they don't exist.
        // Example: add_option('ldtt_default_setting', 'default_value');
    }

    private static function schedule_events() {
        // Example: Schedule a recurring event.
        // if ( ! wp_next_scheduled( 'ldtt_scheduled_event' ) ) {
        //     wp_schedule_event( time(), 'hourly', 'ldtt_scheduled_event' );
        // }
    }

    private static function create_custom_tables() {
        // Create custom database tables if your plugin requires them.
        // global $wpdb;
        // $table_name = $wpdb->prefix . 'ldtt_custom_table';
        // $charset_collate = $wpdb->get_charset_collate();
        // $sql = "CREATE TABLE $table_name (
        //     id mediumint(9) NOT NULL AUTO_INCREMENT,
        //     name tinytext NOT NULL,
        //     PRIMARY KEY (id)
        // ) $charset_collate;";
        // require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        // dbDelta( $sql );
    }

    private static function log_activation() {
        // Log plugin activation or perform other initial setup tasks.
        // Example: error_log( 'LearnDash Testing Toolkit activated.' );
    }
}
