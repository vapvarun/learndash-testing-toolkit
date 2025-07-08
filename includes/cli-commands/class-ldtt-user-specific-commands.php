<?php

/**
 * Add Progress to Specific User Command
 * 
 * @package LearnDash_Testing_Toolkit
 * @since 1.2.0
 */
class LDTT_Add_User_Progress {

    /**
     * Handle the command to add progress to a specific user
     *
     * @param array $args Positional arguments.
     * @param array $assoc_args Associative arguments.
     */
    public static function handle( $args = array(), $assoc_args = array() ) {
        // Check for admin input if no CLI arguments are provided
        if ( empty( $args ) && empty( $assoc_args ) ) {
            $assoc_args = array(
                'user_id'            => isset( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : null,
                'course_selection'   => isset( $_POST['course_selection'] ) ? sanitize_text_field( $_POST['course_selection'] ) : 'specific',
                'specific_course_id' => isset( $_POST['specific_course_id'] ) ? intval( $_POST['specific_course_id'] ) : null,
                'min_progress'       => isset( $_POST['min_progress'] ) ? intval( $_POST['min_progress'] ) : 25,
                'max_progress'       => isset( $_POST['max_progress'] ) ? intval( $_POST['max_progress'] ) : 100,
                'overwrite_existing' => isset( $_POST['overwrite_existing'] ) ? true : false,
                'include_quizzes'    => isset( $_POST['include_quizzes'] ) ? true : false,
                'realistic_timestamps' => isset( $_POST['realistic_timestamps'] ) ? true : false,
            );
        }

        $user_id = ! empty( $assoc_args['user_id'] ) ? absint( $assoc_args['user_id'] ) : null;
        $course_selection = sanitize_text_field( $assoc_args['course_selection'] ?? 'specific' );
        $specific_course_id = ! empty( $assoc_args['specific_course_id'] ) ? absint( $assoc_args['specific_course_id'] ) : null;
        $min_progress = LDTT_Helper::validate_positive_int( $assoc_args['min_progress'] ?? 25, 25, 100 );
        $max_progress = LDTT_Helper::validate_positive_int( $assoc_args['max_progress'] ?? 100, $min_progress, 100 );
        $overwrite_existing = isset( $assoc_args['overwrite_existing'] ) && $assoc_args['overwrite_existing'];
        $include_quizzes = isset( $assoc_args['include_quizzes'] ) && $assoc_args['include_quizzes'];
        $realistic_timestamps = isset( $assoc_args['realistic_timestamps'] ) && $assoc_args['realistic_timestamps'];

        // Validate user ID
        if ( ! $user_id ) {
            $message = 'User ID is required.';
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::error( $message );
            }
            return array( 'status' => 'error', 'message' => $message );
        }

        $user = get_user_by( 'ID', $user_id );
        if ( ! $user ) {
            $message = "User with ID {$user_id} not found.";
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::error( $message );
            }
            return array( 'status' => 'error', 'message' => $message );
        }

        // Get courses to process
        $courses_to_process = self::get_courses_for_user( $user_id, $course_selection, $specific_course_id );
        
