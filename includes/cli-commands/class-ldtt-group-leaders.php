<?php

class LDTT_Group_Leaders {

    /**
     * Handle the WP-CLI command to create a Group Leader and assign them to a group.
     *
     * @param array $args Positional arguments passed from the WP-CLI command.
     * @param array $assoc_args Associative arguments passed from the WP-CLI command.
     */
    public static function handle( $args, $assoc_args ) {
        $group_name = isset( $assoc_args['group'] ) ? $assoc_args['group'] : 'Sample Group';
        $leader_name = isset( $assoc_args['leader'] ) ? $assoc_args['leader'] : 'Sample Leader';

        // Create or find the group
        $group_id = self::get_or_create_group( $group_name );
        if ( is_wp_error( $group_id ) ) {
            LDTT_Helper::cli_error( $group_id->get_error_message() );
            return;
        }

        // Create the group leader user
        $leader_id = self::create_group_leader( $leader_name );
        if ( is_wp_error( $leader_id ) ) {
            LDTT_Helper::cli_error( $leader_id->get_error_message() );
            return;
        }

        // Assign the leader to the group
        $result = self::assign_group_leader( $leader_id, $group_id );
        if ( is_wp_error( $result ) ) {
            LDTT_Helper::cli_error( $result->get_error_message() );
        } else {
            LDTT_Helper::cli_success( "Group Leader '{$leader_name}' assigned to Group '{$group_name}' successfully." );
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
     * Create a group leader user.
     *
     * @param string $leader_name The name of the group leader.
     * @return int|WP_Error The user ID on success, WP_Error on failure.
     */
    private static function create_group_leader( $leader_name ) {
        $leader_username = sanitize_user( strtolower( str_replace( ' ', '_', $leader_name ) ) );
        $leader_email = $leader_username . '@example.com';

        $user_id = wp_create_user( $leader_username, wp_generate_password(), $leader_email );

        if ( is_wp_error( $user_id ) ) {
            return $user_id;
        }

        // Assign the 'group_leader' role to the user
        $user = new WP_User( $user_id );
        $user->set_role( 'group_leader' );

        return $user_id;
    }

    /**
     * Assign a group leader to a group.
     *
     * @param int $leader_id The ID of the group leader.
     * @param int $group_id The ID of the group.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    private static function assign_group_leader( $leader_id, $group_id ) {
        $result = learndash_add_user_to_group( $leader_id, $group_id );
        if ( ! $result ) {
            return new WP_Error( 'assignment_failed', __( 'Failed to assign the group leader to the group.', 'learndash-testing-toolkit' ) );
        }

        return true;
    }
}
