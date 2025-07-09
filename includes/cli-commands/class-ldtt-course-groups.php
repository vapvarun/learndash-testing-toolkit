<?php

/**
 * ENHANCED: Course Groups Command with Guaranteed Group Leaders
 * FIXED: Now uses proper LearnDash functions for group assignments
 */
class LDTT_Course_Groups {

    /**
     * Handle the WP-CLI command to create course groups in LearnDash.
     * ENHANCED: Now includes optional leader and member creation
     *
     * @param array $args Positional arguments passed from the WP-CLI command.
     * @param array $assoc_args Associative arguments passed from the WP-CLI command.
     */
    public static function handle( $args = array(), $assoc_args = array() ) {
        // Check for admin input if no CLI arguments are provided
        if ( empty( $args ) && empty( $assoc_args ) ) {
            $assoc_args = array(
                'count'   => isset( $_POST['count'] ) ? intval( $_POST['count'] ) : 3,
                'prefix'  => isset( $_POST['prefix'] ) ? sanitize_text_field( $_POST['prefix'] ) : 'Test Group',
                'courses' => isset( $_POST['courses'] ) ? sanitize_text_field( $_POST['courses'] ) : '',
                // ENHANCED: New optional parameters
                'create_leaders' => isset( $_POST['create_leaders'] ) ? true : false,
                'leaders_per_group' => isset( $_POST['leaders_per_group'] ) ? intval( $_POST['leaders_per_group'] ) : 1,
                'members_per_group' => isset( $_POST['members_per_group'] ) ? intval( $_POST['members_per_group'] ) : 0,
            );
        }

        $group_count = LDTT_Helper::validate_positive_int( $assoc_args['count'] ?? 3, 3, 50 );
        $group_prefix = sanitize_text_field( $assoc_args['prefix'] ?? 'Test Group' );
        $course_ids = ! empty( $assoc_args['courses'] ) ? array_map( 'absint', explode( ',', $assoc_args['courses'] ) ) : array();
        
        // ENHANCED: New parameters for leader/member creation
        $create_leaders = isset( $assoc_args['create_leaders'] ) && $assoc_args['create_leaders'];
        $leaders_per_group = LDTT_Helper::validate_positive_int( $assoc_args['leaders_per_group'] ?? 1, 1, 5 );
        $members_per_group = LDTT_Helper::validate_positive_int( $assoc_args['members_per_group'] ?? 0, 0, 10 );

        // Create the specified number of course groups (ORIGINAL FUNCTIONALITY)
        $group_ids = self::create_course_groups( $group_prefix, $group_count, $course_ids );
        if ( is_wp_error( $group_ids ) ) {
            $message = $group_ids->get_error_message();
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::error( $message );
            }
            return array( 'status' => 'error', 'message' => $message );
        }

        // ENHANCED: Optionally create and assign leaders
        $leader_results = array();
        if ( $create_leaders && ! empty( $group_ids ) ) {
            $leader_results = self::create_and_assign_leaders( $group_ids, $leaders_per_group );
        }

        // ENHANCED: Optionally create and assign members
        $member_results = array();
        if ( $members_per_group > 0 && ! empty( $group_ids ) ) {
            $member_results = self::create_and_assign_members( $group_ids, $members_per_group );
        }

        // ENHANCED: Build comprehensive message
        $message = "{$group_count} course groups successfully created with the prefix '{$group_prefix}'.";
        
        if ( ! empty( $leader_results ) ) {
            $leaders_created = $leader_results['leaders_created'] ?? 0;
            $groups_with_leaders = $leader_results['groups_with_leaders'] ?? 0;
            $message .= " Leaders: {$leaders_created} created, {$groups_with_leaders}/{$group_count} groups have leaders.";
        }
        
        if ( ! empty( $member_results ) ) {
            $members_created = $member_results['members_created'] ?? 0;
            $groups_with_members = $member_results['groups_with_members'] ?? 0;
            $message .= " Members: {$members_created} created, {$groups_with_members}/{$group_count} groups have members.";
        }

        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::success( $message );
            WP_CLI::line( 'Group IDs: ' . implode( ', ', $group_ids ) );
            