        if ( empty( $courses_to_process ) ) {
            $message = 'No courses found for progress creation.';
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::error( $message );
            }
            return array( 'status' => 'error', 'message' => $message );
        }

        $progress_added = 0;
        $results = array();

        foreach ( $courses_to_process as $course_id ) {
            $course_title = get_the_title( $course_id );
            
            // Check if user already has progress
            $existing_progress = get_user_meta( $user_id, '_ldtt_progress_course_' . $course_id, true );
            
            if ( ! $existing_progress || $overwrite_existing ) {
                $progress_options = array(
                    'min_completion' => $min_progress,
                    'max_completion' => $max_progress,
                    'include_quizzes' => $include_quizzes,
                    'realistic_timestamps' => $realistic_timestamps,
                );

                $result = self::create_progress_for_user( $user_id, $course_id, $progress_options );
                
                if ( $result ) {
                    $progress_added++;
                    $results[ $course_id ] = $result;
                    
                    if ( defined( 'WP_CLI' ) && WP_CLI ) {
                        WP_CLI::line( "✓ Added {$result['completion_rate']}% progress for user {$user_id} in course '{$course_title}' (ID: {$course_id})" );
                    }
                } else {
                    if ( defined( 'WP_CLI' ) && WP_CLI ) {
                        WP_CLI::warning( "Failed to add progress for course '{$course_title}' (ID: {$course_id})" );
                    }
                }
            } else {
                if ( defined( 'WP_CLI' ) && WP_CLI ) {
                    WP_CLI::line( "- User {$user_id} already has progress in course '{$course_title}' (use --overwrite_existing to replace)" );
                }
            }
        }

        $message = "Added progress to {$progress_added} courses for user {$user->user_login} (ID: {$user_id}).";
        
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::success( $message );
        }
        
        return array(
            'status' => 'success',
            'message' => $message,
            'user_id' => $user_id,
            'courses_processed' => count( $courses_to_process ),
            'progress_added' => $progress_added,
            'results' => $results,
        );
    }

    /**
     * Get courses for a user based on selection criteria
     * 
     * @param int $user_id
     * @param string $course_selection
     * @param int $specific_course_id
     * @return array
     */
    private static function get_courses_for_user( $user_id, $course_selection, $specific_course_id ) {
        if ( $course_selection === 'specific' && $specific_course_id ) {
            // Validate specific course
            if ( get_post_type( $specific_course_id ) !== learndash_get_post_type_slug( 'course' ) ) {
                return array();
            }
            return array( $specific_course_id );
        } elseif ( $course_selection === 'enrolled' ) {
            // Get all enrolled courses for user
            return self::get_user_enrolled_courses( $user_id );
        }
        
        return array();
    }

    /**
     * Get courses a user is enrolled in
     * 
     * @param int $user_id
     * @return array
     */
    private static function get_user_enrolled_courses( $user_id ) {
        // Try LearnDash function first
        if ( function_exists( 'learndash_user_get_enrolled_courses' ) ) {
            return learndash_user_get_enrolled_courses( $user_id );
        }
        
        // Fallback method
        global $wpdb;
        
        $courses = $wpdb->get_col( $wpdb->prepare( "
            SELECT DISTINCT pm.post_id
            FROM {$wpdb->postmeta} pm
            JOIN {$wpdb->usermeta} um ON um.meta_key LIKE CONCAT('course_', pm.post_id, '_%')
            WHERE um.user_id = %d
            AND pm.meta_key = '_sfwd-courses'
        ", $user_id ) );
        
        return array_map( 'absint', $courses );
    }

    /**
     * Create progress for a user in a specific course
     * 
     * @param int $user_id
     * @param int $course_id
     * @param array $options
     * @return array|false
     */
    private static function create_progress_for_user( $user_id, $course_id, $options = array() ) {
        if ( class_exists( 'LDTT_Progress_Manager' ) ) {
            return LDTT_Progress_Manager::create_realistic_progress( $user_id, $course_id, $options );
        } else {
            // Fallback to basic progress creation
            return self::create_basic_progress( $user_id, $course_id, $options );
        }
    }

    /**
     * Create basic progress for a user (fallback method)
     * 
     * @param int $user_id
     * @param int $course_id
     * @param array $options
     * @return array|false
     */
    private static function create_basic_progress( $user_id, $course_id, $options = array() ) {
        $defaults = array(
            'min_completion' => 25,
            'max_completion' => 100,
            'include_quizzes' => true,
            'realistic_timestamps' => true,
        );
        
        $options = wp_parse_args( $options, $defaults );
        
        // Get course lessons
        $lessons = get_posts( array(
            'post_type' => learndash_get_post_type_slug( 'lesson' ),
            'meta_key' => 'learndash_course',
            'meta_value' => $course_id,
            'numberposts' => -1,
            'fields' => 'ids'
        ) );

        if ( empty( $lessons ) ) {
            return false;
        }

        // Complete random lessons based on completion rate
        $completion_rate = wp_rand( $options['min_completion'], $options['max_completion'] );
        $lessons_to_complete = round( count( $lessons ) * ( $completion_rate / 100 ) );
        
        $lessons_to_complete = min( $lessons_to_complete, count( $lessons ) );
        $selected_lessons = array_slice( $lessons, 0, $lessons_to_complete );

        $current_time = $options['realistic_timestamps'] ? strtotime( '-' . wp_rand( 1, 30 ) . ' days' ) : time();

        foreach ( $selected_lessons as $lesson_id ) {
            if ( $options['realistic_timestamps'] ) {
                $current_time += wp_rand( 3600, 259200 ); // 1 hour to 3 days between lessons
            }
            
            learndash_process_mark_complete( $user_id, $lesson_id, false, $course_id );
            
            // Handle topics
            $topics = learndash_get_topic_list( $lesson_id, $course_id );
            if ( ! empty( $topics ) ) {
                $topic_completion_rate = wp_rand( 50, 100 );
                $topics_to_complete = round( count( $topics ) * ( $topic_completion_rate / 100 ) );
                
                $completed_topics = array_slice( $topics, 0, $topics_to_complete );
                foreach ( $completed_topics as $topic ) {
                    if ( $options['realistic_timestamps'] ) {
                        $current_time += wp_rand( 300, 1800 ); // 5-30 minutes between topics
                    }
                    
                    learndash_process_mark_complete( $user_id, $topic->ID, false, $course_id );
                }
            }
        }

        // Store progress metadata
        update_user_meta( $user_id, '_ldtt_progress_created', current_time( 'timestamp' ) );
        update_user_meta( $user_id, '_ldtt_progress_completion_rate', $completion_rate );
        update_user_meta( $user_id, '_ldtt_progress_course_' . $course_id, array(
            'completion_rate' => $completion_rate,
            'items_completed' => $lessons_to_complete,
            'total_items' => count( $lessons ),
            'created' => current_time( 'timestamp' ),
        ) );

        return array(
            'completion_rate' => $completion_rate,
            'items_completed' => $lessons_to_complete,
            'total_items' => count( $lessons ),
        );
    }
}

/**
 * Enroll User in Courses Command
 * 
 * @package LearnDash_Testing_Toolkit
 * @since 1.2.0
 */
class LDTT_Enroll_User_Courses {

    /**
     * Handle the command to enroll a specific user in courses
     *
     * @param array $args Positional arguments.
     * @param array $assoc_args Associative arguments.
     */
    public static function handle( $args = array(), $assoc_args = array() ) {
        // Check for admin input if no CLI arguments are provided
        if ( empty( $args ) && empty( $assoc_args ) ) {
            $assoc_args = array(
                'user_id'           => isset( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : null,
                'course_ids'        => isset( $_POST['course_ids'] ) ? sanitize_text_field( $_POST['course_ids'] ) : '',
                'auto_progress'     => isset( $_POST['auto_progress'] ) ? true : false,
                'auto_min_progress' => isset( $_POST['auto_min_progress'] ) ? intval( $_POST['auto_min_progress'] ) : 25,
                'auto_max_progress' => isset( $_POST['auto_max_progress'] ) ? intval( $_POST['auto_max_progress'] ) : 100,
            );
        }

        $user_id = ! empty( $assoc_args['user_id'] ) ? absint( $assoc_args['user_id'] ) : null;
        $course_ids_string = sanitize_text_field( $assoc_args['course_ids'] ?? '' );
        $auto_progress = isset( $assoc_args['auto_progress'] ) && $assoc_args['auto_progress'];
        $auto_min_progress = LDTT_Helper::validate_positive_int( $assoc_args['auto_min_progress'] ?? 25, 25, 100 );
        $auto_max_progress = LDTT_Helper::validate_positive_int( $assoc_args['auto_max_progress'] ?? 100, $auto_min_progress, 100 );

        // Validate user ID
        if ( ! $user_id ) {
            $message = 'User ID is required.';
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::error( $message );
            }
            return array( 'status' => 'error', 'message' => $message );
        }

        $user = get_user_by( 'ID', $user_id );
        if ( ! $user ) {
            $message = "User with ID {$user_id} not found.";
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::error( $message );
            }
            return array( 'status' => 'error', 'message' => $message );
        }

        // Parse course IDs
        $course_ids = self::parse_course_ids( $course_ids_string );
        if ( empty( $course_ids ) ) {
            $message = 'No valid course IDs provided.';
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::error( $message );
            }
            return array( 'status' => 'error', 'message' => $message );
        }

        $enrolled_count = 0;
        $progress_count = 0;
        $results = array();

        foreach ( $course_ids as $course_id ) {
            $course_title = get_the_title( $course_id );
            
            // Validate course exists
            if ( get_post_type( $course_id ) !== learndash_get_post_type_slug( 'course' ) ) {
                if ( defined( 'WP_CLI' ) && WP_CLI ) {
                    WP_CLI::warning( "Course ID {$course_id} is not a valid course, skipping." );
                }
                continue;
            }

            // Enroll user in course
            $enrollment_result = ld_update_course_access( $user_id, $course_id, false );
            
            if ( $enrollment_result !== false ) {
                $enrolled_count++;
                $results[ $course_id ] = array( 'enrolled' => true );
                
                if ( defined( 'WP_CLI' ) && WP_CLI ) {
                    WP_CLI::line( "✓ Enrolled user {$user_id} in course '{$course_title}' (ID: {$course_id})" );
                }

                // Add automatic progress if requested
                if ( $auto_progress ) {
                    $progress_options = array(
                        'min_completion' => $auto_min_progress,
                        'max_completion' => $auto_max_progress,
                        'include_quizzes' => true,
                        'realistic_timestamps' => true,
                    );

                    $progress_result = self::create_progress_for_user( $user_id, $course_id, $progress_options );
                    
                    if ( $progress_result ) {
                        $progress_count++;
                        $results[ $course_id ]['progress'] = $progress_result;
                        
                        if ( defined( 'WP_CLI' ) && WP_CLI ) {
                            WP_CLI::line( "  ✓ Added {$progress_result['completion_rate']}% progress" );
                        }
                    }
                }
            } else {
                if ( defined( 'WP_CLI' ) && WP_CLI ) {
                    WP_CLI::warning( "Failed to enroll user {$user_id} in course '{$course_title}' (ID: {$course_id})" );
                }
            }
        }

        $progress_text = $auto_progress ? " and added progress to {$progress_count} courses" : '';
        $message = "Enrolled user {$user->user_login} (ID: {$user_id}) in {$enrolled_count} courses{$progress_text}.";
        
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::success( $message );
        }
        
        return array(
            'status' => 'success',
            'message' => $message,
            'user_id' => $user_id,
            'courses_enrolled' => $enrolled_count,
            'progress_added' => $progress_count,
            'results' => $results,
        );
    }

    /**
     * Parse course IDs from string
     * 
     * @param string $course_ids_string
     * @return array
     */
    private static function parse_course_ids( $course_ids_string ) {
        if ( empty( $course_ids_string ) ) {
            return array();
        }

        $ids = explode( ',', $course_ids_string );
        $valid_ids = array();

        foreach ( $ids as $id ) {
            $id = trim( $id );
            if ( is_numeric( $id ) && $id > 0 ) {
                $valid_ids[] = absint( $id );
            }
        }

        return array_unique( $valid_ids );
    }

    /**
     * Create progress for a user in a specific course
     * 
     * @param int $user_id
     * @param int $course_id
     * @param array $options
     * @return array|false
     */
    private static function create_progress_for_user( $user_id, $course_id, $options = array() ) {
        if ( class_exists( 'LDTT_Progress_Manager' ) ) {
            return LDTT_Progress_Manager::create_realistic_progress( $user_id, $course_id, $options );
        } else {
            // Use the same fallback method as in LDTT_Add_User_Progress
            return LDTT_Add_User_Progress::create_basic_progress( $user_id, $course_id, $options );
        }
    }
}

