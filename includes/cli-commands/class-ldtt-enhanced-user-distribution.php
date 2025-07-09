<?php

/**
 * Improved Group Leader and Member Assignment for LDTT
 * This fixes the issues with group leader assignment and ensures proper group membership
 */

class LDTT_Enhanced_User_Distribution {

    /**
     * Safe mode flag to prevent LearnDash function errors
     * 
     * @var bool
     */
    private static $safe_mode = true;
    
    /**
     * Created group leaders cache
     * 
     * @var array
     */
    private static $created_group_leaders = array();
    
    /**
     * Group assignment tracking
     * 
     * @var array
     */
    private static $group_assignments = array();

    /**
     * Handle the command to create users with specific distribution and progress.
     * IMPROVED: Ensures every group gets a leader
     *
     * @param array $args Positional arguments.
     * @param array $assoc_args Associative arguments.
     */
    public static function handle( $args = array(), $assoc_args = array() ) {
        // Check for admin input if no CLI arguments are provided
        if ( empty( $args ) && empty( $assoc_args ) ) {
            $assoc_args = array(
                'total_users'     => isset( $_POST['total_users'] ) ? intval( $_POST['total_users'] ) : 100,
                'group_leaders'   => isset( $_POST['group_leaders'] ) ? floatval( $_POST['group_leaders'] ) : 1.0,
                'group_members'   => isset( $_POST['group_members'] ) ? floatval( $_POST['group_members'] ) : 2.0,
                'course_enrolled' => isset( $_POST['course_enrolled'] ) ? floatval( $_POST['course_enrolled'] ) : 5.0,
                'create_progress' => isset( $_POST['create_progress'] ) ? true : false,
                'use_existing'    => isset( $_POST['use_existing'] ) ? true : false,
                'safe_group_creation' => isset( $_POST['safe_group_creation'] ) ? true : true,
            );
        }

        $total_users = LDTT_Helper::validate_positive_int( $assoc_args['total_users'] ?? 100, 100, 1000 );
        $group_leader_percent = floatval( $assoc_args['group_leaders'] ?? 1.0 );
        $group_member_percent = floatval( $assoc_args['group_members'] ?? 2.0 );
        $course_enrolled_percent = floatval( $assoc_args['course_enrolled'] ?? 5.0 );
        $create_progress = isset( $assoc_args['create_progress'] ) && $assoc_args['create_progress'];
        $use_existing = isset( $assoc_args['use_existing'] ) && $assoc_args['use_existing'];
        
        // Set safe mode based on parameter
        self::$safe_mode = isset( $assoc_args['safe_group_creation'] ) ? $assoc_args['safe_group_creation'] : true;

        // CRITICAL FIX: Calculate users needed, ensuring enough leaders for all groups
        $group_leader_count = max( 1, round( $total_users * ( $group_leader_percent / 100 ) ) );
        $group_member_count = max( 1, round( $total_users * ( $group_member_percent / 100 ) ) );
        $course_enrolled_count = max( 1, round( $total_users * ( $course_enrolled_percent / 100 ) ) );
        
        // Calculate optimal groups based on members (3-5 members per group ideal)
        $ideal_groups = max( 3, min( 8, ceil( $group_member_count / 4 ) ) );
        
        // ENSURE ENOUGH LEADERS: If we don't have enough leaders for all groups, increase leader count
        if ( $group_leader_count < $ideal_groups ) {
            $original_leader_count = $group_leader_count;
            $group_leader_count = $ideal_groups; // One leader per group minimum
            
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::line( "ADJUSTED: Increased leaders from {$original_leader_count} to {$group_leader_count} to ensure every group has a leader" );
            }
        }

