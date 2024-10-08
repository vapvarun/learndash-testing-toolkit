<?php

class LDTT_Sample_Data {

    /**
     * Generate a sample course with associated lessons, topics, quizzes, and questions.
     *
     * @param string $course_name The name of the course.
     * @return int The ID of the created course.
     */
    public static function create_sample_course( $course_name = 'Sample Course' ) {
        $course_id = self::create_course( $course_name );

        if ( $course_id ) {
            $lesson_ids = self::create_sample_lessons( $course_id );
            foreach ( $lesson_ids as $lesson_id ) {
                $topic_ids = self::create_sample_topics( $lesson_id );
                foreach ( $topic_ids as $topic_id ) {
                    $quiz_id = self::create_sample_quiz( $topic_id );
                    self::create_sample_questions( $quiz_id );
                }
            }
        }

        return $course_id;
    }

    /**
     * Create a sample course.
     *
     * @param string $course_name The name of the course.
     * @return int The ID of the created course.
     */
    public static function create_course( $course_name ) {
        $course_id = wp_insert_post( array(
            'post_title'   => $course_name,
            'post_type'    => 'sfwd-courses',
            'post_status'  => 'publish',
            'post_content' => 'This is a sample course generated by the LearnDash Testing Toolkit.',
        ) );

        return $course_id;
    }

    /**
     * Create sample lessons for a course.
     *
     * @param int $course_id The ID of the course.
     * @return array An array of created lesson IDs.
     */
    public static function create_sample_lessons( $course_id ) {
        $lessons = array();

        for ( $i = 1; $i <= 3; $i++ ) {
            $lesson_id = wp_insert_post( array(
                'post_title'   => "Sample Lesson {$i}",
                'post_type'    => 'sfwd-lessons',
                'post_status'  => 'publish',
                'post_content' => "This is sample lesson {$i} for course ID {$course_id}.",
            ) );

            if ( $lesson_id ) {
                ld_update_course_access( $course_id, $lesson_id, true );
                $lessons[] = $lesson_id;
            }
        }

        return $lessons;
    }

    /**
     * Create sample topics for a lesson.
     *
     * @param int $lesson_id The ID of the lesson.
     * @return array An array of created topic IDs.
     */
    public static function create_sample_topics( $lesson_id ) {
        $topics = array();

        for ( $i = 1; $i <= 2; $i++ ) {
            $topic_id = wp_insert_post( array(
                'post_title'   => "Sample Topic {$i}",
                'post_type'    => 'sfwd-topic',
                'post_status'  => 'publish',
                'post_content' => "This is sample topic {$i} for lesson ID {$lesson_id}.",
            ) );

            if ( $topic_id ) {
                $topics[] = $topic_id;
            }
        }

        return $topics;
    }

    /**
     * Create a sample quiz for a topic.
     *
     * @param int $topic_id The ID of the topic.
     * @return int The ID of the created quiz.
     */
    public static function create_sample_quiz( $topic_id ) {
        $quiz_id = wp_insert_post( array(
            'post_title'   => 'Sample Quiz',
            'post_type'    => 'sfwd-quiz',
            'post_status'  => 'publish',
            'post_content' => "This is a sample quiz for topic ID {$topic_id}.",
        ) );

        if ( $quiz_id ) {
            update_post_meta( $quiz_id, 'quiz_topic_id', $topic_id );
        }

        return $quiz_id;
    }

    /**
     * Create sample questions for a quiz.
     *
     * @param int $quiz_id The ID of the quiz.
     */
    public static function create_sample_questions( $quiz_id ) {
        for ( $i = 1; $i <= 5; $i++ ) {
            wp_insert_post( array(
                'post_title'   => "Sample Question {$i}",
                'post_type'    => 'sfwd-question',
                'post_status'  => 'publish',
                'post_content' => "This is sample question {$i} for quiz ID {$quiz_id}.",
            ) );
        }
    }
}
