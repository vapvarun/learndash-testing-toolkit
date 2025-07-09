<?php

/**
 * Updated Enrollment Command with Enhanced Parameters
 */
class LDTT_Enrollment {

    /**
     * Handle the enrollment command with enhanced parameters
     *
     * @param array $args
     * @param array $assoc_args
     * @return array
     */
    public static function handle( $args = array(), $assoc_args = array() ) {
        // Check for admin input if no CLI arguments are provided
        if ( empty( $args ) && empty( $assoc_args ) ) {
            $assoc_args = array(
                'course_id'          => isset( $_POST['course_id'] ) ? intval( $_POST['course_id'] ) : null,
                'count'              => isset( $_POST['count'] ) ? intval( $_POST['count'] ) : 10,
                'use_existing'       => isset( $_POST['use_existing_users'] ) && $_POST['use_existing_users'] ? true : false,
                'add_progress'       => isset( $_POST['add_progress'] ) ? true : false,
                'progress_percentage' => isset( $_POST['progress_percentage'] ) ? intval( $_POST['progress_percentage'] ) : null,
                'user_role'          => isset( $_POST['user_role'] ) ? sanitize_text_field( $_POST['user_role'] ) : 'subscriber',
                'user_prefix'        => isset( $_POST['user_prefix'] ) ? sanitize_text_field( $_POST['user_prefix'] ) : 'student',
                'dry_run'            => isset( $_POST['dry_run'] ) ? true : false,
                'verbose'            => isset( $_POST['verbose'] ) ? true : false,
            );
        }

        $course_id = ! empty( $assoc_args['course_id'] ) ? absint( $assoc_args['course_id'] ) : null;
        $user_count = LDTT_Helper::validate_positive_int( $assoc_args['count'] ?? 10, 1, 500 );
        $use_existing = isset( $assoc_args['use_existing'] ) && $assoc_args['use_existing'];
        $add_progress = isset( $assoc_args['add_progress'] ) && $assoc_args['add_progress'];
        $progress_percentage = isset( $assoc_args['progress_percentage'] ) ? intval( $assoc_args['progress_percentage'] ) : null;
        $user_role = sanitize_text_field( $assoc_args['user_role'] ?? 'subscriber' );
        $user_prefix = sanitize_text_field( $assoc_args['user_prefix'] ?? 'student' );
        $dry_run = isset( $assoc_args['dry_run'] ) && $assoc_args['dry_run'];
        $verbose = isset( $assoc_args['verbose'] ) && $assoc_args['verbose'];

        if ( ! $course_id ) {
            $message = 'Course ID is required.';
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::error( $message );
            }
            return array( 'status' => 'error', 'message' => $message );
        }

