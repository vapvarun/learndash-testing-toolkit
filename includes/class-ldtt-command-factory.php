<?php

/**
 * Command Factory Class - Updated with User-Specific Commands
 * 
 * @package LearnDash_Testing_Toolkit
 * @since 1.2.0
 */
class LDTT_Command_Factory {
    
    /**
     * Available commands
     * 
     * @var array
     */
    private $commands = array();
    
    /**
     * Initialize the factory
     */
    public function init() {
        $this->register_commands();
        $this->load_command_files();
    }
    
    /**
     * Register available commands
     */
    private function register_commands() {
        $this->commands = array(
            'create-courses' => array(
                'class' => 'LDTT_Create_Courses',
                'file'  => 'class-ldtt-create-courses.php',
                'title' => 'Create Courses',
                'description' => 'Create multiple LearnDash courses with various access modes',
            ),
            'create-lessons' => array(
                'class' => 'LDTT_Create_Lessons',
                'file'  => 'class-ldtt-create-lessons.php',
                'title' => 'Create Lessons',
                'description' => 'Create multiple LearnDash lessons and assign to courses',
            ),
            'create-topics' => array(
                'class' => 'LDTT_Create_Topics',
                'file'  => 'class-ldtt-create-topics.php',
                'title' => 'Create Topics',
                'description' => 'Create multiple LearnDash topics and assign to lessons',
            ),
            'create-quizzes' => array(
                'class' => 'LDTT_Create_Quizzes',
                'file'  => 'class-ldtt-create-quizzes.php',
                'title' => 'Create Quizzes',
                'description' => 'Create quizzes with questions and assign to lessons',
            ),
            'create-questions' => array(
                'class' => 'LDTT_Create_Questions',
                'file'  => 'class-ldtt-create-questions.php',
                'title' => 'Create Questions',
                'description' => 'Create quiz questions and assign to quizzes',
            ),
            'enrollment' => array(
                'class' => 'LDTT_Enrollment',
                'file'  => 'class-ldtt-enrollment.php',
                'title' => 'User Enrollment',
                'description' => 'Create users and enroll them in courses',
            ),
            'course-groups' => array(
                'class' => 'LDTT_Course_Groups',
                'file'  => 'class-ldtt-course-groups.php',
                'title' => 'Course Groups',
                'description' => 'Create and manage LearnDash groups',
            ),
            'group-leaders' => array(
                'class' => 'LDTT_Group_Leaders',
                'file'  => 'class-ldtt-group-leaders.php',
                'title' => 'Group Leaders',
                'description' => 'Create group leaders and assign to groups',
            ),
            'group-enrollment' => array(
                'class' => 'LDTT_Group_Enrollment',
                'file'  => 'class-ldtt-group-enrollment.php',
                'title' => 'Group Enrollment',
                'description' => 'Enroll users in groups',
            ),
            'enhanced-user-distribution' => array(
                'class' => 'LDTT_Enhanced_User_Distribution',
                'file'  => 'class-ldtt-enhanced-user-distribution.php',
                'title' => 'Enhanced User Distribution',
                'description' => 'Create users with realistic distribution and progress',
            ),
            'assign-progress' => array(
                'class' => 'LDTT_Enhanced_User_Distribution',
                'file'  => 'class-ldtt-enhanced-user-distribution.php',
                'title' => 'Assign Progress to Enrolled Users',
                'description' => 'Add realistic progress to existing enrolled users',
                'method' => 'assign_progress_to_enrolled',
            ),
            // New user-specific commands
            'add-user-progress' => array(
                'class' => 'LDTT_Add_User_Progress',
                'file'  => 'class-ldtt-user-specific-commands.php',
                'title' => 'Add Progress to Specific User',
                'description' => 'Add realistic course progress to a specific user ID',
            ),
            'enroll-user-courses' => array(
                'class' => 'LDTT_Enroll_User_Courses',
                'file'  => 'class-ldtt-user-specific-commands.php',
                'title' => 'Enroll User in Courses',
                'description' => 'Enroll a specific user in one or more courses',
            ),
            'user-info' => array(
                'class' => 'LDTT_User_Info',
                'file'  => 'class-ldtt-user-specific-commands.php',
                'title' => 'User Information',
                'description' => 'Get detailed information about a specific user',
            ),
            'delete-items' => array(
                'class' => 'LDTT_Delete_Items',
                'file'  => 'class-ldtt-delete-items.php',
                'title' => 'Delete Test Data',
                'description' => 'Clean up test data created by the toolkit',
            ),
        );
    }
    
