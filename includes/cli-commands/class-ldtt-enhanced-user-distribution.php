<?php

/**
 * Simplified Enhanced User Distribution - Clean and Functional
 * Creates users with basic distribution and simple progress
 */
class LDTT_Enhanced_User_Distribution {

    /**
     * Handle the command to create users with distribution
     *
     * @param array $args Positional arguments.
     * @param array $assoc_args Associative arguments.
     */
    public static function handle( $args = array(), $assoc_args = array() ) {
        // Check for admin input if no CLI arguments are provided
        if ( empty( $args ) && empty( $assoc_args ) ) {
            $assoc_args = array(
                'total_users'                => isset( $_POST['total_users'] ) ? intval( $_POST['total_users'] ) : 100,
                'group_leaders'              => isset( $_POST['group_leaders'] ) ? floatval( $_POST['group_leaders'] ) : 1.0,
                'group_members'              => isset( $_POST['group_members'] ) ? floatval( $_POST['group_members'] ) : 2.0,
                'course_enrolled'            => isset( $_POST['course_enrolled'] ) ? floatval( $_POST['course_enrolled'] ) : 5.0,
                'create_progress'            => isset( $_POST['create_progress'] ) ? true : false,
                'use_existing'               => isset( $_POST['use_existing'] ) ? true : false,
                'min_progress'               => isset( $_POST['min_progress'] ) ? intval( $_POST['min_progress'] ) : 25,
                'max_progress'               => isset( $_POST['max_progress'] ) ? intval( $_POST['max_progress'] ) : 85,
                'user_prefix'                => isset( $_POST['user_prefix'] ) ? sanitize_text_field( $_POST['user_prefix'] ) : 'testuser',
                'assign_to_existing_groups'  => isset( $_POST['assign_to_existing_groups'] ) ? true : false,
                'dry_run'                    => isset( $_POST['dry_run'] ) ? true : false,
                'verbose'                    => isset( $_POST['verbose'] ) ? true : false,
            );
        }

        $total_users = LDTT_Helper::validate_positive_int( $assoc_args['total_users'] ?? 100, 10, 1000 );
        $group_leader_percent = floatval( $assoc_args['group_leaders'] ?? 1.0 );
        $group_member_percent = floatval( $assoc_args['group_members'] ?? 2.0 );
        $course_enrolled_percent = floatval( $assoc_args['course_enrolled'] ?? 5.0 );
        $create_progress = isset( $assoc_args['create_progress'] ) && $assoc_args['create_progress'];
        $use_existing = isset( $assoc_args['use_existing'] ) && $assoc_args['use_existing'];
        $min_progress = LDTT_Helper::validate_positive_int( $assoc_args['min_progress'] ?? 25, 0, 100 );
        $max_progress = LDTT_Helper::validate_positive_int( $assoc_args['max_progress'] ?? 85, $min_progress, 100 );
        $user_prefix = sanitize_text_field( $assoc_args['user_prefix'] ?? 'testuser' );
        $assign_to_existing_groups = isset( $assoc_args['assign_to_existing_groups'] ) && $assoc_args['assign_to_existing_groups'];
        $dry_run = isset( $assoc_args['dry_run'] ) && $assoc_args['dry_run'];
        $verbose = isset( $assoc_args['verbose'] ) && $assoc_args['verbose'];

        // Calculate user counts
        $group_leader_count = max( 1, round( $total_users * ( $group_leader_percent / 100 ) ) );
        $group_member_count = max( 1, round( $total_users * ( $group_member_percent / 100 ) ) );
        $course_enrolled_count = max( 1, round( $total_users * ( $course_enrolled_percent / 100 ) ) );
        $regular_user_count = max( 0, $total_users - $group_leader_count - $group_member_count - $course_enrolled_count );

        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::line( "Creating user distribution:" );
            WP_CLI::line( "- Group Leaders: {$group_leader_count}" );
            WP_CLI::line( "- Group Members: {$group_member_count}" );
            WP_CLI::line( "- Course Enrolled: {$course_enrolled_count}" );
            WP_CLI::line( "- Regular Users: {$regular_user_count}" );
            if ( $create_progress ) {
                WP_CLI::line( "- Progress Range: {$min_progress}% - {$max_progress}%" );
            }
            if ( ! $use_existing ) {
                WP_CLI::line( "- Username Prefix: {$user_prefix}" );
            }
            WP_CLI::line( "- Groups: " . ( $assign_to_existing_groups ? 'Use existing' : 'Create new if needed' ) );
        }