            // ENHANCED: Show leader/member assignment results
            if ( $create_leaders || $members_per_group > 0 ) {
                WP_CLI::line( "\nGroup Assignment Summary:" );
                foreach ( $group_ids as $group_id ) {
                    $group_title = get_the_title( $group_id );
                    $leaders_count = self::count_group_leaders( $group_id );
                    $members_count = self::count_group_members( $group_id );
                    WP_CLI::line( "- {$group_title} (ID: {$group_id}): {$leaders_count} leaders, {$members_count} members" );
                }
            }
        }

        return array( 
            'status' => 'success', 
            'message' => $message, 
            'group_ids' => $group_ids,
            'leader_results' => $leader_results,
            'member_results' => $member_results,
        );
    }

    /**
     * Create course groups in LearnDash.
     * ORIGINAL FUNCTIONALITY PRESERVED
     *
     * @param string $group_prefix The prefix for the group titles.
     * @param int $group_count The number of groups to create.
     * @param array $course_ids The IDs of courses to associate with the groups.
     * @return array|WP_Error An array of group IDs on success, WP_Error on failure.
     */
    private static function create_course_groups( $group_prefix, $group_count, $course_ids = array() ) {
        $group_ids = array();
        $admin_id = LDTT_Helper::get_admin_user_id();

        if ( ! $admin_id ) {
            return new WP_Error( 'no_admin', 'No admin user found to assign as group author.' );
        }

        for ( $i = 1; $i <= $group_count; $i++ ) {
            $group_title = "{$group_prefix} {$i}";
            $group_content = "This is the content for {$group_title}.";

            $group_id = wp_insert_post( array(
                'post_title'   => $group_title,
                'post_type'    => learndash_get_post_type_slug( 'group' ),
                'post_status'  => 'publish',
                'post_content' => $group_content,
                'post_author'  => $admin_id,
                'meta_input'   => array(
                    '_ldtt_test_data' => true,
                ),
            ) );

            if ( is_wp_error( $group_id ) ) {
                return $group_id;
            }

            // Associate courses with the group if course IDs are provided
            if ( ! empty( $course_ids ) ) {
                $valid_course_ids = array();
                foreach ( $course_ids as $course_id ) {
                    if ( is_numeric( $course_id ) && get_post_type( $course_id ) === learndash_get_post_type_slug( 'course' ) ) {
                        $valid_course_ids[] = intval( $course_id );
                    }
                }
                
                if ( ! empty( $valid_course_ids ) ) {
                    // Use LearnDash function to associate courses with group
                    if ( function_exists( 'learndash_set_group_enrolled_courses' ) ) {
                        learndash_set_group_enrolled_courses( $group_id, $valid_course_ids );
                    } else {
                        // Fallback method using meta
                        update_post_meta( $group_id, 'learndash_group_enrolled_' . $group_id, $valid_course_ids );
                    }
                }
            }

            $group_ids[] = $group_id;

            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::line( "Created group: {$group_title} (ID: {$group_id})" );
            }
        }

        return $group_ids;
    }

    /**
     * ENHANCED: Create and assign leaders to groups
     *
     * @param array $group_ids Array of group IDs.
     * @param int $leaders_per_group Number of leaders per group.
     * @return array Results of leader creation and assignment.
     */
    private static function create_and_assign_leaders( $group_ids, $leaders_per_group = 1 ) {
        $total_leaders_needed = count( $group_ids ) * $leaders_per_group;
        $created_leaders = array();
        $groups_with_leaders = 0;

        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::line( "Creating {$total_leaders_needed} leaders for " . count( $group_ids ) . " groups..." );
        }

        // Create the leaders
        for ( $i = 1; $i <= $total_leaders_needed; $i++ ) {
            $username = 'group_leader_' . LDTT_Helper::generate_random_string( 8 );
            $email = $username . '@example.com';

            $user_id = wp_create_user( $username, wp_generate_password( 12 ), $email );

            if ( is_wp_error( $user_id ) ) {
                if ( defined( 'WP_CLI' ) && WP_CLI ) {
                    WP_CLI::warning( "Failed to create leader: {$username}" );
                }
                continue;
            }

            // Set as group leader role
            $user = new WP_User( $user_id );
            $user->set_role( 'group_leader' );

            // Set display name
            wp_update_user( array(
                'ID'           => $user_id,
                'display_name' => 'Group Leader ' . $i,
                'first_name'   => 'Leader',
                'last_name'    => $i,
            ) );

            // Mark as test user
            update_user_meta( $user_id, '_ldtt_test_user', true );
            update_user_meta( $user_id, '_ldtt_user_type', 'group_leader' );

            $created_leaders[] = $user_id;

            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::line( "Created group leader: {$username} (ID: {$user_id})" );
            }
        }

        // Assign leaders to groups
        $leader_index = 0;
        foreach ( $group_ids as $group_id ) {
            $group_title = get_the_title( $group_id );
            $leaders_assigned = 0;

            for ( $j = 0; $j < $leaders_per_group; $j++ ) {
                if ( $leader_index >= count( $created_leaders ) ) {
                    break;
                }

                $leader_id = $created_leaders[ $leader_index ];
                $assignment_result = self::assign_group_leader( $leader_id, $group_id );

                if ( $assignment_result ) {
                    $leaders_assigned++;
                    if ( defined( 'WP_CLI' ) && WP_CLI ) {
                        WP_CLI::line( "  ✓ Assigned leader {$leader_id} to group: {$group_title}" );
                    }
                } else {
                    if ( defined( 'WP_CLI' ) && WP_CLI ) {
                        WP_CLI::warning( "  ✗ Failed to assign leader {$leader_id} to group: {$group_title}" );
                    }
                }

                $leader_index++;
            }

            if ( $leaders_assigned > 0 ) {
                $groups_with_leaders++;
            }
        }

        return array(
            'leaders_created' => count( $created_leaders ),
            'groups_with_leaders' => $groups_with_leaders,
            'created_leader_ids' => $created_leaders,
        );
    }

    /**
     * ENHANCED: Create and assign members to groups
     *
     * @param array $group_ids Array of group IDs.
     * @param int $members_per_group Number of members per group.
     * @return array Results of member creation and assignment.
     */
    private static function create_and_assign_members( $group_ids, $members_per_group = 3 ) {
        $total_members_needed = count( $group_ids ) * $members_per_group;
        $created_members = array();
        $groups_with_members = 0;

        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::line( "Creating {$total_members_needed} members for " . count( $group_ids ) . " groups..." );
        }

        // Create the members
        for ( $i = 1; $i <= $total_members_needed; $i++ ) {
            $username = 'group_member_' . LDTT_Helper::generate_random_string( 8 );
            $email = $username . '@example.com';

            $user_id = wp_create_user( $username, wp_generate_password( 12 ), $email );

            if ( is_wp_error( $user_id ) ) {
                if ( defined( 'WP_CLI' ) && WP_CLI ) {
                    WP_CLI::warning( "Failed to create member: {$username}" );
                }
                continue;
            }

            // Set as subscriber
            $user = new WP_User( $user_id );
            $user->set_role( 'subscriber' );

            // Set display name
            wp_update_user( array(
                'ID'           => $user_id,
                'display_name' => 'Group Member ' . $i,
                'first_name'   => 'Member',
                'last_name'    => $i,
            ) );

            // Mark as test user
            update_user_meta( $user_id, '_ldtt_test_user', true );
            update_user_meta( $user_id, '_ldtt_user_type', 'group_member' );

            $created_members[] = $user_id;

            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::line( "Created group member: {$username} (ID: {$user_id})" );
            }
        }

        // Assign members to groups
        $member_index = 0;
        foreach ( $group_ids as $group_id ) {
            $group_title = get_the_title( $group_id );
            $members_assigned = 0;

            for ( $j = 0; $j < $members_per_group; $j++ ) {
                if ( $member_index >= count( $created_members ) ) {
                    break;
                }

                $member_id = $created_members[ $member_index ];
                $assignment_result = self::enroll_user_in_group( $member_id, $group_id );

                if ( $assignment_result ) {
                    $members_assigned++;
                    if ( defined( 'WP_CLI' ) && WP_CLI ) {
                        WP_CLI::line( "  ✓ Assigned member {$member_id} to group: {$group_title}" );
                    }
                } else {
                    if ( defined( 'WP_CLI' ) && WP_CLI ) {
                        WP_CLI::warning( "  ✗ Failed to assign member {$member_id} to group: {$group_title}" );
                    }
                }

                $member_index++;
            }

            if ( $members_assigned > 0 ) {
                $groups_with_members++;
            }
        }

        return array(
            'members_created' => count( $created_members ),
            'groups_with_members' => $groups_with_members,
            'created_member_ids' => $created_members,
        );
    }

    /**
     * FIXED: Assign a group leader using proper LearnDash functions
     *
     * @param int $leader_id User ID of the leader.
     * @param int $group_id Group ID.
     * @return bool Success status.
     */
    private static function assign_group_leader( $leader_id, $group_id ) {
        $leader_id = absint( $leader_id );
        $group_id = absint( $group_id );
        
        if ( ! $leader_id || ! $group_id ) {
            return false;
        }

        // Validate user exists
        $user = get_user_by( 'ID', $leader_id );
        if ( ! $user ) {
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::warning( "User ID {$leader_id} does not exist" );
            }
            return false;
        }

        // Validate group exists
        $group = get_post( $group_id );
        if ( ! $group || $group->post_type !== learndash_get_post_type_slug( 'group' ) ) {
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::warning( "Group {$group_id} does not exist or is not a valid group" );
            }
            return false;
        }

        try {
            // Method 1: Try LearnDash function first (proper approach)
            if ( function_exists( 'learndash_get_groups_administrators' ) && function_exists( 'learndash_set_groups_administrators' ) ) {
                // Get current group leaders as an array of user IDs
                $current_admins = learndash_get_groups_administrators( $group_id );
                
                // Ensure we have a valid array
                if ( ! is_array( $current_admins ) ) {
                    $current_admins = array();
                }
                
                // Make sure all entries are integers (sometimes it's not!)
                $current_admins = array_map( 'intval', $current_admins );
                
                // Avoid duplicate
                if ( ! in_array( $leader_id, $current_admins ) ) {
                    $current_admins[] = $leader_id;
                    $result = learndash_set_groups_administrators( $group_id, $current_admins );
                    
                    if ( $result !== false ) {
                        if ( defined( 'WP_CLI' ) && WP_CLI ) {
                            WP_CLI::line( "    Used LearnDash function for leader assignment" );
                        }
                        return true;
                    } else {
                        if ( defined( 'WP_CLI' ) && WP_CLI ) {
                            WP_CLI::warning( "LearnDash function returned false, trying fallback method" );
                        }
                    }
                } else {
                    // Already assigned
                    if ( defined( 'WP_CLI' ) && WP_CLI ) {
                        WP_CLI::line( "    User {$leader_id} is already a leader of group {$group_id}" );
                    }
                    return true;
                }
            } else {
                if ( defined( 'WP_CLI' ) && WP_CLI ) {
                    WP_CLI::warning( "LearnDash group administrator functions not available, using fallback" );
                }
            }
        } catch ( Exception $e ) {
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::warning( "LearnDash function failed: " . $e->getMessage() . ", using fallback method" );
            }
        }

        // Method 2: Fallback - direct meta update
        $current_admins = get_post_meta( $group_id, '_ld_group_administrators', true );
        if ( ! is_array( $current_admins ) ) {
            $current_admins = array();
        }

        // Ensure integers
        $current_admins = array_map( 'intval', $current_admins );

        if ( ! in_array( $leader_id, $current_admins ) ) {
            $current_admins[] = $leader_id;
            $result = update_post_meta( $group_id, '_ld_group_administrators', $current_admins );
            
            if ( $result ) {
                // Update reverse relationship
                $user_groups = get_user_meta( $leader_id, 'learndash_group_leaders_' . $leader_id, true );
                if ( ! is_array( $user_groups ) ) {
                    $user_groups = array();
                }
                $user_groups = array_map( 'intval', $user_groups );
                
                if ( ! in_array( $group_id, $user_groups ) ) {
                    $user_groups[] = $group_id;
                    update_user_meta( $leader_id, 'learndash_group_leaders_' . $leader_id, $user_groups );
                }
                
                if ( defined( 'WP_CLI' ) && WP_CLI ) {
                    WP_CLI::line( "    Used meta-based assignment for leader" );
                }
                
                return true;
            }
        } else {
            // Already assigned
            return true;
        }

        return false;
    }

    /**
     * FIXED: Enroll a user in a group using proper LearnDash functions
     *
     * @param int $user_id User ID.
     * @param int $group_id Group ID.
     * @return bool Success status.
     */
    private static function enroll_user_in_group( $user_id, $group_id ) {
        $user_id = absint( $user_id );
        $group_id = absint( $group_id );
        
        if ( ! $user_id || ! $group_id ) {
            return false;
        }

        // Validate user exists
        $user = get_user_by( 'ID', $user_id );
        if ( ! $user ) {
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::warning( "User ID {$user_id} does not exist" );
            }
            return false;
        }

        // Validate group exists
        $group = get_post( $group_id );
        if ( ! $group || $group->post_type !== learndash_get_post_type_slug( 'group' ) ) {
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::warning( "Group {$group_id} does not exist or is not a valid group" );
            }
            return false;
        }

        try {
            // Method 1: Try LearnDash function first (proper approach)
            if ( function_exists( 'learndash_get_groups_users' ) && function_exists( 'learndash_set_groups_users' ) ) {
                // Get current users in the group
                $current_members = learndash_get_groups_users( $group_id );
                
                // Ensure we have a valid array
                if ( ! is_array( $current_members ) ) {
                    $current_members = array();
                }
                
                // Normalize to array of user IDs (in case of stray WP_User objects)
                $current_members = array_map( 'intval', $current_members );
                
                // Add user if not already present
                if ( ! in_array( $user_id, $current_members ) ) {
                    $current_members[] = $user_id;
                    $result = learndash_set_groups_users( $group_id, $current_members );
                    
                    if ( $result !== false ) {
                        if ( defined( 'WP_CLI' ) && WP_CLI ) {
                            WP_CLI::line( "    Used LearnDash function for member enrollment" );
                        }
                        return true;
                    } else {
                        if ( defined( 'WP_CLI' ) && WP_CLI ) {
                            WP_CLI::warning( "LearnDash function returned false, trying fallback method" );
                        }
                    }
                } else {
                    // Already enrolled
                    if ( defined( 'WP_CLI' ) && WP_CLI ) {
                        WP_CLI::line( "    User {$user_id} is already a member of group {$group_id}" );
                    }
                    return true;
                }
            } else {
                if ( defined( 'WP_CLI' ) && WP_CLI ) {
                    WP_CLI::warning( "LearnDash group user functions not available, using fallback" );
                }
            }
        } catch ( Exception $e ) {
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::warning( "LearnDash enrollment function failed: " . $e->getMessage() . ", using fallback" );
            }
        }

        // Method 2: Fallback - direct meta update
        // Update group's user list
        $group_users = get_post_meta( $group_id, 'learndash_group_users_' . $group_id, true );
        if ( ! is_array( $group_users ) ) {
            $group_users = array();
        }
        
        // Ensure integers
        $group_users = array_map( 'intval', $group_users );
        
        if ( ! in_array( $user_id, $group_users ) ) {
            $group_users[] = $user_id;
            $group_result = update_post_meta( $group_id, 'learndash_group_users_' . $group_id, $group_users );
            
            // Update user's group list
            $user_groups = get_user_meta( $user_id, 'learndash_group_users_' . $user_id, true );
            if ( ! is_array( $user_groups ) ) {
                $user_groups = array();
            }
            $user_groups = array_map( 'intval', $user_groups );
            
            if ( ! in_array( $group_id, $user_groups ) ) {
                $user_groups[] = $group_id;
                $user_result = update_user_meta( $user_id, 'learndash_group_users_' . $user_id, $user_groups );
                
                if ( $group_result && $user_result ) {
                    if ( defined( 'WP_CLI' ) && WP_CLI ) {
                        WP_CLI::line( "    Used meta-based assignment for member" );
                    }
                    return true;
                }
            }
        } else {
            // Already enrolled
            return true;
        }

        return false;
    }

    /**
     * ENHANCED: Count group leaders for a specific group
     *
     * @param int $group_id Group ID.
     * @return int Number of leaders.
     */
    private static function count_group_leaders( $group_id ) {
        if ( function_exists( 'learndash_get_groups_administrators' ) ) {
            $leaders = learndash_get_groups_administrators( $group_id );
            return is_array( $leaders ) ? count( array_filter( array_map( 'intval', $leaders ) ) ) : 0;
        }
        
        // Fallback
        $leaders = get_post_meta( $group_id, '_ld_group_administrators', true );
        return is_array( $leaders ) ? count( array_filter( array_map( 'intval', $leaders ) ) ) : 0;
    }

    /**
     * ENHANCED: Count group members for a specific group
     *
     * @param int $group_id Group ID.
     * @return int Number of members.
     */
    private static function count_group_members( $group_id ) {
        if ( function_exists( 'learndash_get_groups_users' ) ) {
            $members = learndash_get_groups_users( $group_id );
            return is_array( $members ) ? count( array_filter( array_map( 'intval', $members ) ) ) : 0;
        }
        
        // Fallback
        $members = get_post_meta( $group_id, 'learndash_group_users_' . $group_id, true );
        return is_array( $members ) ? count( array_filter( array_map( 'intval', $members ) ) ) : 0;
    }
}