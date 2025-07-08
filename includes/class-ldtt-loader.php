<?php

class LDTT_Loader {

    /**
     * Initialize the plugin by loading necessary files and CLI commands.
     */
    public static function init() {
        // Check if LearnDash is active
        if ( ! self::is_learndash_active() ) {
            add_action( 'admin_notices', array( __CLASS__, 'learndash_missing_notice' ) );
            return;
        }

        // Load helper classes
        require_once LDTT_PLUGIN_DIR . 'includes/helpers/class-ldtt-helper.php';
        require_once LDTT_PLUGIN_DIR . 'includes/helpers/class-ldtt-sample-data.php';

        // Include CLI command files to ensure they are always loaded
        self::include_files();

        // Load CLI commands if WP-CLI is available
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            self::load_cli_commands();
        }
    }

    /**
     * Check if LearnDash is active.
     */
    private static function is_learndash_active() {
        return function_exists( 'learndash_get_post_type_slug' ) || class_exists( 'LDLMS_Post_Types' );
    }

    /**
     * Display notice when LearnDash is not active.
     */
    public static function learndash_missing_notice() {
        echo '<div class="notice notice-error"><p>';
        esc_html_e( 'LearnDash Testing Toolkit requires LearnDash LMS to be installed and activated.', 'learndash-testing-toolkit' );
        echo '</p></div>';
    }

    /**
     * Include all necessary CLI command files.
     */
    private static function include_files() {
        require_once LDTT_PLUGIN_DIR . 'includes/cli-commands/class-ldtt-create-courses.php';
        require_once LDTT_PLUGIN_DIR . 'includes/cli-commands/class-ldtt-create-lessons.php';
        require_once LDTT_PLUGIN_DIR . 'includes/cli-commands/class-ldtt-create-topics.php';
        require_once LDTT_PLUGIN_DIR . 'includes/cli-commands/class-ldtt-create-quizzes.php';
        require_once LDTT_PLUGIN_DIR . 'includes/cli-commands/class-ldtt-create-questions.php';
        require_once LDTT_PLUGIN_DIR . 'includes/cli-commands/class-ldtt-enrollment.php';
        require_once LDTT_PLUGIN_DIR . 'includes/cli-commands/class-ldtt-course-groups.php';
        require_once LDTT_PLUGIN_DIR . 'includes/cli-commands/class-ldtt-group-leaders.php';
        require_once LDTT_PLUGIN_DIR . 'includes/cli-commands/class-ldtt-group-enrollment.php';
        require_once LDTT_PLUGIN_DIR . 'includes/cli-commands/class-ldtt-delete-items.php';
        require_once LDTT_PLUGIN_DIR . 'includes/cli-commands/class-ldtt-enhanced-user-distribution.php';
        require_once LDTT_PLUGIN_DIR . 'includes/helpers/class-ldtt-progress-manager.php';
    }

    /**
     * Load CLI command classes and register them with WP-CLI.
     */
    private static function load_cli_commands() {
        $commands = array(
            'Create_Courses',
            'Create_Lessons',
            'Create_Topics',
            'Create_Quizzes',
            'Create_Questions',
            'Delete_Items',
            'Enrollment',
            'Course_Groups',
            'Group_Leaders',
            'Group_Enrollment',
            'Enhanced_User_Distribution',
        );

        foreach ( $commands as $command ) {
            $class_name = 'LDTT_' . $command;
            $command_name = strtolower( str_replace( '_', '-', $command ) );
            WP_CLI::add_command( 'ldtt ' . $command_name, array( $class_name, 'handle' ) );
        }
    }
}