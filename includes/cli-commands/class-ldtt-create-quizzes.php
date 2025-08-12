<?php

class LDTT_Create_Quizzes {

    public static function handle( $args = array(), $assoc_args = array() ) {
        // Check for admin input if no arguments are provided
        if ( empty( $args ) && empty( $assoc_args ) ) {
            $assoc_args = array(
                'count'     => isset( $_POST['count'] ) ? intval( $_POST['count'] ) : 5,
                'questions' => isset( $_POST['questions'] ) ? intval( $_POST['questions'] ) : 5,
            );
        }

        $quiz_count = LDTT_Helper::validate_positive_int( $assoc_args['count'] ?? 5, 5, 100 );
        $questions_per_quiz = LDTT_Helper::validate_positive_int( $assoc_args['questions'] ?? 5, 5, 50 );

        // Get available lessons
        $lessons = get_posts( array(
            'post_type' => learndash_get_post_type_slug( 'lesson' ),
            'numberposts' => -1,
            'post_status' => 'publish',
            'fields' => 'ids',
        ) );

        if ( empty( $lessons ) ) {
            $message = "No lessons available for quiz assignment.";
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::error( $message );
            }
            return array( 'status' => 'error', 'message' => $message );
        }

        // Get an author ID with fallback to current user
        $admin_id = LDTT_Helper::get_author_id();
        if ( ! $admin_id ) {
            // This should never happen with the new helper, but just in case
            $admin_id = 1;
        }
        
        $created_quizzes = array();

        for ( $i = 0; $i < $quiz_count; $i++ ) {
            $lesson_id = $lessons[ array_rand( $lessons ) ];
            $course_id = learndash_get_course_id( $lesson_id );
            $title = 'Test Quiz ' . ( $i + 1 );

            $quiz_id = wp_insert_post( array(
                'post_title' => $title,
                'post_type' => learndash_get_post_type_slug( 'quiz' ),
                'post_status' => 'publish',
                'post_author' => $admin_id,
                'post_content' => "This is a test quiz: {$title}",
                'meta_input' => array(
                    '_ldtt_test_data' => true,
                ),
            ) );

            if ( is_wp_error( $quiz_id ) ) {
                if ( defined( 'WP_CLI' ) && WP_CLI ) {
                    WP_CLI::warning( "Failed to create quiz: {$title}" );
                }
                continue;
            }

            // Associate quiz with lesson and course using LearnDash functions
            learndash_update_setting( $quiz_id, 'course', $course_id );
            learndash_update_setting( $quiz_id, 'lesson', $lesson_id );

            // Create questions for this quiz
            self::create_questions_for_quiz( $quiz_id, $questions_per_quiz );

            $created_quizzes[] = $quiz_id;

            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::line( "Created quiz: {$title} (ID: {$quiz_id})" );
            }
        }

        $message = count( $created_quizzes ) . " quizzes created successfully with {$questions_per_quiz} questions each.";
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::success( $message );
        }
        return array(
            'status' => 'success',
            'message' => $message,
            'quiz_ids' => $created_quizzes,
        );
    }

    private static function create_questions_for_quiz( $quiz_id, $count ) {
        // Get an author ID with fallback to current user
        $admin_id = LDTT_Helper::get_author_id();
        if ( ! $admin_id ) {
            // This should never happen with the new helper, but just in case
            $admin_id = 1;
        }
        $course_id = learndash_get_course_id( $quiz_id );

        $sample_questions = array(
            array(
                'title' => 'What is the capital of France?',
                'answers' => array( 'Paris', 'London', 'Berlin', 'Madrid' ),
                'correct' => 0,
            ),
            array(
                'title' => 'Which programming language is known for web development?',
                'answers' => array( 'C++', 'JavaScript', 'Assembly', 'FORTRAN' ),
                'correct' => 1,
            ),
            array(
                'title' => 'What does HTML stand for?',
                'answers' => array( 'HyperText Markup Language', 'High Tech Modern Language', 'Home Tool Markup Language', 'Hyperlink and Text Markup Language' ),
                'correct' => 0,
            ),
            array(
                'title' => 'Which planet is closest to the Sun?',
                'answers' => array( 'Venus', 'Earth', 'Mercury', 'Mars' ),
                'correct' => 2,
            ),
            array(
                'title' => 'What is 2 + 2?',
                'answers' => array( '3', '4', '5', '6' ),
                'correct' => 1,
            ),
        );

        for ( $i = 0; $i < $count; $i++ ) {
            $question_data = $sample_questions[ $i % count( $sample_questions ) ];
            $title = 'Question ' . ( $i + 1 ) . ': ' . $question_data['title'];

            $question_id = wp_insert_post( array(
                'post_title' => $title,
                'post_type' => learndash_get_post_type_slug( 'question' ),
                'post_status' => 'publish',
                'post_author' => $admin_id,
                'post_content' => $question_data['title'],
                'meta_input' => array(
                    '_ldtt_test_data' => true,
                ),
            ) );

            if ( ! is_wp_error( $question_id ) ) {
                // Associate question with quiz and course
                learndash_update_setting( $question_id, 'quiz', $quiz_id );
                learndash_update_setting( $question_id, 'course', $course_id );
            }
        }
    }
}