<?php

class LDTT_Create_Courses {

    /**
     * Handle the WP-CLI command to create courses in LearnDash with different access modes.
     *
     * @param array $args Positional arguments passed from the WP-CLI command.
     * @param array $assoc_args Associative arguments passed from the WP-CLI command.
     */
    public static function handle( $args, $assoc_args ) {
        $course_prefix = isset( $assoc_args['prefix'] ) ? $assoc_args['prefix'] : 'Sample Course';
        $batch_size = 5;
        $total_courses = isset( $assoc_args['count'] ) ? intval( $assoc_args['count'] ) : 20;
        $specified_access_mode = isset( $assoc_args['access_mode'] ) ? $assoc_args['access_mode'] : null;

        $remaining_courses = $total_courses;

        while ( $remaining_courses > 0 ) {
            $courses_to_create = min( $batch_size, $remaining_courses );

            $course_ids = self::create_courses( $course_prefix, $courses_to_create, $specified_access_mode );
            if ( is_wp_error( $course_ids ) ) {
                LDTT_Helper::cli_error( $course_ids->get_error_message() );
                return;
            } else {
                LDTT_Helper::cli_success( "{$courses_to_create} courses successfully created with the prefix '{$course_prefix}'." );
            }

            $remaining_courses -= $batch_size;
        }
    }

    /**
     * Create courses in LearnDash with different access modes.
     *
     * @param string $course_prefix The prefix for the course titles.
     * @param int $course_count The number of courses to create.
     * @param string|null $specified_access_mode The specified access mode, if any.
     * @return array|WP_Error An array of course IDs on success, WP_Error on failure.
     */
    private static function create_courses( $course_prefix, $course_count, $specified_access_mode = null ) {
        $course_ids = array();

        $access_modes = array(
            'open' => 'Open',
            'free' => 'Free',
            'buy-now' => 'Buy now',
            'recurring' => 'Recurring',
            'closed' => 'Closed'
        );

        $current_mode_index = 0;

        for ( $i = 1; $i <= $course_count; $i++ ) {
            $course_title = "{$course_prefix} {$i}";
            $course_content = "This is the content for {$course_title}.";

            $access_mode = $specified_access_mode ? $specified_access_mode : array_keys( $access_modes )[ $current_mode_index ];
            $course_settings = self::get_course_settings_by_access_mode( $access_mode );

            $course_id = wp_insert_post( array(
                'post_title'   => $course_title,
                'post_type'    => 'sfwd-courses',
                'post_status'  => 'publish',
                'post_content' => $course_content,
                'meta_input'   => $course_settings,
            ) );

            if ( is_wp_error( $course_id ) ) {
                return $course_id;
            }

            $course_ids[] = $course_id;

            // Cycle through access modes if no specific mode is provided
            if ( ! $specified_access_mode ) {
                $current_mode_index = ( $current_mode_index + 1 ) % count( $access_modes );
            }
        }

        return $course_ids;
    }

    /**
     * Get course settings based on the access mode.
     *
     * @param string $access_mode The access mode of the course.
     * @return array The course settings array.
     */
    private static function get_course_settings_by_access_mode( $access_mode ) {
        $settings = array();

        switch ( $access_mode ) {
            case 'open':
                $settings['sfwd-courses_course_price_type'] = 'open';
                break;

            case 'free':
                $settings['sfwd-courses_course_price_type'] = 'free';
                break;

            case 'buy-now':
                $settings['sfwd-courses_course_price_type'] = 'paynow';
                $settings['sfwd-courses_course_price'] = '100'; // Example price
                break;

            case 'recurring':
                $settings['sfwd-courses_course_price_type'] = 'subscribe';
                $settings['sfwd-courses_course_price'] = '50'; // Example recurring price
                $settings['sfwd-courses_course_price_billing_cycle_interval'] = '1'; // Example billing cycle
                $settings['sfwd-courses_course_price_billing_cycle_period'] = 'M'; // Monthly
                break;

            case 'closed':
                $settings['sfwd-courses_course_price_type'] = 'closed';
                break;
        }

        return $settings;
    }
}
