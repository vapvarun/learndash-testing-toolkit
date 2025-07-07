<?php

class LDTT_Deactivator {

    public static function deactivate() {
        // Clear scheduled hooks, if any.
        self::clear_scheduled_hooks();

        // Log deactivation
        self::log_deactivation();

        // Note: We don't delete test data on deactivation
        // Users should use the cleanup function in admin if they want to remove test data
    }

    private static function clear_scheduled_hooks() {
        // Clear any scheduled events if we had them
        // wp_clear_scheduled_hook('ldtt_scheduled_event');
    }

    private static function log_deactivation() {
        // Log plugin deactivation
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[LDTT] LearnDash Testing Toolkit deactivated.' );
        }
    }
}