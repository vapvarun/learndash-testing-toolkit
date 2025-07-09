<?php

/**
 * Group Enrollment Command - Fixed with Standard LearnDash Functions Only
 * Creates users and enrolls them in LearnDash groups using official functions
 */
class LDTT_Group_Enrollment {

    /**
     * Handle the command to enroll users into a LearnDash group
     *
     * @param array $args Positional arguments passed from the WP-CLI command.
     * @param array $assoc_args Associative arguments passed from the WP-CLI command.
     */
    public static function handle( $args = array(), $assoc_args = array() ) {
        // Check for admin input if no CLI arguments are provided
        if ( empty( $args ) && empty( $assoc_args ) ) {
            $assoc_args = array(
                'group_name'    => isset( $_POST['group_name'] ) ? sanitize_text_field( $_POST['group_name'] ) : 'Test Group',
                'users'         => isset( $_POST['users'] ) ? intval( $_POST['users'] ) : 10,
                'create_group'  => isset( $_POST['create_group'] ) ? true : false,
                'use_existing'  => isset( $_POST['use_existing'] ) ? true : false,
                'user_prefix'   => isset( $_POST['user_prefix'] ) ? sanitize_text_field( $_POST['user_prefix'] ) : 'groupuser',
            );
        }

        $group_name = sanitize_text_field( $assoc_args['group_name'] ?? $assoc_args['group'] ?? 'Test Group' );
        $user_count = LDTT_Helper::validate_positive_int( $assoc_args['users'] ?? 10, 1, 100 );
        $create_group = isset( $assoc_args['create_group'] ) && $assoc_args['create_group'];
        $use_existing = isset( $assoc_args['use_existing'] ) && $assoc_args['use_existing'];
        $user_prefix = sanitize_text_field( $assoc_args['user_prefix'] ?? 'groupuser' );

        // Get or create the group
        $group_id = self::get_or_create_group( $group_name, $create_group );
        if ( is_wp_error( $group_id ) ) {
            $message = $group_id->get_error_message();
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::error( $message );
            }
            return array( 'status' => 'error', 'message' => $message );
        }

        // Enroll users in the group
        if ( $use_existing ) {
            $user_ids = self::enroll_existing_users( $group_id, $user_count );
        } else {
            $user_ids = self::create_and_enroll_users( $group_id, $user_count, $user_prefix );
        }

