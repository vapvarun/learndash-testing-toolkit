<?php

/**
 * LearnDash Detection and Compatibility Class
 * 
 * @package LearnDash_Testing_Toolkit
 * @since 1.2.0
 */
class LDTT_LearnDash_Detector {
    
    /**
     * Detection cache
     * 
     * @var array|null
     */
    private $detection_cache = null;
    
    /**
     * Fallback functions loaded flag
     * 
     * @var bool
     */
    private $fallback_loaded = false;
    
    /**
     * Initialize the detector
     */
    public function init() {
        add_action( 'plugins_loaded', array( $this, 'detect_learndash' ), 5 );
        add_action( 'admin_notices', array( $this, 'show_admin_notices' ) );
    }
    
    /**
     * Detect LearnDash availability
     */
    public function detect_learndash() {
        $status = $this->get_detection_status();
        
        if ( ! $status['is_active'] ) {
            LDTT_Logger::warning( 'LearnDash not detected: ' . $status['message'] );
            
            // Check if bypass mode is enabled
            if ( get_option( 'ldtt_bypass_learndash_check', false ) ) {
                $this->init_fallback_functions();
                LDTT_Logger::info( 'LearnDash fallback functions loaded due to bypass mode' );
            }
        } else {
            LDTT_Logger::info( 'LearnDash detected successfully: ' . $status['message'] );
        }
    }
    
    /**
     * Get detailed detection status
     * 
     * @return array
     */
    public function get_detailed_status() {
        if ( null !== $this->detection_cache ) {
            return $this->detection_cache;
        }
        
        $status = array(
            'is_active' => false,
            'message'   => '',
            'method'    => '',
            'details'   => array(),
        );
        
        // Method 1: Check for core LearnDash functions
        if ( function_exists( 'learndash_get_post_type_slug' ) ) {
            $status['is_active'] = true;
            $status['method'] = 'function_detection';
            $status['message'] = 'LearnDash functions available';
            $status['details']['functions'] = $this->get_function_status();
        }
        
        // Method 2: Check for LearnDash classes
        elseif ( class_exists( 'LDLMS_Post_Types' ) || class_exists( 'LearnDash_Settings_Section' ) ) {
            $status['is_active'] = true;
            $status['method'] = 'class_detection';
            $status['message'] = 'LearnDash classes available';
            $status['details']['classes'] = $this->get_class_status();
        }
        
        // Method 3: Check active plugins
        elseif ( $this->is_plugin_active() ) {
            $status['is_active'] = false; // Plugin active but functions not loaded
            $status['method'] = 'plugin_active';
            $status['message'] = 'LearnDash plugin active but functions not loaded (loading order issue)';
            $status['details']['plugins'] = $this->get_plugin_status();
        }
        
        // Method 4: Check for LearnDash database content
        elseif ( $this->has_learndash_content() ) {
            $status['is_active'] = false;
            $status['method'] = 'database_content';
            $status['message'] = 'LearnDash content found in database but plugin not active';
            $status['details']['database'] = $this->get_database_status();
        }
        
        else {
            $status['method'] = 'not_found';
            $status['message'] = 'LearnDash not detected by any method';
            $status['details'] = $this->get_full_debug_info();
        }
        
        // Add bypass status
        $status['bypass_enabled'] = get_option( 'ldtt_bypass_learndash_check', false );
        $status['fallback_loaded'] = $this->fallback_loaded;
        
        $this->detection_cache = $status;
        return $status;
    }
    
    /**
     * Get simple detection status
     * 
     * @return array
     */
    public function get_detection_status() {
        $detailed = $this->get_detailed_status();
        
        return array(
            'is_active' => $detailed['is_active'],
            'message'   => $detailed['message'],
            'method'    => $detailed['method'],
        );
    }
    
