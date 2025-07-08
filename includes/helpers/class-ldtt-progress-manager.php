<?php

class LDTT_Progress_Manager {

    /**
     * Create realistic course progress for a user - FIXED: Parameter validation
     *
     * @param int $user_id User ID.
     * @param int $course_id Course ID.
     * @param array $options Progress options.
     * @return array|false Progress data or false on failure
     */
    public static function create_realistic_progress( $user_id, $course_id, $options = array() ) {
        // FIXED: Ensure we're working with integers only
        $user_id = absint( $user_id );
        $course_id = absint( $course_id );
        
        // FIXED: Validate required parameters
        if ( ! $user_id || ! $course_id ) {
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::warning( "Invalid user_id ({$user_id}) or course_id ({$course_id}) provided to progress manager" );
            }
            return false;
        }
        
        $defaults = array(
            'min_completion' => 25,
            'max_completion' => 100,
            'include_quizzes' => true,
            'include_assignments' => true,
            'random_timestamps' => true,
        );
        
        $options = wp_parse_args( $options, $defaults );
        
        // Get course structure
        $course_lessons = learndash_get_course_lessons_list( $course_id );
        
        if ( empty( $course_lessons ) ) {
            return false;
        }
        
        // Determine completion percentage
        $completion_rate = wp_rand( $options['min_completion'], $options['max_completion'] );
        $total_items = count( $course_lessons );
        $items_to_complete = round( $total_items * ( $completion_rate / 100 ) );
        
        // Start progress tracking
        $start_date = strtotime( '-' . wp_rand( 1, 30 ) . ' days' );
        $current_time = $start_date;
        
        // Shuffle lessons for realistic non-linear progress
        $lessons_copy = $course_lessons;
        shuffle( $lessons_copy );
        $lessons_to_complete = array_slice( $lessons_copy, 0, $items_to_complete );
        
        foreach ( $lessons_to_complete as $lesson ) {
            $lesson_id = absint( $lesson['post']->ID ); // FIXED: Ensure integer
            
            // Add realistic time progression (1-3 days between completions)
            if ( $options['random_timestamps'] ) {
                $current_time += wp_rand( 3600, 259200 ); // 1 hour to 3 days
            }
            
            // FIXED: Pass integers to LearnDash functions
            learndash_process_mark_complete( $user_id, $lesson_id, false, $course_id );
            
            // Update completion time in user activity
            $activity_args = array(
                'course_id'     => $course_id,
                'user_id'       => $user_id,
                'post_id'       => $lesson_id,
                'activity_type' => 'lesson',
                'activity_action' => 'insert',
                'activity_status' => true,
                'activity_started' => $current_time - wp_rand( 300, 3600 ), // Started 5min to 1hr before completion
                'activity_completed' => $current_time,
            );
            
            learndash_update_user_activity( $activity_args );
            
            // Handle lesson topics
            $topics = learndash_get_topic_list( $lesson_id, $course_id );
            if ( ! empty( $topics ) ) {
                $topic_completion_rate = wp_rand( 50, 100 ); // Complete 50-100% of topics
                $topics_to_complete = round( count( $topics ) * ( $topic_completion_rate / 100 ) );
                
                $completed_topics = array_slice( $topics, 0, $topics_to_complete );
                foreach ( $completed_topics as $topic ) {
                    $topic_id = absint( $topic->ID ); // FIXED: Ensure integer
                    $current_time += wp_rand( 300, 1800 ); // 5-30 minutes between topics
                    
                    // FIXED: Pass integers to LearnDash functions
                    learndash_process_mark_complete( $user_id, $topic_id, false, $course_id );
                    
                    $topic_activity_args = array(
                        'course_id'     => $course_id,
                        'user_id'       => $user_id,
                        'post_id'       => $topic_id,
                        'activity_type' => 'topic',
                        'activity_action' => 'insert',
                        'activity_status' => true,
                        'activity_started' => $current_time - wp_rand( 180, 900 ),
                        'activity_completed' => $current_time,
                    );
                    
                    learndash_update_user_activity( $topic_activity_args );
                }
            }
            
            // Handle quizzes if enabled
            if ( $options['include_quizzes'] ) {
                $lesson_quizzes = learndash_get_lesson_quiz_list( $lesson_id, $user_id, $course_id );
                if ( is_array( $lesson_quizzes ) ) { // FIXED: Validate array
                    foreach ( $lesson_quizzes as $quiz ) {
                        // 70% chance to complete quiz
                        if ( wp_rand( 1, 100 ) <= 70 ) {
                            $quiz_id = absint( $quiz['post']->ID ); // FIXED: Ensure integer
                            self::complete_quiz_with_score( $user_id, $quiz_id, $course_id, $current_time );
                            $current_time += wp_rand( 600, 1800 ); // 10-30 minutes for quiz
                        }
                    }
                }
            }
        }
        
