<?php

class LDTT_Create_Lessons {

    /**
     * Handle the WP-CLI command to create lessons within a LearnDash course.
     *
     * @param array $args Positional arguments passed from the WP-CLI command.
     * @param array $assoc_args Associative arguments passed from the WP-CLI command.
     */
    public static function handle( $args, $assoc_args ) {
        $course_name = isset( $assoc_args['course'] ) ? $assoc_args['course'] : 'Sample Course';
        $lesson_count = isset( $assoc_args['lessons'] ) ? intval( $assoc_args['lessons'] ) : 3;

        // Get or create the course
        $course_id = self::get_or_create_course( $course_name );
        if ( is_wp_error( $course_id ) ) {
            LDTT_Helper::cli_error( $course_id->get_error_message() );
            return;
        }

        // Create lessons under the course
        $lesson_ids = self::create_lessons( $course_id, $lesson_count );
        if ( is_wp_error( $lesson_ids ) ) {
            LDTT_Helper::cli_error( $lesson_ids->get_error_message() );
        } else {
            LDTT_Helper::cli_success( "{$lesson_count} lessons successfully created under the course '{$course_name}'." );
        }
    }

    /**
     * Get an existing course by name or create a new one.
     *
     * @param string $course_name The name of the course.
     * @return int|WP_Error The course ID on success, WP_Error on failure.
     */
    private static function get_or_create_course( $course_name ) {
        $course = get_page_by_title( $course_name, OBJECT, 'sfwd-courses' );
        if ( $course ) {
            return $course->ID;
        }

        $course_id = wp_insert_post( array(
            'post_title'   => $course_name,
            'post_type'    => 'sfwd-courses',
            'post_status'  => 'publish',
            'post_content' => 'This is a sample course created by the LearnDash Testing Toolkit.',
        ) );

        if ( is_wp_error( $course_id ) ) {
            return $course_id;
        }

        return $course_id;
    }

    /**
     * Create lessons under a specified course.
     *
     * @param int $course_id The ID of the course.
     * @param int $lesson_count The number of lessons to create.
     * @return array|WP_Error An array of lesson IDs on success, WP_Error on failure.
     */
    private static function create_lessons( $course_id, $lesson_count ) {
        $lesson_ids = array();

        for ( $i = 1; $i <= $lesson_count; $i++ ) {
            $lesson_title = "Sample Lesson {$i}";
            $lesson_content = "This is sample lesson {$i} for course ID {$course_id}.";

            $lesson_id = wp_insert_post( array(
                'post_title'   => $lesson_title,
                'post_type'    => 'sfwd-lessons',
                'post_status'  => 'publish',
                'post_content' => $lesson_content,
                'post_parent'  => $course_id, // Associate the lesson with the course.
            ) );

            if ( is_wp_error( $lesson_id ) ) {
                return $lesson_id;
            }

            $lesson_ids[] = $lesson_id;
        }

        return $lesson_ids;
    }
}