    /**
     * Check if LearnDash plugin is active
     * 
     * @return bool
     */
    private function is_plugin_active() {
        $active_plugins = get_option( 'active_plugins', array() );
        
        foreach ( $active_plugins as $plugin ) {
            if ( strpos( $plugin, 'sfwd-lms' ) !== false || strpos( $plugin, 'learndash' ) !== false ) {
                return true;
            }
        }
        
        // Check network active plugins for multisite
        if ( is_multisite() ) {
            $network_plugins = get_site_option( 'active_sitewide_plugins', array() );
            foreach ( array_keys( $network_plugins ) as $plugin ) {
                if ( strpos( $plugin, 'sfwd-lms' ) !== false || strpos( $plugin, 'learndash' ) !== false ) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Check if LearnDash content exists in database
     * 
     * @return bool
     */
    private function has_learndash_content() {
        global $wpdb;
        
        $ld_post_types = array( 'sfwd-courses', 'sfwd-lessons', 'sfwd-topic', 'sfwd-quiz', 'groups' );
        $placeholders = implode( ',', array_fill( 0, count( $ld_post_types ), '%s' ) );
        
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ({$placeholders})",
            $ld_post_types
        );
        
        $count = $wpdb->get_var( $query );
        return $count > 0;
    }
    
    /**
     * Get function status
     * 
     * @return array
     */
    private function get_function_status() {
        $functions = array(
            'learndash_get_post_type_slug',
            'learndash_get_course_id',
            'learndash_update_setting',
            'ld_update_course_access',
            'learndash_process_mark_complete',
        );
        
        $status = array();
        foreach ( $functions as $function ) {
            $status[ $function ] = function_exists( $function );
        }
        
        return $status;
    }
    
    /**
     * Get class status
     * 
     * @return array
     */
    private function get_class_status() {
        $classes = array(
            'LDLMS_Post_Types',
            'LearnDash_Settings_Section',
            'SFWD_LMS',
            'LearnDash_Custom_Label',
        );
        
        $status = array();
        foreach ( $classes as $class ) {
            $status[ $class ] = class_exists( $class );
        }
        
        return $status;
    }
    
    /**
     * Get plugin status
     * 
     * @return array
     */
    private function get_plugin_status() {
        $active_plugins = get_option( 'active_plugins', array() );
        $plugin_files = array(
            'sfwd-lms/sfwd_lms.php',
            'learndash/learndash.php',
        );
        
        $status = array(
            'active_plugins' => array_filter( $active_plugins, function( $plugin ) {
                return strpos( $plugin, 'learndash' ) !== false || strpos( $plugin, 'sfwd' ) !== false;
            } ),
            'plugin_files' => array(),
        );
        
        foreach ( $plugin_files as $file ) {
            $full_path = WP_PLUGIN_DIR . '/' . $file;
            $status['plugin_files'][ $file ] = array(
                'exists' => file_exists( $full_path ),
                'active' => in_array( $file, $active_plugins ),
            );
        }
        
        return $status;
    }
    
    /**
     * Get database status
     * 
     * @return array
     */
    private function get_database_status() {
        global $wpdb;
        
        $post_types = $wpdb->get_results(
            "SELECT post_type, COUNT(*) as count FROM {$wpdb->posts} WHERE post_type LIKE 'sfwd-%' OR post_type = 'groups' GROUP BY post_type"
        );
        
        $status = array( 'post_types' => array() );
        foreach ( $post_types as $pt ) {
            $status['post_types'][ $pt->post_type ] = intval( $pt->count );
        }
        
        return $status;
    }
    
    /**
     * Get full debug information
     * 
     * @return array
     */
    private function get_full_debug_info() {
        return array(
            'functions' => $this->get_function_status(),
            'classes'   => $this->get_class_status(),
            'plugins'   => $this->get_plugin_status(),
            'database'  => $this->get_database_status(),
            'constants' => array(
                'LEARNDASH_LMS_PLUGIN_DIR' => defined( 'LEARNDASH_LMS_PLUGIN_DIR' ),
                'LEARNDASH_VERSION' => defined( 'LEARNDASH_VERSION' ),
            ),
        );
    }
    
    /**
     * Initialize fallback functions
     */
    public function init_fallback_functions() {
        if ( $this->fallback_loaded ) {
            return;
        }
        
        // Load fallback functions
        if ( ! function_exists( 'learndash_get_post_type_slug' ) ) {
            function learndash_get_post_type_slug( $post_type = '' ) {
                $post_type_map = array(
                    'course'   => 'sfwd-courses',
                    'lesson'   => 'sfwd-lessons',
                    'topic'    => 'sfwd-topic',
                    'quiz'     => 'sfwd-quiz',
                    'question' => 'sfwd-question',
                    'group'    => 'groups',
                );
                
                return isset( $post_type_map[ $post_type ] ) ? $post_type_map[ $post_type ] : $post_type;
            }
        }
        
        if ( ! function_exists( 'learndash_get_course_id' ) ) {
            function learndash_get_course_id( $post_id = 0 ) {
                if ( ! $post_id ) {
                    global $post;
                    if ( isset( $post->ID ) ) {
                        $post_id = $post->ID;
                    }
                }
                
                $course_id = get_post_meta( $post_id, 'course_id', true );
                if ( ! $course_id ) {
                    $course_id = get_post_meta( $post_id, 'learndash_course', true );
                }
                
                return absint( $course_id );
            }
        }
        
        if ( ! function_exists( 'learndash_update_setting' ) ) {
            function learndash_update_setting( $post_id, $setting_key, $setting_value ) {
                return update_post_meta( $post_id, 'learndash_' . $setting_key, $setting_value );
            }
        }
        
        if ( ! function_exists( 'ld_update_course_access' ) ) {
            function ld_update_course_access( $user_id, $course_id, $remove = false ) {
                $user_courses = get_user_meta( $user_id, '_sfwd-course_progress', true );
                if ( ! is_array( $user_courses ) ) {
                    $user_courses = array();
                }
                
                if ( $remove ) {
                    unset( $user_courses[ $course_id ] );
                } else {
                    $user_courses[ $course_id ] = array(
                        'completed' => 0,
                        'total'     => 0,
                    );
                }
                
                return update_user_meta( $user_id, '_sfwd-course_progress', $user_courses );
            }
        }
        
        if ( ! function_exists( 'learndash_process_mark_complete' ) ) {
            function learndash_process_mark_complete( $user_id, $post_id, $trigger = false, $course_id = 0 ) {
                $completed_key = '_completed_' . $post_id;
                return update_user_meta( $user_id, $completed_key, time() );
            }
        }
        
        if ( ! function_exists( 'learndash_update_user_activity' ) ) {
            function learndash_update_user_activity( $activity_args ) {
                // Store activity in user meta as fallback
                $user_id = isset( $activity_args['user_id'] ) ? $activity_args['user_id'] : 0;
                if ( ! $user_id ) {
                    return false;
                }
                
                $activity_key = '_ldtt_activity_' . time() . '_' . wp_rand( 1000, 9999 );
                return update_user_meta( $user_id, $activity_key, $activity_args );
            }
        }
        
        // Additional fallback functions
        $this->load_additional_fallbacks();
        
        $this->fallback_loaded = true;
        LDTT_Logger::info( 'LearnDash fallback functions loaded successfully' );
    }
    
    /**
     * Load additional fallback functions
     */
    private function load_additional_fallbacks() {
        if ( ! function_exists( 'learndash_get_course_lessons_list' ) ) {
            function learndash_get_course_lessons_list( $course_id, $user_id = 0 ) {
                $lessons = get_posts( array(
                    'post_type'   => 'sfwd-lessons',
                    'numberposts' => -1,
                    'meta_key'    => 'learndash_course',
                    'meta_value'  => $course_id,
                    'post_status' => 'publish',
                ) );
                
                $formatted_lessons = array();
                foreach ( $lessons as $lesson ) {
                    $formatted_lessons[] = array( 'post' => $lesson );
                }
                
                return $formatted_lessons;
            }
        }
        
        if ( ! function_exists( 'learndash_get_topic_list' ) ) {
            function learndash_get_topic_list( $lesson_id, $course_id = 0 ) {
                return get_posts( array(
                    'post_type'   => 'sfwd-topic',
                    'numberposts' => -1,
                    'meta_key'    => 'learndash_lesson',
                    'meta_value'  => $lesson_id,
                    'post_status' => 'publish',
                ) );
            }
        }
        
        if ( ! function_exists( 'learndash_get_quiz_questions' ) ) {
            function learndash_get_quiz_questions( $quiz_id ) {
                return get_posts( array(
                    'post_type'   => 'sfwd-question',
                    'numberposts' => -1,
                    'meta_key'    => 'learndash_quiz',
                    'meta_value'  => $quiz_id,
                    'post_status' => 'publish',
                ) );
            }
        }
        
        if ( ! function_exists( 'learndash_user_set_course_progress' ) ) {
            function learndash_user_set_course_progress( $user_id, $course_id, $progress_data ) {
                return update_user_meta( $user_id, '_course_progress_' . $course_id, $progress_data );
            }
        }
    }
    
    /**
     * Show admin notices
     */
    public function show_admin_notices() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        $status = $this->get_detailed_status();
        
        if ( ! $status['is_active'] && ! $status['bypass_enabled'] ) {
            $this->show_detection_notice( $status );
        } elseif ( $status['bypass_enabled'] ) {
            $this->show_bypass_notice();
        }
    }
    
    /**
     * Show detection notice
     * 
     * @param array $status
     */
    private function show_detection_notice( $status ) {
        $bypass_url = wp_nonce_url(
            add_query_arg( 'ldtt_action', 'bypass_learndash_check' ),
            'ldtt_action_bypass_learndash_check',
            'nonce'
        );
        
        ?>
        <div class="notice notice-warning">
            <h3><?php esc_html_e( 'LearnDash Testing Toolkit - LearnDash Not Detected', 'learndash-testing-toolkit' ); ?></h3>
            <p><strong><?php echo esc_html( $status['message'] ); ?></strong></p>
            <p><?php esc_html_e( 'This could happen if:', 'learndash-testing-toolkit' ); ?></p>
            <ul style="margin-left: 20px;">
                <li>• <?php esc_html_e( 'LearnDash is not installed or activated', 'learndash-testing-toolkit' ); ?></li>
                <li>• <?php esc_html_e( 'Plugin loading order issue', 'learndash-testing-toolkit' ); ?></li>
                <li>• <?php esc_html_e( 'Theme or plugin conflict', 'learndash-testing-toolkit' ); ?></li>
            </ul>
            <p>
                <a href="<?php echo esc_url( $bypass_url ); ?>" class="button button-primary">
                    <?php esc_html_e( 'Enable Bypass Mode', 'learndash-testing-toolkit' ); ?>
                </a>
                <button type="button" class="button" onclick="ldttTestConnection()">
                    <?php esc_html_e( 'Test Connection', 'learndash-testing-toolkit' ); ?>
                </button>
                <button type="button" class="button" onclick="ldttToggleDebugInfo()">
                    <?php esc_html_e( 'Show Debug Info', 'learndash-testing-toolkit' ); ?>
                </button>
            </p>
            <div id="ldtt-debug-info" style="display: none; background: #f9f9f9; padding: 15px; margin-top: 10px;">
                <h4><?php esc_html_e( 'Debug Information', 'learndash-testing-toolkit' ); ?></h4>
                <pre style="font-size: 11px; overflow: auto; max-height: 300px;"><?php echo esc_html( wp_json_encode( $status['details'], JSON_PRETTY_PRINT ) ); ?></pre>
            </div>
        </div>
        
        <script>
        function ldttToggleDebugInfo() {
            var debugDiv = document.getElementById('ldtt-debug-info');
            debugDiv.style.display = debugDiv.style.display === 'none' ? 'block' : 'none';
        }
        
        function ldttTestConnection() {
            jQuery.post(ajaxurl, {
                action: 'ldtt_handle_action',
                action_type: 'test_learndash_connection',
                nonce: '<?php echo wp_create_nonce( 'ldtt_ajax_nonce' ); ?>'
            }, function(response) {
                alert('Test Result: ' + response.message);
                if (response.details) {
                    console.log('LearnDash Detection Details:', response.details);
                }
            });
        }
        </script>
        <?php
    }
    
    /**
     * Show bypass notice
     */
    private function show_bypass_notice() {
        $disable_url = wp_nonce_url(
            add_query_arg( 'ldtt_action', 'disable_bypass' ),
            'ldtt_action_disable_bypass',
            'nonce'
        );
        
        ?>
        <div class="notice notice-info">
            <p>
                <strong><?php esc_html_e( 'LDTT Bypass Mode Active:', 'learndash-testing-toolkit' ); ?></strong>
                <?php esc_html_e( 'Using fallback LearnDash functions. Some features may be limited.', 'learndash-testing-toolkit' ); ?>
                <a href="<?php echo esc_url( $disable_url ); ?>" class="button button-small" style="margin-left: 10px;">
                    <?php esc_html_e( 'Disable Bypass', 'learndash-testing-toolkit' ); ?>
                </a>
            </p>
        </div>
        <?php
    }
    
    /**
     * Check if LearnDash is available
     * 
     * @return bool
     */
    public function is_learndash_available() {
        $status = $this->get_detection_status();
        return $status['is_active'] || get_option( 'ldtt_bypass_learndash_check', false );
    }
}