<?php

class LDTT_Group_Enrollment {

    /**
     * Handle the WP-CLI command to enroll users into a LearnDash group.
     *
     * @param array $args Positional arguments passed from the WP-CLI command.
     * @param array $assoc_args Associative arguments passed from the WP-CLI command.
     */
    public static function handle( $args, $assoc_args ) {
        $group_name = isset( $assoc_args['group'] ) ? $assoc_args['group'] : 'Sample Group';
        $user_count = isset( $assoc_args['users'] ) ? intval( $assoc_args['users'] ) : 5;

        // Get or create the group
        $group_id = self::get_or_create_group( $group_name );
        if ( is_wp_error( $group_id ) ) {
            LDTT_Helper::cli_error( $group_id->get_error_message() );
            return;
        }

        // Enroll users in the group
        $user_ids = self::create_and_enroll_users( $group_id, $user_count );
        if ( is_wp_error( $user_ids ) ) {
            LDTT_Helper::cli_error( $user_ids->get_error_message() );
        } else {
            LDTT_Helper::cli_success( "{$user_count} users successfully enrolled in the group '{$group_name}'." );
        }
    }

    /**
     * Get an existing group by name or create a new one.
     *
     * @param string $group_name The name of the group.
     * @return int|WP_Error The group ID on success, WP_Error on failure.
     */
    private static function get_or_create_group( $group_name ) {
        $group = get_page_by_title( $group_name, OBJECT, 'groups' );
        if ( $group ) {
            return $group->ID;
        }

        $group_id = wp_insert_post( array(
            'post_title'   => $group_name,
            'post_type'    => 'groups',
            'post_status'  => 'publish',
            'post_content' => 'This is a sample group created by the LearnDash Testing Toolkit.',
        ) );

        if ( is_wp_error( $group_id ) ) {
            return $group_id;
        }

        return $group_id;
    }

    /**
     * Create users and enroll them in a group.
     *
     * @param int $group_id The ID of the group.
     * @param int $user_count The number of users to create and enroll.
     * @return array|WP_Error An array of user IDs on success, WP_Error on failure.
     */
    private static function create_and_enroll_users( $group_id, $user_count ) {
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

            // Enroll the user in the group
            $result = learndash_add_user_to_group( $user_id, $group_id );
            if ( ! $result ) {
                return new WP_Error( 'enrollment_failed', __( "Failed to enroll user '{$username}' in the group.", 'learndash-testing-toolkit' ) );
            }

            $user_ids[] = $user_id;
        }

        return $user_ids;
    }
}
