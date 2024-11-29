<?php

class LDTT_Enrollment {

    /**
     * Handle the command to enroll users into a LearnDash course.
     *
     * @param array $args Positional arguments.
     * @param array $assoc_args Associative arguments.
     * @return array Structured result for success or error.
     */
    public static function handle( $args = array(), $assoc_args = array() ) {
        // Check for admin input if no CLI arguments are provided
        if ( empty( $args ) && empty( $assoc_args ) ) {
            $assoc_args = array(
                'course' => isset( $_POST['course'] ) ? sanitize_text_field( $_POST['course'] ) : 'Sample Course',
                'users'  => isset( $_POST['users'] ) ? intval( $_POST['users'] ) : 5,
            );
        }

        $course_name = $assoc_args['course'];
        $user_count = $assoc_args['users'];

        // Get or create the course
        $course_id = self::get_or_create_course( $course_name );
        if ( is_wp_error( $course_id ) ) {
            return array( 'status' => 'error', 'message' => $course_id->get_error_message() );
        }

        // Enroll users in the course
        $user_ids = self::create_and_enroll_users( $course_id, $user_count );
        if ( is_wp_error( $user_ids ) ) {
            return array( 'status' => 'error', 'message' => $user_ids->get_error_message() );
        }

        return array(
            'status'  => 'success',
            'message' => "{$user_count} users successfully enrolled in the course '{$course_name}'.",
            'user_ids' => $user_ids,
        );
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

            // Assign the 'subscriber' role to the user
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
