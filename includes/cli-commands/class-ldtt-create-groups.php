<?php

/**
 * Create Groups Command - Creates LearnDash groups with realistic data
 */
class LDTT_Create_Groups {

    /**
     * Handle the command to create LearnDash groups
     *
     * @param array $args Positional arguments.
     * @param array $assoc_args Associative arguments.
     */
    public static function handle( $args = array(), $assoc_args = array() ) {
        // Check for admin interface data
        if ( empty( $args ) && empty( $assoc_args ) ) {
            $assoc_args = array(
                'count'  => isset( $_POST['count'] ) ? intval( $_POST['count'] ) : 5,
                'prefix' => isset( $_POST['prefix'] ) ? sanitize_text_field( $_POST['prefix'] ) : 'Test Group',
            );
        }

        $group_count = LDTT_Helper::validate_positive_int( $assoc_args['count'] ?? 5, 1, 50 );
        $group_prefix = sanitize_text_field( $assoc_args['prefix'] ?? 'Test Group' );

        // Get an author ID with fallback to current user
        $admin_user_id = LDTT_Helper::get_author_id();
        if ( ! $admin_user_id ) {
            $admin_user_id = 1;
        }

        // Create the groups
        $group_ids = self::create_groups( $group_count, $group_prefix, $admin_user_id );

        if ( is_wp_error( $group_ids ) ) {
            $message = $group_ids->get_error_message();
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::error( $message );
            }
            return array( 'status' => 'error', 'message' => $message );
        }

        $message = "{$group_count} groups created successfully.";
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::success( $message );
            WP_CLI::line( 'Group IDs: ' . implode( ', ', $group_ids ) );
        }
        
        return array( 
            'status' => 'success', 
            'message' => $message, 
            'group_ids' => $group_ids,
            'total_created' => count( $group_ids ),
        );
    }

    /**
     * Create multiple groups with realistic data
     *
     * @param int $group_count Number of groups to create.
     * @param string $group_prefix Prefix for group titles.
     * @param int $admin_user_id Admin user ID for post author.
     * @return array|WP_Error Array of group IDs on success, WP_Error on failure.
     */
    private static function create_groups( $group_count, $group_prefix, $admin_user_id ) {
        $group_ids = array();

        for ( $i = 1; $i <= $group_count; $i++ ) {
            $base_title = LDTT_Sample_Data::get_random_group_title();
            $group_title = "{$group_prefix} {$i}: {$base_title}";
            $group_content = LDTT_Sample_Data::get_random_group_description();

            // Create the group post
            $group_id = wp_insert_post( array(
                'post_title'   => $group_title,
                'post_type'    => learndash_get_post_type_slug( 'group' ),
                'post_status'  => 'publish',
                'post_content' => $group_content,
                'post_author'  => $admin_user_id,
                'meta_input'   => array(
                    '_ldtt_test_data' => true,
                    '_ldtt_created_time' => current_time( 'timestamp' ),
                ),
            ) );

            if ( is_wp_error( $group_id ) ) {
                if ( defined( 'WP_CLI' ) && WP_CLI ) {
                    WP_CLI::warning( "Failed to create group: {$group_title} - " . $group_id->get_error_message() );
                }
                continue;
            }

            $group_ids[] = $group_id;

            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                WP_CLI::line( "Created group: {$group_title} (ID: {$group_id})" );
            }
        }

        if ( empty( $group_ids ) ) {
            return new WP_Error( 'no_groups_created', 'No groups could be created successfully.' );
        }

        return $group_ids;
    }

    /**
     * Get available groups for other operations
     *
     * @return array Array of group IDs.
     */
    public static function get_available_groups() {
        $groups = get_posts( array(
            'post_type'   => learndash_get_post_type_slug( 'group' ),
            'post_status' => 'publish',
            'numberposts' => -1,
            'fields'      => 'ids',
        ) );

        return $groups;
    }

    /**
     * Get statistics about created groups
     *
     * @return array Group creation statistics.
     */
    public static function get_group_statistics() {
        $groups = get_posts( array(
            'post_type'   => learndash_get_post_type_slug( 'group' ),
            'meta_key'    => '_ldtt_test_data',
            'meta_value'  => true,
            'numberposts' => -1,
            'post_status' => array( 'publish', 'draft' ),
        ) );

        $stats = array(
            'total_groups' => count( $groups ),
            'creation_dates' => array(),
        );

        foreach ( $groups as $group ) {
            // Get creation date
            $created_time = get_post_meta( $group->ID, '_ldtt_created_time', true );
            if ( $created_time ) {
                $date_key = date( 'Y-m-d', $created_time );
                $stats['creation_dates'][ $date_key ] = ( $stats['creation_dates'][ $date_key ] ?? 0 ) + 1;
            }
        }

        return $stats;
    }

    /**
     * Validate group creation parameters
     *
     * @param array $params Parameters to validate.
     * @return bool|WP_Error True if valid, WP_Error if invalid.
     */
    public static function validate_creation_parameters( $params ) {
        $errors = array();

        // Validate count
        $count = intval( $params['count'] ?? 0 );
        if ( $count < 1 || $count > 50 ) {
            $errors[] = 'Group count must be between 1 and 50';
        }

        // Validate prefix
        $prefix = trim( $params['prefix'] ?? '' );
        if ( empty( $prefix ) ) {
            $errors[] = 'Group prefix is required';
        } elseif ( strlen( $prefix ) > 50 ) {
            $errors[] = 'Group prefix must be 50 characters or less';
        }

        if ( ! empty( $errors ) ) {
            return new WP_Error( 'validation_failed', implode( '; ', $errors ) );
        }

        return true;
    }
}