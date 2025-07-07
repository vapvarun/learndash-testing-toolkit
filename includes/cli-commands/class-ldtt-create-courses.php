<?php

class LDTT_Create_Courses {

    public static function handle( $args = array(), $assoc_args = array() ) {
        // Check for admin interface data
        if ( empty( $args ) && empty( $assoc_args ) ) {
            $assoc_args = array(
                'prefix'      => isset( $_POST['prefix'] ) ? sanitize_text_field( $_POST['prefix'] ) : 'Test Course',
                'count'       => isset( $_POST['count'] ) ? intval( $_POST['count'] ) : 5,
                'access_mode' => isset( $_POST['access_mode'] ) ? sanitize_text_field( $_POST['access_mode'] ) : null,
            );
        }

        $course_prefix = $assoc_args['prefix'] ?? 'Test Course';
        $total_courses = LDTT_Helper::validate_positive_int( $assoc_args['count'] ?? 5, 5, 100 );
        $specified_access_mode = $assoc_args['access_mode'] ?? null;

        // Get an admin user to assign as the author
        $admin_user_id = LDTT_Helper::get_admin_user_id();

        if ( ! $admin_user_id ) {
            $message = 'No admin users found to assign as author.';
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::error( $message );
            }
            return array( 'status' => 'error', 'message' => $message );
        }

        $course_ids = self::create_courses( $course_prefix, $total_courses, $specified_access_mode, $admin_user_id );

        if ( is_wp_error( $course_ids ) ) {
            $message = $course_ids->get_error_message();
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::error( $message );
            }
            return array( 'status' => 'error', 'message' => $message );
        }

        $message = "{$total_courses} courses created successfully.";
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::success( $message );
            WP_CLI::line( 'Course IDs: ' . implode( ', ', $course_ids ) );
        }
        return array( 'status' => 'success', 'message' => $message, 'course_ids' => $course_ids );
    }

    private static function create_courses( $course_prefix, $course_count, $specified_access_mode, $admin_user_id ) {
        $course_ids = array();

        $access_modes = array(
            'open' => 'Open',
            'free' => 'Free',
            'paynow' => 'Buy now',
            'subscribe' => 'Recurring',
            'closed' => 'Closed'
        );

        $movie_titles = LDTT_Helper::get_random_course_titles( $course_count );
        $current_mode_index = 0;

        for ( $i = 1; $i <= $course_count; $i++ ) {
            $course_title = "{$course_prefix} {$i}: " . ( $movie_titles[ $i - 1 ] ?? 'Course' );
            $course_content = "This is the content for {$course_title}.";

            $access_mode = $specified_access_mode ? $specified_access_mode : array_keys( $access_modes )[ $current_mode_index ];

            // Insert the course post
            $course_id = wp_insert_post( array(
                'post_title'   => $course_title,
                'post_type'    => learndash_get_post_type_slug( 'course' ),
                'post_status'  => 'publish',
                'post_content' => $course_content,
                'post_author'  => $admin_user_id,
                'meta_input'   => array(
                    '_ldtt_test_data' => true,
                ),
            ) );

            if ( is_wp_error( $course_id ) ) {
                return $course_id;
            }

            // Update course settings using LearnDash functions
            learndash_update_setting( $course_id, 'course_price_type', $access_mode );

            if ( in_array( $access_mode, array( 'paynow', 'subscribe' ), true ) ) {
                learndash_update_setting( $course_id, 'course_price', '99.00' );
            }

            if ( 'subscribe' === $access_mode ) {
                learndash_update_setting( $course_id, 'course_price_billing_p3', '1' );
                learndash_update_setting( $course_id, 'course_price_billing_t3', 'M' );
            }

            $course_ids[] = $course_id;

            // Cycle through access modes if no specific mode is provided
            if ( ! $specified_access_mode ) {
                $current_mode_index = ( $current_mode_index + 1 ) % count( $access_modes );
            }

            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::line( "Created course: {$course_title} (ID: {$course_id})" );
            }
        }

        return $course_ids;
    }
}