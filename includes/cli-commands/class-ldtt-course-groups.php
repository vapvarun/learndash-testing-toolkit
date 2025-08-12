<?php

/**
 * Enhanced Course Groups Command - Complete Group Setup
 * Creates groups with guaranteed group leaders, members, and course assignments
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
                'count'                     => isset( $_POST['count'] ) ? intval( $_POST['count'] ) : 25,
                'prefix'                    => isset( $_POST['prefix'] ) ? sanitize_text_field( $_POST['prefix'] ) : 'Learning Group',
                'courses'                   => isset( $_POST['courses'] ) ? sanitize_text_field( $_POST['courses'] ) : '',
                'min_courses_per_group'     => isset( $_POST['min_courses_per_group'] ) ? intval( $_POST['min_courses_per_group'] ) : 1,
                'max_courses_per_group'     => isset( $_POST['max_courses_per_group'] ) ? intval( $_POST['max_courses_per_group'] ) : 2,
                'members_per_group'         => isset( $_POST['members_per_group'] ) ? intval( $_POST['members_per_group'] ) : 5,
                'create_leaders_if_needed'  => isset( $_POST['create_leaders_if_needed'] ) ? true : false,
                'create_members_if_needed'  => isset( $_POST['create_members_if_needed'] ) ? true : false,
                'use_existing_only'         => isset( $_POST['use_existing_only'] ) ? true : true, // Default to true
            );
        }

        $group_count = LDTT_Helper::validate_positive_int( $assoc_args['count'] ?? 25, 1, 50 );
        $group_prefix = sanitize_text_field( $assoc_args['prefix'] ?? 'Learning Group' );
        $specific_course_ids = ! empty( $assoc_args['courses'] ) ? array_map( 'absint', explode( ',', $assoc_args['courses'] ) ) : array();
        $min_courses = LDTT_Helper::validate_positive_int( $assoc_args['min_courses_per_group'] ?? 1, 1, 10 );
        $max_courses = LDTT_Helper::validate_positive_int( $assoc_args['max_courses_per_group'] ?? 2, $min_courses, 10 );
        $members_per_group = LDTT_Helper::validate_positive_int( $assoc_args['members_per_group'] ?? 5, 1, 20 );
        $create_leaders_if_needed = isset( $assoc_args['create_leaders_if_needed'] ) && $assoc_args['create_leaders_if_needed'];
        $create_members_if_needed = isset( $assoc_args['create_members_if_needed'] ) && $assoc_args['create_members_if_needed'];
        $use_existing_only = isset( $assoc_args['use_existing_only'] ) ? $assoc_args['use_existing_only'] : true;

        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::line( "Creating {$group_count} groups with complete setup using existing users..." );
            WP_CLI::line( "- Group Prefix: {$group_prefix}" );
            WP_CLI::line( "- Courses per Group: {$min_courses}-{$max_courses}" );
            WP_CLI::line( "- Members per Group: {$members_per_group}" );
            WP_CLI::line( "- Use Existing Users Only: " . ( $use_existing_only ? 'Yes' : 'No' ) );
            WP_CLI::line( "" );
        }

        // Get available courses for assignment
        $available_courses = self::get_available_courses( $specific_course_ids );
        if ( empty( $available_courses ) ) {
            $message = "No courses available for group assignment. Please create courses first.";
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::error( $message );
            }
            return array( 'status' => 'error', 'message' => $message );
        }

        // Create the groups
        $group_ids = self::create_course_groups( $group_prefix, $group_count );
        if ( is_wp_error( $group_ids ) ) {
            $message = $group_ids->get_error_message();
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::error( $message );
            }
            return array( 'status' => 'error', 'message' => $message );
        }

        // Setup each group completely
        $setup_results = self::setup_groups_completely( 
            $group_ids, 
            $available_courses, 
            $min_courses, 
            $max_courses, 
            $members_per_group, 
            $use_existing_only ? false : $create_leaders_if_needed, 
            $use_existing_only ? false : $create_members_if_needed 
        );

        $message = "Successfully created {$group_count} groups with complete setup:";
        $message .= "\n- Groups with Leaders: {$setup_results['groups_with_leaders']}/{$group_count}";
        $message .= "\n- Groups with Courses: {$setup_results['groups_with_courses']}/{$group_count}";
        $message .= "\n- Groups with Members: {$setup_results['groups_with_members']}/{$group_count}";
        $message .= "\n- Total Leaders Created/Assigned: {$setup_results['total_leaders']}";
        $message .= "\n- Total Members Assigned: {$setup_results['total_members']}";
        $message .= "\n- Total Course Assignments: {$setup_results['total_course_assignments']}";
        
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::success( $message );
            WP_CLI::line( 'Group IDs: ' . implode( ', ', $group_ids ) );
        }

        return array( 
            'status' => 'success', 
            'message' => $message, 
            'group_ids' => $group_ids,
            'setup_results' => $setup_results,
        );
    }

    /**
     * Create course groups
     *
     * @param string $group_prefix The prefix for the group titles.
     * @param int $group_count The number of groups to create.
     * @return array|WP_Error An array of group IDs on success, WP_Error on failure.
     */
    private static function create_course_groups( $group_prefix, $group_count ) {
        $group_ids = array();
        
        // Get an author ID with fallback to current user
        $admin_id = LDTT_Helper::get_author_id();
        if ( ! $admin_id ) {
            // This should never happen with the new helper, but just in case
            $admin_id = 1;
        }

        for ( $i = 1; $i <= $group_count; $i++ ) {
            $group_title = $group_prefix . ' ' . $i;
            $group_content = "This is {$group_title} created by LearnDash Testing Toolkit with complete setup including leaders, members, and course assignments.";

            $group_id = wp_insert_post( array(
                'post_title'   => $group_title,
                'post_type'    => learndash_get_post_type_slug( 'group' ),
                'post_status'  => 'publish',
                'post_content' => $group_content,
                'post_author'  => $admin_id,
                'meta_input'   => array(
                    '_ldtt_test_data' => true,
                    '_ldtt_created_time' => current_time( 'timestamp' ),
                ),
            ) );

            if ( is_wp_error( $group_id ) ) {
                if ( defined( 'WP_CLI' ) && WP_CLI ) {
                    WP_CLI::warning( "Failed to create group: {$group_title}" );
                }
                continue;
            }

            $group_ids[] = $group_id;

            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::line( "Created group: {$group_title} (ID: {$group_id})" );
            }
        }

        return $group_ids;
    }

    /**
     * Setup groups completely with leaders, courses, and members
     *
     * @param array $group_ids Array of group IDs
     * @param array $available_courses Available course IDs
     * @param int $min_courses Minimum courses per group
     * @param int $max_courses Maximum courses per group
     * @param int $members_per_group Number of members per group
     * @param bool $create_leaders_if_needed Create leaders if not enough exist
     * @param bool $create_members_if_needed Create members if not enough exist
     * @return array Setup results
     */
    private static function setup_groups_completely( $group_ids, $available_courses, $min_courses, $max_courses, $members_per_group, $create_leaders_if_needed, $create_members_if_needed ) {
        $results = array(
            'groups_with_leaders' => 0,
            'groups_with_courses' => 0,
            'groups_with_members' => 0,
            'total_leaders' => 0,
            'total_members' => 0,
            'total_course_assignments' => 0,
        );

        // Get or create group leaders
        $available_leaders = self::get_or_create_group_leaders( count( $group_ids ), $create_leaders_if_needed );
        
        // Get available users for members
        $available_members = self::get_available_members( count( $group_ids ) * $members_per_group, $create_members_if_needed );

        $member_index = 0;
        $leader_index = 0;

        foreach ( $group_ids as $group_id ) {
            $group_title = get_the_title( $group_id );
            
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::line( "\nSetting up group: {$group_title} (ID: {$group_id})" );
            }

            // 1. Assign Group Leader
            if ( ! empty( $available_leaders ) ) {
                $leader_id = $available_leaders[ $leader_index % count( $available_leaders ) ];
                if ( self::assign_group_leader( $leader_id, $group_id ) ) {
                    $results['groups_with_leaders']++;
                    $results['total_leaders']++;
                    
                    if ( defined( 'WP_CLI' ) && WP_CLI ) {
                        $leader = get_user_by( 'ID', $leader_id );
                        WP_CLI::line( "  ✓ Assigned leader: {$leader->user_login} (ID: {$leader_id})" );
                    }
                }
                $leader_index++;
            }

            // 2. Assign Courses
            $courses_for_group = wp_rand( $min_courses, $max_courses );
            $assigned_courses = self::assign_courses_to_group( $group_id, $available_courses, $courses_for_group );
            
            if ( ! empty( $assigned_courses ) ) {
                $results['groups_with_courses']++;
                $results['total_course_assignments'] += count( $assigned_courses );
                
                if ( defined( 'WP_CLI' ) && WP_CLI ) {
                    WP_CLI::line( "  ✓ Assigned " . count( $assigned_courses ) . " courses" );
                }
            }

            // 3. Assign Members
            $members_assigned = 0;
            for ( $j = 0; $j < $members_per_group && $member_index < count( $available_members ); $j++ ) {
                $member_id = $available_members[ $member_index ];
                
                // Skip if this user is already a group leader
                if ( in_array( $member_id, $available_leaders ) ) {
                    $member_index++;
                    continue;
                }
                
                if ( self::enroll_user_in_group( $member_id, $group_id ) ) {
                    $members_assigned++;
                    $results['total_members']++;
                    
                    // Enroll member in group courses
                    foreach ( $assigned_courses as $course_id ) {
                        ld_update_course_access( $member_id, $course_id, false );
                    }
                }
                
                $member_index++;
            }

            if ( $members_assigned > 0 ) {
                $results['groups_with_members']++;
                
                if ( defined( 'WP_CLI' ) && WP_CLI ) {
                    WP_CLI::line( "  ✓ Assigned {$members_assigned} members" );
                }
            }
        }

        return $results;
    }

    /**
     * Get or create group leaders - Modified to prioritize existing users
     *
     * @param int $needed_count Number of leaders needed
     * @param bool $create_if_needed Create leaders if not enough exist
     * @return array Array of leader user IDs
     */
    private static function get_or_create_group_leaders( $needed_count, $create_if_needed ) {
        // First, get existing group leaders
        $existing_leaders = get_users( array(
            'role' => 'group_leader',
            'fields' => 'ID',
            'number' => $needed_count * 3, // Get more for variety
        ) );

        $leader_ids = array_map( 'absint', $existing_leaders );

        // If we don't have enough existing group leaders, promote regular users
        if ( count( $leader_ids ) < $needed_count ) {
            $needed_promotions = $needed_count - count( $leader_ids );
            $promoted_leaders = self::promote_users_to_leaders( $needed_promotions );
            $leader_ids = array_merge( $leader_ids, $promoted_leaders );
            
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::line( "Promoted " . count( $promoted_leaders ) . " existing users to group leaders" );
            }
        }

        // Only create new users if specifically requested and still not enough
        if ( count( $leader_ids ) < $needed_count && $create_if_needed ) {
            $needed_new = $needed_count - count( $leader_ids );
            $new_leaders = self::create_group_leaders( $needed_new );
            $leader_ids = array_merge( $leader_ids, $new_leaders );
            
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::line( "Created " . count( $new_leaders ) . " new group leaders" );
            }
        }

        shuffle( $leader_ids );
        return array_slice( $leader_ids, 0, $needed_count );
    }

    /**
     * Create new group leaders
     *
     * @param int $count Number of leaders to create
     * @return array Array of created leader user IDs
     */
    private static function create_group_leaders( $count ) {
        $created_leaders = array();

        for ( $i = 1; $i <= $count; $i++ ) {
            $username = 'groupleader_' . LDTT_Helper::generate_random_string( 8 );
            $email = $username . '@example.com';

            $user_id = wp_create_user( $username, wp_generate_password( 12 ), $email );

            if ( is_wp_error( $user_id ) ) {
                continue;
            }

            $user = new WP_User( $user_id );
            $user->set_role( 'group_leader' );

            wp_update_user( array(
                'ID'           => $user_id,
                'display_name' => 'Group Leader ' . $i,
                'first_name'   => 'Leader',
                'last_name'    => $i,
            ) );

            update_user_meta( $user_id, '_ldtt_test_user', true );
            update_user_meta( $user_id, '_ldtt_user_type', 'group_leader' );

            $created_leaders[] = $user_id;

            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::line( "Created group leader: {$username} (ID: {$user_id})" );
            }
        }

        return $created_leaders;
    }

    /**
     * Promote existing users to group leaders
     *
     * @param int $count Number of users to promote
     * @return array Array of promoted user IDs
     */
    private static function promote_users_to_leaders( $count ) {
        $users_to_promote = get_users( array(
            'role__not_in' => array( 'administrator', 'group_leader' ),
            'fields' => 'ID',
            'number' => $count,
        ) );

        $promoted_leaders = array();

        foreach ( $users_to_promote as $user_id ) {
            $user = new WP_User( $user_id );
            $user->set_role( 'group_leader' );
            
            // Update display name
            wp_update_user( array(
                'ID' => $user_id,
                'display_name' => 'Group Leader: ' . $user->display_name,
            ) );
            
            update_user_meta( $user_id, '_ldtt_promoted_to_leader', true );
            update_user_meta( $user_id, '_ldtt_user_type', 'group_leader' );
            
            $promoted_leaders[] = $user_id;
            
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::line( "Promoted user {$user_id} to group leader" );
            }
        }

        return $promoted_leaders;
    }

    /**
     * Get available members for groups - Modified to use existing users only
     *
     * @param int $needed_count Number of members needed
     * @param bool $create_if_needed Create members if not enough exist
     * @return array Array of member user IDs
     */
    private static function get_available_members( $needed_count, $create_if_needed ) {
        // Get existing users (excluding administrators and group leaders)
        $existing_members = get_users( array(
            'role__not_in' => array( 'administrator', 'group_leader' ),
            'fields' => 'ID',
            'number' => $needed_count * 3, // Get more for variety and reuse
        ) );

        $member_ids = array_map( 'absint', $existing_members );

        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::line( "Found " . count( $member_ids ) . " existing users available for group membership" );
            
            if ( count( $member_ids ) < $needed_count ) {
                WP_CLI::line( "Note: Will reuse existing users across multiple groups" );
            }
        }

        // Only create new users if specifically requested and still not enough
        if ( count( $member_ids ) < $needed_count && $create_if_needed ) {
            $needed_new = $needed_count - count( $member_ids );
            $new_members = self::create_group_members( $needed_new );
            $member_ids = array_merge( $member_ids, $new_members );
            
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::line( "Created " . count( $new_members ) . " new members" );
            }
        }

        shuffle( $member_ids );
        
        // If we don't have enough users, we'll cycle through available ones
        if ( count( $member_ids ) < $needed_count && ! $create_if_needed ) {
            // Duplicate the array to allow reuse
            $cycles_needed = ceil( $needed_count / count( $member_ids ) );
            $expanded_ids = array();
            
            for ( $i = 0; $i < $cycles_needed; $i++ ) {
                $expanded_ids = array_merge( $expanded_ids, $member_ids );
            }
            
            shuffle( $expanded_ids );
            return array_slice( $expanded_ids, 0, $needed_count );
        }
        
        return array_slice( $member_ids, 0, $needed_count );
    }

    /**
     * Create new group members
     *
     * @param int $count Number of members to create
     * @return array Array of created member user IDs
     */
    private static function create_group_members( $count ) {
        $created_members = array();

        for ( $i = 1; $i <= $count; $i++ ) {
            $username = 'groupmember_' . LDTT_Helper::generate_random_string( 8 );
            $email = $username . '@example.com';

            $user_id = wp_create_user( $username, wp_generate_password( 12 ), $email );

            if ( is_wp_error( $user_id ) ) {
                continue;
            }

            $user = new WP_User( $user_id );
            $user->set_role( 'subscriber' );

            wp_update_user( array(
                'ID'           => $user_id,
                'display_name' => 'Group Member ' . $i,
                'first_name'   => 'Member',
                'last_name'    => $i,
            ) );

            update_user_meta( $user_id, '_ldtt_test_user', true );
            update_user_meta( $user_id, '_ldtt_user_type', 'group_member' );

            $created_members[] = $user_id;
        }

        return $created_members;
    }

    /**
     * Get available courses for group assignment
     *
     * @param array $specific_course_ids Specific course IDs if provided
     * @return array Array of course IDs
     */
    private static function get_available_courses( $specific_course_ids = array() ) {
        if ( ! empty( $specific_course_ids ) ) {
            $valid_course_ids = array();
            
            foreach ( $specific_course_ids as $course_id ) {
                if ( get_post_type( $course_id ) === learndash_get_post_type_slug( 'course' ) ) {
                    $valid_course_ids[] = $course_id;
                }
            }
            
            return $valid_course_ids;
        }

        // Get all published courses
        $courses = get_posts( array(
            'post_type'   => learndash_get_post_type_slug( 'course' ),
            'numberposts' => -1,
            'post_status' => 'publish',
            'fields'      => 'ids',
        ) );

        return $courses;
    }

    /**
     * Assign courses to a group
     *
     * @param int $group_id Group ID
     * @param array $available_courses Array of available course IDs
     * @param int $courses_count Number of courses to assign
     * @return array Array of assigned course IDs
     */
    private static function assign_courses_to_group( $group_id, $available_courses, $courses_count ) {
        if ( empty( $available_courses ) ) {
            return array();
        }

        // Randomly select courses
        $courses_to_assign = array_rand( 
            array_flip( $available_courses ), 
            min( $courses_count, count( $available_courses ) ) 
        );

        // Ensure it's an array even if only one course
        if ( ! is_array( $courses_to_assign ) ) {
            $courses_to_assign = array( $courses_to_assign );
        }

        // Assign courses to group using standard LearnDash function
        if ( function_exists( 'learndash_set_group_enrolled_courses' ) ) {
            learndash_set_group_enrolled_courses( $group_id, $courses_to_assign );
        } else {
            // Fallback method
            update_post_meta( $group_id, 'learndash_group_enrolled_' . $group_id, $courses_to_assign );
        }

        return $courses_to_assign;
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
}