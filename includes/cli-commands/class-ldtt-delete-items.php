<?php

class LDTT_Delete_Items {

    public static function handle( $args = array(), $assoc_args = array() ) {
        // Check for admin input if no CLI arguments are provided
        if ( empty( $args ) && empty( $assoc_args ) ) {
            $assoc_args = array(
                'courses'   => isset( $_POST['courses'] ) ? true : false,
                'lessons'   => isset( $_POST['lessons'] ) ? true : false,
                'topics'    => isset( $_POST['topics'] ) ? true : false,
                'quizzes'   => isset( $_POST['quizzes'] ) ? true : false,
                'users'     => isset( $_POST['users'] ) ? true : false,
                'confirm'   => isset( $_POST['confirm'] ) ? true : false,
            );
        }

        $delete_courses = isset( $assoc_args['courses'] ) && $assoc_args['courses'];
        $delete_lessons = isset( $assoc_args['lessons'] ) && $assoc_args['lessons'];
        $delete_topics = isset( $assoc_args['topics'] ) && $assoc_args['topics'];
        $delete_quizzes = isset( $assoc_args['quizzes'] ) && $assoc_args['quizzes'];
        $delete_groups = isset( $assoc_args['groups'] ) && $assoc_args['groups'];
        $delete_users = isset( $assoc_args['users'] ) && $assoc_args['users'];
        $confirm = isset( $assoc_args['confirm'] ) && $assoc_args['confirm'];

        // Validate the input
        if ( ! $delete_courses && ! $delete_lessons && ! $delete_topics && ! $delete_quizzes && ! $delete_groups && ! $delete_users ) {
            $message = "Please specify at least one type of item to delete.";
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::error( $message );
            }
            return array( 'status' => 'error', 'message' => $message );
        }

        if ( ! $confirm ) {
            $message = "Confirmation required for data deletion.";
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::error( $message );
            }
            return array( 'status' => 'error', 'message' => $message );
        }

        $results = array();

        // Delete courses
        if ( $delete_courses ) {
            $results['courses'] = self::delete_items( learndash_get_post_type_slug( 'course' ), 'Course' );
        }

        // Delete lessons
        if ( $delete_lessons ) {
            $results['lessons'] = self::delete_items( learndash_get_post_type_slug( 'lesson' ), 'Lesson' );
        }

        // Delete topics
        if ( $delete_topics ) {
            $results['topics'] = self::delete_items( learndash_get_post_type_slug( 'topic' ), 'Topic' );
        }

        // Delete quizzes and questions
        if ( $delete_quizzes ) {
            $results['quizzes'] = self::delete_items( learndash_get_post_type_slug( 'quiz' ), 'Quiz' );
            $results['questions'] = self::delete_items( learndash_get_post_type_slug( 'question' ), 'Question' );
        }

        // Delete groups
        if ( $delete_groups ) {
            $results['groups'] = self::delete_items( learndash_get_post_type_slug( 'group' ), 'Group' );
        }

        // Delete users
        if ( $delete_users ) {
            $results['users'] = self::delete_test_users();
        }

        $total_deleted = array_sum( array_map( function( $result ) {
            return is_array( $result ) ? ( $result['count'] ?? 0 ) : 0;
        }, $results ) );

        $message = "Deletion completed. Total items deleted: {$total_deleted}";
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::success( $message );
            foreach ( $results as $type => $result ) {
                if ( is_array( $result ) ) {
                    WP_CLI::line( ucfirst( $type ) . ': ' . ( $result['count'] ?? 0 ) . ' deleted' );
                }
            }
        }

        return array(
            'status'  => 'success',
            'message' => $message,
            'details' => $results,
        );
    }

    private static function delete_items( $post_type, $item_name ) {
        // Get items marked as test data or with "Test" in the title
        $items = get_posts( array(
            'post_type'   => $post_type,
            'numberposts' => -1,
            'post_status' => array( 'publish', 'draft', 'trash' ),
            'meta_query'  => array(
                'relation' => 'OR',
                array(
                    'key'     => '_ldtt_test_data',
                    'compare' => 'EXISTS',
                ),
            ),
        ) );

        // Also get items with "Test" in the title
        $test_items = get_posts( array(
            'post_type'   => $post_type,
            'numberposts' => -1,
            'post_status' => array( 'publish', 'draft', 'trash' ),
            's'           => 'Test',
        ) );

        // Merge and remove duplicates
        $all_items = array_unique( array_merge( $items, $test_items ), SORT_REGULAR );

        if ( empty( $all_items ) ) {
            return array( 'status' => 'info', 'message' => "No test {$item_name}s found to delete.", 'count' => 0 );
        }

        $deleted_count = 0;

        foreach ( $all_items as $item ) {
            if ( wp_delete_post( $item->ID, true ) ) {
                $deleted_count++;
                if ( defined( 'WP_CLI' ) && WP_CLI ) {
                    WP_CLI::line( "Deleted {$item_name}: {$item->post_title} (ID: {$item->ID})" );
                }
            }
        }

        return array(
            'status' => 'success',
            'message' => "{$deleted_count} {$item_name}(s) have been deleted.",
            'count' => $deleted_count,
        );
    }

    private static function delete_test_users() {
        // Get users marked as test users
        $test_users = get_users( array(
            'meta_key'   => '_ldtt_test_user',
            'meta_value' => true,
        ) );

        // Also get users with testuser_ prefix
        $prefix_users = get_users( array(
            'search'         => 'testuser_*',
            'search_columns' => array( 'user_login' ),
        ) );

        // Merge and remove duplicates
        $all_users = array_unique( array_merge( $test_users, $prefix_users ), SORT_REGULAR );

        if ( empty( $all_users ) ) {
            return array( 'status' => 'info', 'message' => "No test users found to delete.", 'count' => 0 );
        }

        $deleted_count = 0;

        foreach ( $all_users as $user ) {
            // Don't delete admin users
            if ( in_array( 'administrator', $user->roles, true ) ) {
                continue;
            }

            if ( wp_delete_user( $user->ID ) ) {
                $deleted_count++;
                if ( defined( 'WP_CLI' ) && WP_CLI ) {
                    WP_CLI::line( "Deleted user: {$user->user_login} (ID: {$user->ID})" );
                }
            }
        }

        return array(
            'status' => 'success',
            'message' => "{$deleted_count} test user(s) have been deleted.",
            'count' => $deleted_count,
        );
    }
}