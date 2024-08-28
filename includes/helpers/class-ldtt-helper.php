<?php

class LDTT_Helper {

    /**
     * Create a WordPress post of a given type.
     *
     * @param string $post_type The type of post to create (e.g., 'sfwd-courses', 'sfwd-lessons').
     * @param string $title The title of the post.
     * @param string $content The content of the post.
     * @param array  $meta Optional. Array of meta keys and values to add to the post.
     * @return int|WP_Error The post ID on success, WP_Error on failure.
     */
    public static function create_post( $post_type, $title, $content = '', $meta = array() ) {
        $post_id = wp_insert_post( array(
            'post_title'   => $title,
            'post_type'    => $post_type,
            'post_status'  => 'publish',
            'post_content' => $content,
        ) );

        if ( ! is_wp_error( $post_id ) && ! empty( $meta ) ) {
            foreach ( $meta as $key => $value ) {
                update_post_meta( $post_id, $key, $value );
            }
        }

        return $post_id;
    }

    /**
     * Check if LearnDash is active.
     *
     * @return bool True if LearnDash is active, false otherwise.
     */
    public static function is_learndash_active() {
        return class_exists( 'LearnDash_Settings_Section' );
    }

    /**
     * Generate a random string.
     *
     * @param int $length The length of the random string to generate.
     * @return string The generated random string.
     */
    public static function generate_random_string( $length = 10 ) {
        return substr( str_shuffle( '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ' ), 0, $length );
    }

    /**
     * Log messages to the WordPress debug log.
     *
     * @param string $message The message to log.
     */
    public static function log( $message ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[LDTT] ' . $message );
        }
    }

    /**
     * Helper function to output a success message via WP-CLI.
     *
     * @param string $message The success message.
     */
    public static function cli_success( $message ) {
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::success( $message );
        } else {
            self::log( 'Success: ' . $message );
        }
    }

    /**
     * Helper function to output an error message via WP-CLI.
     *
     * @param string $message The error message.
     */
    public static function cli_error( $message ) {
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::error( $message );
        } else {
            self::log( 'Error: ' . $message );
        }
    }
}
