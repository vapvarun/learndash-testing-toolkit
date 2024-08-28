<?php

class LDTT_Create_Topics {

    /**
     * Handle the WP-CLI command to create topics within a LearnDash lesson.
     *
     * @param array $args Positional arguments passed from the WP-CLI command.
     * @param array $assoc_args Associative arguments passed from the WP-CLI command.
     */
    public static function handle( $args, $assoc_args ) {
        $lesson_name = isset( $assoc_args['lesson'] ) ? $assoc_args['lesson'] : 'Sample Lesson';
        $topic_count = isset( $assoc_args['topics'] ) ? intval( $assoc_args['topics'] ) : 3;

        // Get or create the lesson
        $lesson_id = self::get_or_create_lesson( $lesson_name );
        if ( is_wp_error( $lesson_id ) ) {
            LDTT_Helper::cli_error( $lesson_id->get_error_message() );
            return;
        }

        // Create topics under the lesson
        $topic_ids = self::create_topics( $lesson_id, $topic_count );
        if ( is_wp_error( $topic_ids ) ) {
            LDTT_Helper::cli_error( $topic_ids->get_error_message() );
        } else {
            LDTT_Helper::cli_success( "{$topic_count} topics successfully created under the lesson '{$lesson_name}'." );
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
     * Create topics under a specified lesson.
     *
     * @param int $lesson_id The ID of the lesson.
     * @param int $topic_count The number of topics to create.
     * @return array|WP_Error An array of topic IDs on success, WP_Error on failure.
     */
    private static function create_topics( $lesson_id, $topic_count ) {
        $topic_ids = array();

        for ( $i = 1; $i <= $topic_count; $i++ ) {
            $topic_title = "Sample Topic {$i}";
            $topic_content = "This is sample topic {$i} for lesson ID {$lesson_id}.";

            $topic_id = wp_insert_post( array(
                'post_title'   => $topic_title,
                'post_type'    => 'sfwd-topic',
                'post_status'  => 'publish',
                'post_content' => $topic_content,
                'post_parent'  => $lesson_id, // Associate the topic with the lesson.
            ) );

            if ( is_wp_error( $topic_id ) ) {
                return $topic_id;
            }

            $topic_ids[] = $topic_id;
        }

        return $topic_ids;
    }
}
