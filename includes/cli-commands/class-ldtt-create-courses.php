<?php

class LDTT_Create_Courses {

    public static function handle( $args, $assoc_args ) {
        $course_prefix = isset( $assoc_args['prefix'] ) ? $assoc_args['prefix'] : 'Sample Course';
        $batch_size = 5;
        $total_courses = isset( $assoc_args['count'] ) ? intval( $assoc_args['count'] ) : 20;
        $specified_access_mode = isset( $assoc_args['access_mode'] ) ? $assoc_args['access_mode'] : null;

        // Get an admin user to assign as the author
        $admin_user_id = self::get_admin_user_id();

        if ( ! $admin_user_id ) {
            LDTT_Helper::cli_error( "No admin users found to assign as author." );
            return;
        }

        $remaining_courses = $total_courses;
        $global_counter = 1; // Start the global counter for course numbering

        while ( $remaining_courses > 0 ) {
            $courses_to_create = min( $batch_size, $remaining_courses );

            $course_ids = self::create_courses( $course_prefix, $courses_to_create, $specified_access_mode, $admin_user_id, $global_counter );
            if ( is_wp_error( $course_ids ) ) {
                LDTT_Helper::cli_error( $course_ids->get_error_message() );
                return;
            } else {
                LDTT_Helper::cli_success( "{$courses_to_create} courses successfully created with the prefix '{$course_prefix}'." );
            }

            $remaining_courses -= $batch_size;
            $global_counter += $courses_to_create; // Increment the counter by the number of courses created in this batch
        }
    }

    private static function get_admin_user_id() {
        // Query for an admin user
        $admin_users = get_users( array(
            'role'    => 'administrator',
            'orderby' => 'ID',
            'order'   => 'ASC',
            'number'  => 1,
        ) );

        if ( ! empty( $admin_users ) && is_array( $admin_users ) ) {
            return $admin_users[0]->ID; // Return the first admin user's ID
        }

        return false; // No admin user found
    }

    private static function create_courses( $course_prefix, $course_count, $admin_user_id, &$global_counter, $specified_access_mode = null ) {
        $course_ids = array();
    
        $access_modes = array(
            'open' => 'Open',
            'free' => 'Free',
            'buy-now' => 'Buy now',
            'recurring' => 'Recurring',
            'closed' => 'Closed'
        );
    
        $movie_titles = array(
            "Inception",
            "The Matrix",
            "Interstellar",
            "The Dark Knight",
            "Pulp Fiction",
            "Forrest Gump",
            "The Shawshank Redemption",
            "Fight Club",
            "The Godfather",
            "Jurassic Park",
            "The Lion King",
            "Star Wars",
            "The Avengers",
            "Back to the Future",
            "Titanic",
            "Gladiator",
            "The Lord of the Rings",
            "Harry Potter",
            "Avatar",
            "Toy Story",
            "Finding Nemo",
            "The Terminator",
            "Mad Max",
            "E.T. the Extra-Terrestrial"
        );
    
        $current_mode_index = 0;
    
        for ( $i = 1; $i <= $course_count; $i++ ) {
            // Generate a random course name from movie titles
            $course_title = self::generate_random_course_name( $movie_titles, $course_prefix, $global_counter );
            $course_content = "This is the content for {$course_title}.";
    
            $access_mode = $specified_access_mode ? $specified_access_mode : array_keys( $access_modes )[ $current_mode_index ];
    
            // Insert the course post
            $course_id = wp_insert_post( array(
                'post_title'   => $course_title,
                'post_type'    => 'sfwd-courses',
                'post_status'  => 'publish',
                'post_content' => $course_content,
                'post_author'  => $admin_user_id, // Assign the admin user as the author
            ) );
    
            if ( is_wp_error( $course_id ) ) {
                return $course_id;
            }
    
            // Update course meta with serialized data
            self::update_course_meta( $course_id, $access_mode );
    
            $course_ids[] = $course_id;
            $global_counter++; // Increment the global counter after each course creation
    
            // Cycle through access modes if no specific mode is provided
            if ( ! $specified_access_mode ) {
                $current_mode_index = ( $current_mode_index + 1 ) % count( $access_modes );
            }
        }
    
        return $course_ids;
    }    
    
    private static function generate_random_course_name( $titles, $prefix, $counter ) {
        $random_title = $titles[ array_rand( $titles ) ];
        return "{$prefix} {$counter}: {$random_title}";
    }    

