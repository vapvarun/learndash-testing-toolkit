<?php

/**
 * Create Courses Command - Cleaned with Standard LearnDash Functions Only
 * Creates LearnDash courses with various access modes using official LearnDash functions
 */
class LDTT_Create_Courses {

    /**
     * Handle the command to create LearnDash courses
     *
     * @param array $args Positional arguments.
     * @param array $assoc_args Associative arguments.
     */
    public static function handle( $args = array(), $assoc_args = array() ) {
        // Check for admin interface data
        if ( empty( $args ) && empty( $assoc_args ) ) {
            $assoc_args = array(
                'prefix'      => isset( $_POST['prefix'] ) ? sanitize_text_field( $_POST['prefix'] ) : 'Test Course',
                'count'       => isset( $_POST['count'] ) ? intval( $_POST['count'] ) : 5,
                'access_mode' => isset( $_POST['access_mode'] ) ? sanitize_text_field( $_POST['access_mode'] ) : null,
            );
        }

        $course_prefix = sanitize_text_field( $assoc_args['prefix'] ?? 'Test Course' );
        $total_courses = LDTT_Helper::validate_positive_int( $assoc_args['count'] ?? 5, 1, 100 );
        $specified_access_mode = ! empty( $assoc_args['access_mode'] ) ? sanitize_text_field( $assoc_args['access_mode'] ) : null;

        // Get an author ID with fallback to current user
        $admin_user_id = LDTT_Helper::get_author_id();
        if ( ! $admin_user_id ) {
            // This should never happen with the new helper, but just in case
            $admin_user_id = 1;
        }

        // Create the courses
        $course_ids = self::create_courses( $course_prefix, $total_courses, $specified_access_mode, $admin_user_id );

        if ( is_wp_error( $course_ids ) ) {
            $message = $course_ids->get_error_message();
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::error( $message );
            }
            return array( 'status' => 'error', 'message' => $message );
        }