        // Update overall course progress - FIXED: Pass integers only
        learndash_user_set_course_progress( $user_id, $course_id, array() );
        
        // Store progress metadata
        update_user_meta( $user_id, '_ldtt_progress_created', current_time( 'timestamp' ) );
        update_user_meta( $user_id, '_ldtt_progress_completion_rate', $completion_rate );
        update_user_meta( $user_id, '_ldtt_progress_course_' . $course_id, array(
            'completion_rate' => $completion_rate,
            'items_completed' => $items_to_complete,
            'total_items' => $total_items,
            'started' => $start_date,
            'last_activity' => $current_time,
        ) );
        
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::line( "  - Created {$completion_rate}% progress for user {$user_id} in course {$course_id}" );
        }
        
        return array(
            'completion_rate' => $completion_rate,
            'items_completed' => $items_to_complete,
            'total_items' => $total_items,
        );
    }
    
    /**
     * Complete a quiz with a realistic score - FIXED: Parameter validation
     *
     * @param int $user_id User ID.
     * @param int $quiz_id Quiz ID.
     * @param int $course_id Course ID.
     * @param int $timestamp Completion timestamp.
     */
    private static function complete_quiz_with_score( $user_id, $quiz_id, $course_id, $timestamp ) {
        // FIXED: Ensure all parameters are integers
        $user_id = absint( $user_id );
        $quiz_id = absint( $quiz_id );
        $course_id = absint( $course_id );
        $timestamp = absint( $timestamp );
        
        if ( ! $user_id || ! $quiz_id || ! $course_id || ! $timestamp ) {
            return false;
        }
        
        $questions = learndash_get_quiz_questions( $quiz_id );
        $total_questions = is_array( $questions ) ? count( $questions ) : 0; // FIXED: Validate array
        
        if ( $total_questions == 0 ) {
            return;
        }
        
        // Generate realistic score (60-95%)
        $score_percentage = wp_rand( 60, 95 );
        $correct_answers = round( $total_questions * ( $score_percentage / 100 ) );
        $points = $correct_answers; // Assuming 1 point per question
        $total_points = $total_questions;
        
        // Create quiz activity entry
        $quiz_activity_args = array(
            'course_id'     => $course_id,
            'user_id'       => $user_id,
            'post_id'       => $quiz_id,
            'activity_type' => 'quiz',
            'activity_action' => 'insert',
            'activity_status' => $score_percentage >= 70, // Pass if 70% or higher
            'activity_started' => $timestamp - wp_rand( 300, 1800 ),
            'activity_completed' => $timestamp,
            'activity_meta' => array(
                'score' => $points,
                'total_score' => $total_points,
                'percentage' => $score_percentage,
                'pass' => $score_percentage >= 70,
                'rank' => '-',
                'time' => wp_rand( 300, 1800 ), // 5-30 minutes
                'quiz_settings' => array(),
            ),
        );
        
        learndash_update_user_activity( $quiz_activity_args );
        
        // Update quiz progress - FIXED: Pass integers only
        learndash_process_mark_complete( $user_id, $quiz_id, false, $course_id );
        
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::line( "    - Completed quiz {$quiz_id} with {$score_percentage}% score" );
        }
    }
    
    /**
     * Get progress statistics for all test users.
     *
     * @return array Progress statistics.
     */
    public static function get_progress_statistics() {
        $test_users = get_users( array(
            'meta_key' => '_ldtt_test_user',
            'meta_value' => true,
        ) );
        
        $stats = array(
            'total_users' => count( $test_users ),
            'users_with_progress' => 0,
            'average_completion' => 0,
            'courses_with_activity' => array(),
            'progress_distribution' => array(
                '0-25%' => 0,
                '26-50%' => 0,
                '51-75%' => 0,
                '76-100%' => 0,
            ),
        );
        
        $total_completion = 0;
        
        foreach ( $test_users as $user ) {
            $user_progress = get_user_meta( $user->ID, '_ldtt_progress_completion_rate', true );
            
            if ( $user_progress ) {
                $stats['users_with_progress']++;
                $total_completion += $user_progress;
                
                // Categorize progress
                if ( $user_progress <= 25 ) {
                    $stats['progress_distribution']['0-25%']++;
                } elseif ( $user_progress <= 50 ) {
                    $stats['progress_distribution']['26-50%']++;
                } elseif ( $user_progress <= 75 ) {
                    $stats['progress_distribution']['51-75%']++;
                } else {
                    $stats['progress_distribution']['76-100%']++;
                }
            }
        }
        
        if ( $stats['users_with_progress'] > 0 ) {
            $stats['average_completion'] = round( $total_completion / $stats['users_with_progress'], 2 );
        }
        
        return $stats;
    }

    /**
     * Create progress for multiple users efficiently - FIXED: Batch processing
     * 
     * @param array $user_ids Array of user IDs
     * @param int $course_id Course ID
     * @param array $options Progress options
     * @return array Results summary
     */
    public static function create_bulk_progress( $user_ids, $course_id, $options = array() ) {
        // FIXED: Validate and sanitize inputs
        $course_id = absint( $course_id );
        if ( ! $course_id ) {
            return array( 'success' => false, 'message' => 'Invalid course ID' );
        }
        
        if ( ! is_array( $user_ids ) || empty( $user_ids ) ) {
            return array( 'success' => false, 'message' => 'No user IDs provided' );
        }
        
        // FIXED: Ensure all user IDs are integers
        $user_ids = array_filter( array_map( 'absint', $user_ids ) );
        
        $successful = 0;
        $failed = 0;
        
        foreach ( $user_ids as $user_id ) {
            $result = self::create_realistic_progress( $user_id, $course_id, $options );
            
            if ( $result !== false ) {
                $successful++;
            } else {
                $failed++;
            }
        }
        
        return array(
            'success' => true,
            'message' => "Progress created for {$successful} users, {$failed} failed",
            'successful' => $successful,
            'failed' => $failed,
            'total' => count( $user_ids )
        );
    }

    /**
     * Validate progress data before creation - FIXED: Enhanced validation
     * 
     * @param int $user_id
     * @param int $course_id
     * @return bool|WP_Error
     */
    public static function validate_progress_data( $user_id, $course_id ) {
        // FIXED: Strict type validation
        $user_id = absint( $user_id );
        $course_id = absint( $course_id );
        
        if ( ! $user_id ) {
            return new WP_Error( 'invalid_user', 'Invalid user ID provided' );
        }
        
        if ( ! $course_id ) {
            return new WP_Error( 'invalid_course', 'Invalid course ID provided' );
        }
        
        // Check if user exists
        $user = get_user_by( 'ID', $user_id );
        if ( ! $user ) {
            return new WP_Error( 'user_not_found', "User with ID {$user_id} not found" );
        }
        
        // Check if course exists
        $course = get_post( $course_id );
        if ( ! $course || $course->post_type !== learndash_get_post_type_slug( 'course' ) ) {
            return new WP_Error( 'course_not_found', "Course with ID {$course_id} not found" );
        }
        
        return true;
    }
}