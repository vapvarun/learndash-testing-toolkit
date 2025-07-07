<?php

class LDTT_Create_Lessons {

    public static function handle( $args = array(), $assoc_args = array() ) {
        // Check for admin input if no arguments are provided
        if ( empty( $args ) && empty( $assoc_args ) ) {
            $assoc_args = array(
                'count'     => isset( $_POST['count'] ) ? intval( $_POST['count'] ) : 10,
                'course_id' => isset( $_POST['course_id'] ) ? intval( $_POST['course_id'] ) : null,
            );
        }

        $lesson_count = LDTT_Helper::validate_positive_int( $assoc_args['count'] ?? 10, 10, 500 );
        $specific_course_id = ! empty( $assoc_args['course_id'] ) ? absint( $assoc_args['course_id'] ) : null;

        // Validate the course ID if provided
        if ( $specific_course_id && get_post_type( $specific_course_id ) !== learndash_get_post_type_slug( 'course' ) ) {
            $message = "Course ID {$specific_course_id} is not a valid course.";
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::error( $message );
            }
            return array( 'status' => 'error', 'message' => $message );
        }

        $courses = $specific_course_id ? array( $specific_course_id ) : self::get_available_courses();

        if ( empty( $courses ) ) {
            $message = "No available courses found to assign lessons.";
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::error( $message );
            }
            return array( 'status' => 'error', 'message' => $message );
        }

        $titles = LDTT_Helper::get_random_lesson_titles( $lesson_count );
        $admin_user_id = LDTT_Helper::get_admin_user_id();

        if ( ! $admin_user_id ) {
            $message = 'No admin users found to assign as author.';
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::error( $message );
            }
            return array( 'status' => 'error', 'message' => $message );
        }

        $created_lessons = array();

        for ( $i = 0; $i < $lesson_count; $i++ ) {
            $course_id = $specific_course_id ? $specific_course_id : $courses[ array_rand( $courses ) ];
            $lesson_title = isset( $titles[ $i ] ) ? trim( $titles[ $i ] ) : "Lesson " . ( $i + 1 );

            if ( empty( $lesson_title ) ) {
                continue;
            }

            $lesson_id = wp_insert_post( array(
                'post_title'   => $lesson_title,
                'post_type'    => learndash_get_post_type_slug( 'lesson' ),
                'post_status'  => 'publish',
                'post_author'  => $admin_user_id,
                'post_content' => "This is a test lesson: {$lesson_title}",
                'meta_input'   => array(
                    '_ldtt_test_data' => true,
                ),
            ) );

            if ( is_wp_error( $lesson_id ) ) {
                if ( defined( 'WP_CLI' ) && WP_CLI ) {
                    WP_CLI::warning( "Failed to create lesson: {$lesson_title}" );
                }
                continue;
            }

            // Associate lesson with course using LearnDash function
            learndash_update_setting( $lesson_id, 'course', $course_id );

            $created_lessons[] = $lesson_id;

            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::line( "Created lesson: {$lesson_title} (ID: {$lesson_id})" );
            }
        }

        $message = count( $created_lessons ) . " lessons created successfully.";
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::success( $message );
        }
        return array(
            'status'  => 'success',
            'message' => $message,
            'lesson_ids' => $created_lessons,
        );
    }

    private static function get_available_courses() {
        $courses = get_posts( array(
            'post_type'   => learndash_get_post_type_slug( 'course' ),
            'numberposts' => -1,
            'post_status' => 'publish',
            'fields'      => 'ids',
        ) );

        return $courses;
    }
}