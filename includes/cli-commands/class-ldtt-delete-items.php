<?php

class LDTT_Delete_Items {

    public static function handle( $args, $assoc_args ) {
        // Check which items to delete based on the command's parameters
        $delete_courses = isset( $assoc_args['courses'] );
        $delete_lessons = isset( $assoc_args['lessons'] );
        $delete_topics = isset( $assoc_args['topics'] );

        if ( ! $delete_courses && ! $delete_lessons && ! $delete_topics ) {
            LDTT_Helper::cli_error( "Please specify at least one type of item to delete: --courses, --lessons, --topics" );
            return;
        }

        // Delete courses
        if ( $delete_courses ) {
            self::delete_items( 'sfwd-courses', 'Course' );
        }

        // Delete lessons
        if ( $delete_lessons ) {
            self::delete_items( 'sfwd-lessons', 'Lesson' );
        }

        // Delete topics
        if ( $delete_topics ) {
            self::delete_items( 'sfwd-topic', 'Topic' );
        }
    }

    private static function delete_items( $post_type, $item_name ) {
        $items = get_posts( array(
            'post_type'   => $post_type,
            'numberposts' => -1,
            'post_status' => 'publish',
            'fields'      => 'ids',
        ) );

        if ( empty( $items ) ) {
            LDTT_Helper::cli_error( "No {$item_name}s found to delete." );
            return;
        }

        foreach ( $items as $item_id ) {
            wp_delete_post( $item_id, true );
            LDTT_Helper::cli_success( "Deleted {$item_name} ID {$item_id}." );
        }

        LDTT_Helper::cli_success( "All {$item_name}s have been deleted." );
    }
}
