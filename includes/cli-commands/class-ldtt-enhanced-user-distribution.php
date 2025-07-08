<?php

class LDTT_Enhanced_User_Distribution {

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
            );
        }

        $total_users = LDTT_Helper::validate_positive_int( $assoc_args['total_users'] ?? 100, 100, 1000 );
        $group_leader_percent = floatval( $assoc_args['group_leaders'] ?? 1.0 );
        $group_member_percent = floatval( $assoc_args['group_members'] ?? 2.0 );
        $course_enrolled_percent = floatval( $assoc_args['course_enrolled'] ?? 5.0 );
        $create_progress = isset( $assoc_args['create_progress'] ) && $assoc_args['create_progress'];

        // Calculate user counts
        $group_leader_count = max( 1, round( $total_users * ( $group_leader_percent / 100 ) ) );
        $group_member_count = max( 1, round( $total_users * ( $group_member_percent / 100 ) ) );
        $course_enrolled_count = max( 1, round( $total_users * ( $course_enrolled_percent / 100 ) ) );

        $results = array();

        // Create group leaders (1%)
        $results['group_leaders'] = self::create_group_leaders( $group_leader_count );
        if ( is_wp_error( $results['group_leaders'] ) ) {
            return array( 'status' => 'error', 'message' => $results['group_leaders']->get_error_message() );
        }

        // Create group members (2%)
        $results['group_members'] = self::create_group_members( $group_member_count );
        if ( is_wp_error( $results['group_members'] ) ) {
            return array( 'status' => 'error', 'message' => $results['group_members']->get_error_message() );
        }

        // Create course-enrolled users (5%)
        $results['course_enrolled'] = self::create_course_enrolled_users( $course_enrolled_count, $create_progress );
        if ( is_wp_error( $results['course_enrolled'] ) ) {
            return array( 'status' => 'error', 'message' => $results['course_enrolled']->get_error_message() );
        }

        // Create remaining regular users
        $remaining_users = $total_users - $group_leader_count - $group_member_count - $course_enrolled_count;
        if ( $remaining_users > 0 ) {
            $results['regular_users'] = self::create_regular_users( $remaining_users );
        }

        $message = "Successfully created {$total_users} users with distribution: " .
                   "{$group_leader_count} group leaders, {$group_member_count} group members, " .
                   "{$course_enrolled_count} course-enrolled users" .
                   ( $remaining_users > 0 ? ", {$remaining_users} regular users" : "" );

        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::success( $message );
        }

        return array( 'status' => 'success', 'message' => $message, 'results' => $results );
    }

    /**
     * Create group leaders and assign them to groups.
     *
     * @param int $count Number of group leaders to create.
     * @return array|WP_Error Array of created user IDs or WP_Error.
     */
    private static function create_group_leaders( $count ) {
        $user_ids = array();
        $groups = self::get_or_create_test_groups( 3 ); // Ensure we have some groups

        for ( $i = 1; $i <= $count; $i++ ) {
            $username = 'leader_' . LDTT_Helper::generate_random_string( 8 );
            $email = $username . '@example.com';

            $user_id = wp_create_user( $username, wp_generate_password( 12 ), $email );

            if ( is_wp_error( $user_id ) ) {
                continue;
            }

            // Set as group leader
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

            // Assign to a random group
            if ( ! empty( $groups ) ) {
                $group_id = $groups[ array_rand( $groups ) ];
                self::assign_group_leader( $user_id, $group_id );
            }

            $user_ids[] = $user_id;

            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::line( "Created group leader: {$username} (ID: {$user_id})" );
            }
        }

        return $user_ids;
    }

    /**
     * Create group members and enroll them in groups.
     *
     * @param int $count Number of group members to create.
     * @return array|WP_Error Array of created user IDs or WP_Error.
     */
    private static function create_group_members( $count ) {
        $user_ids = array();
        $groups = self::get_or_create_test_groups( 3 ); // Ensure we have some groups

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

        return $user_ids;
    }

    /**
     * Create course-enrolled users with optional progress.
     *
     * @param int $count Number of course-enrolled users to create.
     * @param bool $create_progress Whether to create course progress.
     * @return array|WP_Error Array of created user IDs or WP_Error.
     */
    private static function create_course_enrolled_users( $count, $create_progress = false ) {
        $user_ids = array();
        $courses = self::get_available_courses();

        if ( empty( $courses ) ) {
            return new WP_Error( 'no_courses', 'No courses available for enrollment.' );
        }

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
                if ( class_exists( 'LDTT_Progress_Manager' ) ) {
                    LDTT_Progress_Manager::create_realistic_progress( $user_id, $course_id );
                } else {
                    // Fallback to basic progress creation
                    self::create_basic_progress( $user_id, $course_id );
                }
            }

            $user_ids[] = $user_id;

            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::line( "Created course student: {$username} (ID: {$user_id}) enrolled in course {$course_id}" );
            }
        }

        return $user_ids;
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
                }
            }
        }

        return $groups;
    }

    /**
     * Create basic course progress (fallback method)
     * 
     * @param int $user_id
     * @param int $course_id
     */
    private static function create_basic_progress( $user_id, $course_id ) {
        // Get course lessons
        $lessons = get_posts( array(
            'post_type'   => learndash_get_post_type_slug( 'lesson' ),
            'numberposts' => -1,
            'meta_key'    => 'learndash_course',
            'meta_value'  => $course_id,
            'post_status' => 'publish',
        ) );
        
        if ( empty( $lessons ) ) {
            return;
        }

        // Randomly complete some lessons (25% to 100%)
        $completion_rate = wp_rand( 25, 100 );
        $lessons_to_complete = round( count( $lessons ) * ( $completion_rate / 100 ) );

        // Shuffle lessons and complete the first N
        shuffle( $lessons );
        $completed_lessons = array_slice( $lessons, 0, $lessons_to_complete );

        foreach ( $completed_lessons as $lesson ) {
            // Mark lesson as completed
            learndash_process_mark_complete( $user_id, $lesson->ID, false, $course_id );
        }

        // Update course progress timestamp
        update_user_meta( $user_id, '_ldtt_progress_created', current_time( 'timestamp' ) );

        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::line( "  - Created {$completion_rate}% progress for user {$user_id} in course {$course_id}" );
        }
    }

    /**
     * Assign a group leader to a group.
     *
     * @param int $leader_id Leader user ID.
     * @param int $group_id Group ID.
     */
    private static function assign_group_leader( $leader_id, $group_id ) {
        if ( function_exists( 'learndash_set_groups_administrators' ) ) {
            $current_leaders = learndash_get_groups_administrators( $group_id );
            if ( ! is_array( $current_leaders ) ) {
                $current_leaders = array();
            }
            $current_leaders[] = $leader_id;
            learndash_set_groups_administrators( $group_id, $current_leaders );
        } else {
            // Fallback method
            $current_leaders = get_post_meta( $group_id, 'learndash_group_leaders_' . $group_id, true );
            if ( ! is_array( $current_leaders ) ) {
                $current_leaders = array();
            }
            $current_leaders[] = $leader_id;
            update_post_meta( $group_id, 'learndash_group_leaders_' . $group_id, $current_leaders );
        }
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