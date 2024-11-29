<?php
class LDTT_Create_Lessons {

    public static function handle( $args = array(), $assoc_args = array() ) {
        // Check for admin input if no arguments are provided
        if ( empty( $args ) && empty( $assoc_args ) ) {
            $assoc_args = array(
                'count'     => isset( $_POST['count'] ) ? intval( $_POST['count'] ) : 50,
                'course_id' => isset( $_POST['course_id'] ) ? intval( $_POST['course_id'] ) : null,
            );
        }

        $lesson_count = $assoc_args['count'];
        $specific_course_id = $assoc_args['course_id'];

        // Validate the course ID if provided
        if ( $specific_course_id && get_post_type( $specific_course_id ) !== 'sfwd-courses' ) {
            return array( 'status' => 'error', 'message' => "Course ID {$specific_course_id} is not a valid course." );
        }

        $courses = $specific_course_id ? array( $specific_course_id ) : self::get_available_courses();

        if ( empty( $courses ) ) {
            return array( 'status' => 'error', 'message' => "No available courses found to assign lessons." );
        }

        $titles = self::generate_random_titles( $lesson_count );
        $admin_user_id = self::get_admin_user_id();

        if ( ! $admin_user_id ) {
            return array( 'status' => 'error', 'message' => 'No admin users found to assign as author.' );
        }

        $created_lessons = array();

        for ( $i = 0; $i < $lesson_count; $i++ ) {
            $course_id = $specific_course_id ? $specific_course_id : $courses[ array_rand( $courses ) ];
            $lesson_title = isset( $titles[ $i ] ) ? trim( $titles[ $i ] ) : '';

            if ( empty( $lesson_title ) ) {
                continue;
            }

            $lesson_id = wp_insert_post( array(
                'post_title'   => $lesson_title,
                'post_type'    => 'sfwd-lessons',
                'post_status'  => 'publish',
                'post_author'  => $admin_user_id,
            ) );

            if ( is_wp_error( $lesson_id ) ) {
                return array( 'status' => 'error', 'message' => "Failed to create lesson: {$lesson_title}" );
            }

            // Link the lesson to the course and update steps
            self::link_lesson_to_course( $lesson_id, $course_id );
            self::update_course_steps( $course_id, $lesson_id );

            $created_lessons[] = $lesson_id;
        }

        return array(
            'status'  => 'success',
            'message' => count( $created_lessons ) . " lessons created successfully.",
            'lesson_ids' => $created_lessons,
        );
    }

    private static function get_available_courses() {
        $courses = get_posts( array(
            'post_type'   => 'sfwd-courses',
            'numberposts' => -1,
            'post_status' => 'publish',
            'fields'      => 'ids',
        ) );

        return $courses;
    }

    private static function generate_random_titles( $count ) {
        $news_titles = array(
            "Breaking News: New Tech Innovations Unveiled",
            "Economy Update: Market Trends and Analysis",
            "Health Advisory: Tips for a Balanced Life",
            "Sports Highlight: Major League Events Recap",
            "Science Discoveries: New Findings and Theories",
            "Global Politics: Current Affairs and Analysis",
            "Environment: Challenges and Solutions",
            "Business Growth: Strategies for Success",
            "Education Reform: The Future of Learning",
            "Cultural Insights: Exploring Global Traditions",
            "Technology Breakthroughs: What's Next?",
            "Travel Trends: Top Destinations This Year",
            "Automotive Industry: Future of Transportation",
            "Entertainment Buzz: Upcoming Movies and Shows",
            "Social Media: Impact on Modern Society",
            "Finance Tips: Managing Your Investments",
            "Medical Research: Advancements in Healthcare",
            "Real Estate: Market Opportunities and Risks",
            "Legal Insights: Understanding Your Rights",
            "Workplace Dynamics: Building a Positive Culture",
            "Retail Trends: The Future of Shopping",
            "Telecommunications: Keeping the World Connected",
            "Public Health: Addressing Global Challenges",
            "Art and Design: Exploring Creative Spaces",
            "Environmental Policy: Steps Toward Sustainability",
            "Consumer Behavior: What Drives Purchases?",
            "Cybersecurity: Protecting Your Digital Life",
            "Agriculture: Innovations in Food Production",
            "Urban Development: Designing Future Cities",
            "Space Exploration: The Next Frontier",
            "Fashion Industry: Trends and Sustainability",
            "Food and Nutrition: Healthy Eating Habits",
            "Pharmaceuticals: Drug Development and Ethics",
            "Renewable Energy: The Power of the Future",
            "Military Strategy: Analyzing Global Conflicts",
            "Psychology: Understanding Human Behavior",
            "Public Relations: Building Brand Reputation",
            "E-commerce: The Evolution of Online Shopping",
            "Supply Chain: Efficiency and Resilience",
            "Tourism Industry: Adapting to New Realities",
            "Gaming Industry: What's Hot and Trending",
            "Human Resources: Best Practices in Recruitment",
            "Fitness Trends: Staying Active and Healthy",
            "Cryptocurrency: The Future of Money?",
            "Artificial Intelligence: Impact on Jobs",
            "Philanthropy: Making a Difference",
            "Social Justice: Movements and Reforms",
            "Veterinary Science: Advances in Animal Care",
            "Transportation: Innovations in Mobility",
            "Waste Management: Sustainable Practices",
            "Child Development: Early Education Strategies",
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

    private static function link_lesson_to_course( $lesson_id, $course_id ) {
        // Set the course association using LearnDash function
        learndash_set_primary_course_for_step( $lesson_id, $course_id );

        // Update the lesson meta to link it to the course
        update_post_meta( $lesson_id, 'course_id', $course_id );
    }

    private static function update_course_steps( $course_id, $lesson_id ) {
        // Get current course steps
        $course_steps = get_post_meta( $course_id, 'ld_course_steps', true );

        if ( ! is_array( $course_steps ) || empty( $course_steps ) ) {
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
        }

        // Add the lesson to the course steps
        if ( ! isset( $course_steps['steps']['h']['sfwd-lessons'] ) ) {
            $course_steps['steps']['h']['sfwd-lessons'] = array();
        }

        $course_steps['steps']['h']['sfwd-lessons'][] = $lesson_id;
        $course_steps['steps_count'] = count( $course_steps['steps']['h']['sfwd-lessons'] );

        // Update the course steps meta
        update_post_meta( $course_id, 'ld_course_steps', maybe_serialize( $course_steps ) );

        // Update the lesson's course step count meta
        update_post_meta( $course_id, '_ld_course_steps_count', $course_steps['steps_count'] );
    }
}
