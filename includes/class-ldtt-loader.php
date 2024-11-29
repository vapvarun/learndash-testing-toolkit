<?php

class LDTT_Loader {

    /**
     * Initialize the plugin by loading necessary files and CLI commands.
     */
    public static function init() {
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
     * Include all necessary CLI command files.
     */
    private static function include_files() {
        error_log( 'Including CLI command files...' ); // Debugging log
        
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
        );

        foreach ( $commands as $command ) {
            $class_name = 'LDTT_' . $command;
            WP_CLI::add_command( 'ldtt ' . strtolower( str_replace( '_', '-', $command ) ), array( $class_name, 'handle' ) );
        }
    }
}
