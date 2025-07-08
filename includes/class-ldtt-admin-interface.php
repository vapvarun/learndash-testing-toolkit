<?php

/**
 * Admin Interface Class - Complete and Fixed
 * 
 * @package LearnDash_Testing_Toolkit
 * @since 1.2.0
 */
class LDTT_Admin_Interface {
    
    /**
     * Initialize admin interface
     */
    public function init() {
        add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'handle_form_submissions' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'admin_init', array( $this, 'handle_activation_redirect' ) );
    }
    
    /**
     * Register admin menu
     */
    public function register_admin_menu() {
        add_menu_page(
            __( 'LDTT Commands', 'learndash-testing-toolkit' ),
            __( 'LDTT Commands', 'learndash-testing-toolkit' ),
            'manage_options',
            'ldtt-cli-commands',
            array( $this, 'render_commands_page' ),
            'dashicons-hammer',
            20
        );
        
        add_submenu_page(
            'ldtt-cli-commands',
            __( 'Statistics', 'learndash-testing-toolkit' ),
            __( 'Statistics', 'learndash-testing-toolkit' ),
            'manage_options',
            'ldtt-statistics',
            array( $this, 'render_statistics_page' )
        );
        
        add_submenu_page(
            'ldtt-cli-commands',
            __( 'Settings', 'learndash-testing-toolkit' ),
            __( 'Settings', 'learndash-testing-toolkit' ),
            'manage_options',
            'ldtt-settings',
            array( $this, 'render_settings_page' )
        );
        
        add_submenu_page(
            'ldtt-cli-commands',
            __( 'Logs', 'learndash-testing-toolkit' ),
            __( 'Logs', 'learndash-testing-toolkit' ),
            'manage_options',
            'ldtt-logs',
            array( $this, 'render_logs_page' )
        );
    }
    
    /**
     * Handle activation redirect
     */
    public function handle_activation_redirect() {
        if ( get_transient( 'ldtt_activation_redirect' ) ) {
            delete_transient( 'ldtt_activation_redirect' );
            
            if ( ! isset( $_GET['activate-multi'] ) ) {
                wp_safe_redirect( admin_url( 'admin.php?page=ldtt-cli-commands&ldtt_message=activated' ) );
                exit;
            }
        }
    }
    
    /**
     * Handle form submissions
     */
    public function handle_form_submissions() {
        if ( ! isset( $_POST['ldtt_command'] ) || ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        if ( ! wp_verify_nonce( $_POST['ldtt_cli_command_nonce'], 'ldtt_cli_command_action' ) ) {
            wp_die( __( 'Security check failed.', 'learndash-testing-toolkit' ) );
        }
        
        $command = sanitize_text_field( $_POST['ldtt_command'] );
        $result = $this->execute_command( $command );
        
        // Redirect with message
        $redirect_url = add_query_arg( array(
            'page' => 'ldtt-cli-commands',
            'ldtt_message' => $result['success'] ? 'command_success' : 'command_error',
            'ldtt_result' => urlencode( $result['message'] ),
        ), admin_url( 'admin.php' ) );
        
        wp_safe_redirect( $redirect_url );
        exit;
    }
    
    /**
     * Execute command
     * 
     * @param string $command
     * @return array
     */
    private function execute_command( $command ) {
        $core = ldtt_core();
        if ( ! $core ) {
            return array( 'success' => false, 'message' => 'Core not available' );
        }

        $command_factory = $core->get_component( 'command_factory' );
        if ( ! $command_factory ) {
            return array( 'success' => false, 'message' => 'Command factory not available' );
        }

        // Process user mode for enhanced distribution
        if ( $command === 'enhanced-user-distribution' && isset( $_POST['user_mode'] ) ) {
            $user_mode = sanitize_text_field( $_POST['user_mode'] );
            if ( $user_mode === 'use_existing' ) {
                $_POST['use_existing'] = true;
            } else {
                $_POST['create_new'] = true;
            }
        }

        return $command_factory->execute_command( $command, array(), $_POST );
    }
    
    /**
     * Enqueue admin assets
     * 
     * @param string $hook
     */
    public function enqueue_admin_assets( $hook ) {
        // Only load on our admin pages
        if ( strpos( $hook, 'ldtt' ) === false ) {
            return;
        }
        
        wp_enqueue_style(
            'ldtt-admin-style',
            LDTT_PLUGIN_URL . 'includes/assets/css/admin-style.css',
            array(),
            LDTT_VERSION
        );
        
        wp_enqueue_script(
            'ldtt-admin-script',
            LDTT_PLUGIN_URL . 'includes/assets/js/admin-script.js',
            array( 'jquery' ),
            LDTT_VERSION,
            true
        );
        
        wp_localize_script( 'ldtt-admin-script', 'ldtt_ajax', array(
            'url'   => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'ldtt_ajax_nonce' ),
        ) );
    }
    
    /**
     * Render commands page
     */
    public function render_commands_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'LearnDash Testing Toolkit - Commands', 'learndash-testing-toolkit' ); ?></h1>
            
            <?php $this->show_admin_messages(); ?>
            <?php $this->show_learndash_status(); ?>
            
            <div class="ldtt-tabs">
                <h2 class="nav-tab-wrapper">
                    <a href="#courses" class="nav-tab nav-tab-active"><?php esc_html_e( 'Courses', 'learndash-testing-toolkit' ); ?></a>
                    <a href="#lessons" class="nav-tab"><?php esc_html_e( 'Lessons', 'learndash-testing-toolkit' ); ?></a>
                    <a href="#topics" class="nav-tab"><?php esc_html_e( 'Topics', 'learndash-testing-toolkit' ); ?></a>
                    <a href="#quizzes" class="nav-tab"><?php esc_html_e( 'Quizzes', 'learndash-testing-toolkit' ); ?></a>
                    <a href="#users" class="nav-tab"><?php esc_html_e( 'Users', 'learndash-testing-toolkit' ); ?></a>
                    <a href="#groups" class="nav-tab"><?php esc_html_e( 'Groups', 'learndash-testing-toolkit' ); ?></a>
                    <a href="#distribution" class="nav-tab"><?php esc_html_e( 'Distribution', 'learndash-testing-toolkit' ); ?></a>
                    <a href="#cleanup" class="nav-tab"><?php esc_html_e( 'Cleanup', 'learndash-testing-toolkit' ); ?></a>
                </h2>

                <?php $this->render_courses_tab(); ?>
                <?php $this->render_lessons_tab(); ?>
                <?php $this->render_topics_tab(); ?>
                <?php $this->render_quizzes_tab(); ?>
                <?php $this->render_users_tab(); ?>
                <?php $this->render_groups_tab(); ?>
                <?php $this->render_distribution_tab(); ?>
                <?php $this->render_cleanup_tab(); ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Show admin messages
     */
    private function show_admin_messages() {
        if ( isset( $_GET['ldtt_message'] ) ) {
            $message_type = sanitize_text_field( $_GET['ldtt_message'] );
            $result = isset( $_GET['ldtt_result'] ) ? urldecode( $_GET['ldtt_result'] ) : '';
            
            $messages = array(
                'activated' => array( 'type' => 'success', 'text' => __( 'LearnDash Testing Toolkit activated successfully!', 'learndash-testing-toolkit' ) ),
                'command_success' => array( 'type' => 'success', 'text' => $result ?: __( 'Command executed successfully.', 'learndash-testing-toolkit' ) ),
                'command_error' => array( 'type' => 'error', 'text' => $result ?: __( 'Command execution failed.', 'learndash-testing-toolkit' ) ),
            );
            
            if ( isset( $messages[ $message_type ] ) ) {
                $message = $messages[ $message_type ];
                printf(
                    '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                    esc_attr( $message['type'] ),
                    esc_html( $message['text'] )
                );
            }
        }
    }
    
    /**
     * Show LearnDash status
     */
    private function show_learndash_status() {
        $core = ldtt_core();
        if ( ! $core ) {
            return;
        }
        
        $detector = $core->get_component( 'learndash_detector' );
        if ( ! $detector ) {
            return;
        }
        
        $status = $detector->get_detection_status();
        $status_class = $status['is_active'] ? 'success' : 'warning';
        $status_text = $status['is_active'] ? __( 'LearnDash Active', 'learndash-testing-toolkit' ) : __( 'LearnDash Issues', 'learndash-testing-toolkit' );
        
        ?>
        <div class="notice notice-<?php echo esc_attr( $status_class ); ?>">
            <p>
                <strong><?php echo esc_html( $status_text ); ?>:</strong>
                <?php echo esc_html( $status['message'] ); ?>
                <small>(<?php echo esc_html( $status['method'] ); ?>)</small>
            </p>
        </div>
        <?php
    }
    
    /**
     * Render courses tab
     */
    private function render_courses_tab() {
        ?>
        <div id="courses" class="tab-content" style="display: block;">
            <h3><?php esc_html_e( 'Create Test Courses', 'learndash-testing-toolkit' ); ?></h3>
            <form method="post" class="ldtt-form">
                <?php wp_nonce_field( 'ldtt_cli_command_action', 'ldtt_cli_command_nonce' ); ?>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e( 'Number of Courses', 'learndash-testing-toolkit' ); ?></th>
                        <td><input type="number" name="count" value="5" min="1" max="100" /></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Course Prefix', 'learndash-testing-toolkit' ); ?></th>
                        <td><input type="text" name="prefix" value="Test Course" /></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Access Mode', 'learndash-testing-toolkit' ); ?></th>
                        <td>
                            <select name="access_mode">
                                <option value=""><?php esc_html_e( 'Random', 'learndash-testing-toolkit' ); ?></option>
                                <option value="open"><?php esc_html_e( 'Open', 'learndash-testing-toolkit' ); ?></option>
                                <option value="free"><?php esc_html_e( 'Free', 'learndash-testing-toolkit' ); ?></option>
                                <option value="paynow"><?php esc_html_e( 'Buy Now', 'learndash-testing-toolkit' ); ?></option>
                                <option value="subscribe"><?php esc_html_e( 'Subscription', 'learndash-testing-toolkit' ); ?></option>
                                <option value="closed"><?php esc_html_e( 'Closed', 'learndash-testing-toolkit' ); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>
                <button type="submit" name="ldtt_command" value="create-courses" class="button button-primary">
                    <?php esc_html_e( 'Create Courses', 'learndash-testing-toolkit' ); ?>
                </button>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render lessons tab
     */
    private function render_lessons_tab() {
        ?>
        <div id="lessons" class="tab-content">
            <h3><?php esc_html_e( 'Create Test Lessons', 'learndash-testing-toolkit' ); ?></h3>
            <form method="post" class="ldtt-form">
                <?php wp_nonce_field( 'ldtt_cli_command_action', 'ldtt_cli_command_nonce' ); ?>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e( 'Number of Lessons', 'learndash-testing-toolkit' ); ?></th>
                        <td><input type="number" name="count" value="10" min="1" max="500" /></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Course ID (Optional)', 'learndash-testing-toolkit' ); ?></th>
                        <td><input type="number" name="course_id" placeholder="<?php esc_attr_e( 'Leave empty for random assignment', 'learndash-testing-toolkit' ); ?>" /></td>
                    </tr>
                </table>
                <button type="submit" name="ldtt_command" value="create-lessons" class="button button-primary">
                    <?php esc_html_e( 'Create Lessons', 'learndash-testing-toolkit' ); ?>
                </button>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render topics tab
     */
    private function render_topics_tab() {
        ?>
        <div id="topics" class="tab-content">
            <h3><?php esc_html_e( 'Create Test Topics', 'learndash-testing-toolkit' ); ?></h3>
            <form method="post" class="ldtt-form">
                <?php wp_nonce_field( 'ldtt_cli_command_action', 'ldtt_cli_command_nonce' ); ?>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e( 'Number of Topics', 'learndash-testing-toolkit' ); ?></th>
                        <td><input type="number" name="count" value="20" min="1" max="1000" /></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Lesson ID (Optional)', 'learndash-testing-toolkit' ); ?></th>
                        <td><input type="number" name="lesson_id" placeholder="<?php esc_attr_e( 'Leave empty for random assignment', 'learndash-testing-toolkit' ); ?>" /></td>
                    </tr>
                </table>
                <button type="submit" name="ldtt_command" value="create-topics" class="button button-primary">
                    <?php esc_html_e( 'Create Topics', 'learndash-testing-toolkit' ); ?>
                </button>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render quizzes tab
     */
    private function render_quizzes_tab() {
        ?>
        <div id="quizzes" class="tab-content">
            <h3><?php esc_html_e( 'Create Test Quizzes', 'learndash-testing-toolkit' ); ?></h3>
            <form method="post" class="ldtt-form">
                <?php wp_nonce_field( 'ldtt_cli_command_action', 'ldtt_cli_command_nonce' ); ?>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e( 'Number of Quizzes', 'learndash-testing-toolkit' ); ?></th>
                        <td><input type="number" name="count" value="5" min="1" max="100" /></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Questions per Quiz', 'learndash-testing-toolkit' ); ?></th>
                        <td><input type="number" name="questions" value="5" min="1" max="50" /></td>
                    </tr>
                </table>
                <button type="submit" name="ldtt_command" value="create-quizzes" class="button button-primary">
                    <?php esc_html_e( 'Create Quizzes', 'learndash-testing-toolkit' ); ?>
                </button>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render users tab - Complete with all sections
     */
    private function render_users_tab() {
        ?>
        <div id="users" class="tab-content">
            <h3><?php esc_html_e( 'User Management', 'learndash-testing-toolkit' ); ?></h3>
            
            <!-- Create and Enroll Users Section -->
            <div class="ldtt-section">
                <h4><?php esc_html_e( 'Create and Enroll Users', 'learndash-testing-toolkit' ); ?></h4>
                <form method="post" class="ldtt-form">
                    <?php wp_nonce_field( 'ldtt_cli_command_action', 'ldtt_cli_command_nonce' ); ?>
                    <table class="form-table">
                        <tr>
                            <th><?php esc_html_e( 'Number of Users', 'learndash-testing-toolkit' ); ?></th>
                            <td><input type="number" name="count" value="10" min="1" max="100" /></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Course ID', 'learndash-testing-toolkit' ); ?></th>
                            <td><input type="number" name="course_id" placeholder="<?php esc_attr_e( 'Enter course ID', 'learndash-testing-toolkit' ); ?>" required /></td>
                        </tr>
                    </table>
                    <button type="submit" name="ldtt_command" value="enrollment" class="button button-primary">
                        <?php esc_html_e( 'Create & Enroll Users', 'learndash-testing-toolkit' ); ?>
                    </button>
                </form>
            </div>

            <hr style="margin: 30px 0;">

            <!-- Add Progress to Specific User Section -->
            <div class="ldtt-section">
                <h4><?php esc_html_e( 'Add Progress to Specific User', 'learndash-testing-toolkit' ); ?></h4>
                <p class="description"><?php esc_html_e( 'Add realistic course progress to a specific user ID.', 'learndash-testing-toolkit' ); ?></p>
                <form method="post" class="ldtt-form">
                    <?php wp_nonce_field( 'ldtt_cli_command_action', 'ldtt_cli_command_nonce' ); ?>
                    <table class="form-table">
                        <tr>
                            <th><?php esc_html_e( 'User ID', 'learndash-testing-toolkit' ); ?></th>
                            <td>
                                <input type="number" name="user_id" placeholder="<?php esc_attr_e( 'Enter user ID', 'learndash-testing-toolkit' ); ?>" required />
                                <p class="description"><?php esc_html_e( 'The ID of the user to add progress for', 'learndash-testing-toolkit' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Course Selection', 'learndash-testing-toolkit' ); ?></th>
                            <td>
                                <label>
                                    <input type="radio" name="course_selection" value="specific" checked />
                                    <?php esc_html_e( 'Specific Course', 'learndash-testing-toolkit' ); ?>
                                </label><br>
                                <input type="number" name="specific_course_id" placeholder="<?php esc_attr_e( 'Course ID', 'learndash-testing-toolkit' ); ?>" style="margin: 5px 0 10px 25px;" />
                                <br>
                                <label>
                                    <input type="radio" name="course_selection" value="enrolled" />
                                    <?php esc_html_e( 'All Enrolled Courses', 'learndash-testing-toolkit' ); ?>
                                </label>
                                <p class="description"><?php esc_html_e( 'Choose to add progress to a specific course or all courses the user is enrolled in', 'learndash-testing-toolkit' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Progress Range', 'learndash-testing-toolkit' ); ?></th>
                            <td>
                                <label><?php esc_html_e( 'Min:', 'learndash-testing-toolkit' ); ?> 
                                    <input type="number" name="min_progress" value="25" min="0" max="100" style="width: 80px;" />%
                                </label>
                                <label style="margin-left: 15px;"><?php esc_html_e( 'Max:', 'learndash-testing-toolkit' ); ?> 
                                    <input type="number" name="max_progress" value="100" min="0" max="100" style="width: 80px;" />%
                                </label>
                                <p class="description"><?php esc_html_e( 'Random completion percentage will be chosen within this range', 'learndash-testing-toolkit' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Options', 'learndash-testing-toolkit' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="overwrite_existing" value="1" />
                                    <?php esc_html_e( 'Overwrite existing progress', 'learndash-testing-toolkit' ); ?>
                                </label><br>
                                <label>
                                    <input type="checkbox" name="include_quizzes" value="1" checked />
                                    <?php esc_html_e( 'Include quiz completions', 'learndash-testing-toolkit' ); ?>
                                </label><br>
                                <label>
                                    <input type="checkbox" name="realistic_timestamps" value="1" checked />
                                    <?php esc_html_e( 'Use realistic timestamps', 'learndash-testing-toolkit' ); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                    <button type="submit" name="ldtt_command" value="add-user-progress" class="button button-primary">
                        <?php esc_html_e( 'Add Progress to User', 'learndash-testing-toolkit' ); ?>
                    </button>
                </form>
            </div>

            <hr style="margin: 30px 0;">

            <!-- Enroll Specific User in Course Section -->
            <div class="ldtt-section">
                <h4><?php esc_html_e( 'Enroll Specific User in Course', 'learndash-testing-toolkit' ); ?></h4>
                <p class="description"><?php esc_html_e( 'Enroll a specific user in one or more courses.', 'learndash-testing-toolkit' ); ?></p>
                <form method="post" class="ldtt-form">
                    <?php wp_nonce_field( 'ldtt_cli_command_action', 'ldtt_cli_command_nonce' ); ?>
                    <table class="form-table">
                        <tr>
                            <th><?php esc_html_e( 'User ID', 'learndash-testing-toolkit' ); ?></th>
                            <td>
                                <input type="number" name="user_id" placeholder="<?php esc_attr_e( 'Enter user ID', 'learndash-testing-toolkit' ); ?>" required />
                                <p class="description"><?php esc_html_e( 'The ID of the user to enroll', 'learndash-testing-toolkit' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Course IDs', 'learndash-testing-toolkit' ); ?></th>
                            <td>
                                <input type="text" name="course_ids" placeholder="<?php esc_attr_e( 'e.g., 123,456,789', 'learndash-testing-toolkit' ); ?>" required />
                                <p class="description"><?php esc_html_e( 'Comma-separated list of course IDs to enroll the user in', 'learndash-testing-toolkit' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Auto-create Progress', 'learndash-testing-toolkit' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="auto_progress" value="1" />
                                    <?php esc_html_e( 'Automatically create realistic progress after enrollment', 'learndash-testing-toolkit' ); ?>
                                </label>
                                <div id="progress-options" style="margin-top: 10px; display: none;">
                                    <label><?php esc_html_e( 'Progress:', 'learndash-testing-toolkit' ); ?> 
                                        <input type="number" name="auto_min_progress" value="25" min="0" max="100" style="width: 60px;" />% - 
                                        <input type="number" name="auto_max_progress" value="100" min="0" max="100" style="width: 60px;" />%
                                    </label>
                                </div>
                            </td>
                        </tr>
                    </table>
                    <button type="submit" name="ldtt_command" value="enroll-user-courses" class="button button-primary">
                        <?php esc_html_e( 'Enroll User in Courses', 'learndash-testing-toolkit' ); ?>
                    </button>
                </form>
            </div>

            <hr style="margin: 30px 0;">

            <!-- User Information Lookup -->
            <div class="ldtt-section">
                <h4><?php esc_html_e( 'User Information Lookup', 'learndash-testing-toolkit' ); ?></h4>
                <p class="description"><?php esc_html_e( 'Get detailed information about a specific user\'s enrollments and progress.', 'learndash-testing-toolkit' ); ?></p>
                <form method="post" class="ldtt-form">
                    <?php wp_nonce_field( 'ldtt_cli_command_action', 'ldtt_cli_command_nonce' ); ?>
                    <table class="form-table">
                        <tr>
                            <th><?php esc_html_e( 'User ID', 'learndash-testing-toolkit' ); ?></th>
                            <td>
                                <input type="number" name="user_id" placeholder="<?php esc_attr_e( 'Enter user ID', 'learndash-testing-toolkit' ); ?>" required />
                            </td>
                        </tr>
                    </table>
                    <button type="submit" name="ldtt_command" value="user-info" class="button button-secondary">
                        <?php esc_html_e( 'Get User Information', 'learndash-testing-toolkit' ); ?>
                    </button>
                </form>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Toggle progress options when auto-progress is checked
            $('input[name="auto_progress"]').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#progress-options').slideDown();
                } else {
                    $('#progress-options').slideUp();
                }
            });

            // Handle course selection radio buttons
            $('input[name="course_selection"]').on('change', function() {
                if ($(this).val() === 'specific') {
                    $('input[name="specific_course_id"]').prop('disabled', false).focus();
                } else {
                    $('input[name="specific_course_id"]').prop('disabled', true);
                }
            });

            // Validate user ID fields
            $('input[name="user_id"]').on('blur', function() {
                var userId = $(this).val();
                if (userId && userId > 0) {
                    $(this).css('border-color', '#00a32a');
                } else {
                    $(this).css('border-color', '#dc3232');
                }
            });

            // Validate course IDs format
            $('input[name="course_ids"]').on('input', function() {
                var value = $(this).val();
                var isValid = /^[\d,\s]+$/.test(value) || value === '';
                
                if (isValid || value === '') {
                    $(this).css('border-color', '');
                } else {
                    $(this).css('border-color', '#dc3232');
                }
            });

            // Progress range validation
            $('input[name="min_progress"], input[name="max_progress"]').on('input', function() {
                var min = parseInt($('input[name="min_progress"]').val()) || 0;
                var max = parseInt($('input[name="max_progress"]').val()) || 100;
                
                if (min > max) {
                    $(this).css('border-color', '#dc3232');
                } else {
                    $(this).css('border-color', '#00a32a');
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render groups tab
     */
    private function render_groups_tab() {
        ?>
        <div id="groups" class="tab-content">
            <h3><?php esc_html_e( 'Create Test Groups', 'learndash-testing-toolkit' ); ?></h3>
            <form method="post" class="ldtt-form">
                <?php wp_nonce_field( 'ldtt_cli_command_action', 'ldtt_cli_command_nonce' ); ?>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e( 'Number of Groups', 'learndash-testing-toolkit' ); ?></th>
                        <td><input type="number" name="count" value="3" min="1" max="50" /></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Group Prefix', 'learndash-testing-toolkit' ); ?></th>
                        <td><input type="text" name="prefix" value="Test Group" /></td>
                    </tr>
                </table>
                <button type="submit" name="ldtt_command" value="course-groups" class="button button-primary">
                    <?php esc_html_e( 'Create Groups', 'learndash-testing-toolkit' ); ?>
                </button>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render distribution tab - Enhanced with Safe Mode
     */
    private function render_distribution_tab() {
        ?>
        <div id="distribution" class="tab-content">
            <h3><?php esc_html_e( 'Create Users with Distribution & Progress', 'learndash-testing-toolkit' ); ?></h3>
            <p><?php esc_html_e( 'Create a realistic user base with proper distribution and course progress.', 'learndash-testing-toolkit' ); ?></p>
            
            <form method="post" class="ldtt-form">
                <?php wp_nonce_field( 'ldtt_cli_command_action', 'ldtt_cli_command_nonce' ); ?>
                
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e( 'User Source', 'learndash-testing-toolkit' ); ?></th>
                        <td>
                            <label>
                                <input type="radio" name="user_mode" value="create_new" checked />
                                <?php esc_html_e( 'Create New Users', 'learndash-testing-toolkit' ); ?>
                            </label><br>
                            <label>
                                <input type="radio" name="user_mode" value="use_existing" />
                                <?php esc_html_e( 'Use Existing Users (Random Assignment)', 'learndash-testing-toolkit' ); ?>
                            </label>
                            <p class="description"><?php esc_html_e( 'Choose whether to create new users or randomly assign roles to existing users', 'learndash-testing-toolkit' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Total Users', 'learndash-testing-toolkit' ); ?></th>
                        <td>
                            <input type="number" name="total_users" value="100" min="10" max="1000" />
                            <p class="description"><?php esc_html_e( 'Total number of users to create or process from existing users', 'learndash-testing-toolkit' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Group Leaders (%)', 'learndash-testing-toolkit' ); ?></th>
                        <td>
                            <input type="number" name="group_leaders" value="1" min="0.1" max="10" step="0.1" />
                            <p class="description"><?php esc_html_e( 'Percentage of users to assign as group leaders', 'learndash-testing-toolkit' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Group Members (%)', 'learndash-testing-toolkit' ); ?></th>
                        <td>
                            <input type="number" name="group_members" value="2" min="0.1" max="20" step="0.1" />
                            <p class="description"><?php esc_html_e( 'Percentage of users to enroll in groups', 'learndash-testing-toolkit' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Course Enrolled (%)', 'learndash-testing-toolkit' ); ?></th>
                        <td>
                            <input type="number" name="course_enrolled" value="5" min="0.1" max="50" step="0.1" />
                            <p class="description"><?php esc_html_e( 'Percentage of users to enroll in courses', 'learndash-testing-toolkit' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Create Course Progress', 'learndash-testing-toolkit' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="create_progress" value="1" checked />
                                <?php esc_html_e( 'Generate realistic course progress (25-100% completion)', 'learndash-testing-toolkit' ); ?>
                            </label>
                            <p class="description"><?php esc_html_e( 'Create varied progress levels for enrolled users', 'learndash-testing-toolkit' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Safe Group Creation', 'learndash-testing-toolkit' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="safe_group_creation" value="1" checked />
                                <?php esc_html_e( 'Use enhanced safe mode for group leader creation', 'learndash-testing-toolkit' ); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e( 'Recommended: Uses improved method to prevent LearnDash compatibility issues when creating group leaders', 'learndash-testing-toolkit' ); ?>
                                <br><strong><?php esc_html_e( 'Note: This is enabled by default to fix known group leader creation bugs', 'learndash-testing-toolkit' ); ?></strong>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <div class="ldtt-margin-top">
                    <h4><?php esc_html_e( 'Distribution Preview', 'learndash-testing-toolkit' ); ?></h4>
                    <div id="distribution-preview" style="background: #f9f9f9; padding: 15px; border: 1px solid #ddd;">
                        <!-- Preview will be populated by JavaScript -->
                    </div>
                </div>
                
                <p class="ldtt-margin-top">
                    <button type="submit" name="ldtt_command" value="enhanced-user-distribution" class="button button-primary">
                        <?php esc_html_e( 'Create Distributed User Base', 'learndash-testing-toolkit' ); ?>
                    </button>
                </p>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render cleanup tab
     */
    private function render_cleanup_tab() {
        ?>
        <div id="cleanup" class="tab-content">
            <h3><?php esc_html_e( 'Clean Test Data', 'learndash-testing-toolkit' ); ?></h3>
            <div class="notice notice-warning">
                <p><?php esc_html_e( 'Warning: This will permanently delete data. Use with caution!', 'learndash-testing-toolkit' ); ?></p>
            </div>
            <form method="post" class="ldtt-form">
                <?php wp_nonce_field( 'ldtt_cli_command_action', 'ldtt_cli_command_nonce' ); ?>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e( 'Data to Clean', 'learndash-testing-toolkit' ); ?></th>
                        <td>
                            <label><input type="checkbox" name="courses" /> <?php esc_html_e( 'Courses', 'learndash-testing-toolkit' ); ?></label><br>
                            <label><input type="checkbox" name="lessons" /> <?php esc_html_e( 'Lessons', 'learndash-testing-toolkit' ); ?></label><br>
                            <label><input type="checkbox" name="topics" /> <?php esc_html_e( 'Topics', 'learndash-testing-toolkit' ); ?></label><br>
                            <label><input type="checkbox" name="quizzes" /> <?php esc_html_e( 'Quizzes', 'learndash-testing-toolkit' ); ?></label><br>
                            <label><input type="checkbox" name="groups" /> <?php esc_html_e( 'Groups', 'learndash-testing-toolkit' ); ?></label><br>
                            <label><input type="checkbox" name="users" /> <?php esc_html_e( 'Test Users', 'learndash-testing-toolkit' ); ?></label>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Confirmation', 'learndash-testing-toolkit' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="confirm" required />
                                <?php esc_html_e( 'I understand this will permanently delete data', 'learndash-testing-toolkit' ); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                <button type="submit" name="ldtt_command" value="delete-items" class="button button-secondary">
                    <?php esc_html_e( 'Clean Data', 'learndash-testing-toolkit' ); ?>
                </button>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render statistics page
     */
    public function render_statistics_page() {
        $core = ldtt_core();
        $data_manager = $core ? $core->get_component( 'data_manager' ) : null;
        $stats = $data_manager ? $data_manager->get_test_data_statistics() : array();
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'LDTT Statistics', 'learndash-testing-toolkit' ); ?></h1>
            
            <div class="ldtt-stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                
                <div class="ldtt-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px;">
                    <h3><?php esc_html_e( 'Test Posts', 'learndash-testing-toolkit' ); ?></h3>
                    <?php if ( ! empty( $stats['posts'] ) ): ?>
                        <ul>
                            <?php foreach ( $stats['posts'] as $post_type => $count ): ?>
                                <li><?php echo esc_html( ucfirst( str_replace( array( 'sfwd-', '-' ), array( '', ' ' ), $post_type ) ) ); ?>: <strong><?php echo esc_html( $count ); ?></strong></li>
                            <?php endforeach; ?>
                        </ul>
                        <p><strong><?php esc_html_e( 'Total:', 'learndash-testing-toolkit' ); ?> <?php echo esc_html( $stats['total_posts'] ); ?></strong></p>
                    <?php else: ?>
                        <p><?php esc_html_e( 'No test posts found.', 'learndash-testing-toolkit' ); ?></p>
                    <?php endif; ?>
                </div>
                
                <div class="ldtt-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px;">
                    <h3><?php esc_html_e( 'Test Users', 'learndash-testing-toolkit' ); ?></h3>
                    <p><strong><?php esc_html_e( 'Total:', 'learndash-testing-toolkit' ); ?> <?php echo esc_html( $stats['users'] ?? 0 ); ?></strong></p>
                    
                    <?php if ( ! empty( $stats['user_types'] ) ): ?>
                        <h4><?php esc_html_e( 'User Types:', 'learndash-testing-toolkit' ); ?></h4>
                        <ul>
                            <?php foreach ( $stats['user_types'] as $type => $count ): ?>
                                <li><?php echo esc_html( ucfirst( str_replace( '_', ' ', $type ) ) ); ?>: <strong><?php echo esc_html( $count ); ?></strong></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
                
                <?php if ( ! empty( $stats['user_progress'] ) ): ?>
                <div class="ldtt-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px;">
                    <h3><?php esc_html_e( 'User Progress', 'learndash-testing-toolkit' ); ?></h3>
                    <p><?php esc_html_e( 'Users with Progress:', 'learndash-testing-toolkit' ); ?> <strong><?php echo esc_html( $stats['user_progress']['users_with_progress'] ); ?></strong></p>
                    <p><?php esc_html_e( 'Average Completion:', 'learndash-testing-toolkit' ); ?> <strong><?php echo esc_html( $stats['user_progress']['average_completion'] ); ?>%</strong></p>
                </div>
                <?php endif; ?>
                
                <?php if ( ! empty( $stats['group_assignments'] ) ): ?>
                <div class="ldtt-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px;">
                    <h3><?php esc_html_e( 'Group Assignments', 'learndash-testing-toolkit' ); ?></h3>
                    <p><?php esc_html_e( 'Total Groups:', 'learndash-testing-toolkit' ); ?> <strong><?php echo esc_html( $stats['group_assignments']['total_groups'] ); ?></strong></p>
                    <p><?php esc_html_e( 'Groups with Leaders:', 'learndash-testing-toolkit' ); ?> <strong><?php echo esc_html( $stats['group_assignments']['groups_with_leaders'] ); ?></strong></p>
                    <p><?php esc_html_e( 'Leader Assignment Rate:', 'learndash-testing-toolkit' ); ?> <strong><?php echo esc_html( $stats['group_assignments']['leader_assignment_rate'] ); ?>%</strong></p>
                </div>
                <?php endif; ?>
                
            </div>
        </div>
        <?php
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        if ( isset( $_POST['submit'] ) ) {
            $this->save_settings();
        }
        
        $settings = get_option( 'ldtt_settings', array() );
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'LDTT Settings', 'learndash-testing-toolkit' ); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field( 'ldtt_save_settings', 'ldtt_settings_nonce' ); ?>
                
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e( 'Log Level', 'learndash-testing-toolkit' ); ?></th>
                        <td>
                            <select name="ldtt_settings[log_level]">
                                <option value="ERROR" <?php selected( $settings['log_level'] ?? '', 'ERROR' ); ?>><?php esc_html_e( 'Error', 'learndash-testing-toolkit' ); ?></option>
                                <option value="WARNING" <?php selected( $settings['log_level'] ?? '', 'WARNING' ); ?>><?php esc_html_e( 'Warning', 'learndash-testing-toolkit' ); ?></option>
                                <option value="INFO" <?php selected( $settings['log_level'] ?? '', 'INFO' ); ?>><?php esc_html_e( 'Info', 'learndash-testing-toolkit' ); ?></option>
                                <option value="DEBUG" <?php selected( $settings['log_level'] ?? '', 'DEBUG' ); ?>><?php esc_html_e( 'Debug', 'learndash-testing-toolkit' ); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Default Safe Mode', 'learndash-testing-toolkit' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="ldtt_settings[default_safe_mode]" value="1" <?php checked( $settings['default_safe_mode'] ?? true ); ?> />
                                <?php esc_html_e( 'Enable safe mode by default for group leader creation', 'learndash-testing-toolkit' ); ?>
                            </label>
                            <p class="description"><?php esc_html_e( 'Recommended: Prevents LearnDash compatibility issues with group leader assignment', 'learndash-testing-toolkit' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Cleanup on Uninstall', 'learndash-testing-toolkit' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="ldtt_settings[cleanup_on_uninstall]" value="1" <?php checked( $settings['cleanup_on_uninstall'] ?? false ); ?> />
                                <?php esc_html_e( 'Delete all test data when plugin is uninstalled', 'learndash-testing-toolkit' ); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Save settings
     */
    private function save_settings() {
        if ( ! wp_verify_nonce( $_POST['ldtt_settings_nonce'], 'ldtt_save_settings' ) ) {
            wp_die( __( 'Security check failed.', 'learndash-testing-toolkit' ) );
        }
        
        $settings = array(
            'log_level' => sanitize_text_field( $_POST['ldtt_settings']['log_level'] ?? 'INFO' ),
            'default_safe_mode' => isset( $_POST['ldtt_settings']['default_safe_mode'] ),
            'cleanup_on_uninstall' => isset( $_POST['ldtt_settings']['cleanup_on_uninstall'] ),
        );
        
        update_option( 'ldtt_settings', $settings );
        
        echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved successfully.', 'learndash-testing-toolkit' ) . '</p></div>';
    }
    
    /**
     * Render logs page
     */
    public function render_logs_page() {
        if ( isset( $_POST['clear_logs'] ) ) {
            LDTT_Logger::clear_logs();
            echo '<div class="notice notice-success"><p>' . esc_html__( 'Logs cleared successfully.', 'learndash-testing-toolkit' ) . '</p></div>';
        }
        
        $logs = LDTT_Logger::get_recent_logs( 100 );
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'LDTT Logs', 'learndash-testing-toolkit' ); ?></h1>
            
            <form method="post" style="margin-bottom: 20px;">
                <?php wp_nonce_field( 'ldtt_clear_logs', 'ldtt_logs_nonce' ); ?>
                <button type="submit" name="clear_logs" class="button button-secondary">
                    <?php esc_html_e( 'Clear Logs', 'learndash-testing-toolkit' ); ?>
                </button>
            </form>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Time', 'learndash-testing-toolkit' ); ?></th>
                        <th><?php esc_html_e( 'Level', 'learndash-testing-toolkit' ); ?></th>
                        <th><?php esc_html_e( 'Message', 'learndash-testing-toolkit' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $logs ) ): ?>
                        <?php foreach ( $logs as $log ): ?>
                            <tr>
                                <td><?php echo esc_html( date( 'Y-m-d H:i:s', $log['timestamp'] ) ); ?></td>
                                <td>
                                    <span class="ldtt-log-level ldtt-log-<?php echo esc_attr( strtolower( $log['level'] ) ); ?>">
                                        <?php echo esc_html( $log['level'] ); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html( $log['message'] ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3"><?php esc_html_e( 'No logs found.', 'learndash-testing-toolkit' ); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}