<?php

class LDTT_Group_Enrollment {

    /**
     * Handle the WP-CLI command to enroll users into a LearnDash group.
     *
     * @param array $args Positional arguments passed from the WP-CLI command.
     * @param array $assoc_args Associative arguments passed from the WP-CLI command.
     */
    public static function handle( $args = array(), $assoc_args = array() ) {
        // Check for admin input if no CLI arguments are provided
        if ( empty( $args ) && empty( $assoc_args ) ) {
            $assoc_args = array(
                'group' => isset( $_POST['group'] ) ? sanitize_text_field( $_POST['group'] ) : 'Test Group',
                'users' => isset( $_POST['users'] ) ? intval( $_POST['users'] ) : 10,
            );
        }

        $group_name = sanitize_text_field( $assoc_args['group'] ?? 'Test Group' );
        $user_count = LDTT_Helper::validate_positive_int( $assoc_args['users'] ?? 10, 10, 100 );

        // Get or create the group
        $group_id = self::get_or_create_group( $group_name );
        if ( is_wp_error( $group_id ) ) {
            $message = $group_id->get_error_message();
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::error( $message );
            }
            return array( 'status' => 'error', 'message' => $message );
        }

        // Enroll users in the group
        $user_ids = self::create_and_enroll_users( $group_id, $user_count );
        if ( is_wp_error( $user_ids ) ) {
            $message = $user_ids->get_error_message();
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::error( $message );
            }
            return array( 'status' => 'error', 'message' => $message );
        }

        $message = "{$user_count} users successfully enrolled in the group '{$group_name}'.";
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
     * Create users and enroll them in a group.
     *
     * @param int $group_id The ID of the group.
     * @param int $user_count The number of users to create and enroll.
     * @return array|WP_Error An array of user IDs on success, WP_Error on failure.
     */
    private static function create_and_enroll_users( $group_id, $user_count ) {
        $user_ids = array();

        for ( $i = 1; $i <= $user_count; $i++ ) {
            $username = 'groupuser_' . LDTT_Helper::generate_random_string( 8 );
            $email = $username . '@example.com';

            $user_id = wp_create_user( $username, wp_generate_password( 12 ), $email );

            if ( is_wp_error( $user_id ) ) {
                if ( defined( 'WP_CLI' ) && WP_CLI ) {
                    WP_CLI::warning( "Failed to create user: {$username}" );
                }
                continue;
            }

            // Assign the 'subscriber' role to the user (or another role as needed)
            $user = new WP_User( $user_id );
            $user->set_role( 'subscriber' );

            // Mark as test user
            update_user_meta( $user_id, '_ldtt_test_user', true );

            // Enroll the user in the group
            $result = self::enroll_user_in_group( $user_id, $group_id );
            if ( is_wp_error( $result ) ) {
                if ( defined( 'WP_CLI' ) && WP_CLI ) {
                    WP_CLI::warning( "Failed to enroll user '{$username}' in the group." );
                }
                continue;
            }

            $user_ids[] = $user_id;

            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::line( "Created and enrolled user: {$username} (ID: {$user_id})" );
            }
        }

        return $user_ids;
    }

    /**
     * Enroll a user in a group using LearnDash functions.
     *
     * @param int $user_id The user ID.
     * @param int $group_id The group ID.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    private static function enroll_user_in_group( $user_id, $group_id ) {
        // Use LearnDash function if available
        if ( function_exists( 'ld_update_group_access' ) ) {
            $result = ld_update_group_access( $user_id, $group_id, false );
        } elseif ( function_exists( 'learndash_set_users_group_ids' ) ) {
            // Alternative LearnDash function
            $current_groups = learndash_get_users_group_ids( $user_id );
            if ( ! is_array( $current_groups ) ) {
                $current_groups = array();
            }
            $current_groups[] = $group_id;
            $result = learndash_set_users_group_ids( $user_id, $current_groups );
        } else {
            // Fallback method using meta
            $current_groups = get_user_meta( $user_id, 'learndash_group_users_' . $group_id, true );
            if ( ! is_array( $current_groups ) ) {
                $current_groups = array();
            }
            $current_groups[] = $group_id;
            $result = update_user_meta( $user_id, 'learndash_group_users_' . $group_id, $current_groups );
            
            // Also update group meta
            $group_users = get_post_meta( $group_id, 'learndash_group_users_' . $group_id, true );
            if ( ! is_array( $group_users ) ) {
                $group_users = array();
            }
            $group_users[] = $user_id;
            update_post_meta( $group_id, 'learndash_group_users_' . $group_id, $group_users );
        }

        if ( ! $result ) {
            return new WP_Error( 'enrollment_failed', "Failed to enroll user in the group." );
        }

        return true;
    }
}