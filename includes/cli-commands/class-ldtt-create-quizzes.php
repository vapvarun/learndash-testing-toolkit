<?php
/**
 * LearnDash Create Quizzes.
 *
 * @since 4.2.0
 * @package LearnDash\Quiz\Quizzes
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'LDTT_Create_Quizzes' ) ) {

    /**
     * Class LDTT_Create_Quizzes.
     *
     * @since 4.2.0
     */
    class LDTT_Create_Quizzes {

        /**
         * Handle the WP-CLI command for creating a quiz.
         *
         * @param array $args The positional arguments from WP-CLI.
         * @param array $assoc_args The associative arguments from WP-CLI.
         *
         * @return void
         */
        public function handle( $args, $assoc_args ) {
            $quiz_title = isset( $assoc_args['title'] ) ? $assoc_args['title'] : 'Untitled Quiz';
            $questions_count = isset( $assoc_args['questions'] ) ? (int) $assoc_args['questions'] : 10;

            // Generate dummy questions if necessary.
            $questions = $this->generate_dummy_questions( $questions_count );

            // Create the quiz.
            $quiz_id = $this->create_quiz( $quiz_title, array(), $questions );

            if ( is_wp_error( $quiz_id ) ) {
                WP_CLI::error( $quiz_id->get_error_message() );
            } else {
                WP_CLI::success( "Quiz created with ID: $quiz_id" );
            }
        }

        /**
         * Create a quiz and optionally create and associate questions.
         *
         * @param string $quiz_title The title of the quiz.
         * @param array $quiz_data The data related to the quiz.
         * @param array $questions Array of questions to create and associate with the quiz.
         *
         * @return int|WP_Error The new quiz ID or WP_Error on failure.
         */
        public function create_quiz( $quiz_title, $quiz_data = array(), $questions = array() ) {
            if ( empty( $quiz_title ) ) {
                return new WP_Error( 'missing_data', __( 'Missing quiz title.', 'learndash' ) );
            }

            // Prepare quiz post data.
            $quiz_args = array(
                'post_title'  => wp_strip_all_tags( $quiz_title ),
                'post_status' => 'publish',
                'post_type'   => 'sfwd-quiz',
                'meta_input'  => $quiz_data,
            );

            // Insert the quiz post.
            $quiz_id = wp_insert_post( $quiz_args );

            if ( is_wp_error( $quiz_id ) ) {
                return $quiz_id;
            }

            // Associate quiz with a lesson if not already associated.
            if ( empty( $quiz_data['lesson_id'] ) ) {
                $lesson_id = $this->get_default_lesson_id();
                if ( ! empty( $lesson_id ) ) {
                    update_post_meta( $quiz_id, 'lesson_id', $lesson_id );
                }
            }

            // Ensure a minimum of 5 questions is provided.
            if ( count( $questions ) < 5 ) {
                $questions = array_merge( $questions, $this->generate_dummy_questions( 5 - count( $questions ) ) );
            }

            // Create and associate questions with the quiz.
            if ( ! empty( $questions ) ) {
                $question_creator = new LDTT_Create_Questions();
                $question_creator->create_questions_for_quiz( $quiz_id, $questions );
            }

            return $quiz_id;
        }

        /**
         * Get a random lesson ID that does not already have an associated quiz.
         *
         * @return int The lesson ID or 0 if no available lessons are found.
         */
        protected function get_default_lesson_id() {
            $args = array(
                'post_type'      => 'lesson',
                'posts_per_page' => -1, // Fetch all lessons.
                'post_status'    => 'publish',
                'fields'         => 'ids', // Corrected the assignment operator here.
            );
        
            $lessons = get_posts( $args );
        
            if ( ! empty( $lessons ) && is_array( $lessons ) ) {
                // Filter out lessons that already have quizzes associated with them.
                $available_lessons = array_filter( $lessons, function( $lesson_id ) {
                    $quiz_id = get_post_meta( $lesson_id, 'quiz_id', true );
                    return empty( $quiz_id );
                });
        
                if ( ! empty( $available_lessons ) ) {
                    // Randomize the selection.
                    return $available_lessons[ array_rand( $available_lessons ) ];
                }
            }
        
            return 0; // Return 0 if no available lessons are found.
        }
        

        /**
         * Generate dummy questions to ensure a minimum of 5 questions per quiz.
         *
         * @param int $count The number of dummy questions to generate.
         * @return array The array of dummy questions.
         */
        protected function generate_dummy_questions( $count ) {
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
            for ( $i = 0; $i < $count; $dummy_questions[] = $predefined_questions[ $i % $total_questions ], $i++ );

            return $dummy_questions;
        }
    }

    // Register the CLI command.
    if ( defined( 'WP_CLI' ) && WP_CLI ) {
        WP_CLI::add_command( 'ldtt create_quizzes', array( 'LDTT_Create_Quizzes', 'handle' ) );
    }
}
