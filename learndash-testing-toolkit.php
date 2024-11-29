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
require_once LDTT_PLUGIN_DIR . 'includes/class-ldtt-activator.php';
require_once LDTT_PLUGIN_DIR . 'includes/class-ldtt-deactivator.php';

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

// Add admin menu for CLI commands
add_action( 'admin_menu', 'ldtt_register_admin_menu' );

/**
 * Register LDTT CLI Commands admin menu.
 */
function ldtt_register_admin_menu() {
    add_menu_page(
        'LDTT CLI Commands',       // Page title
        'LDTT Commands',           // Menu title
        'manage_options',          // Capability
        'ldtt-cli-commands',       // Menu slug
        'ldtt_admin_page',         // Callback function to render the admin page
        'dashicons-hammer',        // Menu icon
        20                         // Menu position
    );
}

/**
 * Render the admin page for LDTT CLI Commands.
 */
function ldtt_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    // Check if a command has been executed
    if ( isset( $_POST['ldtt_command'] ) ) {
        check_admin_referer( 'ldtt_cli_command_action', 'ldtt_cli_command_nonce' ); // Verify nonce for security

        $command = sanitize_text_field( $_POST['ldtt_command'] );
        $result = ldtt_run_command( $command );

        // Display a success or error message
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $result ) . '</p></div>';
    }

    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'LDTT CLI Commands', 'learndash-testing-toolkit' ); ?></h1>
        <form method="post">
            <?php
            // Add nonce field for security
            wp_nonce_field( 'ldtt_cli_command_action', 'ldtt_cli_command_nonce' );

            // List of commands
            $commands = array(
                'create-courses' => 'Create Courses',
                'create-lessons' => 'Create Lessons',
                'create-topics' => 'Create Topics',
                'create-quizzes' => 'Create Quizzes',
                'create-questions' => 'Create Questions',
                'delete-items' => 'Delete Items',
                'enrollment' => 'Enrollment',
                'course-groups' => 'Course Groups',
                'group-leaders' => 'Group Leaders',
                'group-enrollment' => 'Group Enrollment',
            );

            // Generate buttons for each command
            foreach ( $commands as $slug => $label ) {
                echo '<button type="submit" name="ldtt_command" value="' . esc_attr( $slug ) . '" class="button button-primary" style="margin-right: 10px; margin-bottom: 10px;">' . esc_html( $label ) . '</button>';
            }
            ?>
        </form>
    </div>
    <?php
}

/**
 * Run the selected CLI command.
 *
 * @param string $command Command slug.
 * @return string Command result message.
 */
function ldtt_run_command( $command ) {
    // Map commands to their respective classes
    $commands_map = array(
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
    );

    // Check if the command is valid
    if ( isset( $commands_map[ $command ] ) ) {
        $class_name = $commands_map[ $command ];
        if ( class_exists( $class_name ) && method_exists( $class_name, 'handle' ) ) {
            // Capture the output buffer to handle CLI command output
            ob_start();
            $class_name::handle( array(), array() ); // No args, modify if necessary
            $output = ob_get_clean();
            return $output ?: __( 'Command executed successfully.', 'learndash-testing-toolkit' );
        }
    }

    return __( 'Invalid command.', 'learndash-testing-toolkit' );
}

// Activation and deactivation hooks.
register_activation_hook( __FILE__, array( 'LDTT_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'LDTT_Deactivator', 'deactivate' ) );

// Initialize the plugin.
LDTT_Loader::init();
