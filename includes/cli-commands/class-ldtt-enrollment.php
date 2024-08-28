<?php

class LDTT_Enrollment {

    /**
     * Handle the WP-CLI command to enroll users into a LearnDash course.
     *
     * @param array $args Positional arguments passed from the WP-CLI command.
     * @param array $assoc_args Associative arguments passed from the WP-CLI command.
     */
    public static function handle( $args, $assoc_args ) {
        $course_name = isset( $assoc_args['course'] ) ? $assoc_args['course'] : 'Sample Course';
        $user_count = isset( $assoc_args['users'] ) ? intval( $assoc_args['users'] ) : 5;

        // Get or create the course
        $course_id = self::get_or_create_course( $course_name );
        if ( is_wp_error( $course_id ) ) {
            LDTT_Helper::cli_error( $course_id->get_error_message() );
            return;
        }

        // Enroll users in the course
        $user_ids = self::create_and_enroll_users( $course_id, $user_count );
        if ( is_wp_error( $user_ids ) ) {
            LDTT_Helper::cli_error( $user_ids->get_error_message() );
        } else {
            LDTT_Helper::cli_success( "{$user_count} users successfully enrolled in the course '{$course_name}'." );
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
     * Create users and enroll them in a course.
     *
     * @param int $course_id The ID of the course.
     * @param int $user_count The number of users to create and enroll.
     * @return array|WP_Error An array of user IDs on success, WP_Error on failure.
     */
    private static function create_and_enroll_users( $course_id, $user_count ) {
        $user_ids = array();

        for ( $i = 1; $i <= $user_count; $i++ ) {
            $username = 'user_' . strtolower( LDTT_Helper::generate_random_string( 5 ) );
            $email = $username . '@example.com';

            $user_id = wp_create_user( $username, wp_generate_password(), $email );

            if ( is_wp_error( $user_id ) ) {
                return $user_id;
            }

            // Assign the 'subscriber' role to the user (or another role as needed)
            $user = new WP_User( $user_id );
            $user->set_role( 'subscriber' );

            // Enroll the user in the course
            $result = ld_update_course_access( $user_id, $course_id, true );
            if ( ! $result ) {
                return new WP_Error( 'enrollment_failed', __( "Failed to enroll user '{$username}' in the course.", 'learndash-testing-toolkit' ) );
            }

            $user_ids[] = $user_id;
        }

        return $user_ids;
    }
}
