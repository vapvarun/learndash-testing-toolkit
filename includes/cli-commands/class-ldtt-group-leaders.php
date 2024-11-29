<?php

class LDTT_Group_Leaders {

    /**
     * Handle the command to create a Group Leader and assign them to a group.
     *
     * @param array $args Positional arguments.
     * @param array $assoc_args Associative arguments.
     * @return array Structured result for success or error.
     */
    public static function handle( $args = array(), $assoc_args = array() ) {
        // Check for admin input if no CLI arguments are provided
        if ( empty( $args ) && empty( $assoc_args ) ) {
            $assoc_args = array(
                'group'  => isset( $_POST['group'] ) ? sanitize_text_field( $_POST['group'] ) : 'Sample Group',
                'leader' => isset( $_POST['leader'] ) ? sanitize_text_field( $_POST['leader'] ) : 'Sample Leader',
            );
        }

        $group_name = $assoc_args['group'];
        $leader_name = $assoc_args['leader'];

        // Create or find the group
        $group_id = self::get_or_create_group( $group_name );
        if ( is_wp_error( $group_id ) ) {
            return array( 'status' => 'error', 'message' => $group_id->get_error_message() );
        }

        // Create the group leader user
        $leader_id = self::create_group_leader( $leader_name );
        if ( is_wp_error( $leader_id ) ) {
            return array( 'status' => 'error', 'message' => $leader_id->get_error_message() );
        }

        // Assign the leader to the group
        $result = self::assign_group_leader( $leader_id, $group_id );
        if ( is_wp_error( $result ) ) {
            return array( 'status' => 'error', 'message' => $result->get_error_message() );
        }

        return array(
            'status'  => 'success',
            'message' => "Group Leader '{$leader_name}' assigned to Group '{$group_name}' successfully.",
            'group_id' => $group_id,
            'leader_id' => $leader_id,
        );
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

        // Check if a user with the same email already exists
        if ( email_exists( $leader_email ) ) {
            return new WP_Error( 'user_exists', __( "A user with the email '{$leader_email}' already exists.", 'learndash-testing-toolkit' ) );
        }

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
