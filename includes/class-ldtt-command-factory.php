<?php

/**
 * Command Factory Class
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
                WP_CLI::add_command(
                    'ldtt ' . $command_name,
                    array( $command_data['class'], 'handle' ),
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
        
        if ( ! class_exists( $class_name ) ) {
            return array(
                'success' => false,
                'message' => "Command class not found: {$class_name}",
            );
        }
        
        if ( ! method_exists( $class_name, 'handle' ) ) {
            return array(
                'success' => false,
                'message' => "Command handler not found in class: {$class_name}",
            );
        }
        
        try {
            LDTT_Logger::info( "Executing command: {$command_name}", array(
                'args' => $args,
                'assoc_args' => $assoc_args,
            ) );
            
            $result = call_user_func( array( $class_name, 'handle' ), $args, $assoc_args );
            
            if ( is_array( $result ) ) {
                LDTT_Logger::info( "Command completed: {$command_name}", array( 'result' => $result ) );
                return $result;
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
}

/**
 * Data Manager Class
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
     * Get test data statistics
     * 
     * @return array
     */
    public function get_test_data_statistics() {
        $stats = array(
            'posts' => array(),
            'users' => 0,
            'total_posts' => 0,
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
        foreach ( $users as $user ) {
            $user_type = get_user_meta( $user->ID, '_ldtt_user_type', true );
            if ( $user_type ) {
                if ( ! isset( $user_types[ $user_type ] ) ) {
                    $user_types[ $user_type ] = 0;
                }
                $user_types[ $user_type ]++;
            }
        }
        $stats['user_types'] = $user_types;
        
        return $stats;
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