<?php

class LDTT_Create_Courses {

    /**
     * Handle the WP-CLI command to create courses in LearnDash.
     *
     * @param array $args Positional arguments passed from the WP-CLI command.
     * @param array $assoc_args Associative arguments passed from the WP-CLI command.
     */
    public static function handle( $args, $assoc_args ) {
        $course_count = isset( $assoc_args['count'] ) ? intval( $assoc_args['count'] ) : 1;
        $course_prefix = isset( $assoc_args['prefix'] ) ? $assoc_args['prefix'] : 'Sample Course';

        // Create the specified number of courses
        $course_ids = self::create_courses( $course_prefix, $course_count );
        if ( is_wp_error( $course_ids ) ) {
            LDTT_Helper::cli_error( $course_ids->get_error_message() );
        } else {
            LDTT_Helper::cli_success( "{$course_count} courses successfully created with the prefix '{$course_prefix}'." );
        }
    }

    /**
     * Create courses in LearnDash.
     *
     * @param string $course_prefix The prefix for the course titles.
     * @param int $course_count The number of courses to create.
     * @return array|WP_Error An array of course IDs on success, WP_Error on failure.
     */
    private static function create_courses( $course_prefix, $course_count ) {
        $course_ids = array();

        for ( $i = 1; $i <= $course_count; $i++ ) {
            $course_title = "{$course_prefix} {$i}";
            $course_content = "This is the content for {$course_title}.";

            $course_id = wp_insert_post( array(
                'post_title'   => $course_title,
                'post_type'    => 'sfwd-courses',
                'post_status'  => 'publish',
                'post_content' => $course_content,
            ) );

            if ( is_wp_error( $course_id ) ) {
                return $course_id;
            }

            $course_ids[] = $course_id;
        }

        return $course_ids;
    }
}
