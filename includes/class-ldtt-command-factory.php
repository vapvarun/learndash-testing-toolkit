<?php

/**
 * Command Factory Class - Updated with User-Specific Commands and Enhanced Validation
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
            'create-groups' => array(
                'class' => 'LDTT_Create_Groups',
                'file'  => 'class-ldtt-create-groups.php',
                'title' => 'Create Groups',
                'description' => 'Create multiple LearnDash groups with realistic data',
            ),
            'enrollment' => array(
                'class' => 'LDTT_Enrollment',
                'file'  => 'class-ldtt-enrollment.php',
                'title' => 'User Enrollment',
                'description' => 'Create users and enroll them in courses, or enroll existing users',
                'supports' => array( 'use_existing' ), // ENHANCED: Flag support
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
                'supports' => array( 'use_existing', 'safe_mode' ), // ENHANCED: Flag support
            ),
            'assign-progress' => array(
                'class' => 'LDTT_Enhanced_User_Distribution',
                'file'  => 'class-ldtt-enhanced-user-distribution.php',
                'title' => 'Assign Progress to Enrolled Users',
                'description' => 'Add realistic progress to existing enrolled users',
                'method' => 'assign_progress_to_enrolled',
                'supports' => array( 'user_percentage' ), // ENHANCED: Percentage support
            ),
            // ENHANCED: New user-specific commands
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
     * Execute command with enhanced validation
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
        
        // ENHANCED: Pre-execution validation
        $validation_result = $this->validate_command_params( $command_name, $assoc_args );
        if ( is_wp_error( $validation_result ) ) {
            return array(
                'success' => false,
                'message' => $validation_result->get_error_message(),
            );
        }
        
        try {
            LDTT_Logger::info( "Executing command: {$command_name}", array(
                'args' => $args,
                'assoc_args' => $this->sanitize_args_for_log( $assoc_args ),
                'method' => $method,
            ) );
            
            $result = call_user_func( array( $class_name, $method ), $args, $assoc_args );
            
            if ( is_array( $result ) ) {
                LDTT_Logger::info( "Command completed: {$command_name}", array( 
                    'status' => $result['status'] ?? 'unknown',
                    'message' => $result['message'] ?? 'No message'
                ) );
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
     * ENHANCED: Validate command parameters
     * 
     * @param string $command_name
     * @param array $params
     * @return bool|WP_Error
     */
    private function validate_command_params( $command_name, $params ) {
        // Enhanced validation for specific commands
        switch ( $command_name ) {
            case 'enhanced-user-distribution':
                return $this->validate_distribution_params( $params );
                
            case 'enrollment':
                return $this->validate_enrollment_params( $params );
                
            case 'assign-progress':
                return $this->validate_progress_params( $params );
                
            case 'add-user-progress':
            case 'enroll-user-courses':
            case 'user-info':
                return $this->validate_user_specific_params( $command_name, $params );
                
            default:
                return $this->validate_basic_params( $params );
        }
    }
    
    /**
     * Validate distribution command parameters
     * 
     * @param array $params
     * @return bool|WP_Error
     */
    private function validate_distribution_params( $params ) {
        $total_users = absint( $params['total_users'] ?? 100 );
        $group_leaders = floatval( $params['group_leaders'] ?? 1.0 );
        $group_members = floatval( $params['group_members'] ?? 2.0 );
        $course_enrolled = floatval( $params['course_enrolled'] ?? 5.0 );
        
        if ( $total_users < 10 || $total_users > 1000 ) {
            return new WP_Error( 'invalid_total_users', 'Total users must be between 10 and 1000' );
        }
        
        $total_percentage = $group_leaders + $group_members + $course_enrolled;
        if ( $total_percentage > 100 ) {
            return new WP_Error( 'percentage_overflow', 'Total percentage cannot exceed 100%' );
        }
        
        return true;
    }
    
    /**
     * Validate enrollment command parameters
     * 
     * @param array $params
     * @return bool|WP_Error
     */
    private function validate_enrollment_params( $params ) {
        $course_id = absint( $params['course_id'] ?? 0 );
        $count = absint( $params['count'] ?? 10 );
        $use_existing = isset( $params['use_existing'] ) && $params['use_existing'];
        
        if ( ! $course_id ) {
            return new WP_Error( 'missing_course_id', 'Course ID is required' );
        }
        
        if ( get_post_type( $course_id ) !== learndash_get_post_type_slug( 'course' ) ) {
            return new WP_Error( 'invalid_course', 'Invalid course ID provided' );
        }
        
        if ( $count < 1 || $count > 300 ) {
            return new WP_Error( 'invalid_count', 'Count must be between 1 and 300' );
        }
        
        // If using existing users, check if enough users exist
        if ( $use_existing ) {
            $existing_count = count( get_users( array( 'role__not_in' => array( 'administrator' ), 'fields' => 'ID' ) ) );
            if ( $existing_count < $count ) {
                return new WP_Error( 'insufficient_users', "Only {$existing_count} existing users available, but {$count} requested" );
            }
        }
        
        return true;
    }
    
    /**
     * Validate progress assignment parameters
     * 
     * @param array $params
     * @return bool|WP_Error
     */
    private function validate_progress_params( $params ) {
        $course_id = absint( $params['course_id'] ?? 0 );
        $all_courses = isset( $params['all_courses'] ) && $params['all_courses'];
        $user_percentage = floatval( $params['user_percentage'] ?? 100 );
        
        if ( ! $all_courses && ! $course_id ) {
            return new WP_Error( 'missing_target', 'Either course_id or all_courses must be specified' );
        }
        
        if ( $course_id && get_post_type( $course_id ) !== learndash_get_post_type_slug( 'course' ) ) {
            return new WP_Error( 'invalid_course', 'Invalid course ID provided' );
        }
        
        if ( $user_percentage < 1 || $user_percentage > 100 ) {
            return new WP_Error( 'invalid_percentage', 'User percentage must be between 1 and 100' );
        }
        
        return true;
    }
    
    /**
     * Validate user-specific command parameters
     * 
     * @param string $command_name
     * @param array $params
     * @return bool|WP_Error
     */
    private function validate_user_specific_params( $command_name, $params ) {
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
        
        if ( ! empty( $errors ) ) {
            return new WP_Error( 'validation_failed', implode( '; ', $errors ) );
        }
        
        return true;
    }
    
    /**
     * Basic parameter validation
     * 
     * @param array $params
     * @return bool|WP_Error
     */
    private function validate_basic_params( $params ) {
        // Check for obviously invalid values
        if ( isset( $params['count'] ) && ( $params['count'] < 1 || $params['count'] > 10000 ) ) {
            return new WP_Error( 'invalid_count', 'Count must be between 1 and 10000' );
        }
        
        return true;
    }
    
    /**
     * Sanitize arguments for logging (remove sensitive data)
     * 
     * @param array $args
     * @return array
     */
    private function sanitize_args_for_log( $args ) {
        $sanitized = $args;
        
        // Remove sensitive fields from logs
        $sensitive_fields = array( 'password', 'email', 'api_key', 'token' );
        foreach ( $sensitive_fields as $field ) {
            if ( isset( $sanitized[ $field ] ) ) {
                $sanitized[ $field ] = '[REDACTED]';
            }
        }
        
        return $sanitized;
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
     * Get commands that support specific features
     * 
     * @param string $feature
     * @return array
     */
    public function get_commands_with_support( $feature ) {
        $supported_commands = array();
        
        foreach ( $this->commands as $command_name => $command_data ) {
            if ( isset( $command_data['supports'] ) && in_array( $feature, $command_data['supports'] ) ) {
                $supported_commands[ $command_name ] = $command_data;
            }
        }
        
        return $supported_commands;
    }

    /**
     * Check if command supports a feature
     * 
     * @param string $command_name
     * @param string $feature
     * @return bool
     */
    public function command_supports( $command_name, $feature ) {
        if ( ! isset( $this->commands[ $command_name ] ) ) {
            return false;
        }
        
        $command_data = $this->commands[ $command_name ];
        return isset( $command_data['supports'] ) && in_array( $feature, $command_data['supports'] );
    }
}