    /**
     * Load command files
     */
    private function load_command_files() {
        foreach ( $this->commands as $command_data ) {
            $file_path = LDTT_PLUGIN_DIR . 'includes/cli-commands/' . $command_data['file'];
            
            if ( file_exists( $file_path ) ) {
                require_once $file_path;
            } else {
                LDTT_Logger::warning( "Command file not found: {$file_path}" );
            }
        }
    }
    
    /**
     * Register CLI commands
     */
    public function register_cli_commands() {
        if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
            return;
        }
        
        foreach ( $this->commands as $command_name => $command_data ) {
            if ( class_exists( $command_data['class'] ) ) {
                $method = isset( $command_data['method'] ) ? $command_data['method'] : 'handle';
                
                WP_CLI::add_command(
                    'ldtt ' . $command_name,
                    array( $command_data['class'], $method ),
                    array(
                        'shortdesc' => $command_data['description'],
                    )
                );
                
                LDTT_Logger::debug( "Registered CLI command: ldtt {$command_name}" );
            } else {
                LDTT_Logger::warning( "Command class not found: {$command_data['class']}" );
            }
        }
    }
    
    /**
     * Execute command
     * 
     * @param string $command_name
     * @param array  $args
     * @param array  $assoc_args
     * @return array
     */
    public function execute_command( $command_name, $args = array(), $assoc_args = array() ) {
        if ( ! isset( $this->commands[ $command_name ] ) ) {
            return array(
                'success' => false,
                'message' => "Unknown command: {$command_name}",
            );
        }
        
        $command_data = $this->commands[ $command_name ];
        $class_name = $command_data['class'];
        $method = isset( $command_data['method'] ) ? $command_data['method'] : 'handle';
        
        if ( ! class_exists( $class_name ) ) {
            return array(
                'success' => false,
                'message' => "Command class not found: {$class_name}",
            );
        }
        
        if ( ! method_exists( $class_name, $method ) ) {
            return array(
                'success' => false,
                'message' => "Command method '{$method}' not found in class: {$class_name}",
            );
        }
        
        try {
            LDTT_Logger::info( "Executing command: {$command_name}", array(
                'args' => $args,
                'assoc_args' => $assoc_args,
                'method' => $method,
            ) );
            
            $result = call_user_func( array( $class_name, $method ), $args, $assoc_args );
            
            if ( is_array( $result ) ) {
                LDTT_Logger::info( "Command completed: {$command_name}", array( 'result' => $result ) );
                return array(
                    'success' => $result['status'] === 'success',
                    'message' => $result['message'] ?? 'Command executed successfully',
                    'data' => $result,
                );
            }
            
            return array(
                'success' => true,
                'message' => 'Command executed successfully',
            );
            
        } catch ( Exception $e ) {
            LDTT_Logger::error( "Command failed: {$command_name} - {$e->getMessage()}" );
            
            return array(
                'success' => false,
                'message' => $e->getMessage(),
            );
        }
    }
    
    /**
     * Get available commands
     * 
     * @return array
     */
    public function get_commands() {
        return $this->commands;
    }
    
    /**
     * Get command info
     * 
     * @param string $command_name
     * @return array|null
     */
    public function get_command_info( $command_name ) {
        return isset( $this->commands[ $command_name ] ) ? $this->commands[ $command_name ] : null;
    }

    /**
     * Get user-specific commands
     * 
     * @return array
     */
    public function get_user_commands() {
        $user_commands = array();
        
        foreach ( $this->commands as $command_name => $command_data ) {
            if ( in_array( $command_name, array( 'add-user-progress', 'enroll-user-courses', 'user-info' ) ) ) {
                $user_commands[ $command_name ] = $command_data;
            }
        }
        
        return $user_commands;
    }

    /**
     * Validate user-specific command parameters
     * 
     * @param string $command_name
     * @param array $params
     * @return array
     */
    public function validate_user_command_params( $command_name, $params ) {
        $errors = array();
        
        switch ( $command_name ) {
            case 'add-user-progress':
                if ( empty( $params['user_id'] ) || ! is_numeric( $params['user_id'] ) ) {
                    $errors[] = 'Valid user ID is required';
                }
                
                if ( $params['course_selection'] === 'specific' && empty( $params['specific_course_id'] ) ) {
                    $errors[] = 'Course ID is required when using specific course selection';
                }
                
                $min = intval( $params['min_progress'] ?? 0 );
                $max = intval( $params['max_progress'] ?? 100 );
                
                if ( $min < 0 || $min > 100 || $max < 0 || $max > 100 || $min > $max ) {
                    $errors[] = 'Progress range must be between 0-100% with min <= max';
                }
                break;
                
            case 'enroll-user-courses':
                if ( empty( $params['user_id'] ) || ! is_numeric( $params['user_id'] ) ) {
                    $errors[] = 'Valid user ID is required';
                }
                
                if ( empty( $params['course_ids'] ) ) {
                    $errors[] = 'Course IDs are required';
                } else {
                    $course_ids = explode( ',', $params['course_ids'] );
                    foreach ( $course_ids as $id ) {
                        $id = trim( $id );
                        if ( ! is_numeric( $id ) || $id <= 0 ) {
                            $errors[] = 'All course IDs must be valid positive numbers';
                            break;
                        }
                    }
                }
                break;
                
            case 'user-info':
                if ( empty( $params['user_id'] ) || ! is_numeric( $params['user_id'] ) ) {
                    $errors[] = 'Valid user ID is required';
                }
                break;
        }
        
        return $errors;
    }
}

