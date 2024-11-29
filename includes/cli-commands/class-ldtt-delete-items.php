<?php

class LDTT_Delete_Items {

    public static function handle( $args = array(), $assoc_args = array() ) {
        // Check for admin input if no CLI arguments are provided
        if ( empty( $args ) && empty( $assoc_args ) ) {
            $assoc_args = array(
                'courses'   => isset( $_POST['courses'] ) ? true : false,
                'lessons'   => isset( $_POST['lessons'] ) ? true : false,
                'topics'    => isset( $_POST['topics'] ) ? true : false,
                'permanent' => isset( $_POST['permanent'] ) ? true : false,
            );
        }

        $delete_courses = isset( $assoc_args['courses'] );
        $delete_lessons = isset( $assoc_args['lessons'] );
        $delete_topics = isset( $assoc_args['topics'] );
        $permanent_delete = isset( $assoc_args['permanent'] );

        // Validate the input
        if ( ! $delete_courses && ! $delete_lessons && ! $delete_topics ) {
            return array( 'status' => 'error', 'message' => "Please specify at least one type of item to delete: --courses, --lessons, --topics" );
        }

        $results = array();

        // Delete courses
        if ( $delete_courses ) {
            $results[] = self::delete_items( 'sfwd-courses', 'Course', $permanent_delete );
        }

        // Delete lessons
        if ( $delete_lessons ) {
            $results[] = self::delete_items( 'sfwd-lessons', 'Lesson', $permanent_delete );
        }

        // Delete topics
        if ( $delete_topics ) {
            $results[] = self::delete_items( 'sfwd-topic', 'Topic', $permanent_delete );
        }

        return array(
            'status'  => 'success',
            'message' => "Deletion process completed.",
            'details' => $results,
        );
    }

    private static function delete_items( $post_type, $item_name, $permanent_delete ) {
        $items = get_posts( array(
            'post_type'   => $post_type,
            'numberposts' => -1,
            'post_status' => 'publish',
            'fields'      => 'ids',
        ) );

        if ( empty( $items ) ) {
            return array( 'status' => 'error', 'message' => "No {$item_name}s found to delete." );
        }

        $deleted_count = 0;

        foreach ( $items as $item_id ) {
            wp_delete_post( $item_id, $permanent_delete );
            $deleted_count++;
        }

        return array(
            'status' => 'success',
            'message' => "{$deleted_count} {$item_name}(s) have been deleted.",
            'count' => $deleted_count,
        );
    }
}
