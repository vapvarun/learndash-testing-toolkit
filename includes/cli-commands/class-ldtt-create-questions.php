<?php

class LDTT_Create_Questions {

    /**
     * CLI Command to handle question creation.
     *
     * @param array $args The CLI command arguments.
     * @param array $assoc_args The associative CLI arguments.
     */
    public static function handle( $args, $assoc_args ) {
        $quiz_id = isset( $assoc_args['quiz_id'] ) ? absint( $assoc_args['quiz_id'] ) : 0;
        $count   = isset( $assoc_args['count'] ) ? absint( $assoc_args['count'] ) : 5;

        if ( ! $quiz_id || ! get_post( $quiz_id ) || get_post_type( $quiz_id ) !== learndash_get_post_type_slug( 'quiz' ) ) {
            $message = 'Invalid quiz ID provided.';
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::error( $message );
            }
            return array( 'status' => 'error', 'message' => $message );
        }

        // Generate questions
        $questions = self::generate_dummy_questions( $count );

        // Create questions and associate them with the quiz
        $created_questions = self::create_questions_for_quiz( $quiz_id, $questions );

        $message = sprintf( '%d questions created and associated with quiz ID %d.', count( $created_questions ), $quiz_id );
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::success( $message );
        }
        return array( 'status' => 'success', 'message' => $message, 'question_ids' => $created_questions );
    }

    /**
     * Create questions and associate them with a quiz.
     *
     * @param int $quiz_id The ID of the quiz.
     * @param array $questions The array of questions to create.
     * @return array Array of created question IDs.
     */
    public static function create_questions_for_quiz( $quiz_id, $questions ) {
        $admin_id = LDTT_Helper::get_admin_user_id();
        $course_id = learndash_get_course_id( $quiz_id );
        $created_questions = array();

        foreach ( $questions as $question_data ) {
            $question_title  = isset( $question_data['title'] ) ? $question_data['title'] : 'New Question';
            $question_type   = isset( $question_data['type'] ) ? $question_data['type'] : 'single';
            $question_points = isset( $question_data['points'] ) ? $question_data['points'] : 1;

            // Create question post.
            $question_id = wp_insert_post( array(
                'post_title'  => wp_strip_all_tags( $question_title ),
                'post_status' => 'publish',
                'post_type'   => learndash_get_post_type_slug( 'question' ),
                'post_author' => $admin_id,
                'post_content' => $question_title,
                'meta_input'  => array(
                    '_ldtt_test_data' => true,
                ),
            ) );

            if ( ! is_wp_error( $question_id ) ) {
                // Associate the question with the quiz using LearnDash functions
                learndash_update_setting( $question_id, 'quiz', $quiz_id );
                learndash_update_setting( $question_id, 'course', $course_id );

                $created_questions[] = $question_id;

                if ( defined( 'WP_CLI' ) && WP_CLI ) {
                    WP_CLI::line( "Created question: {$question_title} (ID: {$question_id})" );
                }
            }
        }

        return $created_questions;
    }

    /**
     * Generate dummy questions for CLI or other uses.
     *
     * @param int $count The number of dummy questions to generate.
     * @return array The array of generated dummy questions.
     */
    public static function generate_dummy_questions( $count ) {
        $dummy_questions = array();
    
        // List of predefined dummy questions.
        $predefined_questions = array(
            array(
                'title'      => 'What is the acceleration due to gravity on Earth?',
                'type'       => 'single',
                'options'    => array(
                    '9.8 m/s²',
                    '10 m/s²',
                    '9.5 m/s²',
                    '8.9 m/s²',
                ),
                'correct'    => 0, // First option is correct.
                'points'     => 1,
            ),
            array(
                'title'      => 'Which law states that for every action, there is an equal and opposite reaction?',
                'type'       => 'single',
                'options'    => array(
                    'Newton\'s First Law',
                    'Newton\'s Second Law',
                    'Newton\'s Third Law',
                    'Law of Gravitation',
                ),
                'correct'    => 2, // Third option is correct.
                'points'     => 1,
            ),
            array(
                'title'      => 'What is the formula for kinetic energy?',
                'type'       => 'single',
                'options'    => array(
                    'KE = 1/2 mv^2',
                    'KE = mv^2',
                    'KE = 1/2 mv',
                    'KE = 1/2 m^2v',
                ),
                'correct'    => 0, // First option is correct.
                'points'     => 1,
            ),
            array(
                'title'      => 'Which of the following is a scalar quantity?',
                'type'       => 'single',
                'options'    => array(
                    'Velocity',
                    'Acceleration',
                    'Force',
                    'Temperature',
                ),
                'correct'    => 3, // Fourth option is correct.
                'points'     => 1,
            ),
            array(
                'title'      => 'What is the unit of electric current?',
                'type'       => 'single',
                'options'    => array(
                    'Volt',
                    'Ampere',
                    'Ohm',
                    'Joule',
                ),
                'correct'    => 1, // Second option is correct.
                'points'     => 1,
            ),
        );
    
        // Randomly pick questions from predefined ones.
        $total_questions = count( $predefined_questions );
        for ( $i = 0; $i < $count; $i++ ) {
            $dummy_questions[] = $predefined_questions[ $i % $total_questions ];
        }
    
        return $dummy_questions;
    }
}