/**
 * Data Manager Class - Enhanced with User-Specific Statistics
 * 
 * @package LearnDash_Testing_Toolkit
 * @since 1.2.0
 */
class LDTT_Data_Manager {
    
    /**
     * Test data meta key
     */
    const TEST_DATA_META_KEY = '_ldtt_test_data';
    
    /**
     * User meta key
     */
    const TEST_USER_META_KEY = '_ldtt_test_user';
    
    /**
     * Initialize data manager
     */
    public function init() {
        // Hook into WordPress to mark our data
        add_action( 'wp_insert_post', array( $this, 'maybe_mark_test_post' ), 10, 2 );
        add_action( 'user_register', array( $this, 'maybe_mark_test_user' ) );
    }
    
    /**
     * Mark post as test data if created by LDTT
     * 
     * @param int $post_id
     * @param WP_Post $post
     */
    public function maybe_mark_test_post( $post_id, $post ) {
        // Check if this was called from an LDTT command
        $backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 10 );
        
        foreach ( $backtrace as $frame ) {
            if ( isset( $frame['class'] ) && strpos( $frame['class'], 'LDTT_' ) === 0 ) {
                $this->mark_post_as_test_data( $post_id );
                break;
            }
        }
    }
    
    /**
     * Mark user as test user if created by LDTT
     * 
     * @param int $user_id
     */
    public function maybe_mark_test_user( $user_id ) {
        // Check if this was called from an LDTT command
        $backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 10 );
        
        foreach ( $backtrace as $frame ) {
            if ( isset( $frame['class'] ) && strpos( $frame['class'], 'LDTT_' ) === 0 ) {
                $this->mark_user_as_test_data( $user_id );
                break;
            }
        }
    }
    
    /**
     * Mark post as test data
     * 
     * @param int $post_id
     * @return bool
     */
    public function mark_post_as_test_data( $post_id ) {
        $result = update_post_meta( $post_id, self::TEST_DATA_META_KEY, true );
        
        if ( $result ) {
            LDTT_Logger::debug( "Marked post {$post_id} as test data" );
        }
        
        return $result;
    }
    
    /**
     * Mark user as test data
     * 
     * @param int $user_id
     * @return bool
     */
    public function mark_user_as_test_data( $user_id ) {
        $result = update_user_meta( $user_id, self::TEST_USER_META_KEY, true );
        
        if ( $result ) {
            LDTT_Logger::debug( "Marked user {$user_id} as test data" );
        }
        
        return $result;
    }
    
    /**
     * Get test posts by type
     * 
     * @param string $post_type
     * @param array  $args
     * @return array
     */
    public function get_test_posts( $post_type = '', $args = array() ) {
        $default_args = array(
            'post_status' => array( 'publish', 'draft', 'trash' ),
            'numberposts' => -1,
            'meta_query'  => array(
                array(
                    'key'     => self::TEST_DATA_META_KEY,
                    'compare' => 'EXISTS',
                ),
            ),
        );
        
        if ( ! empty( $post_type ) ) {
            $default_args['post_type'] = $post_type;
        }
        
        $args = wp_parse_args( $args, $default_args );
        
        return get_posts( $args );
    }
    
    /**
     * Get test users
     * 
     * @param array $args
     * @return array
     */
    public function get_test_users( $args = array() ) {
        $default_args = array(
            'meta_key'   => self::TEST_USER_META_KEY,
            'meta_value' => true,
        );
        
        $args = wp_parse_args( $args, $default_args );
        
        return get_users( $args );
    }
    
    /**
     * Delete test posts
     * 
     * @param string $post_type
     * @param bool   $force_delete
     * @return array
     */
    public function delete_test_posts( $post_type = '', $force_delete = true ) {
        $posts = $this->get_test_posts( $post_type );
        $deleted = 0;
        $errors = array();
        
        foreach ( $posts as $post ) {
            $result = wp_delete_post( $post->ID, $force_delete );
            
            if ( $result ) {
                $deleted++;
                LDTT_Logger::debug( "Deleted test post: {$post->post_title} (ID: {$post->ID})" );
            } else {
                $errors[] = "Failed to delete post ID: {$post->ID}";
                LDTT_Logger::warning( "Failed to delete test post ID: {$post->ID}" );
            }
        }
        
        return array(
            'total'   => count( $posts ),
            'deleted' => $deleted,
            'errors'  => $errors,
        );
    }
    
    /**
     * Delete test users
     * 
     * @param bool $reassign_posts
     * @return array
     */
    public function delete_test_users( $reassign_posts = false ) {
        $users = $this->get_test_users();
        $deleted = 0;
        $errors = array();
        
        foreach ( $users as $user ) {
            // Don't delete admin users
            if ( in_array( 'administrator', $user->roles, true ) ) {
                $errors[] = "Skipped admin user: {$user->user_login}";
                continue;
            }
            
            $reassign_id = $reassign_posts ? $this->get_fallback_admin_id() : null;
            $result = wp_delete_user( $user->ID, $reassign_id );
            
            if ( $result ) {
                $deleted++;
                LDTT_Logger::debug( "Deleted test user: {$user->user_login} (ID: {$user->ID})" );
            } else {
                $errors[] = "Failed to delete user ID: {$user->ID}";
                LDTT_Logger::warning( "Failed to delete test user ID: {$user->ID}" );
            }
        }
        
        return array(
            'total'   => count( $users ),
            'deleted' => $deleted,
            'errors'  => $errors,
        );
    }
    
    /**
     * Get fallback admin ID for reassigning posts
     * 
     * @return int|null
     */
    private function get_fallback_admin_id() {
        $admins = get_users( array(
            'role'   => 'administrator',
            'number' => 1,
            'orderby' => 'ID',
            'order'  => 'ASC',
        ) );
        
        return ! empty( $admins ) ? $admins[0]->ID : null;
    }
    
    /**
     * Get test data statistics - Enhanced with User-Specific Data
     * 
     * @return array
     */
    public function get_test_data_statistics() {
        $stats = array(
            'posts' => array(),
            'users' => 0,
            'total_posts' => 0,
            'user_progress' => array(),
            'group_assignments' => array(),
        );
        
        // Get post statistics by type
        $post_types = array(
            'sfwd-courses',
            'sfwd-lessons',
            'sfwd-topic',
            'sfwd-quiz',
            'sfwd-question',
            'groups',
        );
        
        foreach ( $post_types as $post_type ) {
            $posts = $this->get_test_posts( $post_type );
            $count = count( $posts );
            $stats['posts'][ $post_type ] = $count;
            $stats['total_posts'] += $count;
        }
        
        // Get user statistics
        $users = $this->get_test_users();
        $stats['users'] = count( $users );
        
        // Get user types
        $user_types = array();
        $users_with_progress = 0;
        $total_completion = 0;
        
        foreach ( $users as $user ) {
            $user_type = get_user_meta( $user->ID, '_ldtt_user_type', true );
            if ( $user_type ) {
                if ( ! isset( $user_types[ $user_type ] ) ) {
                    $user_types[ $user_type ] = 0;
                }
                $user_types[ $user_type ]++;
            }
            
            // Check for progress data
            $progress_rate = get_user_meta( $user->ID, '_ldtt_progress_completion_rate', true );
            if ( $progress_rate ) {
                $users_with_progress++;
                $total_completion += $progress_rate;
            }
        }
        
        $stats['user_types'] = $user_types;
        $stats['user_progress'] = array(
            'users_with_progress' => $users_with_progress,
            'average_completion' => $users_with_progress > 0 ? round( $total_completion / $users_with_progress, 2 ) : 0,
        );
        
        // Get group assignment statistics
        $groups = $this->get_test_posts( 'groups' );
        $groups_with_leaders = 0;
        $total_group_members = 0;
        
        foreach ( $groups as $group ) {
            $leaders = get_post_meta( $group->ID, '_ld_group_administrators', true );
            if ( ! empty( $leaders ) && is_array( $leaders ) ) {
                $groups_with_leaders++;
            }
            
            $members = get_post_meta( $group->ID, 'learndash_group_users_' . $group->ID, true );
            if ( is_array( $members ) ) {
                $total_group_members += count( $members );
            }
        }
        
        $stats['group_assignments'] = array(
            'total_groups' => count( $groups ),
            'groups_with_leaders' => $groups_with_leaders,
            'total_group_members' => $total_group_members,
            'leader_assignment_rate' => count( $groups ) > 0 ? round( ( $groups_with_leaders / count( $groups ) ) * 100, 2 ) : 0,
        );
        
        return $stats;
    }
    
    /**
     * Get user-specific statistics
     * 
     * @param int $user_id
     * @return array
     */
    public function get_user_statistics( $user_id ) {
        $user = get_user_by( 'ID', $user_id );
        if ( ! $user ) {
            return array();
        }
        
        $stats = array(
            'user_id' => $user_id,
            'username' => $user->user_login,
            'is_test_user' => (bool) get_user_meta( $user_id, self::TEST_USER_META_KEY, true ),
            'user_type' => get_user_meta( $user_id, '_ldtt_user_type', true ),
            'enrollments' => array(),
            'progress' => array(),
            'group_memberships' => array(),
        );
        
        // Get course enrollments
        if ( function_exists( 'learndash_user_get_enrolled_courses' ) ) {
            $enrolled_courses = learndash_user_get_enrolled_courses( $user_id );
            foreach ( $enrolled_courses as $course_id ) {
                $course_title = get_the_title( $course_id );
                $progress_meta = get_user_meta( $user_id, '_ldtt_progress_course_' . $course_id, true );
                
                $stats['enrollments'][ $course_id ] = array(
                    'title' => $course_title,
                    'has_progress' => ! empty( $progress_meta ),
                    'completion_rate' => $progress_meta['completion_rate'] ?? null,
                );
            }
        }
        
        // Get group memberships
        if ( function_exists( 'learndash_get_users_group_ids' ) ) {
            $group_ids = learndash_get_users_group_ids( $user_id );
            foreach ( $group_ids as $group_id ) {
                $group_title = get_the_title( $group_id );
                $is_leader = $this->is_user_group_leader( $user_id, $group_id );
                
                $stats['group_memberships'][ $group_id ] = array(
                    'title' => $group_title,
                    'role' => $is_leader ? 'leader' : 'member',
                );
            }
        }
        
        // Calculate progress statistics
        $progress_rates = array_filter( array_column( $stats['enrollments'], 'completion_rate' ) );
        $stats['progress'] = array(
            'total_courses' => count( $stats['enrollments'] ),
            'courses_with_progress' => count( $progress_rates ),
            'average_completion' => ! empty( $progress_rates ) ? round( array_sum( $progress_rates ) / count( $progress_rates ), 2 ) : 0,
        );
        
        return $stats;
    }
    
    /**
     * Check if user is a group leader
     * 
     * @param int $user_id
     * @param int $group_id
     * @return bool
     */
    private function is_user_group_leader( $user_id, $group_id ) {
        $group_leaders = get_post_meta( $group_id, '_ld_group_administrators', true );
        return is_array( $group_leaders ) && in_array( $user_id, $group_leaders );
    }
    
    /**
     * Clean all test data
     * 
     * @param array $options
     * @return array
     */
    public function clean_all_test_data( $options = array() ) {
        $defaults = array(
            'posts' => true,
            'users' => true,
            'force_delete' => true,
            'reassign_posts' => false,
        );
        
        $options = wp_parse_args( $options, $defaults );
        $results = array();
        
        try {
            if ( $options['posts'] ) {
                $post_types = array( 'sfwd-courses', 'sfwd-lessons', 'sfwd-topic', 'sfwd-quiz', 'sfwd-question', 'groups' );
                
                foreach ( $post_types as $post_type ) {
                    $result = $this->delete_test_posts( $post_type, $options['force_delete'] );
                    $results['posts'][ $post_type ] = $result;
                }
            }
            
            if ( $options['users'] ) {
                $results['users'] = $this->delete_test_users( $options['reassign_posts'] );
            }
            
            LDTT_Logger::info( 'Test data cleanup completed', $results );
            
        } catch ( Exception $e ) {
            LDTT_Logger::error( 'Test data cleanup failed: ' . $e->getMessage() );
            $results['error'] = $e->getMessage();
        }
        
        return $results;
    }
    
    /**
     * Export test data
     * 
     * @return array
     */
    public function export_test_data() {
        $export_data = array(
            'version' => LDTT_VERSION,
            'timestamp' => current_time( 'timestamp' ),
            'posts' => array(),
            'users' => array(),
        );
        
        // Export posts
        $post_types = array( 'sfwd-courses', 'sfwd-lessons', 'sfwd-topic', 'sfwd-quiz', 'sfwd-question', 'groups' );
        
        foreach ( $post_types as $post_type ) {
            $posts = $this->get_test_posts( $post_type );
            $export_data['posts'][ $post_type ] = array();
            
            foreach ( $posts as $post ) {
                $export_data['posts'][ $post_type ][] = array(
                    'ID' => $post->ID,
                    'post_title' => $post->post_title,
                    'post_content' => $post->post_content,
                    'post_status' => $post->post_status,
                    'post_meta' => get_post_meta( $post->ID ),
                );
            }
        }
        
        // Export users
        $users = $this->get_test_users();
        foreach ( $users as $user ) {
            $export_data['users'][] = array(
                'ID' => $user->ID,
                'user_login' => $user->user_login,
                'user_email' => $user->user_email,
                'display_name' => $user->display_name,
                'roles' => $user->roles,
                'user_meta' => get_user_meta( $user->ID ),
            );
        }
        
        return $export_data;
    }
    
    /**
     * Check if post is test data
     * 
     * @param int $post_id
     * @return bool
     */
    public function is_test_post( $post_id ) {
        return (bool) get_post_meta( $post_id, self::TEST_DATA_META_KEY, true );
    }
    
    /**
     * Check if user is test data
     * 
     * @param int $user_id
     * @return bool
     */
    public function is_test_user( $user_id ) {
        return (bool) get_user_meta( $user_id, self::TEST_USER_META_KEY, true );
    }
}