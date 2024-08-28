<?php
/**
 * Plugin Name: LearnDash Testing Toolkit
 * Description: A CLI toolkit for testing LearnDash courses, lessons, topics, quizzes, and more.
 * Version: 1.0.0
 * Author: vapvarun
 * Text Domain: learndash-testing-toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

define( 'LDTT_VERSION', '1.0.0' );
define( 'LDTT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// Include the main class loader.
require_once LDTT_PLUGIN_DIR . 'includes/class-ldtt-loader.php';

function ldtt_enqueue_admin_assets() {
    // Enqueue the CSS file
    wp_enqueue_style(
        'ldtt-admin-style',
        plugin_dir_url(__FILE__) . 'assets/css/admin-style.css',
        array(),
        '1.0.0'
    );

    // Enqueue the JavaScript file
    wp_enqueue_script(
        'ldtt-admin-script',
        plugin_dir_url(__FILE__) . 'assets/js/admin-script.js',
        array('jquery'),  // Dependency on jQuery if needed
        '1.0.0',
        true  // Load in footer
    );
}
add_action('admin_enqueue_scripts', 'ldtt_enqueue_admin_assets');


// Activation and deactivation hooks.
register_activation_hook( __FILE__, array( 'LDTT_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'LDTT_Deactivator', 'deactivate' ) );

// Initialize the plugin.
LDTT_Loader::init();
