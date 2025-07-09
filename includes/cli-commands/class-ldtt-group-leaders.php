<?php

/**
 * Group Leaders Command - Updated with LearnDash Functions and Enhanced Error Handling
 * Creates group leaders and properly assigns them to groups using correct LearnDash functions
 */
class LDTT_Group_Leaders {

    /**
     * Handle the command to create group leaders and assign them to groups
     *
     * @param array $args Positional arguments.
     * @param array $assoc_args Associative arguments.
     */
    public static function handle( $args = array(), $assoc_args = array() ) {
        // Check for admin input if no CLI arguments are provided
        if ( empty( $args ) && empty( $assoc_args ) ) {
            $assoc_args = array(
                'group_name'    => isset( $_POST['group_name'] ) ? sanitize_text_field( $_POST['group_name'] ) : 'Test Group',
                'leader_name'   => isset( $_POST['leader_name'] ) ? sanitize_text_field( $_POST['leader_name'] ) : 'Test Leader',
                'count'         => isset( $_POST['count'] ) ? intval( $_POST['count'] ) : 1,
                'create_group'  => isset( $_POST['create_group'] ) ? true : false,
                'use_existing'  => isset( $_POST['use_existing'] ) ? true : false,
                'safe_mode'     => isset( $_POST['safe_mode'] ) ? true : false,
            );
        }

        $group_name = sanitize_text_field( $assoc_args['group_name'] ?? $assoc_args['group'] ?? 'Test Group' );
        $leader_name = sanitize_text_field( $assoc_args['leader_name'] ?? $assoc_args['leader'] ?? 'Test Leader' );
        $leader_count = LDTT_Helper::validate_positive_int( $assoc_args['count'] ?? 1, 1, 20 );
        $create_group = isset( $assoc_args['create_group'] ) && $assoc_args['create_group'];
        $use_existing = isset( $assoc_args['use_existing'] ) && $assoc_args['use_existing'];
        $safe_mode = isset( $assoc_args['safe_mode'] ) && $assoc_args['safe_mode'];

        // Create or find the group
        $group_id = self::get_or_create_group( $group_name, $create_group );
        if ( is_wp_error( $group_id ) ) {
            $message = $group_id->get_error_message();
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::error( $message );
            }
            return array( 'status' => 'error', 'message' => $message );
        }

        $created_leaders = array();
        $assignment_results = array();

        if ( $use_existing ) {
            // Use existing users and promote them to group leaders
            $existing_users = self::get_existing_users_for_promotion( $leader_count );
            
            if ( empty( $existing_users ) ) {
                $message = 'No suitable existing users found to promote to group leaders.';
                if ( defined( 'WP_CLI' ) && WP_CLI ) {
                    WP_CLI::error( $message );
                }
                return array( 'status' => 'error', 'message' => $message );
            }

            foreach ( $existing_users as $user_id ) {
                $result = self::promote_user_to_group_leader( $user_id, $group_id, $safe_mode );
                
                if ( ! is_wp_error( $result ) ) {
                    $created_leaders[] = $user_id;
                    $assignment_results[] = $result;
                    
                    if ( defined( 'WP_CLI' ) && WP_CLI ) {
                        $user = get_user_by( 'ID', $user_id );
                        WP_CLI::line( "Promoted existing user to group leader: {$user->user_login} (ID: {$user_id})" );
                    }
                } else {
                    if ( defined( 'WP_CLI' ) && WP_CLI ) {
                        WP_CLI::warning( "Failed to promote user ID {$user_id}: " . $result->get_error_message() );
                    }
                }
            }
        } else {
            // Create new group leader users
            for ( $i = 1; $i <= $leader_count; $i++ ) {
                $current_leader_name = $leader_count > 1 ? "{$leader_name} {$i}" : $leader_name;
                
                // Create the group leader user
                $leader_id = self::create_group_leader_user( $current_leader_name, $i );
                if ( is_wp_error( $leader_id ) ) {
                    if ( defined( 'WP_CLI' ) && WP_CLI ) {
                        WP_CLI::warning( "Failed to create leader: {$current_leader_name} - " . $leader_id->get_error_message() );
                    }
                    continue;
                }

                // Assign the leader to the group
                $result = self::assign_group_leader( $leader_id, $group_id, $safe_mode );
                if ( is_wp_error( $result ) ) {
                    if ( defined( 'WP_CLI' ) && WP_CLI ) {
                        WP_CLI::warning( "Failed to assign leader to group: {$current_leader_name} - " . $result->get_error_message() );
                    }
                    continue;
                }

                $created_leaders[] = $leader_id;
                $assignment_results[] = $result;

                if ( defined( 'WP_CLI' ) && WP_CLI ) {
                    WP_CLI::line( "Created and assigned group leader: {$current_leader_name} (ID: {$leader_id})" );
                }
            }
        }

