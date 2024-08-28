<?php
/**
 * LearnDash Create Questions.
 *
 * @since 4.2.0
 * @package LearnDash\Quiz\Questions
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'LDTT_Create_Questions' ) ) {

    /**
     * Class LDTT_Create_Questions.
     *
     * @since 4.2.0
     */
    class LDTT_Create_Questions {

        /**
         * Create questions and associate them with a quiz.
         *
         * @param int $quiz_id The ID of the quiz.
         * @param array $questions The array of questions to create.
         *
         * @return void
         */
        public function create_questions_for_quiz( $quiz_id, $questions ) {
            foreach ( $questions as $question_data ) {
                $question_title  = isset( $question_data['title'] ) ? $question_data['title'] : __( 'New Question', 'learndash' );
                $question_type   = isset( $question_data['type'] ) ? $question_data['type'] : 'single';
                $question_points = isset( $question_data['points'] ) ? $question_data['points'] : 1;

                // Create question post.
                $question_args = array(
                    'post_title'  => wp_strip_all_tags( $question_title ),
                    'post_status' => 'publish',
                    'post_type'   => 'sfwd-question',
                    'meta_input'  => array(
                        'quiz_id' => $quiz_id,
                        'type'    => $question_type,
                        'points'  => $question_points,
                        'options' => isset( $question_data['options'] ) ? $question_data['options'] : array(),
                        'correct' => isset( $question_data['correct'] ) ? $question_data['correct'] : 0,
                    ),
                );

                // Insert the question post.
                $question_id = wp_insert_post( $question_args );

                if ( ! is_wp_error( $question_id ) ) {
                    // Associate the question with the quiz.
                    add_post_meta( $quiz_id, 'question_id', $question_id );
                }
            }
        }

        /**
         * Generate dummy questions for CLI or other uses.
         *
         * @param int $count The number of dummy questions to generate.
         * @return array The array of generated dummy questions.
         */
        public function generate_dummy_questions( $count ) {
            $dummy_questions = array();
        
            // List of predefined dummy questions.
            $predefined_questions = array(
                array(
                    'title'      => __( 'What is the acceleration due to gravity on Earth?', 'learndash' ),
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
                    'title'      => __( 'Which law states that for every action, there is an equal and opposite reaction?', 'learndash' ),
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
                    'title'      => __( 'What is the formula for kinetic energy?', 'learndash' ),
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
                    'title'      => __( 'Which of the following is a scalar quantity?', 'learndash' ),
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
                    'title'      => __( 'What is the unit of electric current?', 'learndash' ),
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
                array(
                    'title'      => __( 'Which of the following is a fundamental data structure in computer science?', 'learndash' ),
                    'type'       => 'multiple',
                    'options'    => array(
                        'Array',
                        'Linked List',
                        'Tree',
                        'All of the above',
                    ),
                    'correct'    => 3, // Fourth option is correct.
                    'points'     => 1,
                ),
                array(
                    'title'      => __( 'What does CPU stand for?', 'learndash' ),
                    'type'       => 'single',
                    'options'    => array(
                        'Central Process Unit',
                        'Central Processing Unit',
                        'Control Processing Unit',
                        'Computer Process Unit',
                    ),
                    'correct'    => 1, // Second option is correct.
                    'points'     => 1,
                ),
                array(
                    'title'      => __( 'Which sorting algorithm has the best average-case complexity?', 'learndash' ),
                    'type'       => 'single',
                    'options'    => array(
                        'Bubble Sort',
                        'Quick Sort',
                        'Insertion Sort',
                        'Selection Sort',
                    ),
                    'correct'    => 1, // Second option is correct.
                    'points'     => 1,
                ),
                array(
                    'title'      => __( 'Which language is known as the "mother" of all programming languages?', 'learndash' ),
                    'type'       => 'single',
                    'options'    => array(
                        'Python',
                        'C',
                        'Java',
                        'Assembly',
                    ),
                    'correct'    => 1, // Second option is correct.
                    'points'     => 1,
                ),
                array(
                    'title'      => __( 'What does HTML stand for?', 'learndash' ),
                    'type'       => 'single',
                    'options'    => array(
                        'HyperText Markup Language',
                        'HyperText Markdown Language',
                        'HighText Markup Language',
                        'HyperTool Markup Language',
                    ),
                    'correct'    => 0, // First option is correct.
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

        /**
         * CLI Command to handle question creation.
         *
         * @param array $args The CLI command arguments.
         * @param array $assoc_args The associative CLI arguments.
         *
         * @return void
         */
        public function handle( $args, $assoc_args ) {
            $quiz_id = isset( $assoc_args['quiz_id'] ) ? absint( $assoc_args['quiz_id'] ) : 0;
            $count   = isset( $assoc_args['count'] ) ? absint( $assoc_args['count'] ) : 5;

            if ( ! $quiz_id || ! get_post( $quiz_id ) || 'quiz' !== get_post_type( $quiz_id ) ) {
                WP_CLI::error( __( 'Invalid quiz ID provided.', 'learndash' ) );
                return;
            }

            // Generate dummy questions.
            $questions = $this->generate_dummy_questions( $count );

            // Create questions and associate them with the quiz.
            $this->create_questions_for_quiz( $quiz_id, $questions );

            WP_CLI::success( sprintf( __( '%d questions created and associated with quiz ID %d.', 'learndash' ), $count, $quiz_id ) );
        }
    }

    // Register the CLI command.
    if ( defined( 'WP_CLI' ) && WP_CLI ) {
        WP_CLI::add_command( 'ldtt create-questions', array( 'LDTT_Create_Questions', 'handle' ) );
    }
}
