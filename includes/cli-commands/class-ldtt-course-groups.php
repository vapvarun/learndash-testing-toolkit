<?php

/**
 * Simplified Course Groups Command - Clean and Functional
 * Creates groups with guaranteed group leaders and members
 */
class LDTT_Course_Groups {

    /**
     * Handle the command to create course groups
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
            );
        }

        $group_count = LDTT_Helper::validate_positive_int( $assoc_args['count'] ?? 3, 3, 20 );
        $group_prefix = sanitize_text_field( $assoc_args['prefix'] ?? 'Test Group' );
        $course_ids = ! empty( $assoc_args['courses'] ) ? array_map( 'absint', explode( ',', $assoc_args['courses'] ) ) : array();

        // Create the groups
        $group_ids = self::create_course_groups( $group_prefix, $group_count, $course_ids );
        if ( is_wp_error( $group_ids ) ) {
            $message = $group_ids->get_error_message();
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::error( $message );
            }
            return array( 'status' => 'error', 'message' => $message );
        }

        // Assign members and leaders to each group
        $assignment_results = self::assign_members_and_leaders( $group_ids );

        $message = "{$group_count} course groups created successfully with members and leaders assigned.";
        
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::success( $message );
            WP_CLI::line( 'Group IDs: ' . implode( ', ', $group_ids ) );
            WP_CLI::line( "Assignment summary:" );
            WP_CLI::line( "- Groups with leaders: {$assignment_results['groups_with_leaders']}/{$group_count}" );
            WP_CLI::line( "- Groups with members: {$assignment_results['groups_with_members']}/{$group_count}" );
            WP_CLI::line( "- Total members assigned: {$assignment_results['total_members']}" );
        }

        return array( 
            'status' => 'success', 
            'message' => $message, 
            'group_ids' => $group_ids,
            'assignment_results' => $assignment_results,
        );
    }

    /**
     * Create course groups
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

            // Associate courses with the group if provided
            if ( ! empty( $course_ids ) ) {
                $valid_course_ids = self::validate_course_ids( $course_ids );
                if ( ! empty( $valid_course_ids ) ) {
                    self::associate_courses_with_group( $group_id, $valid_course_ids );
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
     * Assign members and leaders to groups
     * Simple approach: get existing users and assign them
     *
     * @param array $group_ids Array of group IDs
     * @return array Assignment results
     */
    private static function assign_members_and_leaders( $group_ids ) {
        $results = array(
            'groups_with_leaders' => 0,
            'groups_with_members' => 0,
            'total_members' => 0,
        );

        // Get existing users (excluding administrators)
        $available_users = get_users( array(
            'role__not_in' => array( 'administrator' ),
            'fields' => 'ID',
            'number' => 100, // Get up to 100 users
        ) );

        if ( empty( $available_users ) ) {
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::warning( 'No existing users found to assign to groups' );
            }
            return $results;
        }

        // Convert to integers and shuffle for random assignment
        $user_ids = array_map( 'absint', $available_users );
        shuffle( $user_ids );

        // Get existing group leaders (users with group_leader role)
        $group_leaders = get_users( array(
            'role' => 'group_leader',
            'fields' => 'ID',
            'number' => 50,
        ) );

        $leader_ids = array_map( 'absint', $group_leaders );
        
        // If no group leaders exist, promote some regular users
        if ( empty( $leader_ids ) ) {
            $leader_ids = self::create_group_leaders_from_users( array_slice( $user_ids, 0, count( $group_ids ) ) );
        }

        $leader_index = 0;
        $user_index = 0;

        foreach ( $group_ids as $group_id ) {
            $group_title = get_the_title( $group_id );

            // Assign one leader per group
            if ( ! empty( $leader_ids ) ) {
                $leader_id = $leader_ids[ $leader_index % count( $leader_ids ) ];
                if ( self::assign_group_leader( $leader_id, $group_id ) ) {
                    $results['groups_with_leaders']++;
                    if ( defined( 'WP_CLI' ) && WP_CLI ) {
                        WP_CLI::line( "  ✓ Assigned leader {$leader_id} to group: {$group_title}" );
                    }
                }
                $leader_index++;
            }

            // Assign 3-5 members per group
            $members_per_group = wp_rand( 3, 5 );
            $members_assigned = 0;
            
            for ( $j = 0; $j < $members_per_group && $user_index < count( $user_ids ); $j++ ) {
                $user_id = $user_ids[ $user_index ];
                
                // Skip if this user is already a group leader
                if ( in_array( $user_id, $leader_ids ) ) {
                    $user_index++;
                    continue;
                }
                
                if ( self::enroll_user_in_group( $user_id, $group_id ) ) {
                    $members_assigned++;
                    $results['total_members']++;
                }
                
                $user_index++;
            }

            if ( $members_assigned > 0 ) {
                $results['groups_with_members']++;
                if ( defined( 'WP_CLI' ) && WP_CLI ) {
                    WP_CLI::line( "  ✓ Assigned {$members_assigned} members to group: {$group_title}" );
                }
            }
        }