        // Validate course ID
        if ( get_post_type( $course_id ) !== learndash_get_post_type_slug( 'course' ) ) {
            $message = "Course ID {$course_id} is not a valid course.";
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::error( $message );
            }
            return array( 'status' => 'error', 'message' => $message );
        }

        // Validate progress percentage if provided
        if ( $progress_percentage !== null && ( $progress_percentage < 0 || $progress_percentage > 100 ) ) {
            $message = 'Progress percentage must be between 0 and 100.';
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::error( $message );
            }
            return array( 'status' => 'error', 'message' => $message );
        }

        $course_title = get_the_title( $course_id );

        // Show what will be done if dry run
        if ( $dry_run ) {
            return self::show_dry_run_preview( $course_id, $course_title, $user_count, $use_existing, $add_progress, $progress_percentage, $user_role, $user_prefix );
        }

        if ( $verbose && defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::line( "Starting enrollment process..." );
            WP_CLI::line( "Course: {$course_title} (ID: {$course_id})" );
            WP_CLI::line( "Users to process: {$user_count}" );
            WP_CLI::line( "Method: " . ( $use_existing ? 'Use existing users' : 'Create new users' ) );
            WP_CLI::line( "User role: {$user_role}" );
            if ( ! $use_existing ) {
                WP_CLI::line( "Username prefix: {$user_prefix}" );
            }
            if ( $add_progress ) {
                $progress_text = $progress_percentage ? "{$progress_percentage}%" : "random (25-85%)";
                WP_CLI::line( "Add progress: {$progress_text}" );
            }
            WP_CLI::line( "" );
        }

        // Choose method based on use_existing flag
        if ( $use_existing ) {
            $result = self::enroll_existing_users( $course_id, $user_count, $user_role, $add_progress, $progress_percentage, $verbose );
        } else {
            $result = self::create_and_enroll_users( $course_id, $user_count, $user_role, $user_prefix, $add_progress, $progress_percentage, $verbose );
        }

        if ( is_wp_error( $result ) ) {
            $message = $result->get_error_message();
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::error( $message );
            }
            return array( 'status' => 'error', 'message' => $message );
        }

        $action_text = $use_existing ? 'enrolled existing' : 'created and enrolled';
        $progress_text = $add_progress ? ' with progress added' : '';
        $message = count( $result['user_ids'] ) . " users successfully {$action_text} in course '{$course_title}'{$progress_text}.";
        
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::success( $message );
            if ( $verbose ) {
                WP_CLI::line( 'User IDs: ' . implode( ', ', $result['user_ids'] ) );
                if ( $add_progress && ! empty( $result['progress_added'] ) ) {
                    WP_CLI::line( "Progress added to {$result['progress_added']} users" );
                }
            }
        }
        
        return array(
            'status'  => 'success',
            'message' => $message,
            'user_ids' => $result['user_ids'],
            'method' => $use_existing ? 'existing_users' : 'new_users',
            'progress_added' => $result['progress_added'] ?? 0,
        );
    }

    /**
     * Show dry run preview
     */
    private static function show_dry_run_preview( $course_id, $course_title, $user_count, $use_existing, $add_progress, $progress_percentage, $user_role, $user_prefix ) {
        $preview = array(
            'course' => array(
                'id' => $course_id,
                'title' => $course_title,
            ),
            'users' => array(
                'count' => $user_count,
                'method' => $use_existing ? 'existing' : 'new',
                'role' => $user_role,
                'prefix' => $use_existing ? 'N/A' : $user_prefix,
            ),
            'progress' => array(
                'enabled' => $add_progress,
                'percentage' => $progress_percentage ?: 'random (25-85%)',
            ),
        );

        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::line( "=== DRY RUN PREVIEW ===" );
            WP_CLI::line( "Course: {$course_title} (ID: {$course_id})" );
            WP_CLI::line( "Users: {$user_count} " . ( $use_existing ? 'existing users' : 'new users to create' ) );
            WP_CLI::line( "Role: {$user_role}" );
            if ( ! $use_existing ) {
                WP_CLI::line( "Username format: {$user_prefix}_XXXXXXXX" );
            }
            if ( $add_progress ) {
                $progress_text = $progress_percentage ? "{$progress_percentage}%" : "random between 25-85%";
                WP_CLI::line( "Progress: {$progress_text}" );
            }
            WP_CLI::line( "" );
            WP_CLI::line( "Run without --dry_run to execute." );
        }

        return array(
            'status' => 'success',
            'message' => 'Dry run completed',
            'preview' => $preview,
        );
    }

    /**
     * Enroll existing users in a course
     */
    private static function enroll_existing_users( $course_id, $user_count, $user_role, $add_progress = false, $progress_percentage = null, $verbose = false ) {
        // Get existing users (excluding administrators)
        $existing_users = get_users( array(
            'role__not_in' => array( 'administrator' ),
            'fields' => 'ID',
            'number' => $user_count * 2, // Get more than needed for filtering
        ) );

        if ( empty( $existing_users ) ) {
            return new WP_Error( 'no_existing_users', 'No existing users found to enroll.' );
        }

        // Convert to integers and shuffle for random selection
        $user_ids = array_map( 'absint', $existing_users );
        shuffle( $user_ids );

        // Limit to requested count
        $users_to_enroll = array_slice( $user_ids, 0, $user_count );
        $enrolled_users = array();
        $progress_added = 0;

        foreach ( $users_to_enroll as $user_id ) {
            // Check if user is already enrolled
            if ( self::is_user_enrolled( $user_id, $course_id ) ) {
                if ( $verbose && defined( 'WP_CLI' ) && WP_CLI ) {
                    WP_CLI::line( "User {$user_id} already enrolled, skipping." );
                }
                continue;
            }

            // Update user role if different
            $user = new WP_User( $user_id );
            if ( ! in_array( $user_role, $user->roles ) ) {
                $user->set_role( $user_role );
                if ( $verbose && defined( 'WP_CLI' ) && WP_CLI ) {
                    WP_CLI::line( "Updated user {$user_id} role to {$user_role}" );
                }
            }

            // Enroll the user in the course
            $result = ld_update_course_access( $user_id, $course_id, false );
            
            if ( $result !== false ) {
                // Mark user as managed by LDTT for this operation
                update_user_meta( $user_id, '_ldtt_enrolled_course_' . $course_id, current_time( 'timestamp' ) );
                
                $enrolled_users[] = $user_id;

                // Add progress if requested
                if ( $add_progress ) {
                    self::add_user_progress( $user_id, $course_id, $progress_percentage );
                    $progress_added++;
                }

                if ( $verbose && defined( 'WP_CLI' ) && WP_CLI ) {
                    $user = get_user_by( 'ID', $user_id );
                    $progress_text = $add_progress ? ' with progress' : '';
                    WP_CLI::line( "Enrolled existing user: {$user->user_login} (ID: {$user_id}){$progress_text}" );
                }
            } else {
                if ( $verbose && defined( 'WP_CLI' ) && WP_CLI ) {
                    WP_CLI::warning( "Failed to enroll user ID {$user_id} in the course." );
                }
            }
        }

        if ( empty( $enrolled_users ) ) {
            return new WP_Error( 'enrollment_failed', 'No users could be enrolled. They may already be enrolled.' );
        }

        return array(
            'user_ids' => $enrolled_users,
            'progress_added' => $progress_added,
        );
    }

    /**
     * Create new users and enroll them in a course
     */
    private static function create_and_enroll_users( $course_id, $user_count, $user_role, $user_prefix, $add_progress = false, $progress_percentage = null, $verbose = false ) {
        $user_ids = array();
        $progress_added = 0;

        for ( $i = 1; $i <= $user_count; $i++ ) {
            $username = $user_prefix . '_' . LDTT_Helper::generate_random_string( 8 );
            $email = $username . '@example.com';

            $user_id = wp_create_user( $username, wp_generate_password( 12 ), $email );

            if ( is_wp_error( $user_id ) ) {
                if ( $verbose && defined( 'WP_CLI' ) && WP_CLI ) {
                    WP_CLI::warning( "Failed to create user: {$username}" );
                }
                continue;
            }

            // Assign the specified role to the user
            $user = new WP_User( $user_id );
            $user->set_role( $user_role );

            // Set display name
            wp_update_user( array(
                'ID'           => $user_id,
                'display_name' => ucfirst( $user_prefix ) . ' ' . $i,
                'first_name'   => ucfirst( $user_prefix ),
                'last_name'    => $i,
            ) );

            // Mark as test user
            update_user_meta( $user_id, '_ldtt_test_user', true );
            update_user_meta( $user_id, '_ldtt_user_type', 'course_enrolled' );

            // Enroll the user in the course
            $result = ld_update_course_access( $user_id, $course_id, false );
            if ( ! $result && $verbose && defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::warning( "Failed to enroll user '{$username}' in the course." );
            }

            // Add progress if requested
            if ( $add_progress ) {
                self::add_user_progress( $user_id, $course_id, $progress_percentage );
                $progress_added++;
            }

            $user_ids[] = $user_id;

            if ( $verbose && defined( 'WP_CLI' ) && WP_CLI ) {
                $progress_text = $add_progress ? ' with progress' : '';
                WP_CLI::line( "Created and enrolled user: {$username} (ID: {$user_id}){$progress_text}" );
            }
        }

        return array(
            'user_ids' => $user_ids,
            'progress_added' => $progress_added,
        );
    }

    /**
     * Add progress to a user for a course using the correct approach
     */
    private static function add_user_progress( $user_id, $course_id, $progress_percentage = null ) {
        if ( ! function_exists( 'learndash_process_mark_complete' ) ) {
            return false;
        }

        // Get lessons first
        $lessons = learndash_course_get_lessons( $course_id );
        if ( empty( $lessons ) ) {
            return false;
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
            return false;
        }

        // Use specific percentage or random
        $completion_rate = $progress_percentage ?: wp_rand( 25, 85 );
        $total_steps = count( $all_steps );
        $target = ceil( $total_steps * ( $completion_rate / 100 ) );

        $marked = 0;
        foreach ( $all_steps as $step ) {
            $step_id = $step['id'];
            
            if ( learndash_is_item_complete( $user_id, $step_id ) ) {
                continue;
            }

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

        return true;
    }

    /**
     * Check if a user is already enrolled in a course
     */
    private static function is_user_enrolled( $user_id, $course_id ) {
        // Try LearnDash function first
        if ( function_exists( 'learndash_user_get_enrolled_courses' ) ) {
            $enrolled_courses = learndash_user_get_enrolled_courses( $user_id );
            return in_array( $course_id, $enrolled_courses );
        }

        // Fallback method - check user meta
        $course_progress = get_user_meta( $user_id, '_sfwd-course_progress', true );
        if ( is_array( $course_progress ) && isset( $course_progress[ $course_id ] ) ) {
            return true;
        }

        return false;
    }
}