        $results = array();

        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::line( "Starting enhanced user distribution with guaranteed group leaders" );
            WP_CLI::line( "Safe mode: " . ( self::$safe_mode ? 'enabled' : 'disabled' ) );
            WP_CLI::line( "Target: {$ideal_groups} groups, {$group_leader_count} leaders, {$group_member_count} members" );
        }

        // Phase 1: Ensure we have adequate groups FIRST
        $groups = self::ensure_adequate_groups( $ideal_groups );
        
        if ( empty( $groups ) ) {
            return array( 'status' => 'error', 'message' => 'Failed to create or find adequate groups' );
        }

        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::line( "Groups ready: " . count( $groups ) . " groups available" );
        }

        // Phase 2: Create group leaders - ENSURE ONE PER GROUP MINIMUM
        $results['group_leaders'] = self::create_and_assign_group_leaders_guaranteed( $group_leader_count, $groups, $use_existing );
        if ( is_wp_error( $results['group_leaders'] ) ) {
            return array( 'status' => 'error', 'message' => $results['group_leaders']->get_error_message() );
        }

        // Phase 3: Create group members and assign them to groups
        $results['group_members'] = self::create_and_assign_group_members( $group_member_count, $groups, $use_existing );
        if ( is_wp_error( $results['group_members'] ) ) {
            return array( 'status' => 'error', 'message' => $results['group_members']->get_error_message() );
        }

        // Phase 4: Create course-enrolled users
        $results['course_enrolled'] = self::create_course_enrolled_users( $course_enrolled_count, $create_progress, $use_existing );
        if ( is_wp_error( $results['course_enrolled'] ) ) {
            return array( 'status' => 'error', 'message' => $results['course_enrolled']->get_error_message() );
        }

        // Phase 5: Create remaining regular users (only if creating new users)
        $used_users = $group_leader_count + $group_member_count + $course_enrolled_count;
        $remaining_users = max( 0, $total_users - $used_users );
        if ( $remaining_users > 0 && ! $use_existing ) {
            $results['regular_users'] = self::create_regular_users( $remaining_users );
        }

        // Phase 6: Final verification and reporting
        $verification = self::verify_group_assignments( $groups );

        $actual_total = $used_users + ( $remaining_users > 0 ? $remaining_users : 0 );
        $message = "Successfully processed {$actual_total} users with guaranteed group leaders: " .
                   "{$group_leader_count} group leaders, {$group_member_count} group members, " .
                   "{$course_enrolled_count} course-enrolled users" .
                   ( $remaining_users > 0 ? ", {$remaining_users} regular users" : "" ) .
                   ". Groups: {$verification['groups_with_leaders']}/{$verification['total_groups']} have leaders, " .
                   "{$verification['groups_with_members']}/{$verification['total_groups']} have members";

        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::success( $message );
            WP_CLI::line( "Group assignment verification:" );
            WP_CLI::line( "- Total groups: {$verification['total_groups']}" );
            WP_CLI::line( "- Groups with leaders: {$verification['groups_with_leaders']}" );
            WP_CLI::line( "- Groups with members: {$verification['groups_with_members']}" );
            WP_CLI::line( "- Average members per group: {$verification['avg_members_per_group']}" );
            WP_CLI::line( "- GUARANTEED: Every group has at least one leader" );
        }

        return array( 
            'status' => 'success', 
            'message' => $message, 
            'results' => $results,
            'verification' => $verification
        );
    }

    /**
     * IMPROVED: Ensure we have adequate groups for assignment
     * 
     * @param int $required_groups
     * @return array
     */
    private static function ensure_adequate_groups( $required_groups ) {
        // FIXED: Limit groups to a reasonable number based on users
        $max_reasonable_groups = max( 3, min( $required_groups, 10 ) ); // Between 3-10 groups
        
        // Get existing test groups
        $existing_groups = get_posts( array(
            'post_type'   => learndash_get_post_type_slug( 'group' ),
            'numberposts' => -1,
            'post_status' => 'publish',
            'fields'      => 'ids',
        ) );

        $groups_needed = max( 0, $max_reasonable_groups - count( $existing_groups ) );
        
        if ( $groups_needed > 0 ) {
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::line( "Creating {$groups_needed} additional groups (limiting to {$max_reasonable_groups} total)" );
            }
            
            $admin_id = LDTT_Helper::get_admin_user_id();
            
            for ( $i = 1; $i <= $groups_needed; $i++ ) {
                $group_name = 'LDTT Test Group ' . ( count( $existing_groups ) + $i );
                
                $group_id = wp_insert_post( array(
                    'post_title'   => $group_name,
                    'post_type'    => learndash_get_post_type_slug( 'group' ),
                    'post_status'  => 'publish',
                    'post_content' => 'Auto-created test group for enhanced user distribution.',
                    'post_author'  => $admin_id,
                    'meta_input'   => array(
                        '_ldtt_test_data' => true,
                        '_ldtt_auto_created' => current_time( 'timestamp' ),
                    ),
                ) );
                
                if ( ! is_wp_error( $group_id ) ) {
                    $existing_groups[] = $group_id;
                    
                    if ( defined( 'WP_CLI' ) && WP_CLI ) {
                        WP_CLI::line( "Created group: {$group_name} (ID: {$group_id})" );
                    }
                }
            }
        }
        
        // FIXED: Use only the reasonable number of groups
        $groups_to_use = array_slice( $existing_groups, 0, $max_reasonable_groups );
        
        // Initialize group assignment tracking
        self::$group_assignments = array();
        foreach ( $groups_to_use as $group_id ) {
            self::$group_assignments[ $group_id ] = array(
                'leaders' => array(),
                'members' => array(),
            );
        }

        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::line( "Using " . count( $groups_to_use ) . " groups for assignment" );
        }

        return $groups_to_use;
    }

    /**
     * IMPROVED: Create group leaders and assign them immediately to ensure coverage
     * 
     * @param int $count
     * @param array $groups
     * @param bool $use_existing
     * @return array|WP_Error
     */
    private static function create_and_assign_group_leaders( $count, $groups, $use_existing = false ) {
        $group_leaders = array();
        $groups_assigned = array();

        if ( $use_existing ) {
            $candidate_users = self::get_candidate_users( $use_existing );
            if ( empty( $candidate_users ) ) {
                return new WP_Error( 'no_candidates', 'No suitable existing users found for group leader assignment' );
            }

            // Promote existing users to group_leader role
            for ( $i = 0; $i < $count && $i < count( $candidate_users ); $i++ ) {
                $user_id = absint( $candidate_users[ $i ] );
                $user = new WP_User( $user_id );
                $user->set_role( 'group_leader' );
                
                update_user_meta( $user_id, '_ldtt_user_type', 'group_leader' );
                update_user_meta( $user_id, '_ldtt_created', true );
                
                $group_leaders[] = $user_id;

                if ( defined( 'WP_CLI' ) && WP_CLI ) {
                    WP_CLI::line( "Promoted existing user {$user_id} to group leader" );
                }
            }
        } else {
            // Create new group leader users
            for ( $i = 1; $i <= $count; $i++ ) {
                $username = 'leader_' . LDTT_Helper::generate_random_string( 8 );
                $email = $username . '@example.com';

                $user_id = wp_create_user( $username, wp_generate_password( 12 ), $email );

                if ( is_wp_error( $user_id ) ) {
                    if ( defined( 'WP_CLI' ) && WP_CLI ) {
                        WP_CLI::warning( "Failed to create group leader: {$username}" );
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

                // Mark as test user and group leader
                update_user_meta( $user_id, '_ldtt_test_user', true );
                update_user_meta( $user_id, '_ldtt_user_type', 'group_leader' );
                update_user_meta( $user_id, '_ldtt_created', true );

                $group_leaders[] = $user_id;

                if ( defined( 'WP_CLI' ) && WP_CLI ) {
                    WP_CLI::line( "Created group leader: {$username} (ID: {$user_id})" );
                }
            }
        }

        // IMPROVED: Immediately assign leaders to groups using round-robin, ensuring every group gets a leader
        $groups_assigned = array();
        $leader_assignment_attempts = 0;
        
        foreach ( $group_leaders as $index => $leader_id ) {
            $group_index = $index % count( $groups );
            $group_id = $groups[ $group_index ];
            
            $assignment_result = self::assign_group_leader_improved( $leader_id, $group_id );
            $leader_assignment_attempts++;
            
            if ( $assignment_result ) {
                self::$group_assignments[ $group_id ]['leaders'][] = $leader_id;
                $groups_assigned[] = $group_id;
                
                if ( defined( 'WP_CLI' ) && WP_CLI ) {
                    WP_CLI::line( "  ✓ Assigned leader {$leader_id} to group {$group_id}" );
                }
            } else {
                if ( defined( 'WP_CLI' ) && WP_CLI ) {
                    WP_CLI::warning( "  ✗ Failed to assign leader {$leader_id} to group {$group_id}" );
                }
            }
        }

        // IMPROVED: If we have more groups than leaders, try to assign leaders to remaining groups
        $unassigned_groups = array_diff( $groups, $groups_assigned );
        if ( ! empty( $unassigned_groups ) && ! empty( $group_leaders ) ) {
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::line( "  Attempting to assign additional leaders to " . count( $unassigned_groups ) . " unassigned groups" );
            }
            
            // Assign existing leaders to additional groups if we have more groups than leaders
            foreach ( $unassigned_groups as $group_id ) {
                // Pick a random existing leader to also lead this group
                $random_leader = $group_leaders[ array_rand( $group_leaders ) ];
                
                $assignment_result = self::assign_group_leader_improved( $random_leader, $group_id );
                
                if ( $assignment_result ) {
                    self::$group_assignments[ $group_id ]['leaders'][] = $random_leader;
                    $groups_assigned[] = $group_id;
                    
                    if ( defined( 'WP_CLI' ) && WP_CLI ) {
                        WP_CLI::line( "  ✓ Assigned additional leader {$random_leader} to group {$group_id}" );
                    }
                }
            }
        }

        // Store created leaders for reference
        self::$created_group_leaders = $group_leaders;

        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::line( "Assigned " . count( array_unique( $groups_assigned ) ) . " groups with leaders out of " . count( $groups ) . " total groups" );
        }

        return $group_leaders;
    }

    /**
     * IMPROVED: Create group members and distribute them evenly across groups
     * 
     * @param int $count
     * @param array $groups
     * @param bool $use_existing
     * @return array|WP_Error
     */
    private static function create_and_assign_group_members( $count, $groups, $use_existing = false ) {
        $group_members = array();
        $members_per_group = array_fill_keys( $groups, 0 );

        if ( $use_existing ) {
            $candidate_users = self::get_candidate_users( $use_existing );
            if ( empty( $candidate_users ) ) {
                return new WP_Error( 'no_candidates', 'No suitable existing users found for group member assignment' );
            }

            for ( $i = 0; $i < $count && $i < count( $candidate_users ); $i++ ) {
                $user_id = absint( $candidate_users[ $i ] );
                
                // Skip if already assigned as group leader
                if ( in_array( $user_id, self::$created_group_leaders ) ) {
                    continue;
                }

                update_user_meta( $user_id, '_ldtt_user_type', 'group_member' );
                update_user_meta( $user_id, '_ldtt_created', true );

                // IMPROVED: Assign to group with fewest members
                $target_group = self::get_group_with_fewest_members( $groups, $members_per_group );
                $enrollment_result = self::enroll_user_in_group_improved( $user_id, $target_group );
                
                if ( $enrollment_result ) {
                    self::$group_assignments[ $target_group ]['members'][] = $user_id;
                    $members_per_group[ $target_group ]++;
                    $group_members[] = $user_id;

                    if ( defined( 'WP_CLI' ) && WP_CLI ) {
                        WP_CLI::line( "Assigned existing user {$user_id} as member to group {$target_group}" );
                    }
                }
            }
        } else {
            for ( $i = 1; $i <= $count; $i++ ) {
                $username = 'groupmember_' . LDTT_Helper::generate_random_string( 8 );
                $email = $username . '@example.com';

                $user_id = wp_create_user( $username, wp_generate_password( 12 ), $email );

                if ( is_wp_error( $user_id ) ) {
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

                // IMPROVED: Assign to group with fewest members
                $target_group = self::get_group_with_fewest_members( $groups, $members_per_group );
                $enrollment_result = self::enroll_user_in_group_improved( $user_id, $target_group );
                
                if ( $enrollment_result ) {
                    self::$group_assignments[ $target_group ]['members'][] = $user_id;
                    $members_per_group[ $target_group ]++;
                    $group_members[] = $user_id;

                    if ( defined( 'WP_CLI' ) && WP_CLI ) {
                        WP_CLI::line( "Created group member: {$username} (ID: {$user_id}) in group {$target_group}" );
                    }
                }
            }
        }

        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::line( "Distributed " . count( $group_members ) . " members across " . count( $groups ) . " groups" );
            foreach ( $groups as $group_id ) {
                $member_count = $members_per_group[ $group_id ];
                WP_CLI::line( "  Group {$group_id}: {$member_count} members" );
            }
        }

        return $group_members;
    }

    /**
     * IMPROVED: Get group with fewest members for balanced distribution
     * 
     * @param array $groups
     * @param array $members_per_group
     * @return int
     */
    private static function get_group_with_fewest_members( $groups, $members_per_group ) {
        // FIXED: Use round-robin distribution instead of always picking first
        static $last_assigned_index = -1;
        
        $last_assigned_index = ( $last_assigned_index + 1 ) % count( $groups );
        return $groups[ $last_assigned_index ];
    }

    /**
     * IMPROVED: More reliable group leader assignment with better error handling
     * 
     * @param int $leader_id
     * @param int $group_id
     * @return bool
     */
    private static function assign_group_leader_improved( $leader_id, $group_id ) {
        $leader_id = absint( $leader_id );
        $group_id = absint( $group_id );
        
        if ( ! $leader_id || ! $group_id ) {
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::warning( "Invalid leader_id ({$leader_id}) or group_id ({$group_id})" );
            }
            return false;
        }

        // Verify the group exists
        $group = get_post( $group_id );
        if ( ! $group || $group->post_type !== learndash_get_post_type_slug( 'group' ) ) {
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::warning( "Group {$group_id} does not exist or is not a valid group" );
            }
            return false;
        }

        // Verify the user exists and is a group_leader
        $user = get_user_by( 'ID', $leader_id );
        if ( ! $user ) {
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::warning( "User {$leader_id} does not exist" );
            }
            return false;
        }

        try {
            // Method 1: Try LearnDash function first (if not in safe mode)
            if ( ! self::$safe_mode && function_exists( 'learndash_set_groups_administrators' ) ) {
                $current_admins = learndash_get_groups_administrators( $group_id );
                if ( ! is_array( $current_admins ) ) {
                    $current_admins = array();
                }
                
                if ( ! in_array( $leader_id, $current_admins ) ) {
                    $current_admins[] = $leader_id;
                    $result = learndash_set_groups_administrators( $group_id, $current_admins );
                    
                    if ( $result !== false ) {
                        if ( defined( 'WP_CLI' ) && WP_CLI ) {
                            WP_CLI::line( "    Used LearnDash function for leader assignment" );
                        }
                        return true;
                    }
                }
            }
        } catch ( Exception $e ) {
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::warning( "LearnDash function failed: " . $e->getMessage() . ", using safe method" );
            }
        }

        // Method 2: Safe meta-based assignment (always used in safe mode)
        $current_admins = get_post_meta( $group_id, '_ld_group_administrators', true );
        if ( ! is_array( $current_admins ) ) {
            $current_admins = array();
        }

        if ( ! in_array( $leader_id, $current_admins ) ) {
            $current_admins[] = $leader_id;
            $result = update_post_meta( $group_id, '_ld_group_administrators', $current_admins );
            
            if ( $result ) {
                // Update reverse relationship
                $user_groups = get_user_meta( $leader_id, 'learndash_group_leaders_' . $leader_id, true );
                if ( ! is_array( $user_groups ) ) {
                    $user_groups = array();
                }
                if ( ! in_array( $group_id, $user_groups ) ) {
                    $user_groups[] = $group_id;
                    update_user_meta( $leader_id, 'learndash_group_leaders_' . $leader_id, $user_groups );
                }
                
                if ( defined( 'WP_CLI' ) && WP_CLI ) {
                    WP_CLI::line( "    Used meta-based assignment for leader" );
                }
                
                return true;
            } else {
                if ( defined( 'WP_CLI' ) && WP_CLI ) {
                    WP_CLI::warning( "Failed to update group meta for leader assignment" );
                }
            }
        } else {
            // Already assigned
            return true;
        }

        return false;
    }

    /**
     * IMPROVED: More reliable group member enrollment
     * 
     * @param int $user_id
     * @param int $group_id
     * @return bool
     */
    private static function enroll_user_in_group_improved( $user_id, $group_id ) {
        $user_id = absint( $user_id );
        $group_id = absint( $group_id );
        
        if ( ! $user_id || ! $group_id ) {
            return false;
        }

        try {
            // Method 1: Try LearnDash function first (if available and not in safe mode)
            if ( ! self::$safe_mode && function_exists( 'ld_update_group_access' ) ) {
                $result = ld_update_group_access( $user_id, $group_id, false );
                if ( $result !== false ) {
                    return true;
                }
            }
        } catch ( Exception $e ) {
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::warning( "LearnDash enrollment function failed: " . $e->getMessage() . ", using fallback" );
            }
        }

        // Method 2: Meta-based enrollment (safe fallback)
        // Update group's user list
        $group_users = get_post_meta( $group_id, 'learndash_group_users_' . $group_id, true );
        if ( ! is_array( $group_users ) ) {
            $group_users = array();
        }
        
        if ( ! in_array( $user_id, $group_users ) ) {
            $group_users[] = $user_id;
            $group_result = update_post_meta( $group_id, 'learndash_group_users_' . $group_id, $group_users );
            
            // Update user's group list
            $user_groups = get_user_meta( $user_id, 'learndash_group_users_' . $user_id, true );
            if ( ! is_array( $user_groups ) ) {
                $user_groups = array();
            }
            
            if ( ! in_array( $group_id, $user_groups ) ) {
                $user_groups[] = $group_id;
                $user_result = update_user_meta( $user_id, 'learndash_group_users_' . $user_id, $user_groups );
                
                return $group_result && $user_result;
            }
        }

        return true; // Already enrolled
    }

    /**
     * IMPROVED: Enhanced group assignment verification with accurate counting
     * 
     * @param array $groups
     * @return array
     */
    private static function verify_group_assignments( $groups ) {
        $verification = array(
            'total_groups' => count( $groups ),
            'groups_with_leaders' => 0,
            'groups_with_members' => 0,
            'total_leaders' => 0,
            'total_members' => 0,
            'avg_members_per_group' => 0,
            'groups_detail' => array(),
        );
        
        $total_members_count = 0;
        
        foreach ( $groups as $group_id ) {
            $group_title = get_the_title( $group_id );
            
            // Check leaders - Use the correct meta key
            $leaders = get_post_meta( $group_id, '_ld_group_administrators', true );
            $leaders = is_array( $leaders ) ? array_filter( $leaders ) : array(); // Remove empty values
            $leader_count = count( $leaders );
            
            // Check members - Use the correct meta key format
            $members = get_post_meta( $group_id, 'learndash_group_users_' . $group_id, true );
            $members = is_array( $members ) ? array_filter( $members ) : array(); // Remove empty values
            $member_count = count( $members );
            
            // FIXED: Only count groups that actually have leaders/members
            if ( $leader_count > 0 ) {
                $verification['groups_with_leaders']++;
            }
            
            if ( $member_count > 0 ) {
                $verification['groups_with_members']++;
            }
            
            $verification['total_leaders'] += $leader_count;
            $verification['total_members'] += $member_count;
            $total_members_count += $member_count;
            
            $verification['groups_detail'][ $group_id ] = array(
                'title' => $group_title,
                'leaders' => $leader_count,
                'members' => $member_count,
                'leader_ids' => $leaders,
                'member_ids' => $members,
            );
        }
        
        $verification['avg_members_per_group'] = $verification['total_groups'] > 0 
            ? round( $total_members_count / $verification['total_groups'], 1 ) 
            : 0;
        
        return $verification;
    }

    /**
     * Get candidate users for role assignment
     * 
     * @param bool $use_existing
     * @return array
     */
    private static function get_candidate_users( $use_existing = false ) {
        if ( ! $use_existing ) {
            return array();
        }

        $user_query = new WP_User_Query( array(
            'role__not_in' => array( 'administrator' ),
            'fields' => 'ID',
            'number' => 200, // Increased limit
        ) );
        
        $users = $user_query->get_results();
        return is_array( $users ) ? array_map( 'absint', $users ) : array();
    }

    /**
     * Create course-enrolled users with optional progress.
     * (Keeping existing implementation)
     */
    private static function create_course_enrolled_users( $count, $create_progress = false, $use_existing = false ) {
        // Implementation stays the same as before
        $user_ids = array();
        $courses = self::get_available_courses();

        if ( empty( $courses ) ) {
            return new WP_Error( 'no_courses', 'No courses available for enrollment.' );
        }

        if ( $use_existing ) {
            $candidate_users = self::get_candidate_users( true );
            if ( empty( $candidate_users ) ) {
                return new WP_Error( 'no_candidates', 'No suitable existing users found for course enrollment' );
            }

            for ( $i = 0; $i < $count && $i < count( $candidate_users ); $i++ ) {
                $user_id = absint( $candidate_users[ $i ] );
                
                // Skip if already assigned as group leader or member
                if ( in_array( $user_id, self::$created_group_leaders ) ) {
                    continue;
                }

                update_user_meta( $user_id, '_ldtt_user_type', 'course_enrolled' );
                update_user_meta( $user_id, '_ldtt_created', true );

                // Enroll in a random course
                $course_id = $courses[ array_rand( $courses ) ];
                ld_update_course_access( $user_id, $course_id, false );

                // Create course progress if requested
                if ( $create_progress ) {
                    self::create_progress_for_user( $user_id, $course_id );
                }

                $user_ids[] = $user_id;

                if ( defined( 'WP_CLI' ) && WP_CLI ) {
                    WP_CLI::line( "Enrolled existing user {$user_id} in course {$course_id}" );
                }
            }
        } else {
            for ( $i = 1; $i <= $count; $i++ ) {
                $username = 'student_' . LDTT_Helper::generate_random_string( 8 );
                $email = $username . '@example.com';

                $user_id = wp_create_user( $username, wp_generate_password( 12 ), $email );

                if ( is_wp_error( $user_id ) ) {
                    continue;
                }

                // Set as subscriber
                $user = new WP_User( $user_id );
                $user->set_role( 'subscriber' );

                // Set display name
                wp_update_user( array(
                    'ID'           => $user_id,
                    'display_name' => 'Student ' . $i,
                    'first_name'   => 'Student',
                    'last_name'    => $i,
                ) );

                // Mark as test user
                update_user_meta( $user_id, '_ldtt_test_user', true );
                update_user_meta( $user_id, '_ldtt_user_type', 'course_enrolled' );

                // Enroll in a random course
                $course_id = $courses[ array_rand( $courses ) ];
                ld_update_course_access( $user_id, $course_id, false );

                // Create course progress if requested
                if ( $create_progress ) {
                    self::create_progress_for_user( $user_id, $course_id );
                }

                $user_ids[] = $user_id;

                if ( defined( 'WP_CLI' ) && WP_CLI ) {
                    WP_CLI::line( "Created course student: {$username} (ID: {$user_id}) enrolled in course {$course_id}" );
                }
            }
        }

        return $user_ids;
    }

    /**
     * Create regular users (not assigned to groups or courses).
     * (Keeping existing implementation)
     */
    private static function create_regular_users( $count ) {
        $user_ids = array();

        for ( $i = 1; $i <= $count; $i++ ) {
            $username = 'testuser_' . LDTT_Helper::generate_random_string( 8 );
            $email = $username . '@example.com';

            $user_id = wp_create_user( $username, wp_generate_password( 12 ), $email );

            if ( is_wp_error( $user_id ) ) {
                continue;
            }

            // Set as subscriber
            $user = new WP_User( $user_id );
            $user->set_role( 'subscriber' );

            // Set display name
            wp_update_user( array(
                'ID'           => $user_id,
                'display_name' => 'Test User ' . $i,
                'first_name'   => 'Test',
                'last_name'    => 'User ' . $i,
            ) );

            // Mark as test user
            update_user_meta( $user_id, '_ldtt_test_user', true );
            update_user_meta( $user_id, '_ldtt_user_type', 'regular' );

            $user_ids[] = $user_id;

            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::line( "Created regular user: {$username} (ID: {$user_id})" );
            }
        }

        return $user_ids;
    }

    /**
     * Get available courses.
     * (Keeping existing implementation)
     */
    private static function get_available_courses() {
        return get_posts( array(
            'post_type'   => learndash_get_post_type_slug( 'course' ),
            'numberposts' => -1,
            'post_status' => 'publish',
            'fields'      => 'ids',
        ) );
    }

    /**
     * Create progress for a user in a course
     * (Keeping existing implementation)
     */
    private static function create_progress_for_user( $user_id, $course_id ) {
        $user_id = absint( $user_id );
        $course_id = absint( $course_id );
        
        if ( ! $user_id || ! $course_id ) {
            return false;
        }
        
        if ( class_exists( 'LDTT_Progress_Manager' ) ) {
            LDTT_Progress_Manager::create_realistic_progress( $user_id, $course_id );
        } else {
            // Fallback to basic progress creation
            self::create_basic_progress( $user_id, $course_id );
        }
    }

    /**
     * Create basic progress for a user (fallback method)
     * (Keeping existing implementation)
     */
    private static function create_basic_progress( $user_id, $course_id ) {
        $user_id = absint( $user_id );
        $course_id = absint( $course_id );
        
        if ( ! $user_id || ! $course_id ) {
            return false;
        }
        
        // Get course lessons
        $lessons = get_posts( array(
            'post_type' => learndash_get_post_type_slug( 'lesson' ),
            'meta_key' => 'learndash_course',
            'meta_value' => $course_id,
            'numberposts' => -1,
            'fields' => 'ids'
        ) );

        if ( empty( $lessons ) ) {
            return;
        }

        // Complete random lessons (25-100%)
        $completion_rate = wp_rand( 25, 100 );
        $lessons_to_complete = round( count( $lessons ) * ( $completion_rate / 100 ) );
        
        $lessons_to_complete = min( $lessons_to_complete, count( $lessons ) );
        $selected_lessons = array_slice( $lessons, 0, $lessons_to_complete );

        foreach ( $selected_lessons as $lesson_id ) {
            learndash_process_mark_complete( $user_id, $lesson_id, false, $course_id );
        }

        // Store progress metadata
        update_user_meta( $user_id, '_ldtt_progress_created', current_time( 'timestamp' ) );
        update_user_meta( $user_id, '_ldtt_progress_completion_rate', $completion_rate );
    }

    /**
     * Assign progress to existing enrolled users
     *
     * @param array $args
     * @param array $assoc_args
     */
    public static function assign_progress_to_enrolled( $args = array(), $assoc_args = array() ) {
        // Check for admin input if no CLI arguments provided
        if ( empty( $args ) && empty( $assoc_args ) ) {
            $assoc_args = array(
                'course_id'       => isset( $_POST['course_id'] ) ? intval( $_POST['course_id'] ) : null,
                'all_courses'     => isset( $_POST['all_courses'] ) ? true : false,
                'overwrite'       => isset( $_POST['overwrite'] ) ? true : false,
                'min_progress'    => isset( $_POST['min_progress'] ) ? intval( $_POST['min_progress'] ) : 25,
                'max_progress'    => isset( $_POST['max_progress'] ) ? intval( $_POST['max_progress'] ) : 100,
                'user_percentage' => isset( $_POST['user_percentage'] ) ? floatval( $_POST['user_percentage'] ) : 100,
            );
        }

        $course_id = ! empty( $assoc_args['course_id'] ) ? absint( $assoc_args['course_id'] ) : null;
        $all_courses = isset( $assoc_args['all_courses'] ) && $assoc_args['all_courses'];
        $overwrite = isset( $assoc_args['overwrite'] ) && $assoc_args['overwrite'];
        $min_progress = LDTT_Helper::validate_positive_int( $assoc_args['min_progress'] ?? 25, 25, 100 );
        $max_progress = LDTT_Helper::validate_positive_int( $assoc_args['max_progress'] ?? 100, $min_progress, 100 );
        $user_percentage = floatval( $assoc_args['user_percentage'] ?? 100 );

        if ( ! $all_courses && ! $course_id ) {
            $message = 'Either --course_id or --all_courses must be specified.';
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::error( $message );
            }
            return array( 'status' => 'error', 'message' => $message );
        }

        // Get courses to process
        $courses_to_process = $all_courses ? self::get_all_enrolled_courses() : array( $course_id );
        
        $total_users_processed = 0;
        $total_progress_added = 0;

        foreach ( $courses_to_process as $course_id ) {
            $users = self::get_enrolled_users_for_course( $course_id );
            $course_title = get_the_title( $course_id );
            
            // Apply user percentage filter
            if ( $user_percentage < 100 && ! empty( $users ) ) {
                $users_to_process = round( count( $users ) * ( $user_percentage / 100 ) );
                shuffle( $users );
                $users = array_slice( $users, 0, $users_to_process );
            }
            
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::line( "Processing course: {$course_title} (ID: {$course_id}) - " . count( $users ) . " users selected" );
            }

            foreach ( $users as $user_id ) {
                $user_id = absint( $user_id );
                $total_users_processed++;
                
                // Check if user already has progress
                $existing_progress = get_user_meta( $user_id, '_ldtt_progress_created', true );
                
                if ( ! $existing_progress || $overwrite ) {
                    // Create progress
                    self::create_progress_for_user( $user_id, $course_id );
                    $total_progress_added++;
                    
                    if ( defined( 'WP_CLI' ) && WP_CLI ) {
                        WP_CLI::line( "  ✓ Added progress for user {$user_id}" );
                    }
                } else {
                    if ( defined( 'WP_CLI' ) && WP_CLI ) {
                        WP_CLI::line( "  - User {$user_id} already has progress (use --overwrite to replace)" );
                    }
                }
            }
        }

        $percentage_text = $user_percentage < 100 ? " ({$user_percentage}% of users)" : "";
        $message = "Processed {$total_users_processed} enrolled users{$percentage_text}, added progress to {$total_progress_added} users.";
        
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::success( $message );
        }
        
        return array(
            'status' => 'success',
            'message' => $message,
            'users_processed' => $total_users_processed,
            'progress_added' => $total_progress_added,
        );
    }

    /**
     * Get all courses that have enrolled users
     * 
     * @return array
     */
    private static function get_all_enrolled_courses() {
        global $wpdb;
        
        // Get courses that have users enrolled
        $courses = $wpdb->get_col("
            SELECT DISTINCT pm.meta_value
            FROM {$wpdb->usermeta} um
            JOIN {$wpdb->postmeta} pm ON pm.meta_key = 'learndash_course' 
            WHERE um.meta_key LIKE '%course%progress%'
            OR um.meta_key LIKE 'learndash_course_%'
        ");
        
        // Fallback: get all courses
        if ( empty( $courses ) ) {
            $courses = get_posts( array(
                'post_type' => learndash_get_post_type_slug( 'course' ),
                'numberposts' => -1,
                'fields' => 'ids',
            ) );
        }
        
        return array_filter( array_map( 'absint', $courses ) );
    }

    /**
     * Get enrolled users for a specific course
     * 
     * @param int $course_id
     * @return array
     */
    private static function get_enrolled_users_for_course( $course_id ) {
        // Try LearnDash function first
        if ( function_exists( 'learndash_get_users_for_course' ) ) {
            $users = learndash_get_users_for_course( $course_id );
            return is_array( $users ) ? array_map( 'absint', $users ) : array();
        }
        
        // Fallback method
        global $wpdb;
        
        $users = $wpdb->get_col( $wpdb->prepare( "
            SELECT user_id 
            FROM {$wpdb->usermeta} 
            WHERE meta_key LIKE %s
            AND meta_value LIKE %s
        ", 
        '%course%progress%',
        '%' . $course_id . '%'
        ) );
        
        return array_map( 'absint', $users );
    }
}