        if ( is_wp_error( $user_ids ) ) {
            $message = $user_ids->get_error_message();
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::error( $message );
            }
            return array( 'status' => 'error', 'message' => $message );
        }

        $action_text = $use_existing ? 'enrolled existing' : 'created and enrolled';
        $message = count( $user_ids ) . " users successfully {$action_text} in group '{$group_name}'.";
        
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::success( $message );
            WP_CLI::line( 'Group ID: ' . $group_id );
            WP_CLI::line( 'User IDs: ' . implode( ', ', $user_ids ) );
        }
        
        return array(
            'status'   => 'success',
            'message'  => $message,
            'group_id' => $group_id,
            'user_ids' => $user_ids,
            'method'   => $use_existing ? 'existing_users' : 'new_users',
        );
    }

    /**
     * Get an existing group by name or create a new one
     *
     * @param string $group_name The name of the group.
     * @param bool $create_if_missing Whether to create the group if not found.
     * @return int|WP_Error The group ID on success, WP_Error on failure.
     */
    private static function get_or_create_group( $group_name, $create_if_missing = true ) {
        // Try to find existing group using proper query
        $existing_groups = get_posts( array(
            'post_type'   => learndash_get_post_type_slug( 'group' ),
            'title'       => $group_name,
            'post_status' => 'publish',
            'numberposts' => 1,
            'fields'      => 'ids',
        ) );

        if ( ! empty( $existing_groups ) ) {
            return $existing_groups[0];
        }

        if ( ! $create_if_missing ) {
            return new WP_Error( 'group_not_found', "Group '{$group_name}' not found and creation is disabled." );
        }

        // Create new group
        $admin_id = LDTT_Helper::get_admin_user_id();
        if ( ! $admin_id ) {
            return new WP_Error( 'no_admin', 'No admin user found to assign as group author.' );
        }

        $group_id = wp_insert_post( array(
            'post_title'   => $group_name,
            'post_type'    => learndash_get_post_type_slug( 'group' ),
            'post_status'  => 'publish',
            'post_content' => "Test group created by LearnDash Testing Toolkit for user enrollment.",
            'post_author'  => $admin_id,
            'meta_input'   => array(
                '_ldtt_test_data' => true,
                '_ldtt_created_time' => current_time( 'timestamp' ),
            ),
        ) );

        if ( is_wp_error( $group_id ) ) {
            return $group_id;
        }

        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::line( "Created new group: {$group_name} (ID: {$group_id})" );
        }

        return $group_id;
    }

    /**
     * Enroll existing users in a group
     *
     * @param int $group_id The ID of the group.
     * @param int $user_count The number of users to enroll.
     * @return array|WP_Error An array of user IDs on success, WP_Error on failure.
     */
    private static function enroll_existing_users( $group_id, $user_count ) {
        // Get existing users (excluding administrators and group leaders)
        $existing_users = get_users( array(
            'role__not_in' => array( 'administrator', 'group_leader' ),
            'fields' => 'ID',
            'number' => $user_count * 2, // Get extra for filtering
        ) );

        if ( empty( $existing_users ) ) {
            return new WP_Error( 'no_existing_users', 'No existing users found to enroll in group.' );
        }

        // Convert to integers and shuffle for random selection
        $user_ids = array_map( 'absint', $existing_users );
        shuffle( $user_ids );

        // Limit to requested count
        $users_to_enroll = array_slice( $user_ids, 0, $user_count );
        $enrolled_users = array();

        foreach ( $users_to_enroll as $user_id ) {
            // Check if user is already in this group
            if ( self::is_user_in_group( $user_id, $group_id ) ) {
                if ( defined( 'WP_CLI' ) && WP_CLI ) {
                    WP_CLI::line( "User {$user_id} already in group, skipping." );
                }
                continue;
            }

            // Enroll the user in the group
            $result = self::enroll_user_in_group( $user_id, $group_id );
            if ( ! is_wp_error( $result ) ) {
                $enrolled_users[] = $user_id;
                
                // Mark enrollment for tracking
                update_user_meta( $user_id, '_ldtt_enrolled_group_' . $group_id, current_time( 'timestamp' ) );

                if ( defined( 'WP_CLI' ) && WP_CLI ) {
                    $user = get_user_by( 'ID', $user_id );
                    WP_CLI::line( "Enrolled existing user: {$user->user_login} (ID: {$user_id})" );
                }
            } else {
                if ( defined( 'WP_CLI' ) && WP_CLI ) {
                    WP_CLI::warning( "Failed to enroll user ID {$user_id}: " . $result->get_error_message() );
                }
            }
        }

        if ( empty( $enrolled_users ) ) {
            return new WP_Error( 'enrollment_failed', 'No users could be enrolled in the group.' );
        }

        return $enrolled_users;
    }

    /**
     * Create users and enroll them in a group
     *
     * @param int $group_id The ID of the group.
     * @param int $user_count The number of users to create and enroll.
     * @param string $user_prefix The prefix for usernames.
     * @return array|WP_Error An array of user IDs on success, WP_Error on failure.
     */
    private static function create_and_enroll_users( $group_id, $user_count, $user_prefix = 'groupuser' ) {
        $user_ids = array();

        for ( $i = 1; $i <= $user_count; $i++ ) {
            $username = $user_prefix . '_' . LDTT_Helper::generate_random_string( 8 );
            $email = $username . '@example.com';

            $user_id = wp_create_user( $username, wp_generate_password( 12 ), $email );

            if ( is_wp_error( $user_id ) ) {
                if ( defined( 'WP_CLI' ) && WP_CLI ) {
                    WP_CLI::warning( "Failed to create user: {$username}" );
                }
                continue;
            }

            // Set user details
            wp_update_user( array(
                'ID'           => $user_id,
                'display_name' => 'Group Member ' . $i,
                'first_name'   => 'Group',
                'last_name'    => 'Member ' . $i,
            ) );

            // Assign the 'subscriber' role to the user
            $user = new WP_User( $user_id );
            $user->set_role( 'subscriber' );

            // Mark as test user
            update_user_meta( $user_id, '_ldtt_test_user', true );
            update_user_meta( $user_id, '_ldtt_user_type', 'group_member' );

            // Enroll the user in the group
            $result = self::enroll_user_in_group( $user_id, $group_id );
            if ( is_wp_error( $result ) ) {
                if ( defined( 'WP_CLI' ) && WP_CLI ) {
                    WP_CLI::warning( "Failed to enroll user '{$username}' in the group: " . $result->get_error_message() );
                }
                continue;
            }

            $user_ids[] = $user_id;

            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::line( "Created and enrolled user: {$username} (ID: {$user_id})" );
            }
        }

        if ( empty( $user_ids ) ) {
            return new WP_Error( 'no_users_created', 'No users could be created and enrolled.' );
        }

        return $user_ids;
    }

    /**
     * Enroll a user in a group using standard LearnDash functions
     *
     * @param int $user_id The user ID.
     * @param int $group_id The group ID.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    private static function enroll_user_in_group( $user_id, $group_id ) {
        try {
            // Use standard LearnDash function to get current group users
            $current_users = learndash_get_groups_users( $group_id );
            
            // Handle both user objects and IDs
            $user_ids = array();
            if ( is_array( $current_users ) ) {
                foreach ( $current_users as $user ) {
                    if ( is_object( $user ) && isset( $user->ID ) ) {
                        $user_ids[] = intval( $user->ID );
                    } elseif ( is_numeric( $user ) ) {
                        $user_ids[] = intval( $user );
                    }
                }
            }

            // Add new user if not already present
            if ( ! in_array( $user_id, $user_ids ) ) {
                $user_ids[] = $user_id;
                
                // Use standard LearnDash function to set group users
                $result = learndash_set_groups_users( $group_id, $user_ids );
                
                if ( $result === false ) {
                    throw new Exception( 'learndash_set_groups_users returned false' );
                }
            }

            // Verify enrollment was successful
            if ( ! self::verify_group_enrollment( $user_id, $group_id ) ) {
                throw new Exception( 'Enrollment verification failed' );
            }

            return true;

        } catch ( Exception $e ) {
            return new WP_Error( 
                'enrollment_failed', 
                'Failed to enroll user in group: ' . $e->getMessage(),
                array( 'user_id' => $user_id, 'group_id' => $group_id )
            );
        }
    }

    /**
     * Check if a user is already in a group using standard LearnDash function
     *
     * @param int $user_id The user ID.
     * @param int $group_id The group ID.
     * @return bool True if user is in group, false otherwise.
     */
    private static function is_user_in_group( $user_id, $group_id ) {
        // Use standard LearnDash function
        $user_groups = learndash_get_users_group_ids( $user_id );
        return is_array( $user_groups ) && in_array( $group_id, $user_groups );
    }

    /**
     * Verify that a user was successfully enrolled in a group
     *
     * @param int $user_id The user ID.
     * @param int $group_id The group ID.
     * @return bool True if verified, false otherwise.
     */
    private static function verify_group_enrollment( $user_id, $group_id ) {
        return self::is_user_in_group( $user_id, $group_id );
    }

    /**
     * Get statistics about group enrollments
     *
     * @return array Statistics array.
     */
    public static function get_group_enrollment_statistics() {
        $stats = array(
            'total_groups' => 0,
            'groups_with_members' => 0,
            'total_group_members' => 0,
            'ldtt_created_members' => 0,
            'average_members_per_group' => 0,
        );

        // Get all groups
        $groups = get_posts( array(
            'post_type' => learndash_get_post_type_slug( 'group' ),
            'post_status' => 'publish',
            'numberposts' => -1,
            'fields' => 'ids',
        ) );

        $stats['total_groups'] = count( $groups );
        $total_members = 0;

        foreach ( $groups as $group_id ) {
            $group_users = learndash_get_groups_users( $group_id );
            $member_count = is_array( $group_users ) ? count( $group_users ) : 0;
            
            if ( $member_count > 0 ) {
                $stats['groups_with_members']++;
                $total_members += $member_count;
            }
        }

        $stats['total_group_members'] = $total_members;
        
        // Count LDTT created group members
        $ldtt_members = get_users( array(
            'meta_key' => '_ldtt_user_type',
            'meta_value' => 'group_member',
            'fields' => 'ID',
        ) );
        
        $stats['ldtt_created_members'] = count( $ldtt_members );
        
        // Calculate average
        if ( $stats['groups_with_members'] > 0 ) {
            $stats['average_members_per_group'] = round( $total_members / $stats['groups_with_members'], 2 );
        }

        return $stats;
    }
}