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
     * Add course progress using the correct LearnDash function and approach (UPDATED VERSION)
     * Now includes 100% completion for some users with enhanced logic
     *
     * @param int $user_id
     * @param int $course_id
     * @param int $min_progress
     * @param int $max_progress
     * @return array|false
     */
    private static function add_course_progress( $user_id, $course_id, $min_progress = 25, $max_progress = 85 ) {
        if ( ! function_exists( 'learndash_process_mark_complete' ) ) {
            return false;
        }

        // Get lessons first using the correct function
        $lessons = learndash_course_get_lessons( $course_id );
        if ( empty( $lessons ) ) {
            return false;
        }

        $all_steps = array();
        
        // Process lessons and their topics
        foreach ( $lessons as $lesson ) {
            $lesson_id = $lesson->ID;
            $all_steps[] = array( 'id' => $lesson_id, 'type' => 'lesson' );
            
            // Get topics for this specific lesson using the correct function  
            $topics = learndash_course_get_topics( $course_id, $lesson_id );
            if ( ! empty( $topics ) ) {
                foreach ( $topics as $topic ) {
                    $all_steps[] = array( 'id' => $topic->ID, 'type' => 'topic' );
                }
            }

            // Get lesson quizzes
            $lesson_quizzes = learndash_get_lesson_quiz_list( $lesson_id );
            if ( ! empty( $lesson_quizzes ) ) {
                foreach ( $lesson_quizzes as $quiz ) {
                    if ( isset( $quiz['post'] ) && is_object( $quiz['post'] ) ) {
                        $all_steps[] = array( 'id' => $quiz['post']->ID, 'type' => 'quiz' );
                    }
                }
            }
        }

        // Get course quizzes
        $course_quizzes = learndash_course_get_quizzes( $course_id );
        if ( ! empty( $course_quizzes ) ) {
            foreach ( $course_quizzes as $quiz ) {
                $all_steps[] = array( 'id' => $quiz->ID, 'type' => 'quiz' );
            }
        }

        if ( empty( $all_steps ) ) {
            return false;
        }

        // Enhanced completion rate calculation with configurable 100% possibility
        $total_steps = count( $all_steps );
        
        // Use global completion percentage if set, otherwise default to 20%
        global $ldtt_completion_percentage;
        $completion_chance = isset( $ldtt_completion_percentage ) ? $ldtt_completion_percentage : 20;
        
        // Determine if this user gets 100% completion
        $completion_rate = wp_rand( 1, 100 ) <= $completion_chance ? 100 : wp_rand( $min_progress, $max_progress );
        
        // If max_progress is already 100, increase the chance to 30%
        if ( $max_progress >= 95 && $completion_chance < 30 ) {
            $completion_rate = wp_rand( 1, 100 ) <= 30 ? 100 : wp_rand( $min_progress, $max_progress );
        }
        
        $target = ceil( $total_steps * ( $completion_rate / 100 ) );

        // Shuffle steps for realistic non-linear completion
        shuffle( $all_steps );
        $steps_to_complete = array_slice( $all_steps, 0, $target );

        $marked = 0;
        $current_time = time() - wp_rand( 86400, 2592000 ); // 1-30 days ago

        foreach ( $steps_to_complete as $step ) {
            $step_id = $step['id'];
            
            // Skip if already completed
            if ( learndash_is_item_complete( $user_id, $step_id ) ) {
                continue;
            }

            // Use the correct LearnDash function with proper parameters
            if ( learndash_process_mark_complete( $user_id, $step_id, false, $course_id, false ) ) {
                $marked++;

                // Add realistic timing
                $current_time += wp_rand( 3600, 86400 ); // 1-24 hours between completions

                // Create activity entry for tracking
                $activity_args = array(
                    'user_id'           => $user_id,
                    'post_id'           => $step_id,
                    'course_id'         => $course_id,
                    'activity_type'     => $step['type'],
                    'activity_action'   => 'insert',
                    'activity_status'   => true,
                    'activity_started'  => $current_time - wp_rand( 300, 3600 ),
                    'activity_completed' => $current_time,
                );

                learndash_update_user_activity( $activity_args );
            }

            if ( $marked >= $target ) {
                break;
            }
        }

        // If 100% completion, mark the course as completed
        if ( $completion_rate >= 100 && function_exists( 'learndash_process_mark_complete' ) ) {
            learndash_process_mark_complete( $user_id, $course_id, false, $course_id, false );
            
            // Add course completion activity
            $course_activity_args = array(
                'user_id'           => $user_id,
                'post_id'           => $course_id,
                'course_id'         => $course_id,
                'activity_type'     => 'course',
                'activity_action'   => 'insert',
                'activity_status'   => true,
                'activity_started'  => $current_time - wp_rand( 86400, 604800 ), // Started 1-7 days ago
                'activity_completed' => $current_time,
            );
            learndash_update_user_activity( $course_activity_args );
        }

        // Store progress metadata
        update_user_meta( $user_id, '_ldtt_progress_created', current_time( 'timestamp' ) );
        update_user_meta( $user_id, '_ldtt_progress_completion_rate', $completion_rate );
        update_user_meta( $user_id, '_ldtt_progress_course_' . $course_id, array(
            'completion_rate' => $completion_rate,
            'items_completed' => $marked,
            'total_items' => $total_steps,
            'course_completed' => $completion_rate >= 100,
            'created' => current_time( 'timestamp' ),
        ) );

        return array(
            'completion_rate' => $completion_rate,
            'items_completed' => $marked,
            'total_items' => $total_steps,
            'course_completed' => $completion_rate >= 100,
        );
    }

    /**
     * Assign progress to existing enrolled users (UPDATED VERSION)
     * Enhanced with 100% completion tracking and better reporting
     *
     * @param array $args
     * @param array $assoc_args
     */
    public static function assign_progress_to_enrolled( $args = array(), $assoc_args = array() ) {
        // Check for admin input if no CLI arguments are provided
        if ( empty( $args ) && empty( $assoc_args ) ) {
            $assoc_args = array(
                'course_id'             => isset( $_POST['course_id'] ) ? intval( $_POST['course_id'] ) : null,
                'all_courses'           => isset( $_POST['all_courses'] ) ? true : false,
                'user_percentage'       => isset( $_POST['user_percentage'] ) ? intval( $_POST['user_percentage'] ) : 100,
                'min_progress'          => isset( $_POST['min_progress'] ) ? intval( $_POST['min_progress'] ) : 25,
                'max_progress'          => isset( $_POST['max_progress'] ) ? intval( $_POST['max_progress'] ) : 85,
                'completion_percentage' => isset( $_POST['completion_percentage'] ) ? intval( $_POST['completion_percentage'] ) : 20,
            );
        }

        $course_id = ! empty( $assoc_args['course_id'] ) ? absint( $assoc_args['course_id'] ) : null;
        $all_courses = isset( $assoc_args['all_courses'] ) && $assoc_args['all_courses'];
        $user_percentage = LDTT_Helper::validate_positive_int( $assoc_args['user_percentage'] ?? 100, 1, 100 );
        $min_progress = LDTT_Helper::validate_positive_int( $assoc_args['min_progress'] ?? 25, 0, 100 );
        $max_progress = LDTT_Helper::validate_positive_int( $assoc_args['max_progress'] ?? 85, $min_progress, 100 );
        $completion_percentage = LDTT_Helper::validate_positive_int( $assoc_args['completion_percentage'] ?? 20, 0, 100 );

        if ( ! $all_courses && ! $course_id ) {
            $message = 'Either --course_id or --all_courses must be specified.';
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::error( $message );
            }
            return array( 'status' => 'error', 'message' => $message );
        }

        $courses_to_process = $all_courses ? self::get_available_courses() : array( $course_id );
        
        if ( empty( $courses_to_process ) ) {
            $message = 'No courses found to process.';
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::error( $message );
            }
            return array( 'status' => 'error', 'message' => $message );
        }

        $total_users_processed = 0;
        $total_courses_processed = 0;
        $total_completed_courses = 0;

        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::line( "Processing " . count( $courses_to_process ) . " courses for progress assignment..." );
            WP_CLI::line( "User percentage to process: {$user_percentage}%" );
            WP_CLI::line( "Progress range: {$min_progress}% - {$max_progress}%" );
            WP_CLI::line( "100% completion chance: {$completion_percentage}%" );
            WP_CLI::line( "" );
        }

        foreach ( $courses_to_process as $current_course_id ) {
            $course_title = get_the_title( $current_course_id );
            
            // Skip if course doesn't exist
            if ( ! $course_title || get_post_type( $current_course_id ) !== learndash_get_post_type_slug( 'course' ) ) {
                if ( defined( 'WP_CLI' ) && WP_CLI ) {
                    WP_CLI::warning( "Skipping invalid course ID: {$current_course_id}" );
                }
                continue;
            }

            $enrolled_users = self::get_enrolled_users_for_course( $current_course_id );
            
            if ( empty( $enrolled_users ) ) {
                if ( defined( 'WP_CLI' ) && WP_CLI ) {
                    WP_CLI::line( "No enrolled users found for course: {$course_title} (ID: {$current_course_id})" );
                }
                continue;
            }

            // Calculate how many users to process based on percentage
            $users_to_process_count = round( count( $enrolled_users ) * ( $user_percentage / 100 ) );
            $users_to_process = array_slice( $enrolled_users, 0, $users_to_process_count );
            
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::line( "Course: {$course_title} (ID: {$current_course_id})" );
                WP_CLI::line( "  Total enrolled users: " . count( $enrolled_users ) );
                WP_CLI::line( "  Users to process ({$user_percentage}%): " . count( $users_to_process ) );
            }
            
            $course_users_processed = 0;
            $course_completed_count = 0;
            
            foreach ( $users_to_process as $user_id ) {
                // Ensure we have a valid user ID
                $user_id = absint( $user_id );
                if ( ! $user_id ) {
                    continue;
                }

                // Check if user exists
                $user = get_user_by( 'ID', $user_id );
                if ( ! $user ) {
                    continue;
                }

                // Check if user already has LDTT progress for this course
                $existing_progress = get_user_meta( $user_id, '_ldtt_progress_course_' . $current_course_id, true );
                if ( $existing_progress ) {
                    if ( defined( 'WP_CLI' ) && WP_CLI ) {
                        WP_CLI::line( "  - User {$user->user_login} (ID: {$user_id}) already has progress, skipping" );
                    }
                    continue;
                }

                $result = self::add_course_progress_with_completion( $user_id, $current_course_id, $min_progress, $max_progress, $completion_percentage );
                
                if ( $result ) {
                    $course_users_processed++;
                    $total_users_processed++;
                    
                    if ( $result['course_completed'] ) {
                        $course_completed_count++;
                        $total_completed_courses++;
                    }
                    
                    $completion_icon = $result['course_completed'] ? '🏆' : '📈';
                    
                    if ( defined( 'WP_CLI' ) && WP_CLI ) {
                        WP_CLI::line( "  {$completion_icon} User {$user->user_login} (ID: {$user_id}): {$result['completion_rate']}% progress ({$result['items_completed']}/{$result['total_items']} items)" );
                    }
                }
            }
            
            if ( $course_users_processed > 0 ) {
                $total_courses_processed++;
            }
            
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::line( "  ✓ Processed {$course_users_processed} users in this course ({$course_completed_count} completed)" );
                WP_CLI::line( "" );
            }
        }

        $message = "Added progress to {$total_users_processed} enrolled users across {$total_courses_processed} courses. {$total_completed_courses} users completed their courses (100%).";
        
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::success( $message );
        }
        
        return array(
            'status' => 'success',
            'message' => $message,
            'users_processed' => $total_users_processed,
            'courses_processed' => $total_courses_processed,
            'completed_courses' => $total_completed_courses,
        );
    }

    /**
     * Add course progress with controllable 100% completion rate
     * This is a wrapper method that calls add_course_progress with custom completion logic
     *
     * @param int $user_id
     * @param int $course_id
     * @param int $min_progress
     * @param int $max_progress
     * @param int $completion_percentage Percentage chance for 100% completion
     * @return array|false
     */
    private static function add_course_progress_with_completion( $user_id, $course_id, $min_progress = 25, $max_progress = 85, $completion_percentage = 20 ) {
        // Store the original completion percentage globally for the add_course_progress method
        global $ldtt_completion_percentage;
        $ldtt_completion_percentage = $completion_percentage;
        
        return self::add_course_progress( $user_id, $course_id, $min_progress, $max_progress );
    }

    /**
     * Get enrolled users for a course (UPDATED VERSION)
     * Enhanced with multiple detection methods and better validation
     *
     * @param int $course_id
     * @return array Array of user IDs
     */
    private static function get_enrolled_users_for_course( $course_id ) {
        $user_ids = array();

        // Method 1: Try LearnDash function first
        if ( function_exists( 'learndash_get_users_for_course' ) ) {
            $users = learndash_get_users_for_course( $course_id );
            if ( is_array( $users ) && ! empty( $users ) ) {
                foreach ( $users as $user ) {
                    if ( is_object( $user ) && isset( $user->ID ) ) {
                        $user_ids[] = intval( $user->ID );
                    } elseif ( is_numeric( $user ) ) {
                        $user_ids[] = intval( $user );
                    }
                }
                return array_unique( $user_ids );
            }
        }

        // Method 2: Check course access via user meta
        global $wpdb;
        $course_access_users = $wpdb->get_col( $wpdb->prepare( "
            SELECT user_id 
            FROM {$wpdb->usermeta} 
            WHERE meta_key = %s
            AND meta_value LIKE %s
        ", 
        'course_' . $course_id . '_access_from',
        '%'
        ) );

        if ( ! empty( $course_access_users ) ) {
            $user_ids = array_merge( $user_ids, array_map( 'intval', $course_access_users ) );
        }

        // Method 3: Check course progress meta
        $progress_users = $wpdb->get_col( $wpdb->prepare( "
            SELECT user_id 
            FROM {$wpdb->usermeta} 
            WHERE meta_key = %s
        ", 
        '_sfwd-course_progress'
        ) );

        foreach ( $progress_users as $user_id ) {
            $progress_data = get_user_meta( $user_id, '_sfwd-course_progress', true );
            if ( is_array( $progress_data ) && isset( $progress_data[ $course_id ] ) ) {
                $user_ids[] = intval( $user_id );
            }
        }

        // Method 4: Fallback - get users who have any course activity for this course
        if ( empty( $user_ids ) ) {
            $activity_users = $wpdb->get_col( $wpdb->prepare( "
                SELECT DISTINCT user_id 
                FROM {$wpdb->usermeta} 
                WHERE meta_key LIKE %s
                AND meta_value = %s
            ", 
            'course_%_access_from',
            $course_id
            ) );
            
            $user_ids = array_merge( $user_ids, array_map( 'intval', $activity_users ) );
        }

        // Remove duplicates and invalid IDs
        $user_ids = array_unique( array_filter( $user_ids ) );
        
        // Verify users exist
        $valid_user_ids = array();
        foreach ( $user_ids as $user_id ) {
            if ( get_user_by( 'ID', $user_id ) ) {
                $valid_user_ids[] = $user_id;
            }
        }

        return $valid_user_ids;
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
}