        return $results;
    }

    /**
     * Create group leaders from existing users
     *
     * @param array $user_ids
     * @return array
     */
    private static function create_group_leaders_from_users( $user_ids ) {
        $leader_ids = array();

        foreach ( $user_ids as $user_id ) {
            $user = new WP_User( $user_id );
            $user->set_role( 'group_leader' );
            
            // Update display name
            wp_update_user( array(
                'ID' => $user_id,
                'display_name' => 'Group Leader ' . count( $leader_ids ) + 1,
            ) );
            
            $leader_ids[] = $user_id;
            
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::line( "Promoted user {$user_id} to group leader" );
            }
        }

        return $leader_ids;
    }

    /**
     * Assign a group leader using LearnDash functions
     *
     * @param int $leader_id User ID of the leader
     * @param int $group_id Group ID
     * @return bool Success status
     */
    private static function assign_group_leader( $leader_id, $group_id ) {
        // Use LearnDash function if available
        if ( function_exists( 'learndash_get_groups_administrators' ) && function_exists( 'learndash_set_groups_administrators' ) ) {
            $current_admins = learndash_get_groups_administrators( $group_id );
            if ( ! is_array( $current_admins ) ) {
                $current_admins = array();
            }
            
            $current_admins = array_map( 'intval', $current_admins );
            
            if ( ! in_array( $leader_id, $current_admins ) ) {
                $current_admins[] = $leader_id;
                return learndash_set_groups_administrators( $group_id, $current_admins ) !== false;
            }
            return true; // Already assigned
        }
        
        // Fallback method
        $current_admins = get_post_meta( $group_id, '_ld_group_administrators', true );
        if ( ! is_array( $current_admins ) ) {
            $current_admins = array();
        }
        
        $current_admins = array_map( 'intval', $current_admins );
        
        if ( ! in_array( $leader_id, $current_admins ) ) {
            $current_admins[] = $leader_id;
            return update_post_meta( $group_id, '_ld_group_administrators', $current_admins );
        }
        
        return true;
    }

    /**
     * Enroll a user in a group using LearnDash functions
     *
     * @param int $user_id User ID
     * @param int $group_id Group ID
     * @return bool Success status
     */
    private static function enroll_user_in_group( $user_id, $group_id ) {
        // Use LearnDash function if available
        if ( function_exists( 'learndash_get_groups_users' ) && function_exists( 'learndash_set_groups_users' ) ) {
            $current_members = learndash_get_groups_users( $group_id );
            if ( ! is_array( $current_members ) ) {
                $current_members = array();
            }
            
            // Handle both user IDs and WP_User objects
            $member_ids = array();
            foreach ( $current_members as $member ) {
                if ( is_object( $member ) && isset( $member->ID ) ) {
                    $member_ids[] = intval( $member->ID );
                } elseif ( is_numeric( $member ) ) {
                    $member_ids[] = intval( $member );
                }
            }
            
            if ( ! in_array( $user_id, $member_ids ) ) {
                $member_ids[] = $user_id;
                return learndash_set_groups_users( $group_id, $member_ids ) !== false;
            }
            return true; // Already enrolled
        }
        
        // Fallback method
        $group_users = get_post_meta( $group_id, 'learndash_group_users_' . $group_id, true );
        if ( ! is_array( $group_users ) ) {
            $group_users = array();
        }
        
        $group_users = array_map( 'intval', $group_users );
        
        if ( ! in_array( $user_id, $group_users ) ) {
            $group_users[] = $user_id;
            return update_post_meta( $group_id, 'learndash_group_users_' . $group_id, $group_users );
        }
        
        return true;
    }

    /**
     * Validate course IDs
     *
     * @param array $course_ids
     * @return array
     */
    private static function validate_course_ids( $course_ids ) {
        $valid_ids = array();
        
        foreach ( $course_ids as $course_id ) {
            if ( is_numeric( $course_id ) && get_post_type( $course_id ) === learndash_get_post_type_slug( 'course' ) ) {
                $valid_ids[] = intval( $course_id );
            }
        }
        
        return $valid_ids;
    }

    /**
     * Associate courses with group
     *
     * @param int $group_id
     * @param array $course_ids
     */
    private static function associate_courses_with_group( $group_id, $course_ids ) {
        if ( function_exists( 'learndash_set_group_enrolled_courses' ) ) {
            learndash_set_group_enrolled_courses( $group_id, $course_ids );
        } else {
            // Fallback method
            update_post_meta( $group_id, 'learndash_group_enrolled_' . $group_id, $course_ids );
        }
    }
}