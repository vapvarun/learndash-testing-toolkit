<?php

class LDTT_Loader {

    public static function init() {
        // Load CLI commands.
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            self::load_cli_commands();
        }
    }

    private static function load_cli_commands() {
        $commands = array(
            'Create_Courses',
            'Create_Lessons',
            'Create_Topics',
            'Create_Quizzes',
            'Create_Questions',
            'Enrollment',
            'Course_Groups',
            'Group_Leaders',
            'Group_Enrollment',
        );

        foreach ( $commands as $command ) {
            require_once LDTT_PLUGIN_DIR . 'includes/cli-commands/class-ldtt-' . strtolower( $command ) . '.php';
            $class_name = 'LDTT_' . $command;
            WP_CLI::add_command( 'ldtt ' . strtolower( str_replace( '_', '-', $command ) ), array( $class_name, 'handle' ) );
        }
    }
}