/**
 * User Information Command
 * 
 * @package LearnDash_Testing_Toolkit
 * @since 1.2.0
 */
class LDTT_User_Info {

    /**
     * Handle the command to get user information
     *
     * @param array $args Positional arguments.
     * @param array $assoc_args Associative arguments.
     */
    public static function handle( $args = array(), $assoc_args = array() ) {
        // Check for admin input if no CLI arguments are provided
        if ( empty( $args ) && empty( $assoc_args ) ) {
            $assoc_args = array(
                'user_id' => isset( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : null,
            );
        }

        $user_id = ! empty( $assoc_args['user_id'] ) ? absint( $assoc_args['user_id'] ) : null;

        // Validate user ID
        if ( ! $user_id ) {
            $message = 'User ID is required.';
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::error( $message );
            }
            return array( 'status' => 'error', 'message' => $message );
        }

        $user = get_user_by( 'ID', $user_id );
        if ( ! $user ) {
            $message = "User with ID {$user_id} not found.";
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::error( $message );
            }
            return array( 'status' => 'error', 'message' => $message );
        }

        // Gather user information
        $user_info = self::get_user_info( $user );

        // Display information
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            self::display_user_info_cli( $user_info );
        }

        return array(
            'status' => 'success',
            'message' => "User information retrieved for {$user->user_login}",
            'user_info' => $user_info,
        );
    }

    /**
     * Get comprehensive user information
     * 
     * @param WP_User $user
     * @return array
     */
    private static function get_user_info( $user ) {
        $user_id = $user->ID;
        
        // Basic user info
        $info = array(
            'basic' => array(
                'id' => $user_id,
                'username' => $user->user_login,
                'email' => $user->user_email,
                'display_name' => $user->display_name,
                'first_name' => get_user_meta( $user_id, 'first_name', true ),
                'last_name' => get_user_meta( $user_id, 'last_name', true ),
                'roles' => $user->roles,
                'registered' => $user->user_registered,
            ),
            'ldtt' => array(
                'is_test_user' => (bool) get_user_meta( $user_id, '_ldtt_test_user', true ),
                'user_type' => get_user_meta( $user_id, '_ldtt_user_type', true ),
                'created_by_ldtt' => (bool) get_user_meta( $user_id, '_ldtt_created', true ),
            ),
            'enrollments' => array(),
            'progress' => array(),
            'groups' => array(),
        );

        // Get course enrollments
        $enrolled_courses = self::get_user_enrolled_courses( $user_id );
        foreach ( $enrolled_courses as $course_id ) {
            $course_title = get_the_title( $course_id );
            $progress_meta = get_user_meta( $user_id, '_ldtt_progress_course_' . $course_id, true );
            
            $info['enrollments'][ $course_id ] = array(
                'title' => $course_title,
                'has_ldtt_progress' => ! empty( $progress_meta ),
                'completion_rate' => $progress_meta['completion_rate'] ?? null,
            );
        }

        // Get group memberships
        if ( function_exists( 'learndash_get_users_group_ids' ) ) {
            $group_ids = learndash_get_users_group_ids( $user_id );
            foreach ( $group_ids as $group_id ) {
                $group_title = get_the_title( $group_id );
                $info['groups'][ $group_id ] = array(
                    'title' => $group_title,
                    'role' => self::get_user_group_role( $user_id, $group_id ),
                );
            }
        }

        // Get progress statistics
        $info['progress'] = array(
            'total_courses' => count( $enrolled_courses ),
            'courses_with_progress' => count( array_filter( $info['enrollments'], function( $course ) {
                return $course['has_ldtt_progress'];
            } ) ),
            'average_completion' => self::calculate_average_completion( $info['enrollments'] ),
        );

        return $info;
    }

    /**
     * Get courses a user is enrolled in
     * 
     * @param int $user_id
     * @return array
     */
    private static function get_user_enrolled_courses( $user_id ) {
        if ( function_exists( 'learndash_user_get_enrolled_courses' ) ) {
            return learndash_user_get_enrolled_courses( $user_id );
        }
        
        // Fallback method
        global $wpdb;
        
        $courses = $wpdb->get_col( $wpdb->prepare( "
            SELECT DISTINCT pm.post_id
            FROM {$wpdb->postmeta} pm
            JOIN {$wpdb->usermeta} um ON um.meta_key LIKE CONCAT('course_', pm.post_id, '_%')
            WHERE um.user_id = %d
            AND pm.meta_key = '_sfwd-courses'
        ", $user_id ) );
        
        return array_map( 'absint', $courses );
    }

    /**
     * Get user's role in a specific group
     * 
     * @param int $user_id
     * @param int $group_id
     * @return string
     */
    private static function get_user_group_role( $user_id, $group_id ) {
        // Check if user is group leader
        $group_leaders = get_post_meta( $group_id, '_ld_group_administrators', true );
        if ( is_array( $group_leaders ) && in_array( $user_id, $group_leaders ) ) {
            return 'leader';
        }
        
        return 'member';
    }

    /**
     * Calculate average completion rate
     * 
     * @param array $enrollments
     * @return float
     */
    private static function calculate_average_completion( $enrollments ) {
        $rates = array_filter( array_column( $enrollments, 'completion_rate' ) );
        
        if ( empty( $rates ) ) {
            return 0.0;
        }
        
        return round( array_sum( $rates ) / count( $rates ), 2 );
    }

    /**
     * Display user information via CLI
     * 
     * @param array $user_info
     */
    private static function display_user_info_cli( $user_info ) {
        $basic = $user_info['basic'];
        $ldtt = $user_info['ldtt'];
        $progress = $user_info['progress'];
        
        WP_CLI::line( "\n=== USER INFORMATION ===" );
        WP_CLI::line( "ID: {$basic['id']}" );
        WP_CLI::line( "Username: {$basic['username']}" );
        WP_CLI::line( "Email: {$basic['email']}" );
        WP_CLI::line( "Display Name: {$basic['display_name']}" );
        WP_CLI::line( "Roles: " . implode( ', ', $basic['roles'] ) );
        WP_CLI::line( "Registered: {$basic['registered']}" );
        
        WP_CLI::line( "\n=== LDTT INFORMATION ===" );
        WP_CLI::line( "Is Test User: " . ( $ldtt['is_test_user'] ? 'Yes' : 'No' ) );
        WP_CLI::line( "User Type: " . ( $ldtt['user_type'] ?: 'N/A' ) );
        WP_CLI::line( "Created by LDTT: " . ( $ldtt['created_by_ldtt'] ? 'Yes' : 'No' ) );
        
        WP_CLI::line( "\n=== COURSE ENROLLMENTS ===" );
        if ( ! empty( $user_info['enrollments'] ) ) {
            foreach ( $user_info['enrollments'] as $course_id => $course ) {
                $progress_text = $course['has_ldtt_progress'] ? " ({$course['completion_rate']}% complete)" : ' (no LDTT progress)';
                WP_CLI::line( "- {$course['title']} (ID: {$course_id}){$progress_text}" );
            }
        } else {
            WP_CLI::line( "No course enrollments found." );
        }
        
        WP_CLI::line( "\n=== GROUP MEMBERSHIPS ===" );
        if ( ! empty( $user_info['groups'] ) ) {
            foreach ( $user_info['groups'] as $group_id => $group ) {
                WP_CLI::line( "- {$group['title']} (ID: {$group_id}) - Role: {$group['role']}" );
            }
        } else {
            WP_CLI::line( "No group memberships found." );
        }
        
        WP_CLI::line( "\n=== PROGRESS SUMMARY ===" );
        WP_CLI::line( "Total Courses: {$progress['total_courses']}" );
        WP_CLI::line( "Courses with LDTT Progress: {$progress['courses_with_progress']}" );
        WP_CLI::line( "Average Completion Rate: {$progress['average_completion']}%" );
    }
}