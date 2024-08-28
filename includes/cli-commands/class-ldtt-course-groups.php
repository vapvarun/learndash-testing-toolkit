<?php

class LDTT_Course_Groups {

    /**
     * Handle the WP-CLI command to create course groups in LearnDash.
     *
     * @param array $args Positional arguments passed from the WP-CLI command.
     * @param array $assoc_args Associative arguments passed from the WP-CLI command.
     */
    public static function handle( $args, $assoc_args ) {
        $group_count = isset( $assoc_args['count'] ) ? intval( $assoc_args['count'] ) : 1;
        $group_prefix = isset( $assoc_args['prefix'] ) ? $assoc_args['prefix'] : 'Sample Group';
        $course_ids = isset( $assoc_args['courses'] ) ? explode( ',', $assoc_args['courses'] ) : array();

        // Create the specified number of course groups
        $group_ids = self::create_course_groups( $group_prefix, $group_count, $course_ids );
        if ( is_wp_error( $group_ids ) ) {
            LDTT_Helper::cli_error( $group_ids->get_error_message() );
        } else {
            LDTT_Helper::cli_success( "{$group_count} course groups successfully created with the prefix '{$group_prefix}'." );
        }
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

        for ( $i = 1; $i <= $group_count; $i++ ) {
            $group_title = "{$group_prefix} {$i}";
            $group_content = "This is the content for {$group_title}.";

            $group_id = wp_insert_post( array(
                'post_title'   => $group_title,
                'post_type'    => 'groups',
                'post_status'  => 'publish',
                'post_content' => $group_content,
            ) );

            if ( is_wp_error( $group_id ) ) {
                return $group_id;
            }

            // Associate courses with the group if course IDs are provided
            if ( ! empty( $course_ids ) ) {
                foreach ( $course_ids as $course_id ) {
                    if ( is_numeric( $course_id ) && get_post_type( $course_id ) === 'sfwd-courses' ) {
                        learndash_set_group_enrolled_courses( $group_id, array_map( 'intval', $course_ids ) );
                    }
                }
            }

            $group_ids[] = $group_id;
        }

        return $group_ids;
    }
}
