<?php

class LDTT_Create_Questions {

    /**
     * Handle the WP-CLI command to create questions within a LearnDash quiz.
     *
     * @param array $args Positional arguments passed from the WP-CLI command.
     * @param array $assoc_args Associative arguments passed from the WP-CLI command.
     */
    public static function handle( $args, $assoc_args ) {
        $quiz_name = isset( $assoc_args['quiz'] ) ? $assoc_args['quiz'] : 'Sample Quiz';
        $question_count = isset( $assoc_args['questions'] ) ? intval( $assoc_args['questions'] ) : 5;

        // Get or create the quiz
        $quiz_id = self::get_or_create_quiz( $quiz_name );
        if ( is_wp_error( $quiz_id ) ) {
            LDTT_Helper::cli_error( $quiz_id->get_error_message() );
            return;
        }

        // Create questions under the quiz
        $question_ids = self::create_questions( $quiz_id, $question_count );
        if ( is_wp_error( $question_ids ) ) {
            LDTT_Helper::cli_error( $question_ids->get_error_message() );
        } else {
            LDTT_Helper::cli_success( "{$question_count} questions successfully created under the quiz '{$quiz_name}'." );
        }
    }

    /**
     * Get an existing quiz by name or create a new one.
     *
     * @param string $quiz_name The name of the quiz.
     * @return int|WP_Error The quiz ID on success, WP_Error on failure.
     */
    private static function get_or_create_quiz( $quiz_name ) {
        $quiz = get_page_by_title( $quiz_name, OBJECT, 'sfwd-quiz' );
        if ( $quiz ) {
            return $quiz->ID;
        }

        $quiz_id = wp_insert_post( array(
            'post_title'   => $quiz_name,
            'post_type'    => 'sfwd-quiz',
            'post_status'  => 'publish',
            'post_content' => 'This is a sample quiz created by the LearnDash Testing Toolkit.',
        ) );

        if ( is_wp_error( $quiz_id ) ) {
            return $quiz_id;
        }

        return $quiz_id;
    }

    /**
     * Create questions under a specified quiz.
     *
     * @param int $quiz_id The ID of the quiz.
     * @param int $question_count The number of questions to create.
     * @return array|WP_Error An array of question IDs on success, WP_Error on failure.
     */
    private static function create_questions( $quiz_id, $question_count ) {
        $question_ids = array();

        for ( $i = 1; $i <= $question_count; $i++ ) {
            $question_title = "Sample Question {$i}";
            $question_content = "This is sample question {$i} for quiz ID {$quiz_id}.";

            $question_id = wp_insert_post( array(
                'post_title'   => $question_title,
                'post_type'    => 'sfwd-question',
                'post_status'  => 'publish',
                'post_content' => $question_content,
                'post_parent'  => $quiz_id, // Associate the question with the quiz.
            ) );

            if ( is_wp_error( $question_id ) ) {
                return $question_id;
            }

            // Add default answers (this can be customized as needed)
            self::add_default_answers( $question_id );

            $question_ids[] = $question_id;
        }

        return $question_ids;
    }

    /**
     * Add default answers to a question.
     *
     * @param int $question_id The ID of the question.
     */
    private static function add_default_answers( $question_id ) {
        // Example of adding default multiple-choice answers
        $answers = array(
            array( 'answer' => 'Answer 1', 'correct' => 1 ),
            array( 'answer' => 'Answer 2', 'correct' => 0 ),
            array( 'answer' => 'Answer 3', 'correct' => 0 ),
            array( 'answer' => 'Answer 4', 'correct' => 0 ),
        );

        update_post_meta( $question_id, 'answer_data', $answers );
    }
}
