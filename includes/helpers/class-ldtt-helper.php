<?php

class LDTT_Helper {

    /**
     * Check if LearnDash is active.
     *
     * @return bool True if LearnDash is active, false otherwise.
     */
    public static function is_learndash_active() {
        return function_exists( 'learndash_get_post_type_slug' ) || class_exists( 'LDLMS_Post_Types' );
    }

    /**
     * Generate a random string.
     *
     * @param int $length The length of the random string to generate.
     * @return string The generated random string.
     */
    public static function generate_random_string( $length = 10 ) {
        return substr( str_shuffle( '0123456789abcdefghijklmnopqrstuvwxyz' ), 0, $length );
    }

    /**
     * Log messages to the WordPress debug log.
     *
     * @param string $message The message to log.
     */
    public static function log( $message ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[LDTT] ' . $message );
        }
    }

    /**
     * Helper function to output a success message via WP-CLI.
     *
     * @param string $message The success message.
     */
    public static function cli_success( $message ) {
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::success( $message );
        } else {
            self::log( 'Success: ' . $message );
        }
    }

    /**
     * Helper function to output an error message via WP-CLI.
     *
     * @param string $message The error message.
     */
    public static function cli_error( $message ) {
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::error( $message );
        } else {
            self::log( 'Error: ' . $message );
        }
    }

    /**
     * Validate positive integer with limits.
     *
     * @param mixed $value The value to validate.
     * @param int $default Default value if validation fails.
     * @param int $max Maximum allowed value.
     * @return int Validated integer.
     */
    public static function validate_positive_int( $value, $default = 1, $max = null ) {
        $value = absint( $value );
        if ( $value < 1 ) {
            $value = $default;
        }
        if ( $max && $value > $max ) {
            $value = $max;
        }
        return $value;
    }

    /**
     * Get admin user ID.
     *
     * @return int|false Admin user ID or false if not found.
     */
    public static function get_admin_user_id() {
        $admins = get_users( array(
            'role' => 'administrator',
            'number' => 1,
            'orderby' => 'ID',
            'order' => 'ASC',
        ) );

        return ! empty( $admins ) ? $admins[0]->ID : false;
    }
    
    /**
     * Get an author ID for content creation.
     * Falls back to current user if no admin found.
     *
     * @return int Author user ID (current user, admin, or 1 as last resort)
     */
    public static function get_author_id() {
        // First try to get current user (most reliable when running from admin)
        $current_user_id = get_current_user_id();
        
        if ( $current_user_id > 0 ) {
            return $current_user_id;
        }
        
        // If no current user (e.g., running from CLI), try to get an admin
        $admin_id = self::get_admin_user_id();
        
        if ( $admin_id ) {
            return $admin_id;
        }
        
        // Last resort - get any user
        $users = get_users( array(
            'number' => 1,
            'orderby' => 'ID',
            'order' => 'ASC',
        ) );
        
        return ! empty( $users ) ? $users[0]->ID : 1;
    }

    /**
     * Get random course titles.
     *
     * @param int $count Number of titles to return.
     * @return array Array of course titles.
     */
    public static function get_random_course_titles( $count = 50 ) {
        $titles = array(
            'Introduction to Web Development',
            'Advanced JavaScript Programming',
            'Digital Marketing Fundamentals',
            'Graphic Design Essentials',
            'Project Management Basics',
            'Data Science with Python',
            'Mobile App Development',
            'Social Media Strategy',
            'Photography Masterclass',
            'Business Analytics',
            'UI/UX Design Principles',
            'Content Marketing Strategy',
            'WordPress Development',
            'E-commerce Fundamentals',
            'Leadership Skills',
            'Time Management',
            'Public Speaking',
            'Creative Writing',
            'Financial Planning',
            'Entrepreneurship 101',
        );

        shuffle( $titles );
        return array_slice( $titles, 0, min( $count, count( $titles ) ) );
    }

    /**
     * Get random lesson titles.
     *
     * @param int $count Number of titles to return.
     * @return array Array of lesson titles.
     */
    public static function get_random_lesson_titles( $count = 50 ) {
        $titles = array(
            'Getting Started with the Basics',
            'Understanding Core Concepts',
            'Practical Applications',
            'Advanced Techniques',
            'Best Practices and Tips',
            'Common Mistakes to Avoid',
            'Real-World Examples',
            'Hands-On Exercise',
            'Review and Assessment',
            'Next Steps and Resources',
            'Introduction and Overview',
            'Setting Up Your Environment',
            'Basic Terminology',
            'Step-by-Step Guide',
            'Troubleshooting Common Issues',
            'Expert Strategies',
            'Case Study Analysis',
            'Interactive Workshop',
            'Q&A Session',
            'Final Project Guidelines',
        );

        shuffle( $titles );
        return array_slice( $titles, 0, min( $count, count( $titles ) ) );
    }
}