<?php
class LDTT_Create_Topics {

    public static function handle( $args, $assoc_args ) {
        // Get the count from parameters or default to 50
        $topic_count = isset( $assoc_args['count'] ) ? intval( $assoc_args['count'] ) : 50;

        // Check if a specific author ID is provided
        $author_id = isset( $assoc_args['author_id'] ) ? intval( $assoc_args['author_id'] ) : self::get_admin_user_id();

        // Validate if the specific author exists
        if ( ! get_user_by( 'id', $author_id ) ) {
            LDTT_Helper::cli_error( "Author ID {$author_id} is not a valid user." );
            return;
        }

        $lessons = self::get_available_lessons();

        if ( empty( $lessons ) ) {
            LDTT_Helper::cli_error( "No available lessons found to assign topics." );
            return;
        }

        $titles = self::generate_random_titles( $topic_count );

        for ( $i = 0; $i < $topic_count; $i++ ) {
            $lesson_id = $lessons[ array_rand( $lessons ) ]; // Randomly assign to a lesson
            $topic_title = isset($titles[$i]) ? trim($titles[$i]) : '';

            if ( empty( $topic_title ) ) {
                LDTT_Helper::cli_error( "Topic title cannot be empty. Skipping topic creation." );
                continue;
            }

            $topic_id = wp_insert_post( array(
                'post_title'   => $topic_title,
                'post_type'    => 'sfwd-topic',
                'post_status'  => 'publish',
                'post_author'  => $author_id, // Assign the specified user as the author
            ) );

            if ( is_wp_error( $topic_id ) ) {
                LDTT_Helper::cli_error( "Failed to create topic: " . $topic_title );
            } else {
                // Link the topic to the lesson
                self::link_topic_to_lesson( $topic_id, $lesson_id );

                // Update lesson steps
                self::update_lesson_steps( $lesson_id, $topic_id );

                // Update the course step count
                self::update_course_steps($lesson_id);

                // Ensure all necessary meta keys are set
                self::update_topic_meta($topic_id, $lesson_id);

                LDTT_Helper::cli_success( "Created topic '{$topic_title}' and assigned it to lesson ID {$lesson_id}." );
            }
        }
    }

    private static function get_available_lessons() {
        $lessons = get_posts( array(
            'post_type'   => 'sfwd-lessons',
            'numberposts' => -1,
            'post_status' => 'publish',
            'fields'      => 'ids',
        ) );

        return $lessons;
    }

