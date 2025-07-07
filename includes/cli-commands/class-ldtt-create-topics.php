<?php

class LDTT_Create_Topics {

    public static function handle( $args = array(), $assoc_args = array() ) {
        // Check for admin input if no arguments are provided
        if ( empty( $args ) && empty( $assoc_args ) ) {
            $assoc_args = array(
                'count'     => isset( $_POST['count'] ) ? intval( $_POST['count'] ) : 20,
                'lesson_id' => isset( $_POST['lesson_id'] ) ? intval( $_POST['lesson_id'] ) : null,
            );
        }

        $topic_count = LDTT_Helper::validate_positive_int( $assoc_args['count'] ?? 20, 20, 1000 );
        $specific_lesson_id = ! empty( $assoc_args['lesson_id'] ) ? absint( $assoc_args['lesson_id'] ) : null;
        $author_id = LDTT_Helper::get_admin_user_id();

        // Validate if the lesson exists if specified
        if ( $specific_lesson_id && get_post_type( $specific_lesson_id ) !== learndash_get_post_type_slug( 'lesson' ) ) {
            $message = "Lesson ID {$specific_lesson_id} is not a valid lesson.";
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::error( $message );
            }
            return array( 'status' => 'error', 'message' => $message );
        }

        if ( ! $author_id ) {
            $message = 'No admin users found to assign as author.';
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::error( $message );
            }
            return array( 'status' => 'error', 'message' => $message );
        }

        $lessons = $specific_lesson_id ? array( $specific_lesson_id ) : self::get_available_lessons();

        if ( empty( $lessons ) ) {
            $message = "No available lessons found to assign topics.";
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::error( $message );
            }
            return array( 'status' => 'error', 'message' => $message );
        }

        $titles = LDTT_Helper::get_random_lesson_titles( $topic_count );
        $created_topics = array();

        for ( $i = 0; $i < $topic_count; $i++ ) {
            $lesson_id = $specific_lesson_id ? $specific_lesson_id : $lessons[ array_rand( $lessons ) ];
            $course_id = learndash_get_course_id( $lesson_id );
            $topic_title = isset( $titles[ $i ] ) ? trim( $titles[ $i ] ) : "Topic " . ( $i + 1 );

            if ( empty( $topic_title ) ) {
                continue;
            }

            $topic_id = wp_insert_post( array(
                'post_title'   => $topic_title,
                'post_type'    => learndash_get_post_type_slug( 'topic' ),
                'post_status'  => 'publish',
                'post_author'  => $author_id,
                'post_content' => "This is a test topic: {$topic_title}",
                'meta_input'   => array(
                    '_ldtt_test_data' => true,
                ),
            ) );

            if ( is_wp_error( $topic_id ) ) {
                if ( defined( 'WP_CLI' ) && WP_CLI ) {
                    WP_CLI::warning( "Failed to create topic: {$topic_title}" );
                }
                continue;
            }

            // Associate topic with lesson and course using LearnDash functions
            learndash_update_setting( $topic_id, 'course', $course_id );
            learndash_update_setting( $topic_id, 'lesson', $lesson_id );

            $created_topics[] = $topic_id;

            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::line( "Created topic: {$topic_title} (ID: {$topic_id})" );
            }
        }

        $message = count( $created_topics ) . " topics created successfully.";
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::success( $message );
        }
        return array(
            'status' => 'success',
            'message' => $message,
            'topic_ids' => $created_topics,
        );
    }

    private static function get_available_lessons() {
        $lessons = get_posts( array(
            'post_type'   => learndash_get_post_type_slug( 'lesson' ),
            'numberposts' => -1,
            'post_status' => 'publish',
            'fields'      => 'ids',
        ) );

        return $lessons;
    }
}