        if ( empty( $created_leaders ) ) {
            $message = 'No group leaders could be created or assigned.';
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::error( $message );
            }
            return array( 'status' => 'error', 'message' => $message );
        }

        $action_text = $use_existing ? 'promoted and assigned' : 'created and assigned';
        $message = count( $created_leaders ) . " group leaders {$action_text} to group '{$group_name}' successfully.";
        
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::success( $message );
            WP_CLI::line( 'Group ID: ' . $group_id );
            WP_CLI::line( 'Leader IDs: ' . implode( ', ', $created_leaders ) );
        }
        
        return array(
            'status'        => 'success',
            'message'       => $message,
            'group_id'      => $group_id,
            'leader_ids'    => $created_leaders,
            'assignments'   => $assignment_results,
            'method'        => $use_existing ? 'existing_users' : 'new_users',
        );
    }

    /**
     * Get an existing group by name or create a new one
     *
     * @param string $group_name The name of the group.
     * @param bool $create_if_missing Whether to create the group if it doesn't exist.
     * @return int|WP_Error The group ID on success, WP_Error on failure.
     */
    private static function get_or_create_group( $group_name, $create_if_missing = true ) {
        // Try to find existing group
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
            'post_content' => "Test group created by LearnDash Testing Toolkit for group leader assignment.",
            'post_author'  => $admin_id,
            'meta_input'   => array(
                '_ldtt_test_data' => true,
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
     * Get existing users suitable for promotion to group leaders
     *
     * @param int $count Number of users needed.
     * @return array Array of user IDs.
     */
    private static function get_existing_users_for_promotion( $count ) {
        // Get existing users that are not administrators or already group leaders
        $users = get_users( array(
            'role__not_in' => array( 'administrator', 'group_leader' ),
            'fields' => 'ID',
            'number' => $count * 2, // Get extra in case some can't be promoted
        ) );

        // Convert to integers and shuffle for random selection
        $user_ids = array_map( 'absint', $users );
        shuffle( $user_ids );

        return array_slice( $user_ids, 0, $count );
    }

    /**
     * Promote an existing user to group leader
     *
     * @param int $user_id The user ID to promote.
     * @param int $group_id The group ID to assign to.
     * @param bool $safe_mode Whether to use safe mode.
     * @return array|WP_Error Result array or WP_Error on failure.
     */
    private static function promote_user_to_group_leader( $user_id, $group_id, $safe_mode = false ) {
        $user = get_user_by( 'ID', $user_id );
        if ( ! $user ) {
            return new WP_Error( 'user_not_found', "User with ID {$user_id} not found." );
        }

        // Change user role to group_leader
        $user->set_role( 'group_leader' );

        // Update display name to indicate group leader status
        wp_update_user( array(
            'ID'           => $user_id,
            'display_name' => 'Group Leader: ' . $user->display_name,
        ) );

        // Mark as LDTT managed
        update_user_meta( $user_id, '_ldtt_promoted_to_leader', true );
        update_user_meta( $user_id, '_ldtt_user_type', 'group_leader' );

        // Assign to group
        $assignment_result = self::assign_group_leader( $user_id, $group_id, $safe_mode );
        if ( is_wp_error( $assignment_result ) ) {
            return $assignment_result;
        }

        return array(
            'user_id' => $user_id,
            'promoted' => true,
            'assigned_to_group' => $group_id,
            'method' => 'promoted_existing',
        );
    }

    /**
     * Create a new group leader user
     *
     * @param string $leader_name The name of the group leader.
     * @param int $index The index number for unique naming.
     * @return int|WP_Error The user ID on success, WP_Error on failure.
     */
    private static function create_group_leader_user( $leader_name, $index = 1 ) {
        $leader_username = 'leader_' . LDTT_Helper::generate_random_string( 8 );
        $leader_email = $leader_username . '@example.com';

        // Ensure unique email
        $attempts = 0;
        while ( email_exists( $leader_email ) && $attempts < 10 ) {
            $leader_email = 'leader_' . LDTT_Helper::generate_random_string( 12 ) . '@example.com';
            $attempts++;
        }

        if ( email_exists( $leader_email ) ) {
            return new WP_Error( 'email_exists', 'Could not generate unique email for group leader.' );
        }

        $user_id = wp_create_user( $leader_username, wp_generate_password( 12 ), $leader_email );

        if ( is_wp_error( $user_id ) ) {
            return $user_id;
        }

        // Set user details
        wp_update_user( array(
            'ID'           => $user_id,
            'display_name' => $leader_name,
            'first_name'   => 'Group Leader',
            'last_name'    => $index,
        ) );

        // Assign the 'group_leader' role to the user
        $user = new WP_User( $user_id );
        $user->set_role( 'group_leader' );

        // Mark as test user
        update_user_meta( $user_id, '_ldtt_test_user', true );
        update_user_meta( $user_id, '_ldtt_user_type', 'group_leader' );

        return $user_id;
    }

    /**
     * Assign a group leader to a group using standard LearnDash functions
     *
     * @param int $leader_id The ID of the group leader.
     * @param int $group_id The ID of the group.
     * @param bool $safe_mode Whether to use enhanced safe mode.
     * @return array|WP_Error Result array on success, WP_Error on failure.
     */
    private static function assign_group_leader( $leader_id, $group_id, $safe_mode = false ) {
        // Validate inputs
        if ( ! $leader_id || ! $group_id ) {
            return new WP_Error( 'invalid_params', 'Invalid leader ID or group ID provided.' );
        }

        // Check if user exists and has group_leader role
        $user = get_user_by( 'ID', $leader_id );
        if ( ! $user || ! in_array( 'group_leader', $user->roles ) ) {
            return new WP_Error( 'invalid_leader', 'User is not a valid group leader.' );
        }

        // Check if group exists
        $group = get_post( $group_id );
        if ( ! $group || $group->post_type !== learndash_get_post_type_slug( 'group' ) ) {
            return new WP_Error( 'invalid_group', 'Invalid group ID provided.' );
        }

        try {
            // Use standard LearnDash functions
            $current_administrators = learndash_get_groups_administrators( $group_id );
            
            // Ensure we have an array
            if ( ! is_array( $current_administrators ) ) {
                $current_administrators = array();
            }

            // Convert to integers for consistency
            $current_administrators = array_map( 'intval', $current_administrators );
            
            // Add new leader if not already present
            if ( ! in_array( $leader_id, $current_administrators ) ) {
                $current_administrators[] = $leader_id;
                
                $result = learndash_set_groups_administrators( $group_id, $current_administrators );
                
                if ( $result === false ) {
                    throw new Exception( 'learndash_set_groups_administrators returned false' );
                }
            }

            // Verify assignment was successful
            $verification = self::verify_group_leader_assignment( $leader_id, $group_id );
            if ( ! $verification ) {
                throw new Exception( 'Assignment verification failed' );
            }

            return array(
                'leader_id' => $leader_id,
                'group_id' => $group_id,
                'method' => 'learndash_functions',
                'safe_mode' => $safe_mode,
                'verified' => true,
            );

        } catch ( Exception $e ) {
            return new WP_Error( 
                'assignment_failed', 
                'Failed to assign group leader: ' . $e->getMessage(),
                array( 'leader_id' => $leader_id, 'group_id' => $group_id )
            );
        }
    }

    /**
     * Verify that a group leader was successfully assigned to a group
     *
     * @param int $leader_id The leader user ID.
     * @param int $group_id The group ID.
     * @return bool True if verified, false otherwise.
     */
    private static function verify_group_leader_assignment( $leader_id, $group_id ) {
        // Use standard LearnDash function for verification
        $administrators = learndash_get_groups_administrators( $group_id );
        if ( is_array( $administrators ) ) {
            return in_array( $leader_id, array_map( 'intval', $administrators ) );
        }

        return false;
    }

    /**
     * Get statistics about group leader assignments
     *
     * @return array Statistics array.
     */
    public static function get_group_leader_statistics() {
        $stats = array(
            'total_group_leaders' => 0,
            'ldtt_created_leaders' => 0,
            'ldtt_promoted_leaders' => 0,
            'groups_with_leaders' => 0,
            'orphaned_leaders' => 0,
        );

        // Get all group leaders
        $group_leaders = get_users( array( 'role' => 'group_leader' ) );
        $stats['total_group_leaders'] = count( $group_leaders );

        foreach ( $group_leaders as $leader ) {
            if ( get_user_meta( $leader->ID, '_ldtt_test_user', true ) ) {
                $stats['ldtt_created_leaders']++;
            }
            
            if ( get_user_meta( $leader->ID, '_ldtt_promoted_to_leader', true ) ) {
                $stats['ldtt_promoted_leaders']++;
            }
        }

        // Get groups and check which have leaders using standard LearnDash functions
        $groups = get_posts( array(
            'post_type' => learndash_get_post_type_slug( 'group' ),
            'post_status' => 'publish',
            'fields' => 'ids',
            'numberposts' => -1,
        ) );

        $groups_with_leaders_count = 0;
        foreach ( $groups as $group_id ) {
            $administrators = learndash_get_groups_administrators( $group_id );
            if ( is_array( $administrators ) && ! empty( $administrators ) ) {
                $groups_with_leaders_count++;
            }
        }

        $stats['groups_with_leaders'] = $groups_with_leaders_count;
        $stats['orphaned_leaders'] = max( 0, $stats['total_group_leaders'] - $groups_with_leaders_count );

        return $stats;
    }
}