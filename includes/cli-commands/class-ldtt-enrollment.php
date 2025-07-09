<?php

/**
 * Enrollment Command - Fixed with Standard LearnDash Functions Only
 * Creates users and enrolls them in courses, or enrolls existing users
 */
class LDTT_Enrollment {

    /**
     * Handle the enrollment command with support for existing users
     *
     * @param array $args
     * @param array $assoc_args
     * @return array
     */
    public static function handle( $args = array(), $assoc_args = array() ) {
        // Check for admin input if no CLI arguments are provided
        if ( empty( $args ) && empty( $assoc_args ) ) {
            $assoc_args = array(
                'course_id'    => isset( $_POST['course_id'] ) ? intval( $_POST['course_id'] ) : null,
                'count'        => isset( $_POST['count'] ) ? intval( $_POST['count'] ) : 10,
                'use_existing' => isset( $_POST['use_existing_users'] ) && $_POST['use_existing_users'] ? true : false,
            );
        }

        $course_id = ! empty( $assoc_args['course_id'] ) ? absint( $assoc_args['course_id'] ) : null;
        $user_count = LDTT_Helper::validate_positive_int( $assoc_args['count'] ?? 10, 1, 300 );
        $use_existing = isset( $assoc_args['use_existing'] ) && $assoc_args['use_existing'];

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

        // Choose method based on use_existing flag
        if ( $use_existing ) {
            $user_ids = self::enroll_existing_users( $course_id, $user_count );
        } else {
            $user_ids = self::create_and_enroll_users( $course_id, $user_count );
        }

        if ( is_wp_error( $user_ids ) ) {
            $message = $user_ids->get_error_message();
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::error( $message );
            }
            return array( 'status' => 'error', 'message' => $message );
        }

        $action_text = $use_existing ? 'enrolled existing' : 'created and enrolled';
        $message = count( $user_ids ) . " users successfully {$action_text} in course '{$course_title}'.";
        
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::success( $message );
            WP_CLI::line( 'User IDs: ' . implode( ', ', $user_ids ) );
        }
        
        return array(
            'status'  => 'success',
            'message' => $message,
            'user_ids' => $user_ids,
            'method' => $use_existing ? 'existing_users' : 'new_users',
        );
    }

    /**
     * Enroll existing users in a course using standard LearnDash functions
     *
     * @param int $course_id
     * @param int $user_count
     * @return array|WP_Error
     */
    private static function enroll_existing_users( $course_id, $user_count ) {
        // Get existing users (excluding administrators)
        $existing_users = get_users( array(
            'role__not_in' => array( 'administrator' ),
            'fields' => 'ID',
            'number' => $user_count * 2, // Get more than needed to filter
        ) );

        if ( empty( $existing_users ) ) {
            return new WP_Error( 'no_existing_users', 'No existing users found to enroll.' );
        }

        // Convert to integers and shuffle for random selection
        $user_ids = array_map( 'absint', $existing_users );
        shuffle( $user_ids );

        // Limit to requested count
        $users_to_enroll = array_slice( $user_ids, 0, $user_count );
        $enrolled_users = array();

        foreach ( $users_to_enroll as $user_id ) {
            // Check if user is already enrolled using standard LearnDash function
            if ( self::is_user_enrolled( $user_id, $course_id ) ) {
                if ( defined( 'WP_CLI' ) && WP_CLI ) {
                    WP_CLI::line( "User {$user_id} already enrolled, skipping." );
                }
                continue;
            }

            // Enroll the user in the course using standard LearnDash function
            $result = ld_update_course_access( $user_id, $course_id, false );
            
            if ( $result !== false ) {
                // Mark user as managed by LDTT for this operation
                update_user_meta( $user_id, '_ldtt_enrolled_course_' . $course_id, current_time( 'timestamp' ) );
                
                $enrolled_users[] = $user_id;

                if ( defined( 'WP_CLI' ) && WP_CLI ) {
                    $user = get_user_by( 'ID', $user_id );
                    WP_CLI::line( "Enrolled existing user: {$user->user_login} (ID: {$user_id})" );
                }
            } else {
                if ( defined( 'WP_CLI' ) && WP_CLI ) {
                    WP_CLI::warning( "Failed to enroll user ID {$user_id} in the course." );
                }
            }
        }

        if ( empty( $enrolled_users ) ) {
            return new WP_Error( 'enrollment_failed', 'No users could be enrolled. They may already be enrolled.' );
        }

        return $enrolled_users;
    }

    /**
     * Create new users and enroll them in a course
     *
     * @param int $course_id
     * @param int $user_count
     * @return array|WP_Error
     */
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
            update_user_meta( $user_id, '_ldtt_user_type', 'course_enrolled' );

            // Enroll the user in the course using standard LearnDash function
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

    /**
     * Check if a user is already enrolled in a course using standard LearnDash function
     *
     * @param int $user_id
     * @param int $course_id
     * @return bool
     */
    private static function is_user_enrolled( $user_id, $course_id ) {
        // Use standard LearnDash function
        $enrolled_courses = learndash_user_get_enrolled_courses( $user_id );
        return in_array( $course_id, $enrolled_courses );
    }
}