<?php
/**
 * Plugin Name: LearnDash Testing Toolkit
 * Description: A CLI toolkit for testing LearnDash courses, lessons, topics, quizzes, and more.
 * Version: 1.1.0
 * Author: vapvarun
 * Text Domain: learndash-testing-toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

define( 'LDTT_VERSION', '1.1.0' );
define( 'LDTT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'LDTT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Include the main class loader.
require_once LDTT_PLUGIN_DIR . 'includes/class-ldtt-loader.php';
require_once LDTT_PLUGIN_DIR . 'includes/class-ldtt-activator.php';
require_once LDTT_PLUGIN_DIR . 'includes/class-ldtt-deactivator.php';

// Admin assets
function ldtt_enqueue_admin_assets() {
    wp_enqueue_style(
        'ldtt-admin-style',
        LDTT_PLUGIN_URL . 'includes/assets/css/admin-style.css',
        array(),
        LDTT_VERSION
    );

    wp_enqueue_script(
        'ldtt-admin-script',
        LDTT_PLUGIN_URL . 'includes/assets/js/admin-script.js',
        array('jquery'),
        LDTT_VERSION,
        true
    );

    wp_localize_script( 'ldtt-admin-script', 'ldtt_ajax', array(
        'url' => admin_url( 'admin-ajax.php' ),
        'nonce' => wp_create_nonce( 'ldtt_nonce' ),
    ) );
}
add_action('admin_enqueue_scripts', 'ldtt_enqueue_admin_assets');

// Add admin menu for CLI commands
add_action( 'admin_menu', 'ldtt_register_admin_menu' );

function ldtt_register_admin_menu() {
    add_menu_page(
        'LDTT CLI Commands',
        'LDTT Commands',
        'manage_options',
        'ldtt-cli-commands',
        'ldtt_admin_page',
        'dashicons-hammer',
        20
    );
}

function ldtt_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    // Handle AJAX for form submissions
    if ( isset( $_POST['ldtt_command'] ) && wp_verify_nonce( $_POST['ldtt_cli_command_nonce'], 'ldtt_cli_command_action' ) ) {
        $command = sanitize_text_field( $_POST['ldtt_command'] );
        $result = ldtt_run_command( $command );
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $result ) . '</p></div>';
    }

    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'LDTT CLI Commands', 'learndash-testing-toolkit' ); ?></h1>
        
        <div class="ldtt-tabs">
            <h2 class="nav-tab-wrapper">
                <a href="#courses" class="nav-tab nav-tab-active">Courses</a>
                <a href="#lessons" class="nav-tab">Lessons</a>
                <a href="#topics" class="nav-tab">Topics</a>
                <a href="#quizzes" class="nav-tab">Quizzes</a>
                <a href="#users" class="nav-tab">Users</a>
                <a href="#groups" class="nav-tab">Groups</a>
                <a href="#cleanup" class="nav-tab">Cleanup</a>
            </h2>

            <!-- Courses Tab -->
            <div id="courses" class="tab-content" style="display: block;">
                <h3>Create Test Courses</h3>
                <form method="post" class="ldtt-form">
                    <?php wp_nonce_field( 'ldtt_cli_command_action', 'ldtt_cli_command_nonce' ); ?>
                    <table class="form-table">
                        <tr>
                            <th>Number of Courses</th>
                            <td><input type="number" name="count" value="5" min="1" max="100" /></td>
                        </tr>
                        <tr>
                            <th>Course Prefix</th>
                            <td><input type="text" name="prefix" value="Test Course" /></td>
                        </tr>
                        <tr>
                            <th>Access Mode</th>
                            <td>
                                <select name="access_mode">
                                    <option value="">Random</option>
                                    <option value="open">Open</option>
                                    <option value="free">Free</option>
                                    <option value="paynow">Buy Now</option>
                                    <option value="subscribe">Subscription</option>
                                    <option value="closed">Closed</option>
                                </select>
                            </td>
                        </tr>
                    </table>
                    <button type="submit" name="ldtt_command" value="create-courses" class="button button-primary">Create Courses</button>
                </form>
            </div>

            <!-- Lessons Tab -->
            <div id="lessons" class="tab-content">
                <h3>Create Test Lessons</h3>
                <form method="post" class="ldtt-form">
                    <?php wp_nonce_field( 'ldtt_cli_command_action', 'ldtt_cli_command_nonce' ); ?>
                    <table class="form-table">
                        <tr>
                            <th>Number of Lessons</th>
                            <td><input type="number" name="count" value="10" min="1" max="500" /></td>
                        </tr>
                        <tr>
                            <th>Course ID (Optional)</th>
                            <td><input type="number" name="course_id" placeholder="Leave empty for random assignment" /></td>
                        </tr>
                    </table>
                    <button type="submit" name="ldtt_command" value="create-lessons" class="button button-primary">Create Lessons</button>
                </form>
            </div>

            <!-- Topics Tab -->
            <div id="topics" class="tab-content">
                <h3>Create Test Topics</h3>
                <form method="post" class="ldtt-form">
                    <?php wp_nonce_field( 'ldtt_cli_command_action', 'ldtt_cli_command_nonce' ); ?>
                    <table class="form-table">
                        <tr>
                            <th>Number of Topics</th>
                            <td><input type="number" name="count" value="20" min="1" max="1000" /></td>
                        </tr>
                        <tr>
                            <th>Lesson ID (Optional)</th>
                            <td><input type="number" name="lesson_id" placeholder="Leave empty for random assignment" /></td>
                        </tr>
                    </table>
                    <button type="submit" name="ldtt_command" value="create-topics" class="button button-primary">Create Topics</button>
                </form>
            </div>

            <!-- Quizzes Tab -->
            <div id="quizzes" class="tab-content">
                <h3>Create Test Quizzes</h3>
                <form method="post" class="ldtt-form">
                    <?php wp_nonce_field( 'ldtt_cli_command_action', 'ldtt_cli_command_nonce' ); ?>
                    <table class="form-table">
                        <tr>
                            <th>Number of Quizzes</th>
                            <td><input type="number" name="count" value="5" min="1" max="100" /></td>
                        </tr>
                        <tr>
                            <th>Questions per Quiz</th>
                            <td><input type="number" name="questions" value="5" min="1" max="50" /></td>
                        </tr>
                    </table>
                    <button type="submit" name="ldtt_command" value="create-quizzes" class="button button-primary">Create Quizzes</button>
                </form>
            </div>

            <!-- Users Tab -->
            <div id="users" class="tab-content">
                <h3>Create and Enroll Users</h3>
                <form method="post" class="ldtt-form">
                    <?php wp_nonce_field( 'ldtt_cli_command_action', 'ldtt_cli_command_nonce' ); ?>
                    <table class="form-table">
                        <tr>
                            <th>Number of Users</th>
                            <td><input type="number" name="count" value="10" min="1" max="100" /></td>
                        </tr>
                        <tr>
                            <th>Course ID</th>
                            <td><input type="number" name="course_id" placeholder="Enter course ID" required /></td>
                        </tr>
                    </table>
                    <button type="submit" name="ldtt_command" value="enroll-users" class="button button-primary">Create & Enroll Users</button>
                </form>
            </div>

            <!-- Groups Tab -->
            <div id="groups" class="tab-content">
                <h3>Create Test Groups</h3>
                <form method="post" class="ldtt-form">
                    <?php wp_nonce_field( 'ldtt_cli_command_action', 'ldtt_cli_command_nonce' ); ?>
                    <table class="form-table">
                        <tr>
                            <th>Number of Groups</th>
                            <td><input type="number" name="count" value="3" min="1" max="50" /></td>
                        </tr>
                        <tr>
                            <th>Group Prefix</th>
                            <td><input type="text" name="prefix" value="Test Group" /></td>
                        </tr>
                        <tr>
                            <th>Course IDs (Optional)</th>
                            <td><input type="text" name="courses" placeholder="e.g., 123,456,789" /></td>
                        </tr>
                    </table>
                    <button type="submit" name="ldtt_command" value="course-groups" class="button button-primary">Create Groups</button>
                </form>

                <h3>Create Group Leaders</h3>
                <form method="post" class="ldtt-form">
                    <?php wp_nonce_field( 'ldtt_cli_command_action', 'ldtt_cli_command_nonce' ); ?>
                    <table class="form-table">
                        <tr>
                            <th>Group Name</th>
                            <td><input type="text" name="group" value="Test Group" /></td>
                        </tr>
                        <tr>
                            <th>Leader Name</th>
                            <td><input type="text" name="leader" value="Test Leader" /></td>
                        </tr>
                        <tr>
                            <th>Number of Leaders</th>
                            <td><input type="number" name="count" value="1" min="1" max="10" /></td>
                        </tr>
                    </table>
                    <button type="submit" name="ldtt_command" value="group-leaders" class="button button-primary">Create Group Leaders</button>
                </form>

                <h3>Enroll Users in Groups</h3>
                <form method="post" class="ldtt-form">
                    <?php wp_nonce_field( 'ldtt_cli_command_action', 'ldtt_cli_command_nonce' ); ?>
                    <table class="form-table">
                        <tr>
                            <th>Group Name</th>
                            <td><input type="text" name="group" value="Test Group" /></td>
                        </tr>
                        <tr>
                            <th>Number of Users</th>
                            <td><input type="number" name="users" value="10" min="1" max="100" /></td>
                        </tr>
                    </table>
                    <button type="submit" name="ldtt_command" value="group-enrollment" class="button button-primary">Create & Enroll Users in Group</button>
                </form>
            </div>

            <!-- Cleanup Tab -->
            <div id="cleanup" class="tab-content">
                <h3>Clean Test Data</h3>
                <div class="notice notice-warning">
                    <p>Warning: This will permanently delete data. Use with caution!</p>
                </div>
                <form method="post" class="ldtt-form">
                    <?php wp_nonce_field( 'ldtt_cli_command_action', 'ldtt_cli_command_nonce' ); ?>
                    <table class="form-table">
                        <tr>
                            <th>Data to Clean</th>
                            <td>
                                <label><input type="checkbox" name="courses" /> Courses</label><br>
                                <label><input type="checkbox" name="lessons" /> Lessons</label><br>
                                <label><input type="checkbox" name="topics" /> Topics</label><br>
                                <label><input type="checkbox" name="quizzes" /> Quizzes</label><br>
                                <label><input type="checkbox" name="groups" /> Groups</label><br>
                                <label><input type="checkbox" name="users" /> Test Users</label>
                            </td>
                        </tr>
                        <tr>
                            <th>Confirmation</th>
                            <td>
                                <label><input type="checkbox" name="confirm" required /> I understand this will permanently delete data</label>
                            </td>
                        </tr>
                    </table>
                    <button type="submit" name="ldtt_command" value="delete-items" class="button button-secondary">Clean Data</button>
                </form>
            </div>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        $('.nav-tab').click(function(e) {
            e.preventDefault();
            $('.nav-tab').removeClass('nav-tab-active');
            $('.tab-content').hide();
            $(this).addClass('nav-tab-active');
            $($(this).attr('href')).show();
        });
    });
    </script>
    <?php
}

function ldtt_run_command( $command ) {
    $commands_map = array(
        'create-courses' => 'LDTT_Create_Courses',
        'create-lessons' => 'LDTT_Create_Lessons',
        'create-topics' => 'LDTT_Create_Topics',
        'create-quizzes' => 'LDTT_Create_Quizzes',
        'enroll-users' => 'LDTT_Enrollment',
        'course-groups' => 'LDTT_Course_Groups',
        'group-leaders' => 'LDTT_Group_Leaders',
        'group-enrollment' => 'LDTT_Group_Enrollment',
        'delete-items' => 'LDTT_Delete_Items',
    );

    if ( isset( $commands_map[ $command ] ) ) {
        $class_name = $commands_map[ $command ];
        if ( class_exists( $class_name ) && method_exists( $class_name, 'handle' ) ) {
            $result = $class_name::handle( array(), array() );
            if ( is_array( $result ) ) {
                return $result['message'] ?? 'Command executed successfully.';
            }
            return 'Command executed successfully.';
        }
    }
    return 'Invalid command.';
}

// Activation and deactivation hooks.
register_activation_hook( __FILE__, array( 'LDTT_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'LDTT_Deactivator', 'deactivate' ) );

// Initialize the plugin.
LDTT_Loader::init();