        $message = "{$total_courses} courses created successfully.";
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::success( $message );
            WP_CLI::line( 'Course IDs: ' . implode( ', ', $course_ids ) );
        }
        
        return array( 
            'status' => 'success', 
            'message' => $message, 
            'course_ids' => $course_ids,
            'total_created' => count( $course_ids ),
        );
    }

    /**
     * Create multiple courses with LearnDash settings
     *
     * @param string $course_prefix Prefix for course titles.
     * @param int $course_count Number of courses to create.
     * @param string|null $specified_access_mode Specific access mode or null for cycling.
     * @param int $admin_user_id Admin user ID for post author.
     * @return array|WP_Error Array of course IDs on success, WP_Error on failure.
     */
    private static function create_courses( $course_prefix, $course_count, $specified_access_mode, $admin_user_id ) {
        $course_ids = array();

        // Define available access modes
        $access_modes = array(
            'open'      => 'Open',
            'free'      => 'Free',
            'paynow'    => 'Buy now',
            'subscribe' => 'Recurring',
            'closed'    => 'Closed'
        );

        // Validate specified access mode
        if ( $specified_access_mode && ! array_key_exists( $specified_access_mode, $access_modes ) ) {
            return new WP_Error( 'invalid_access_mode', "Invalid access mode: {$specified_access_mode}" );
        }

        // Get random course titles for variety
        $current_mode_index = 0;

        for ( $i = 1; $i <= $course_count; $i++ ) {
            $base_title = LDTT_Sample_Data::get_random_course_title();
            $course_title = "{$course_prefix} {$i}: {$base_title}";
            $course_content = LDTT_Sample_Data::get_random_course_description();

            // Determine access mode for this course
            $access_mode = $specified_access_mode ?: array_keys( $access_modes )[ $current_mode_index ];

            // Create the course post
            $course_id = wp_insert_post( array(
                'post_title'   => $course_title,
                'post_type'    => learndash_get_post_type_slug( 'course' ),
                'post_status'  => 'publish',
                'post_content' => $course_content,
                'post_author'  => $admin_user_id,
                'meta_input'   => array(
                    '_ldtt_test_data' => true,
                    '_ldtt_created_time' => current_time( 'timestamp' ),
                ),
            ) );

            if ( is_wp_error( $course_id ) ) {
                if ( defined( 'WP_CLI' ) && WP_CLI ) {
                    WP_CLI::warning( "Failed to create course: {$course_title} - " . $course_id->get_error_message() );
                }
                continue;
            }

            // Configure course settings using LearnDash functions
            $settings_result = self::configure_course_settings( $course_id, $access_mode );
            if ( is_wp_error( $settings_result ) ) {
                if ( defined( 'WP_CLI' ) && WP_CLI ) {
                    WP_CLI::warning( "Failed to configure course settings for: {$course_title} - " . $settings_result->get_error_message() );
                }
                // Continue anyway, course is created even if settings failed
            }

            $course_ids[] = $course_id;

            // Cycle through access modes if no specific mode is provided
            if ( ! $specified_access_mode ) {
                $current_mode_index = ( $current_mode_index + 1 ) % count( $access_modes );
            }

            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::line( "Created course: {$course_title} (ID: {$course_id}) - Access: {$access_mode}" );
            }
        }

        if ( empty( $course_ids ) ) {
            return new WP_Error( 'no_courses_created', 'No courses could be created successfully.' );
        }

        return $course_ids;
    }

    /**
     * Configure course settings using standard LearnDash functions
     *
     * @param int $course_id Course post ID.
     * @param string $access_mode Access mode for the course.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    private static function configure_course_settings( $course_id, $access_mode ) {
        try {
            // Set the course price type using LearnDash function
            learndash_update_setting( $course_id, 'course_price_type', $access_mode );

            // Configure specific settings based on access mode
            switch ( $access_mode ) {
                case 'paynow':
                    learndash_update_setting( $course_id, 'course_price', '99.00' );
                    learndash_update_setting( $course_id, 'course_price_type_paynow_price', '99.00' );
                    break;

                case 'subscribe':
                    learndash_update_setting( $course_id, 'course_price', '29.00' );
                    learndash_update_setting( $course_id, 'course_price_billing_p3', '1' );
                    learndash_update_setting( $course_id, 'course_price_billing_t3', 'M' );
                    learndash_update_setting( $course_id, 'course_price_type_subscribe_price', '29.00' );
                    break;

                case 'closed':
                    learndash_update_setting( $course_id, 'course_disable_lesson_progression', 'on' );
                    break;

                case 'free':
                    // Free courses don't need additional price settings
                    break;

                case 'open':
                    learndash_update_setting( $course_id, 'course_disable_lesson_progression', '' );
                    break;
            }

            // Set additional common course settings
            learndash_update_setting( $course_id, 'course_materials_enabled', 'on' );
            learndash_update_setting( $course_id, 'course_materials', 'Additional course materials and resources.' );
            
            // Set course progression settings
            learndash_update_setting( $course_id, 'course_lesson_progression', 'on' );
            learndash_update_setting( $course_id, 'course_lesson_orderby', 'menu_order' );
            learndash_update_setting( $course_id, 'course_lesson_order', 'ASC' );

            return true;

        } catch ( Exception $e ) {
            return new WP_Error( 'settings_error', 'Failed to configure course settings: ' . $e->getMessage() );
        }
    }

    /**
     * Generate rich course content
     *
     * @param string $course_title Course title.
     * @param int $course_number Course number for unique content.
     * @return string Generated course content.
     */
    private static function generate_course_content( $course_title, $course_number ) {
        $content_templates = array(
            "Welcome to {$course_title}! This comprehensive course will guide you through essential concepts and practical applications. You'll learn step-by-step techniques and gain hands-on experience.",
            "In {$course_title}, you'll discover advanced strategies and proven methodologies. This course combines theoretical knowledge with real-world case studies and interactive exercises.",
            "{$course_title} offers an in-depth exploration of key principles and best practices. Through engaging lessons and practical assignments, you'll develop valuable skills and expertise.",
            "Join us for {$course_title}, where you'll master fundamental concepts and advanced techniques. This course features expert instruction, practical examples, and comprehensive assessments.",
            "{$course_title} provides a complete learning experience with structured modules, interactive content, and practical projects. Perfect for both beginners and advanced learners.",
        );

        $base_content = $content_templates[ ( $course_number - 1 ) % count( $content_templates ) ];
        
        $additional_content = "\n\n<h3>Course Overview</h3>\n";
        $additional_content .= "<p>This course includes multiple lessons, interactive topics, and assessments designed to enhance your learning experience.</p>\n";
        $additional_content .= "\n<h3>What You'll Learn</h3>\n";
        $additional_content .= "<ul>\n";
        $additional_content .= "<li>Core concepts and fundamental principles</li>\n";
        $additional_content .= "<li>Practical applications and real-world examples</li>\n";
        $additional_content .= "<li>Advanced techniques and best practices</li>\n";
        $additional_content .= "<li>Hands-on exercises and projects</li>\n";
        $additional_content .= "</ul>\n";

        return $base_content . $additional_content;
    }

    /**
     * Get available course access modes
     *
     * @return array Array of access modes with descriptions.
     */
    public static function get_available_access_modes() {
        return array(
            'open'      => array(
                'label' => 'Open',
                'description' => 'Course is open to all users without restrictions'
            ),
            'free'      => array(
                'label' => 'Free',
                'description' => 'Course is free but requires enrollment'
            ),
            'paynow'    => array(
                'label' => 'Buy Now',
                'description' => 'One-time payment required to access course'
            ),
            'subscribe' => array(
                'label' => 'Recurring',
                'description' => 'Subscription-based access to course'
            ),
            'closed'    => array(
                'label' => 'Closed',
                'description' => 'Course access is restricted to specific users'
            ),
        );
    }

    /**
     * Validate course creation parameters
     *
     * @param array $params Parameters to validate.
     * @return bool|WP_Error True if valid, WP_Error if invalid.
     */
    public static function validate_creation_parameters( $params ) {
        $errors = array();

        // Validate count
        $count = intval( $params['count'] ?? 0 );
        if ( $count < 1 || $count > 100 ) {
            $errors[] = 'Course count must be between 1 and 100';
        }

        // Validate prefix
        $prefix = trim( $params['prefix'] ?? '' );
        if ( empty( $prefix ) ) {
            $errors[] = 'Course prefix is required';
        } elseif ( strlen( $prefix ) > 50 ) {
            $errors[] = 'Course prefix must be 50 characters or less';
        }

        // Validate access mode if specified
        if ( ! empty( $params['access_mode'] ) ) {
            $valid_modes = array_keys( self::get_available_access_modes() );
            if ( ! in_array( $params['access_mode'], $valid_modes ) ) {
                $errors[] = 'Invalid access mode specified';
            }
        }

        if ( ! empty( $errors ) ) {
            return new WP_Error( 'validation_failed', implode( '; ', $errors ) );
        }

        return true;
    }

    /**
     * Get statistics about created courses
     *
     * @return array Course creation statistics.
     */
    public static function get_course_statistics() {
        $courses = get_posts( array(
            'post_type'   => learndash_get_post_type_slug( 'course' ),
            'meta_key'    => '_ldtt_test_data',
            'meta_value'  => true,
            'numberposts' => -1,
            'post_status' => array( 'publish', 'draft' ),
        ) );

        $stats = array(
            'total_courses' => count( $courses ),
            'access_modes' => array(),
            'creation_dates' => array(),
        );

        foreach ( $courses as $course ) {
            // Get access mode
            $access_mode = learndash_get_setting( $course->ID, 'course_price_type' );
            if ( $access_mode ) {
                $stats['access_modes'][ $access_mode ] = ( $stats['access_modes'][ $access_mode ] ?? 0 ) + 1;
            }

            // Get creation date
            $created_time = get_post_meta( $course->ID, '_ldtt_created_time', true );
            if ( $created_time ) {
                $date_key = date( 'Y-m-d', $created_time );
                $stats['creation_dates'][ $date_key ] = ( $stats['creation_dates'][ $date_key ] ?? 0 ) + 1;
            }
        }

        return $stats;
    }
}