        // Show dry run preview if requested
        if ( $dry_run ) {
            return self::show_dry_run_preview( $total_users, $group_leader_count, $group_member_count, $course_enrolled_count, $regular_user_count, $use_existing, $create_progress, $min_progress, $max_progress, $user_prefix, $assign_to_existing_groups );
        }

        $results = array();

        // Create group leaders
        $results['group_leaders'] = self::create_group_leaders( $group_leader_count, $use_existing, $user_prefix, $assign_to_existing_groups, $verbose );
        
        // Create group members
        $results['group_members'] = self::create_group_members( $group_member_count, $use_existing, $user_prefix, $assign_to_existing_groups, $verbose );
        
        // Create course enrolled users
        $results['course_enrolled'] = self::create_course_enrolled_users( $course_enrolled_count, $create_progress, $use_existing, $user_prefix, $min_progress, $max_progress, $verbose );
        
        // Create regular users (only if not using existing)
        if ( $regular_user_count > 0 && ! $use_existing ) {
            $results['regular_users'] = self::create_regular_users( $regular_user_count, $user_prefix, $verbose );
        }

        $actual_total = count( $results['group_leaders'] ) + count( $results['group_members'] ) + 
                       count( $results['course_enrolled'] ) + ( isset( $results['regular_users'] ) ? count( $results['regular_users'] ) : 0 );

