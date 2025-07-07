<?php

class LDTT_Group_Leaders {

    /**
     * Handle the command to create a Group Leader and assign them to a group.
     *
     * @param array $args Positional arguments.
     * @param array $assoc_args Associative arguments.
     */
    public static function handle( $args = array(), $assoc_args = array() ) {
        // Check for admin input if no CLI arguments are provided
        if ( empty( $args ) && empty( $assoc_args ) ) {
            $assoc_args = array(
                'group'  => isset( $_POST['group'] ) ? sanitize_text_field( $_POST['group'] ) : 'Test Group',
                'leader' => isset( $_POST['leader'] ) ? sanitize_text_field( $_POST['leader'] ) : 'Test Leader',
                'count'  => isset( $_POST['count'] ) ? intval( $_POST['count'] ) : 1,
            );
        }

        $group_name = sanitize_text_field( $assoc_args['group'] ?? 'Test Group' );
        $leader_name = sanitize_text_field( $assoc_args['leader'] ?? 'Test Leader' );
        $leader_count = LDTT_Helper::validate_positive_int( $assoc_args['count'] ?? 1, 1, 10 );

        // Create or find the group
        $group_id = self::get_or_create_group( $group_name );
        if ( is_wp_error( $group_id ) ) {
            $message = $group_id->get_error_message();
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::error( $message );
            }
            return array( 'status' => 'error', 'message' => $message );
        }

        $created_leaders = array();

        for ( $i = 1; $i <= $leader_count; $i++ ) {
            $current_leader_name = $leader_count > 1 ? "{$leader_name} {$i}" : $leader_name;
            
            // Create the group leader user
            $leader_id = self::create_group_leader( $current_leader_name );
            if ( is_wp_error( $leader_id ) ) {
                if ( defined( 'WP_CLI' ) && WP_CLI ) {
                    WP_CLI::warning( "Failed to create leader: {$current_leader_name}" );
                }
                continue;
            }

            // Assign the leader to the group
            $result = self::assign_group_leader( $leader_id, $group_id );
            if ( is_wp_error( $result ) ) {
                if ( defined( 'WP_CLI' ) && WP_CLI ) {
                    WP_CLI::warning( "Failed to assign leader to group: {$current_leader_name}" );
                }
                continue;
            }

            $created_leaders[] = $leader_id;

            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::line( "Created and assigned group leader: {$current_leader_name} (ID: {$leader_id})" );
            }
        }

        $message = count( $created_leaders ) . " Group Leader(s) assigned to Group '{$group_name}' successfully.";
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::success( $message );
        }
        return array(
            'status'     => 'success',
            'message'    => $message,
            'group_id'   => $group_id,
            'leader_ids' => $created_leaders,
        );
    }

    /**
     * Get an existing group by name or create a new one.
     *
     * @param string $group_name The name of the group.
     * @return int|WP_Error The group ID on success, WP_Error on failure.
     */
    private static function get_or_create_group( $group_name ) {
        $group = get_page_by_title( $group_name, OBJECT, learndash_get_post_type_slug( 'group' ) );
        if ( $group ) {
            return $group->ID;
        }

        $admin_id = LDTT_Helper::get_admin_user_id();

        $group_id = wp_insert_post( array(
            'post_title'   => $group_name,
            'post_type'    => learndash_get_post_type_slug( 'group' ),
            'post_status'  => 'publish',
            'post_content' => 'This is a test group created by the LearnDash Testing Toolkit.',
            'post_author'  => $admin_id,
            'meta_input'   => array(
                '_ldtt_test_data' => true,
            ),
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
        $leader_username = 'leader_' . LDTT_Helper::generate_random_string( 8 );
        $leader_email = $leader_username . '@example.com';

        // Check if a user with the same email already exists
        if ( email_exists( $leader_email ) ) {
            // Generate a new random email
            $leader_email = 'leader_' . LDTT_Helper::generate_random_string( 12 ) . '@example.com';
        }

        $user_id = wp_create_user( $leader_username, wp_generate_password( 12 ), $leader_email );

        if ( is_wp_error( $user_id ) ) {
            return $user_id;
        }

        // Set display name
        wp_update_user( array(
            'ID'           => $user_id,
            'display_name' => $leader_name,
            'first_name'   => $leader_name,
        ) );

        // Assign the 'group_leader' role to the user
        $user = new WP_User( $user_id );
        $user->set_role( 'group_leader' );

        // Mark as test user
        update_user_meta( $user_id, '_ldtt_test_user', true );

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
        // Use LearnDash function if available
        if ( function_exists( 'learndash_set_groups_administrators' ) ) {
            $current_leaders = learndash_get_groups_administrators( $group_id );
            if ( ! is_array( $current_leaders ) ) {
                $current_leaders = array();
            }
            $current_leaders[] = $leader_id;
            $result = learndash_set_groups_administrators( $group_id, $current_leaders );
        } else {
            // Fallback method using meta
            $current_leaders = get_post_meta( $group_id, 'learndash_group_leaders_' . $group_id, true );
            if ( ! is_array( $current_leaders ) ) {
                $current_leaders = array();
            }
            $current_leaders[] = $leader_id;
            $result = update_post_meta( $group_id, 'learndash_group_leaders_' . $group_id, $current_leaders );
        }

        if ( ! $result ) {
            return new WP_Error( 'assignment_failed', 'Failed to assign the group leader to the group.' );
        }

        return true;
    }
}