    private static function update_course_meta( $course_id, $access_mode ) {
        // Prepare the base serialized array for _sfwd-courses
        $course_meta = array(
            'sfwd-courses_course_start_date' => '0',
            'sfwd-courses_course_end_date' => '0',
            'sfwd-courses_course_seats_limit' => 0,
            'sfwd-courses_course_price_type' => $access_mode, // Set the access mode here
            'sfwd-courses_custom_button_url' => get_site_url(), // Set to the site URL
            'sfwd-courses_course_price' => '100', // Set price to 100
            'sfwd-courses_course_prerequisite_enabled' => '',
            'sfwd-courses_course_prerequisite' => '',
            'sfwd-courses_course_prerequisite_compare' => 'ANY',
            'sfwd-courses_course_points_enabled' => '',
            'sfwd-courses_course_points' => '',
            'sfwd-courses_course_points_access' => '',
            'sfwd-courses_expire_access' => '',
            'sfwd-courses_expire_access_days' => 0,
            'sfwd-courses_expire_access_delete_progress' => '',
            'sfwd-courses_course_price_billing_p3' => '',
            'sfwd-courses_course_trial_price' => '',
            'sfwd-courses_course_trial_duration_t1' => '',
            'sfwd-courses_course_trial_duration_p1' => '',
            'sfwd-courses_course_price_billing_t3' => '',
            'sfwd-courses_course_materials_enabled' => '',
            'sfwd-courses_course_completion_page' => '',
            'sfwd-courses_course_materials' => '',
            'sfwd-courses_certificate' => '',
            'sfwd-courses_exam_challenge' => 0,
            'sfwd-courses_course_disable_content_table' => '',
            'sfwd-courses_course_lesson_per_page' => '',
            'sfwd-courses_course_lesson_per_page_custom' => '',
            'sfwd-courses_course_topic_per_page_custom' => '',
            'sfwd-courses_course_lesson_order_enabled' => '',
            'sfwd-courses_course_lesson_orderby' => '',
            'sfwd-courses_course_lesson_order' => '',
            'sfwd-courses_course_disable_lesson_progression' => '',
        );
    
        // Set specific values based on the access mode
        switch ( $access_mode ) {
            case 'paynow':
                $course_meta['sfwd-courses_course_price_type'] = 'paynow';
                update_post_meta( $course_id, '_ld_price_type', 'paynow' );
                break;
    
            case 'subscribe':
                $course_meta['sfwd-courses_course_price_type'] = 'subscribe';
                $course_meta['sfwd-courses_course_price_billing_p3'] = '1'; // Billing cycle (e.g., 1 month)
                $course_meta['sfwd-courses_course_price_billing_t3'] = 'M'; // Monthly
                update_post_meta( $course_id, '_ld_price_type', 'subscribe' );
                break;
    
            case 'open':
                $course_meta['sfwd-courses_course_price_type'] = 'open';
                update_post_meta( $course_id, '_ld_price_type', 'open' );
                break;
    
            case 'closed':
            default:
                $course_meta['sfwd-courses_course_price_type'] = 'closed';
                update_post_meta( $course_id, '_ld_price_type', 'closed' );
                break;
        }
    
        // Serialize the array for _sfwd-courses
        $serialized_meta = maybe_serialize( $course_meta );
        update_post_meta( $course_id, '_sfwd-courses', $serialized_meta );
    
        // Set the course steps count to 0 initially
        update_post_meta( $course_id, '_ld_course_steps_count', '0' );
    
        // Set the course steps (assuming no steps initially, can be updated later)
        $course_steps = array(
            'steps' => array(
                'h' => array(
                    'sfwd-lessons' => array(),
                    'sfwd-quiz' => array(),
                ),
            ),
            'course_id' => $course_id,
            'version' => '4.13.0',
            'empty' => true,
            'course_builder_enabled' => true,
            'course_shared_steps_enabled' => true,
            'steps_count' => 0,
        );
        update_post_meta( $course_id, 'ld_course_steps', maybe_serialize( $course_steps ) );
    
        // Set the certificate (assuming no certificate assigned initially)
        update_post_meta( $course_id, '_ld_certificate', '' );
    
        // Optional: Set edit lock and last edited by user (admin in this case)
        $admin_user_id = self::get_admin_user_id(); // Reuse the function to get an admin user
        update_post_meta( $course_id, '_edit_lock', time() . ':' . $admin_user_id );
        update_post_meta( $course_id, '_edit_last', $admin_user_id );
    }
    
}
