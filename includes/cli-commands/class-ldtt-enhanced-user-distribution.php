<?php

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
     * Handle the command to create users with specific distribution and progress.
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
                'safe_group_creation' => isset( $_POST['safe_group_creation'] ) ? true : true, // Default to safe mode
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

        // Pre-flight validation
        $validation_result = self::validate_group_leader_requirements( $total_users, $group_leader_percent );
        if ( is_wp_error( $validation_result ) ) {
            $message = $validation_result->get_error_message();
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::error( $message );
            }
            return array( 'status' => 'error', 'message' => $message );
        }

        // Calculate user counts
        $group_leader_count = max( 1, round( $total_users * ( $group_leader_percent / 100 ) ) );
        $group_member_count = max( 1, round( $total_users * ( $group_member_percent / 100 ) ) );
        $course_enrolled_count = max( 1, round( $total_users * ( $course_enrolled_percent / 100 ) ) );

        $results = array();

        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::line( "Starting enhanced user distribution with safe mode: " . ( self::$safe_mode ? 'enabled' : 'disabled' ) );
        }

        // Phase 1: Create group leaders first (safest approach)
        $results['group_leaders'] = self::create_group_leaders_safely( $group_leader_count, $use_existing );
        if ( is_wp_error( $results['group_leaders'] ) ) {
            return array( 'status' => 'error', 'message' => $results['group_leaders']->get_error_message() );
        }

        // Phase 2: Create group members
        $results['group_members'] = self::create_group_members( $group_member_count, $use_existing );
        if ( is_wp_error( $results['group_members'] ) ) {
            return array( 'status' => 'error', 'message' => $results['group_members']->get_error_message() );
        }

        // Phase 3: Create course-enrolled users
        $results['course_enrolled'] = self::create_course_enrolled_users( $course_enrolled_count, $create_progress, $use_existing );
        if ( is_wp_error( $results['course_enrolled'] ) ) {
            return array( 'status' => 'error', 'message' => $results['course_enrolled']->get_error_message() );
        }

        // Phase 4: Create remaining regular users (only if creating new users)
        $remaining_users = $total_users - $group_leader_count - $group_member_count - $course_enrolled_count;
        if ( $remaining_users > 0 && ! $use_existing ) {
            $results['regular_users'] = self::create_regular_users( $remaining_users );
        }

        // Phase 5: Assign group leaders to groups safely
        if ( ! empty( self::$created_group_leaders ) ) {
            $assignment_result = self::assign_leaders_to_groups_safely( self::$created_group_leaders );
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::line( "Group leader assignment result: " . ( $assignment_result ? 'success' : 'partial' ) );
            }
        }

        // Phase 6: Verification
        $verification = self::verify_group_leader_assignments();

        $message = "Successfully processed {$total_users} users with distribution: " .
                   "{$group_leader_count} group leaders, {$group_member_count} group members, " .
                   "{$course_enrolled_count} course-enrolled users" .
                   ( $remaining_users > 0 ? ", {$remaining_users} regular users" : "" ) .
                   ". Group assignment success rate: {$verification['success_rate']}%";

        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::success( $message );
            WP_CLI::line( "Verification: {$verification['total_leaders']} leaders created, {$verification['groups_with_leaders']} groups have leaders" );
        }

        return array( 
            'status' => 'success', 
            'message' => $message, 
            'results' => $results,
            'verification' => $verification
        );
    }

    /**
     * Validate group leader requirements before creation
     * 
     * @param int $total_users
     * @param float $group_leader_percent
     * @return bool|WP_Error
     */
    private static function validate_group_leader_requirements( $total_users, $group_leader_percent ) {
        // Check if we have any groups to assign leaders to
        $groups = get_posts( array(
            'post_type' => learndash_get_post_type_slug( 'group' ),
            'numberposts' => 1,
            'fields' => 'ids'
        ) );

        if ( empty( $groups ) ) {
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::warning( "No groups found. Will create test groups for leader assignment." );
            }
        }

        // Validate percentage
        if ( $group_leader_percent < 0.1 || $group_leader_percent > 50 ) {
            return new WP_Error( 'invalid_percentage', 'Group leader percentage must be between 0.1% and 50%' );
        }

        return true;
    }

    /**
     * Create group leaders safely using pre-creation strategy
     * 
     * @param int $count
     * @param bool $use_existing
     * @return array|WP_Error
     */
    private static function create_group_leaders_safely( $count, $use_existing = false ) {
        $group_leaders = array();

        if ( $use_existing ) {
            // Get candidate users from existing users
            $candidate_users = self::get_candidate_users( $use_existing );
            if ( empty( $candidate_users ) ) {
                return new WP_Error( 'no_candidates', 'No suitable existing users found for group leader assignment' );
            }

            // Promote existing users to group_leader role
            for ( $i = 0; $i < $count && $i < count( $candidate_users ); $i++ ) {
                $user_id = $candidate_users[ $i ];
                $user = new WP_User( $user_id );
                $user->set_role( 'group_leader' );
                
                // Mark as LDTT managed
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

                // Set as group leader role (safe)
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

        // Store created leaders for later assignment
        self::$created_group_leaders = $group_leaders;

        return $group_leaders;
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

        $users = get_users( array(
            'role__not_in' => array( 'administrator' ),
            'fields' => 'ID',
            'number' => 100, // Limit to prevent memory issues
        ) );

        return array_map( 'intval', $users );
    }

    /**
     * Assign group leaders to groups safely
     * 
     * @param array $group_leader_ids
     * @return bool
     */
    private static function assign_leaders_to_groups_safely( $group_leader_ids ) {
        if ( empty( $group_leader_ids ) ) {
            return false;
        }

        // Get or create groups
        $groups = self::get_or_create_test_groups( max( 3, count( $group_leader_ids ) ) );
        
        if ( empty( $groups ) ) {
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::warning( "No groups available for leader assignment" );
            }
            return false;
        }

        $assignments_successful = 0;

        foreach ( $groups as $index => $group_id ) {
            $leader_index = $index % count( $group_leader_ids );
            $leader_id = $group_leader_ids[ $leader_index ];
            
            if ( self::$safe_mode ) {
                // Use safe assignment method
                $result = self::assign_group_leader_safely( $leader_id, $group_id );
            } else {
                // Try LearnDash function with fallback
                $result = self::assign_group_leader_with_fallback( $leader_id, $group_id );
            }

            if ( $result ) {
                $assignments_successful++;
                if ( defined( 'WP_CLI' ) && WP_CLI ) {
                    WP_CLI::line( "  ✓ Assigned leader {$leader_id} to group {$group_id}" );
                }
            } else {
                if ( defined( 'WP_CLI' ) && WP_CLI ) {
                    WP_CLI::warning( "  ✗ Failed to assign leader {$leader_id} to group {$group_id}" );
                }
            }
        }

        return $assignments_successful > 0;
    }

    /**
     * Assign group leader safely using direct meta update
     * 
     * @param int $leader_id
     * @param int $group_id
     * @return bool
     */
    private static function assign_group_leader_safely( $leader_id, $group_id ) {
        // Use direct meta update instead of LearnDash function
        $current_admins = get_post_meta( $group_id, '_ld_group_administrators', true );
        if ( ! is_array( $current_admins ) ) {
            $current_admins = array();
        }

        // Ensure we're storing integers, not objects
        $leader_id = (int) $leader_id;
        
        if ( ! in_array( $leader_id, $current_admins ) ) {
            $current_admins[] = $leader_id;
            $result = update_post_meta( $group_id, '_ld_group_administrators', $current_admins );
            
            if ( $result ) {
                // Also update the reverse relationship
                $user_groups = get_user_meta( $leader_id, 'learndash_group_leaders_' . $leader_id, true );
                if ( ! is_array( $user_groups ) ) {
                    $user_groups = array();
                }
                if ( ! in_array( $group_id, $user_groups ) ) {
                    $user_groups[] = $group_id;
                    update_user_meta( $leader_id, 'learndash_group_leaders_' . $leader_id, $user_groups );
                }
                
                return true;
            }
        }

        return false;
    }

    /**
     * Assign group leader with LearnDash function and fallback
     * 
     * @param int $leader_id
     * @param int $group_id
     * @return bool
     */
    private static function assign_group_leader_with_fallback( $leader_id, $group_id ) {
        try {
            // Try LearnDash function first
            if ( function_exists( 'learndash_set_groups_administrators' ) ) {
                $current_leaders = learndash_get_groups_administrators( $group_id );
                if ( ! is_array( $current_leaders ) ) {
                    $current_leaders = array();
                }
                
                // Ensure we pass integers, not user objects
                $leader_id = (int) $leader_id;
                $current_leaders[] = $leader_id;
                
                // Clean the array to ensure only integers
                $current_leaders = array_map( 'intval', $current_leaders );
                $current_leaders = array_unique( $current_leaders );
                
                $result = learndash_set_groups_administrators( $group_id, $current_leaders );
                
                if ( $result !== false ) {
                    return true;
                }
            }
        } catch ( Exception $e ) {
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::warning( "LearnDash function failed: " . $e->getMessage() . ", using safe method..." );
            }
            
            // Log the error
            if ( class_exists( 'LDTT_Logger' ) ) {
                LDTT_Logger::warning( "LearnDash group assignment failed: " . $e->getMessage() );
            }
        }

        // Fallback to safe method
        return self::assign_group_leader_safely( $leader_id, $group_id );
    }

    /**
     * Verify group leader assignments
     * 
     * @return array
     */
    private static function verify_group_leader_assignments() {
        $leaders = get_users( array( 'role' => 'group_leader' ) );
        $groups_with_leaders = 0;
        
        $groups = get_posts( array(
            'post_type' => learndash_get_post_type_slug( 'group' ),
            'numberposts' => -1,
            'fields' => 'ids'
        ) );
        
        foreach ( $groups as $group_id ) {
            $admins = get_post_meta( $group_id, '_ld_group_administrators', true );
            if ( ! empty( $admins ) && is_array( $admins ) ) {
                $groups_with_leaders++;
            }
        }
        
        $total_groups = count( $groups );
        $success_rate = $total_groups > 0 ? round( ( $groups_with_leaders / $total_groups ) * 100, 2 ) : 0;
        
        return array(
            'total_leaders' => count( $leaders ),
            'groups_with_leaders' => $groups_with_leaders,
            'total_groups' => $total_groups,
            'success_rate' => $success_rate
        );
    }

    /**
     * Create group members and enroll them in groups.
     *
     * @param int $count Number of group members to create.
     * @param bool $use_existing Whether to use existing users.
     * @return array|WP_Error Array of created user IDs or WP_Error.
     */
    private static function create_group_members( $count, $use_existing = false ) {
        $user_ids = array();
        $groups = self::get_or_create_test_groups( 3 );

        if ( $use_existing ) {
            $candidate_users = self::get_candidate_users( true );
            if ( empty( $candidate_users ) ) {
                return new WP_Error( 'no_candidates', 'No suitable existing users found for group member assignment' );
            }

            for ( $i = 0; $i < $count && $i < count( $candidate_users ); $i++ ) {
                $user_id = $candidate_users[ $i ];
                
                // Skip if already assigned as group leader
                if ( in_array( $user_id, self::$created_group_leaders ) ) {
                    continue;
                }

                update_user_meta( $user_id, '_ldtt_user_type', 'group_member' );
                update_user_meta( $user_id, '_ldtt_created', true );

                // Enroll in a random group
                if ( ! empty( $groups ) ) {
                    $group_id = $groups[ array_rand( $groups ) ];
                    self::enroll_user_in_group( $user_id, $group_id );
                }

                $user_ids[] = $user_id;

                if ( defined( 'WP_CLI' ) && WP_CLI ) {
                    WP_CLI::line( "Assigned existing user {$user_id} as group member" );
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

                // Enroll in a random group
                if ( ! empty( $groups ) ) {
                    $group_id = $groups[ array_rand( $groups ) ];
                    self::enroll_user_in_group( $user_id, $group_id );
                }

                $user_ids[] = $user_id;

                if ( defined( 'WP_CLI' ) && WP_CLI ) {
                    WP_CLI::line( "Created group member: {$username} (ID: {$user_id})" );
                }
            }
        }

        return $user_ids;
    }

    /**
     * Create course-enrolled users with optional progress.
     *
     * @param int $count Number of course-enrolled users to create.
     * @param bool $create_progress Whether to create course progress.
     * @param bool $use_existing Whether to use existing users.
     * @return array|WP_Error Array of created user IDs or WP_Error.
     */
    private static function create_course_enrolled_users( $count, $create_progress = false, $use_existing = false ) {
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
                $user_id = $candidate_users[ $i ];
                
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
     * Create progress for a user in a course
     * 
     * @param int $user_id
     * @param int $course_id
     */
    private static function create_progress_for_user( $user_id, $course_id ) {
        if ( class_exists( 'LDTT_Progress_Manager' ) ) {
            LDTT_Progress_Manager::create_realistic_progress( $user_id, $course_id );
        } else {
            // Fallback to basic progress creation
            self::create_basic_progress( $user_id, $course_id );
        }
    }

    /**
     * Create basic progress for a user (fallback method)
     * 
     * @param int $user_id
     * @param int $course_id
     */
    private static function create_basic_progress( $user_id, $course_id ) {
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
     * Create regular users (not assigned to groups or courses).
     *
     * @param int $count Number of regular users to create.
     * @return array Array of created user IDs.
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
     *
     * @return array Array of course IDs.
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
     * Get or create test groups.
     *
     * @param int $min_groups Minimum number of groups to ensure exist.
     * @return array Array of group IDs.
     */
    private static function get_or_create_test_groups( $min_groups = 3 ) {
        $groups = get_posts( array(
            'post_type'   => learndash_get_post_type_slug( 'group' ),
            'numberposts' => -1,
            'meta_query'  => array(
                array(
                    'key'     => '_ldtt_test_data',
                    'value'   => true,
                    'compare' => '=',
                ),
            ),
            'fields' => 'ids',
        ) );

        // Create more groups if we don't have enough
        $groups_needed = max( 0, $min_groups - count( $groups ) );
        if ( $groups_needed > 0 ) {
            $admin_id = LDTT_Helper::get_admin_user_id();
            
            for ( $i = 1; $i <= $groups_needed; $i++ ) {
                $group_id = wp_insert_post( array(
                    'post_title'   => 'Auto Test Group ' . ( count( $groups ) + $i ),
                    'post_type'    => learndash_get_post_type_slug( 'group' ),
                    'post_status'  => 'publish',
                    'post_content' => 'Auto-created test group for user distribution.',
                    'post_author'  => $admin_id,
                    'meta_input'   => array(
                        '_ldtt_test_data' => true,
                    ),
                ) );
                
                if ( ! is_wp_error( $group_id ) ) {
                    $groups[] = $group_id;
                    
                    if ( defined( 'WP_CLI' ) && WP_CLI ) {
                        WP_CLI::line( "Created test group: Auto Test Group " . ( count( $groups ) ) . " (ID: {$group_id})" );
                    }
                }
            }
        }

        return $groups;
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
                'course_id'    => isset( $_POST['course_id'] ) ? intval( $_POST['course_id'] ) : null,
                'all_courses'  => isset( $_POST['all_courses'] ) ? true : false,
                'overwrite'    => isset( $_POST['overwrite'] ) ? true : false,
                'min_progress' => isset( $_POST['min_progress'] ) ? intval( $_POST['min_progress'] ) : 25,
                'max_progress' => isset( $_POST['max_progress'] ) ? intval( $_POST['max_progress'] ) : 100,
            );
        }

        $course_id = ! empty( $assoc_args['course_id'] ) ? absint( $assoc_args['course_id'] ) : null;
        $all_courses = isset( $assoc_args['all_courses'] ) && $assoc_args['all_courses'];
        $overwrite = isset( $assoc_args['overwrite'] ) && $assoc_args['overwrite'];
        $min_progress = LDTT_Helper::validate_positive_int( $assoc_args['min_progress'] ?? 25, 25, 100 );
        $max_progress = LDTT_Helper::validate_positive_int( $assoc_args['max_progress'] ?? 100, $min_progress, 100 );

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
            
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::line( "Processing course: {$course_title} (ID: {$course_id}) - " . count( $users ) . " enrolled users" );
            }

            foreach ( $users as $user_id ) {
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

        $message = "Processed {$total_users_processed} enrolled users, added progress to {$total_progress_added} users.";
        
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
            return learndash_get_users_for_course( $course_id );
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

    /**
     * Enroll a user in a group.
     *
     * @param int $user_id User ID.
     * @param int $group_id Group ID.
     */
    private static function enroll_user_in_group( $user_id, $group_id ) {
        if ( function_exists( 'ld_update_group_access' ) ) {
            ld_update_group_access( $user_id, $group_id, false );
        } else {
            // Fallback method
            $current_users = get_post_meta( $group_id, 'learndash_group_users_' . $group_id, true );
            if ( ! is_array( $current_users ) ) {
                $current_users = array();
            }
            $current_users[] = $user_id;
            update_post_meta( $group_id, 'learndash_group_users_' . $group_id, $current_users );
        }
    }
}