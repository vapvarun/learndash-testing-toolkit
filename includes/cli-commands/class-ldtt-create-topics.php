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
        
        // Get an author ID with fallback to current user
        $author_id = LDTT_Helper::get_author_id();
        if ( ! $author_id ) {
            // This should never happen with the new helper, but just in case
            $author_id = 1;
        }

        // Validate if the lesson exists if specified
        if ( $specific_lesson_id && get_post_type( $specific_lesson_id ) !== learndash_get_post_type_slug( 'lesson' ) ) {
            $message = "Lesson ID {$specific_lesson_id} is not a valid lesson.";
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

        $created_topics = array();
        $lesson_distribution = array();

        for ( $i = 0; $i < $topic_count; $i++ ) {
            // Use round-robin distribution when no specific lesson is provided
            if ( $specific_lesson_id ) {
                $lesson_id = $specific_lesson_id;
            } else {
                // Distribute topics evenly across all lessons
                $lesson_index = $i % count( $lessons );
                $lesson_id = $lessons[ $lesson_index ];
            }
            
            // Track distribution for reporting
            if ( ! isset( $lesson_distribution[ $lesson_id ] ) ) {
                $lesson_distribution[ $lesson_id ] = 0;
            }
            $lesson_distribution[ $lesson_id ]++;
            
            $course_id = learndash_get_course_id( $lesson_id );
            $topic_title = LDTT_Sample_Data::get_random_topic_title();

            if ( empty( $topic_title ) ) {
                continue;
            }

            $topic_id = wp_insert_post( array(
                'post_title'   => $topic_title,
                'post_type'    => learndash_get_post_type_slug( 'topic' ),
                'post_status'  => 'publish',
                'post_author'  => $author_id,
                'post_content' => LDTT_Sample_Data::get_random_topic_content(),
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
                $lesson_title = get_the_title( $lesson_id );
                WP_CLI::line( "Created topic: {$topic_title} (ID: {$topic_id}) for lesson: {$lesson_title}" );
            }
        }

        $message = count( $created_topics ) . " topics created successfully.";
        
        // Add distribution information if topics were spread across lessons
        if ( ! $specific_lesson_id && count( $lesson_distribution ) > 1 ) {
            $message .= " Topics distributed across " . count( $lesson_distribution ) . " lessons.";
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::line( "\nDistribution summary:" );
                foreach ( $lesson_distribution as $lid => $count ) {
                    $lesson_title = get_the_title( $lid );
                    WP_CLI::line( "  - {$lesson_title} (ID: {$lid}): {$count} topics" );
                }
            }
        }
        
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::success( $message );
        }
        return array(
            'status' => 'success',
            'message' => $message,
            'topic_ids' => $created_topics,
            'distribution' => $lesson_distribution,
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