        $message = "Successfully created/processed {$actual_total} users with distribution.";
        
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::success( $message );
        }

        return array( 
            'status' => 'success', 
            'message' => $message, 
            'results' => $results
        );
    }

    /**
     * Show dry run preview
     */
    private static function show_dry_run_preview( $total_users, $group_leader_count, $group_member_count, $course_enrolled_count, $regular_user_count, $use_existing, $create_progress, $min_progress, $max_progress, $user_prefix, $assign_to_existing_groups ) {
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::line( "=== DRY RUN PREVIEW ===" );
            WP_CLI::line( "Total Users: {$total_users}" );
            WP_CLI::line( "Method: " . ( $use_existing ? 'Use existing users' : 'Create new users' ) );
            WP_CLI::line( "" );
            WP_CLI::line( "Distribution:" );
            WP_CLI::line( "- Group Leaders: {$group_leader_count}" );
            WP_CLI::line( "- Group Members: {$group_member_count}" );
            WP_CLI::line( "- Course Enrolled: {$course_enrolled_count}" );
            WP_CLI::line( "- Regular Users: {$regular_user_count}" );
            WP_CLI::line( "" );
            if ( ! $use_existing ) {
                WP_CLI::line( "Username format: {$user_prefix}_XXXXXXXX" );
            }
            if ( $create_progress ) {
                WP_CLI::line( "Progress: Random between {$min_progress}% - {$max_progress}%" );
            }
            WP_CLI::line( "Groups: " . ( $assign_to_existing_groups ? 'Use existing groups' : 'Create new groups if needed' ) );
            WP_CLI::line( "" );
            WP_CLI::line( "Run without --dry_run to execute." );
        }

        return array(
            'status' => 'success',
            'message' => 'Dry run completed',
            'preview' => array(
                'total_users' => $total_users,
                'distribution' => array(
                    'group_leaders' => $group_leader_count,
                    'group_members' => $group_member_count,
                    'course_enrolled' => $course_enrolled_count,
                    'regular_users' => $regular_user_count,
                ),
                'settings' => array(
                    'use_existing' => $use_existing,
                    'create_progress' => $create_progress,
                    'progress_range' => "{$min_progress}%-{$max_progress}%",
                    'user_prefix' => $user_prefix,
                    'existing_groups' => $assign_to_existing_groups,
                ),
            ),
        );
    }

    /**
     * Create group leaders
     *
     * @param int $count
     * @param bool $use_existing
     * @param string $user_prefix
     * @param bool $assign_to_existing_groups
     * @param bool $verbose
     * @return array
     */
    private static function create_group_leaders( $count, $use_existing = false, $user_prefix = 'leader', $assign_to_existing_groups = false, $verbose = false ) {
        $group_leaders = array();
        $groups = $assign_to_existing_groups ? self::get_available_groups() : self::ensure_groups_exist();

        if ( $use_existing ) {
            $users = self::get_existing_users( $count );
            foreach ( $users as $user_id ) {
                $user = new WP_User( $user_id );
                $user->set_role( 'group_leader' );
                
                update_user_meta( $user_id, '_ldtt_user_type', 'group_leader' );
                $group_leaders[] = $user_id;
                
                // Assign to a random group
                if ( ! empty( $groups ) ) {
                    $group_id = $groups[ array_rand( $groups ) ];
                    self::assign_group_leader( $user_id, $group_id );
                }
            }
        } else {
            for ( $i = 1; $i <= $count; $i++ ) {
                $username = 'leader_' . LDTT_Helper::generate_random_string( 8 );
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

                $group_leaders[] = $user_id;

                // Assign to a random group
                if ( ! empty( $groups ) ) {
                    $group_id = $groups[ array_rand( $groups ) ];
                    self::assign_group_leader( $user_id, $group_id );
                }

                if ( defined( 'WP_CLI' ) && WP_CLI ) {
                    WP_CLI::line( "Created group leader: {$username} (ID: {$user_id})" );
                }
            }
        }

        return $group_leaders;
    }

    /**
     * Create group members
     *
     * @param int $count
     * @param bool $use_existing
     * @param string $user_prefix
     * @param bool $assign_to_existing_groups
     * @param bool $verbose
     * @return array
     */
    private static function create_group_members( $count, $use_existing = false, $user_prefix = 'member', $assign_to_existing_groups = false, $verbose = false ) {
        $group_members = array();
        $groups = self::get_available_groups();

        if ( $use_existing ) {
            $users = self::get_existing_users( $count );
            foreach ( $users as $user_id ) {
                update_user_meta( $user_id, '_ldtt_user_type', 'group_member' );
                $group_members[] = $user_id;
                
                // Assign to a random group
                if ( ! empty( $groups ) ) {
                    $group_id = $groups[ array_rand( $groups ) ];
                    self::enroll_user_in_group( $user_id, $group_id );
                }
            }
        } else {
            for ( $i = 1; $i <= $count; $i++ ) {
                $username = 'member_' . LDTT_Helper::generate_random_string( 8 );
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

                $group_members[] = $user_id;

                // Assign to a random group
                if ( ! empty( $groups ) ) {
                    $group_id = $groups[ array_rand( $groups ) ];
                    self::enroll_user_in_group( $user_id, $group_id );
                }

                if ( defined( 'WP_CLI' ) && WP_CLI ) {
                    WP_CLI::line( "Created group member: {$username} (ID: {$user_id})" );
                }
            }
        }

        return $group_members;
    }

    /**
     * Create course enrolled users
     *
     * @param int $count
     * @param bool $create_progress
     * @param bool $use_existing
     * @param string $user_prefix
     * @param int $min_progress
     * @param int $max_progress
     * @param bool $verbose
     * @return array
     */
    private static function create_course_enrolled_users( $count, $create_progress = false, $use_existing = false, $user_prefix = 'student', $min_progress = 25, $max_progress = 85, $verbose = false ) {
        $enrolled_users = array();
        $courses = self::get_available_courses();

        if ( empty( $courses ) ) {
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::warning( 'No courses available for enrollment' );
            }
            return $enrolled_users;
        }

        if ( $use_existing ) {
            $users = self::get_existing_users( $count );
            foreach ( $users as $user_id ) {
                update_user_meta( $user_id, '_ldtt_user_type', 'course_enrolled' );
                
                // Enroll in random course
                $course_id = $courses[ array_rand( $courses ) ];
                ld_update_course_access( $user_id, $course_id, false );
                
                // Add progress if requested
                if ( $create_progress ) {
                    self::add_course_progress( $user_id, $course_id, $min_progress, $max_progress );
                }
                
                $enrolled_users[] = $user_id;
            }
        } else {
            for ( $i = 1; $i <= $count; $i++ ) {
                $username = 'student_' . LDTT_Helper::generate_random_string( 8 );
                $email = $username . '@example.com';

                $user_id = wp_create_user( $username, wp_generate_password( 12 ), $email );

                if ( is_wp_error( $user_id ) ) {
                    continue;
                }

                $user = new WP_User( $user_id );
                $user->set_role( 'subscriber' );

                wp_update_user( array(
                    'ID'           => $user_id,
                    'display_name' => 'Student ' . $i,
                    'first_name'   => 'Student',
                    'last_name'    => $i,
                ) );

                update_user_meta( $user_id, '_ldtt_test_user', true );
                update_user_meta( $user_id, '_ldtt_user_type', 'course_enrolled' );

                // Enroll in random course
                $course_id = $courses[ array_rand( $courses ) ];
                ld_update_course_access( $user_id, $course_id, false );
                
                // Add progress if requested
                if ( $create_progress ) {
                    self::add_course_progress( $user_id, $course_id, $min_progress, $max_progress );
                }

                $enrolled_users[] = $user_id;

                if ( defined( 'WP_CLI' ) && WP_CLI ) {
                    WP_CLI::line( "Created student: {$username} (ID: {$user_id}) enrolled in course {$course_id}" );
                }
            }
        }

        return $enrolled_users;
    }

    /**
     * Create regular users
     *
     * @param int $count
     * @param string $user_prefix
     * @param bool $verbose
     * @return array
     */
    private static function create_regular_users( $count, $user_prefix = 'testuser', $verbose = false ) {
        $users = array();

        for ( $i = 1; $i <= $count; $i++ ) {
            $username = 'testuser_' . LDTT_Helper::generate_random_string( 8 );
            $email = $username . '@example.com';

            $user_id = wp_create_user( $username, wp_generate_password( 12 ), $email );

            if ( is_wp_error( $user_id ) ) {
                continue;
            }

            $user = new WP_User( $user_id );
            $user->set_role( 'subscriber' );

            wp_update_user( array(
                'ID'           => $user_id,
                'display_name' => 'Test User ' . $i,
                'first_name'   => 'Test',
                'last_name'    => 'User ' . $i,
            ) );

            update_user_meta( $user_id, '_ldtt_test_user', true );
            update_user_meta( $user_id, '_ldtt_user_type', 'regular' );

            $users[] = $user_id;

            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::line( "Created regular user: {$username} (ID: {$user_id})" );
            }
        }

        return $users;
    }

    /**
     * Add course progress using the correct LearnDash function and approach
     *
     * @param int $user_id
     * @param int $course_id
     * @param int $min_progress
     * @param int $max_progress
     */
    private static function add_course_progress( $user_id, $course_id, $min_progress = 25, $max_progress = 85 ) {
        if ( ! function_exists( 'learndash_process_mark_complete' ) ) {
            return;
        }

        // Get lessons first
        $lessons = learndash_course_get_lessons( $course_id );
        if ( empty( $lessons ) ) {
            return;
        }

        $all_steps = array();
        
        // Process lessons and their topics
        foreach ( $lessons as $lesson ) {
            $lesson_id = $lesson->ID;
            $all_steps[] = array( 'id' => $lesson_id, 'type' => 'lesson' );
            
            // Get topics for this specific lesson - FIXED APPROACH
            $topics = learndash_course_get_topics( $course_id, $lesson_id );
            if ( ! empty( $topics ) ) {
                foreach ( $topics as $topic ) {
                    $all_steps[] = array( 'id' => $topic->ID, 'type' => 'topic' );
                }
            }
        }

        // Get quizzes
        $quizzes = learndash_course_get_quizzes( $course_id );
        if ( ! empty( $quizzes ) ) {
            foreach ( $quizzes as $quiz ) {
                $all_steps[] = array( 'id' => $quiz->ID, 'type' => 'quiz' );
            }
        }

        if ( empty( $all_steps ) ) {
            return;
        }

        // Calculate random percentage of steps to complete
        $total_steps = count( $all_steps );
        $completion_rate = wp_rand( $min_progress, $max_progress );
        $target = ceil( $total_steps * ( $completion_rate / 100 ) );

        $marked = 0;
        foreach ( $all_steps as $step ) {
            $step_id = $step['id'];
            
            if ( learndash_is_item_complete( $user_id, $step_id ) ) {
                continue;
            }

            // Use the correct LearnDash function: learndash_process_mark_complete()
            // Parameters: user_id, post_id, only_calculate, course_id, force
            if ( learndash_process_mark_complete( $user_id, $step_id, false, $course_id, false ) ) {
                $marked++;
            }

            if ( $marked >= $target ) {
                break;
            }
        }

        // Store progress metadata
        update_user_meta( $user_id, '_ldtt_progress_created', current_time( 'timestamp' ) );
        update_user_meta( $user_id, '_ldtt_progress_completion_rate', $completion_rate );

        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::line( "  - Added {$completion_rate}% progress ({$marked}/{$total_steps} steps) for user {$user_id}" );
        }
    }

    /**
     * Get existing users for assignment
     *
     * @param int $count
     * @return array
     */
    private static function get_existing_users( $count ) {
        $users = get_users( array(
            'role__not_in' => array( 'administrator' ),
            'fields' => 'ID',
            'number' => $count,
        ) );

        return array_map( 'absint', $users );
    }

    /**
     * Get available groups or ensure some exist
     *
     * @return array
     */
    private static function ensure_groups_exist() {
        $groups = self::get_available_groups();
        
        if ( empty( $groups ) ) {
            // Create a basic group
            $admin_id = LDTT_Helper::get_admin_user_id();
            $group_id = wp_insert_post( array(
                'post_title'   => 'Test Group',
                'post_type'    => learndash_get_post_type_slug( 'group' ),
                'post_status'  => 'publish',
                'post_content' => 'Test group created for user distribution',
                'post_author'  => $admin_id,
                'meta_input'   => array(
                    '_ldtt_test_data' => true,
                ),
            ) );
            
            if ( ! is_wp_error( $group_id ) ) {
                $groups = array( $group_id );
            }
        }
        
        return $groups;
    }

    /**
     * Get available groups
     *
     * @return array
     */
    private static function get_available_groups() {
        return get_posts( array(
            'post_type'   => learndash_get_post_type_slug( 'group' ),
            'numberposts' => -1,
            'post_status' => 'publish',
            'fields'      => 'ids',
        ) );
    }

    /**
     * Get available courses
     *
     * @return array
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
     * Assign group leader using simple LearnDash functions
     *
     * @param int $leader_id
     * @param int $group_id
     * @return bool
     */
    private static function assign_group_leader( $leader_id, $group_id ) {
        if ( function_exists( 'learndash_set_groups_administrators' ) ) {
            $current_admins = learndash_get_groups_administrators( $group_id );
            if ( ! is_array( $current_admins ) ) {
                $current_admins = array();
            }
            $current_admins[] = $leader_id;
            return learndash_set_groups_administrators( $group_id, $current_admins ) !== false;
        }

        // Fallback
        $current_admins = get_post_meta( $group_id, '_ld_group_administrators', true );
        if ( ! is_array( $current_admins ) ) {
            $current_admins = array();
        }
        $current_admins[] = $leader_id;
        return update_post_meta( $group_id, '_ld_group_administrators', $current_admins );
    }

    /**
     * Enroll user in group using simple LearnDash functions
     *
     * @param int $user_id
     * @param int $group_id
     * @return bool
     */
    private static function enroll_user_in_group( $user_id, $group_id ) {
        if ( function_exists( 'learndash_set_groups_users' ) ) {
            $current_members = learndash_get_groups_users( $group_id );
            if ( ! is_array( $current_members ) ) {
                $current_members = array();
            }
            
            // Handle user objects
            $member_ids = array();
            foreach ( $current_members as $member ) {
                if ( is_object( $member ) && isset( $member->ID ) ) {
                    $member_ids[] = $member->ID;
                } else {
                    $member_ids[] = $member;
                }
            }
            
            $member_ids[] = $user_id;
            return learndash_set_groups_users( $group_id, $member_ids ) !== false;
        }

        // Fallback
        $group_users = get_post_meta( $group_id, 'learndash_group_users_' . $group_id, true );
        if ( ! is_array( $group_users ) ) {
            $group_users = array();
        }
        $group_users[] = $user_id;
        return update_post_meta( $group_id, 'learndash_group_users_' . $group_id, $group_users );
    }

    /**
     * Assign progress to existing enrolled users (simplified version)
     *
     * @param array $args
     * @param array $assoc_args
     */
    public static function assign_progress_to_enrolled( $args = array(), $assoc_args = array() ) {
        $course_id = ! empty( $assoc_args['course_id'] ) ? absint( $assoc_args['course_id'] ) : null;
        $all_courses = isset( $assoc_args['all_courses'] ) && $assoc_args['all_courses'];

        if ( ! $all_courses && ! $course_id ) {
            $message = 'Either --course_id or --all_courses must be specified.';
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::error( $message );
            }
            return array( 'status' => 'error', 'message' => $message );
        }

        $courses_to_process = $all_courses ? self::get_available_courses() : array( $course_id );
        $total_users_processed = 0;

        foreach ( $courses_to_process as $course_id ) {
            $users = self::get_enrolled_users_for_course( $course_id );
            
            foreach ( $users as $user_id ) {
                self::add_course_progress( $user_id, $course_id );
                $total_users_processed++;
            }
        }

        $message = "Added progress to {$total_users_processed} enrolled users.";
        
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::success( $message );
        }
        
        return array(
            'status' => 'success',
            'message' => $message,
            'users_processed' => $total_users_processed,
        );
    }

    /**
     * Get enrolled users for a course
     *
     * @param int $course_id
     * @return array
     */
    private static function get_enrolled_users_for_course( $course_id ) {
        if ( function_exists( 'learndash_get_users_for_course' ) ) {
            return learndash_get_users_for_course( $course_id );
        }

        // Fallback: get users with course progress
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