    private static function generate_random_titles( $count ) {
        $news_titles = array(
            "Introduction to Physics",
            "Physics Basics",
            "Deep Dive into Physics",
            "Physics in Real Life",
            "Physics Case Studies",
            "Trending in Physics",
            "Physics Challenges & Solutions",
            "The Future of Physics",
            "Physics Exercises",
            "Wrapping Up Physics",
            "Laws of Motion",
            "Energy & Its Forms",
            "Thermodynamics Unveiled",
            "Electromagnetism 101",
            "The World of Optics",
            "Modern Physics",
            "Quantum Mechanics",
            "Nuclear Physics",
            "Particle Physics",
            "Astrophysics & Cosmology",
            "Relativity: Simple & Clear",
            "The Standard Model",
            "String Theory",
            "The Higgs Boson",
            "Dark Matter & Energy",
            "Gravitational Waves",
            "Black Holes",
            "The Big Bang",
            "The Multiverse",
            "Physics Experiments",
            "Famous Physicists",
            "Physics Nobel Prizes",
            "Women in Physics",
            "Physics in Pop Culture",
            "Physics Careers",
            "Physics & Technology",
            "Physics & Medicine",
            "Physics & Engineering",
            "Physics & the Environment",
            "Physics & Philosophy",
            "The Beauty of Physics",
            "Physics Fun Facts",
            "Physics Puzzles",
            "Physics Myths Debunked",
            "Physics & Everyday Life",
            "Physics for Kids",
            "Physics for Beginners",
            "Physics for Experts",
            "Physics Resources",
            "The Joy of Physics"
        );

        shuffle( $news_titles ); // Shuffle to get randomness

        // Return the requested number of titles, ensuring we don't exceed the array length
        return array_slice( $news_titles, 0, min($count, count($news_titles)) );
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

    private static function link_topic_to_lesson( $topic_id, $lesson_id ) {
        // Set the lesson association using LearnDash function
        learndash_set_primary_course_for_step( $topic_id, $lesson_id );

        // Update the topic meta to link it to the lesson
        update_post_meta( $topic_id, 'lesson_id', $lesson_id );
    }

    private static function update_lesson_steps( $lesson_id, $topic_id ) {
        // Get current lesson steps
        $lesson_steps = get_post_meta( $lesson_id, 'ld_lesson_steps', true );

        if ( ! is_array( $lesson_steps ) || empty( $lesson_steps ) ) {
            $lesson_steps = array(
                'steps' => array(
                    'sfwd-topic' => array(),
                ),
                'lesson_id' => $lesson_id,
                'version' => '4.13.0',
                'empty' => true,
                'steps_count' => 0,
            );
        }

        // Add the topic to the lesson steps
        if ( ! isset( $lesson_steps['steps']['sfwd-topic'] ) ) {
            $lesson_steps['steps']['sfwd-topic'] = array();
        }

        $lesson_steps['steps']['sfwd-topic'][] = $topic_id;
        $lesson_steps['steps_count'] = count( $lesson_steps['steps']['sfwd-topic'] );

        // Update the lesson steps meta
        update_post_meta( $lesson_id, 'ld_lesson_steps', maybe_serialize( $lesson_steps ) );

        // Update the lesson's step count meta
        update_post_meta( $lesson_id, '_ld_lesson_steps_count', $lesson_steps['steps_count'] );
    }

    private static function update_course_steps($lesson_id) {
        // Get the course ID associated with the lesson
        $course_id = learndash_get_course_id($lesson_id);

        if ($course_id) {
            // Get current course steps
            $course_steps = get_post_meta( $course_id, 'ld_course_steps', true );

            if ( ! is_array( $course_steps ) || empty( $course_steps ) ) {
                $course_steps = array(
                    'steps' => array(
                        'h' => array(
                            'sfwd-lessons' => array(),
                            'sfwd-topic' => array(),
                        ),
                    ),
                    'course_id' => $course_id,
                    'version' => '4.13.0',
                    'empty' => true,
                    'course_builder_enabled' => true,
                    'course_shared_steps_enabled' => true,
                    'steps_count' => 0,
                );
            }

            // Add the lesson steps to the course steps
            if ( ! isset( $course_steps['steps']['h']['sfwd-lessons'] ) ) {
                $course_steps['steps']['h']['sfwd-lessons'] = array();
            }

            $course_steps['steps']['h']['sfwd-lessons'][] = $lesson_id;
            $course_steps['steps_count'] = count( $course_steps['steps']['h']['sfwd-lessons'] );

            // Update the course steps meta
            update_post_meta( $course_id, 'ld_course_steps', maybe_serialize( $course_steps ) );

            // Update the course's step count meta
            update_post_meta( $course_id, '_ld_course_steps_count', $course_steps['steps_count'] );
        }
    }

    private static function update_topic_meta($topic_id, $lesson_id) {
        $lesson_course_id = learndash_get_course_id($lesson_id);

        $topic_meta = array(
            '_sfwd-topic' => array(
                'sfwd-topic_course' => $lesson_course_id,
                'sfwd-topic_lesson' => $lesson_id,
            ),
            'course_id' => $lesson_course_id,
            'lesson_id' => $lesson_id,
        );

        foreach ( $topic_meta as $meta_key => $meta_value ) {
            update_post_meta( $topic_id, $meta_key, $meta_value );
        }
    }
}

