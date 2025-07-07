<?php

class LDTT_Enrollment {

    public static function handle( $args = array(), $assoc_args = array() ) {
        // Check for admin input if no CLI arguments are provided
        if ( empty( $args ) && empty( $assoc_args ) ) {
            $assoc_args = array(
                'course_id' => isset( $_POST['course_id'] ) ? intval( $_POST['course_id'] ) : null,
                'count'     => isset( $_POST['count'] ) ? intval( $_POST['count'] ) : 10,
            );
        }

        $course_id = ! empty( $assoc_args['course_id'] ) ? absint( $assoc_args['course_id'] ) : null;
        $user_count = LDTT_Helper::validate_positive_int( $assoc_args['count'] ?? 10, 10, 100 );

        if ( ! $course_id ) {
            $message = 'Course ID is required.';
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::error( $message );
            }
            return array( 'status' => 'error', 'message' => $message );
        }

        // Validate course ID
        if ( get_post_type( $course_id ) !== learndash_get_post_type_slug( 'course' ) ) {
            $message = "Course ID {$course_id} is not a valid course.";
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::error( $message );
            }
            return array( 'status' => 'error', 'message' => $message );
        }

        $course_title = get_the_title( $course_id );

        // Create and enroll users
        $user_ids = self::create_and_enroll_users( $course_id, $user_count );
        if ( is_wp_error( $user_ids ) ) {
            $message = $user_ids->get_error_message();
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::error( $message );
            }
            return array( 'status' => 'error', 'message' => $message );
        }

        $message = "{$user_count} users successfully created and enrolled in course '{$course_title}'.";
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::success( $message );
            WP_CLI::line( 'User IDs: ' . implode( ', ', $user_ids ) );
        }
        return array(
            'status'  => 'success',
            'message' => $message,
            'user_ids' => $user_ids,
        );
    }

    private static function create_and_enroll_users( $course_id, $user_count ) {
        $user_ids = array();

        for ( $i = 1; $i <= $user_count; $i++ ) {
            $username = 'testuser_' . LDTT_Helper::generate_random_string( 8 );
            $email = $username . '@example.com';

            $user_id = wp_create_user( $username, wp_generate_password( 12 ), $email );

            if ( is_wp_error( $user_id ) ) {
                if ( defined( 'WP_CLI' ) && WP_CLI ) {
                    WP_CLI::warning( "Failed to create user: {$username}" );
                }
                continue;
            }

            // Assign the 'subscriber' role to the user
            $user = new WP_User( $user_id );
            $user->set_role( 'subscriber' );

            // Mark as test user
            update_user_meta( $user_id, '_ldtt_test_user', true );

            // Enroll the user in the course using LearnDash function
            $result = ld_update_course_access( $user_id, $course_id, false );
            if ( ! $result ) {
                if ( defined( 'WP_CLI' ) && WP_CLI ) {
                    WP_CLI::warning( "Failed to enroll user '{$username}' in the course." );
                }
                // Don't skip the user, just log the warning
            }

            $user_ids[] = $user_id;

            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::line( "Created and enrolled user: {$username} (ID: {$user_id})" );
            }
        }

        return $user_ids;
    }
}