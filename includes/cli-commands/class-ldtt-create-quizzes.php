<?php

class LDTT_Create_Quizzes {

    /**
     * Handle the WP-CLI command to create quizzes within a LearnDash lesson or topic.
     *
     * @param array $args Positional arguments passed from the WP-CLI command.
     * @param array $assoc_args Associative arguments passed from the WP-CLI command.
     */
    public static function handle( $args, $assoc_args ) {
        $lesson_name = isset( $assoc_args['lesson'] ) ? $assoc_args['lesson'] : null;
        $topic_name = isset( $assoc_args['topic'] ) ? $assoc_args['topic'] : null;
        $quiz_count = isset( $assoc_args['quizzes'] ) ? intval( $assoc_args['quizzes'] ) : 1;

        if ( !$lesson_name && !$topic_name ) {
            LDTT_Helper::cli_error( "Please specify either a lesson or a topic to associate the quizzes with." );
            return;
        }

        // Get or create the lesson or topic
        $parent_id = $lesson_name ? self::get_or_create_lesson( $lesson_name ) : self::get_or_create_topic( $topic_name );
        if ( is_wp_error( $parent_id ) ) {
            LDTT_Helper::cli_error( $parent_id->get_error_message() );
            return;
        }

        // Create quizzes under the lesson or topic
        $quiz_ids = self::create_quizzes( $parent_id, $quiz_count );
        if ( is_wp_error( $quiz_ids ) ) {
            LDTT_Helper::cli_error( $quiz_ids->get_error_message() );
        } else {
            $parent_type = $lesson_name ? 'lesson' : 'topic';
            $parent_name = $lesson_name ?: $topic_name;
            LDTT_Helper::cli_success( "{$quiz_count} quizzes successfully created under the {$parent_type} '{$parent_name}'." );
        }
    }

    /**
     * Get an existing lesson by name or create a new one.
     *
     * @param string $lesson_name The name of the lesson.
     * @return int|WP_Error The lesson ID on success, WP_Error on failure.
     */
    private static function get_or_create_lesson( $lesson_name ) {
        $lesson = get_page_by_title( $lesson_name, OBJECT, 'sfwd-lessons' );
        if ( $lesson ) {
            return $lesson->ID;
        }

        $lesson_id = wp_insert_post( array(
            'post_title'   => $lesson_name,
            'post_type'    => 'sfwd-lessons',
            'post_status'  => 'publish',
            'post_content' => 'This is a sample lesson created by the LearnDash Testing Toolkit.',
        ) );

        if ( is_wp_error( $lesson_id ) ) {
            return $lesson_id;
        }

        return $lesson_id;
    }

    /**
     * Get an existing topic by name or create a new one.
     *
     * @param string $topic_name The name of the topic.
     * @return int|WP_Error The topic ID on success, WP_Error on failure.
     */
    private static function get_or_create_topic( $topic_name ) {
        $topic = get_page_by_title( $topic_name, OBJECT, 'sfwd-topic' );
        if ( $topic ) {
            return $topic->ID;
        }

        $topic_id = wp_insert_post( array(
            'post_title'   => $topic_name,
            'post_type'    => 'sfwd-topic',
            'post_status'  => 'publish',
            'post_content' => 'This is a sample topic created by the LearnDash Testing Toolkit.',
        ) );

        if ( is_wp_error( $topic_id ) ) {
            return $topic_id;
        }

        return $topic_id;
    }

    /**
     * Create quizzes under a specified lesson or topic.
     *
     * @param int $parent_id The ID of the parent lesson or topic.
     * @param int $quiz_count The number of quizzes to create.
     * @return array|WP_Error An array of quiz IDs on success, WP_Error on failure.
     */
    private static function create_quizzes( $parent_id, $quiz_count ) {
        $quiz_ids = array();

        for ( $i = 1; $i <= $quiz_count; $i++ ) {
            $quiz_title = "Sample Quiz {$i}";
            $quiz_content = "This is sample quiz {$i} for parent ID {$parent_id}.";

            $quiz_id = wp_insert_post( array(
                'post_title'   => $quiz_title,
                'post_type'    => 'sfwd-quiz',
                'post_status'  => 'publish',
                'post_content' => $quiz_content,
                'post_parent'  => $parent_id, // Associate the quiz with the lesson or topic.
            ) );

            if ( is_wp_error( $quiz_id ) ) {
                return $quiz_id;
            }

            $quiz_ids[] = $quiz_id;
        }

        return $quiz_ids;
    }
}
