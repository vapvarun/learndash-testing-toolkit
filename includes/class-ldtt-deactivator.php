<?php

class LDTT_Deactivator {

    public static function deactivate() {
        // Clear scheduled hooks, if any.
        self::clear_scheduled_hooks();

        // Optionally, remove any custom options or settings stored in the database.
        // delete_option('ldtt_custom_option');

        // Log deactivation or perform any cleanup needed.
        // self::cleanup_plugin_data();
    }

    private static function clear_scheduled_hooks() {
        // Example: If you have scheduled events, they should be cleared on deactivation.
        // wp_clear_scheduled_hook('ldtt_scheduled_event');
    }

    private static function cleanup_plugin_data() {
        // Cleanup operations, like removing custom tables or files, if necessary.
    }
}
