<?php

class LDTT_Course_Groups {

    /**
     * Handle the WP-CLI command to create course groups in LearnDash.
     *
     * @param array $args Positional arguments passed from the WP-CLI command.
     * @param array $assoc_args Associative arguments passed from the WP-CLI command.
     */
    public static function handle( $args = array(), $assoc_args = array() ) {
        // Check for admin input if no CLI arguments are provided
        if ( empty( $args ) && empty( $assoc_args ) ) {
            $assoc_args = array(
                'count'   => isset( $_POST['count'] ) ? intval( $_POST['count'] ) : 3,
                'prefix'  => isset( $_POST['prefix'] ) ? sanitize_text_field( $_POST['prefix'] ) : 'Test Group',
                'courses' => isset( $_POST['courses'] ) ? sanitize_text_field( $_POST['courses'] ) : '',
            );
        }

        $group_count = LDTT_Helper::validate_positive_int( $assoc_args['count'] ?? 3, 3, 50 );
        $group_prefix = sanitize_text_field( $assoc_args['prefix'] ?? 'Test Group' );
        $course_ids = ! empty( $assoc_args['courses'] ) ? array_map( 'absint', explode( ',', $assoc_args['courses'] ) ) : array();

        // Create the specified number of course groups
        $group_ids = self::create_course_groups( $group_prefix, $group_count, $course_ids );
        if ( is_wp_error( $group_ids ) ) {
            $message = $group_ids->get_error_message();
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::error( $message );
            }
            return array( 'status' => 'error', 'message' => $message );
        }

        $message = "{$group_count} course groups successfully created with the prefix '{$group_prefix}'.";
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::success( $message );
            WP_CLI::line( 'Group IDs: ' . implode( ', ', $group_ids ) );
        }
        return array( 'status' => 'success', 'message' => $message, 'group_ids' => $group_ids );
    }

    /**
     * Create course groups in LearnDash.
     *
     * @param string $group_prefix The prefix for the group titles.
     * @param int $group_count The number of groups to create.
     * @param array $course_ids The IDs of courses to associate with the groups.
     * @return array|WP_Error An array of group IDs on success, WP_Error on failure.
     */
    private static function create_course_groups( $group_prefix, $group_count, $course_ids = array() ) {
        $group_ids = array();
        $admin_id = LDTT_Helper::get_admin_user_id();

        if ( ! $admin_id ) {
            return new WP_Error( 'no_admin', 'No admin user found to assign as group author.' );
        }

        for ( $i = 1; $i <= $group_count; $i++ ) {
            $group_title = "{$group_prefix} {$i}";
            $group_content = "This is the content for {$group_title}.";

            $group_id = wp_insert_post( array(
                'post_title'   => $group_title,
                'post_type'    => learndash_get_post_type_slug( 'group' ),
                'post_status'  => 'publish',
                'post_content' => $group_content,
                'post_author'  => $admin_id,
                'meta_input'   => array(
                    '_ldtt_test_data' => true,
                ),
            ) );

            if ( is_wp_error( $group_id ) ) {
                return $group_id;
            }

            // Associate courses with the group if course IDs are provided
            if ( ! empty( $course_ids ) ) {
                $valid_course_ids = array();
                foreach ( $course_ids as $course_id ) {
                    if ( is_numeric( $course_id ) && get_post_type( $course_id ) === learndash_get_post_type_slug( 'course' ) ) {
                        $valid_course_ids[] = intval( $course_id );
                    }
                }
                
                if ( ! empty( $valid_course_ids ) ) {
                    // Use LearnDash function to associate courses with group
                    if ( function_exists( 'learndash_set_group_enrolled_courses' ) ) {
                        learndash_set_group_enrolled_courses( $group_id, $valid_course_ids );
                    } else {
                        // Fallback method using meta
                        update_post_meta( $group_id, 'learndash_group_enrolled_' . $group_id, $valid_course_ids );
                    }
                }
            }

            $group_ids[] = $group_id;

            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::line( "Created group: {$group_title} (ID: {$group_id})" );
            }
        }

        return $group_ids;
    }
}