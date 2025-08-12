<?php
/**
 * Admin Interface Class - Rebuilt Version
 * 
 * @package LearnDash_Testing_Toolkit
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class LDTT_Admin_Interface {
    
    /**
     * Initialize admin interface
     */
    public function init() {
        add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'admin_init', array( $this, 'handle_form_submissions' ) );
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
            array( $this, 'render_admin_page' ),
            'dashicons-hammer',
            20
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets( $hook ) {
        // Only load on our admin page
        if ( $hook !== 'toplevel_page_ldtt-cli-commands' ) {
            return;
        }
        
        // Enqueue WordPress admin tabs styles
        wp_enqueue_style( 'wp-admin' );
        
        // Add inline styles for our tabs
        wp_add_inline_style( 'wp-admin', '
            .ldtt-admin-wrapper { margin: 20px 20px 0 2px; }
            .ldtt-admin-wrapper h1 { margin-bottom: 20px; }
            .nav-tab-wrapper { margin-bottom: 0; }
            .ldtt-tab-content { 
                display: none; 
                background: #fff; 
                padding: 20px; 
                border: 1px solid #ddd; 
                border-top: none;
            }
            .ldtt-tab-content.active { display: block; }
            .ldtt-tab-content h2 { margin-top: 0; }
            .form-table th { width: 200px; }
            .ldtt-section { margin-bottom: 30px; }
            .ldtt-section h3 { margin-bottom: 10px; }
        ' );
        
        // Add inline JavaScript for tab functionality
        wp_add_inline_script( 'jquery', '
            jQuery(document).ready(function($) {
                // Tab click handler
                $(".nav-tab").on("click", function(e) {
                    e.preventDefault();
                    
                    // Get target
                    var target = $(this).data("tab");
                    
                    // Update active states
                    $(".nav-tab").removeClass("nav-tab-active");
                    $(this).addClass("nav-tab-active");
                    
                    // Show/hide content
                    $(".ldtt-tab-content").removeClass("active");
                    $("#" + target).addClass("active");
                    
                    // Update URL without reload
                    window.history.pushState({}, "", $(this).attr("href"));
                });
                
                // Handle initial tab from URL
                var hash = window.location.hash.substr(1);
                if (hash) {
                    $(\'.nav-tab[data-tab="\' + hash + \'"]\').click();
                }
            });
        ' );
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        // Get current tab
        $current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'courses';
        ?>
        <div class="wrap ldtt-admin-wrapper">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            
            <?php $this->show_notices(); ?>
            
            <nav class="nav-tab-wrapper">
                <a href="#courses" data-tab="courses" class="nav-tab <?php echo $current_tab === 'courses' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Courses', 'learndash-testing-toolkit' ); ?>
                </a>
                <a href="#lessons" data-tab="lessons" class="nav-tab <?php echo $current_tab === 'lessons' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Lessons', 'learndash-testing-toolkit' ); ?>
                </a>
                <a href="#topics" data-tab="topics" class="nav-tab <?php echo $current_tab === 'topics' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Topics', 'learndash-testing-toolkit' ); ?>
                </a>
                <a href="#quizzes" data-tab="quizzes" class="nav-tab <?php echo $current_tab === 'quizzes' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Quizzes & Questions', 'learndash-testing-toolkit' ); ?>
                </a>
                <a href="#enrollment" data-tab="enrollment" class="nav-tab <?php echo $current_tab === 'enrollment' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Enrollment', 'learndash-testing-toolkit' ); ?>
                </a>
                <a href="#groups" data-tab="groups" class="nav-tab <?php echo $current_tab === 'groups' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Groups', 'learndash-testing-toolkit' ); ?>
                </a>
                <a href="#users" data-tab="users" class="nav-tab <?php echo $current_tab === 'users' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'User Distribution', 'learndash-testing-toolkit' ); ?>
                </a>
                <a href="#progress" data-tab="progress" class="nav-tab <?php echo $current_tab === 'progress' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Progress', 'learndash-testing-toolkit' ); ?>
                </a>
                <a href="#cleanup" data-tab="cleanup" class="nav-tab <?php echo $current_tab === 'cleanup' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Delete Items', 'learndash-testing-toolkit' ); ?>
                </a>
            </nav>
            
            <!-- Courses Tab -->
            <div id="courses" class="ldtt-tab-content <?php echo $current_tab === 'courses' ? 'active' : ''; ?>">
                <h2><?php esc_html_e( 'Create Test Courses', 'learndash-testing-toolkit' ); ?></h2>
                <form method="post" action="">
                    <?php wp_nonce_field( 'ldtt_action', 'ldtt_nonce' ); ?>
                    <input type="hidden" name="ldtt_action" value="create_courses">
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="course_count"><?php esc_html_e( 'Number of Courses', 'learndash-testing-toolkit' ); ?></label>
                            </th>
                            <td>
                                <input type="number" id="course_count" name="count" value="5" min="1" max="100" class="small-text">
                                <p class="description"><?php esc_html_e( 'How many test courses to create.', 'learndash-testing-toolkit' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="course_prefix"><?php esc_html_e( 'Course Prefix', 'learndash-testing-toolkit' ); ?></label>
                            </th>
                            <td>
                                <input type="text" id="course_prefix" name="prefix" value="Test Course" class="regular-text">
                                <p class="description"><?php esc_html_e( 'Prefix for course titles (e.g., "Training Course", "Module").', 'learndash-testing-toolkit' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="course_access_mode"><?php esc_html_e( 'Access Mode', 'learndash-testing-toolkit' ); ?></label>
                            </th>
                            <td>
                                <select id="course_access_mode" name="access_mode">
                                    <option value=""><?php esc_html_e( 'Mixed (Cycle through all)', 'learndash-testing-toolkit' ); ?></option>
                                    <option value="open"><?php esc_html_e( 'Open (No enrollment needed)', 'learndash-testing-toolkit' ); ?></option>
                                    <option value="free"><?php esc_html_e( 'Free (Enrollment required)', 'learndash-testing-toolkit' ); ?></option>
                                    <option value="paynow"><?php esc_html_e( 'Buy Now (One-time payment)', 'learndash-testing-toolkit' ); ?></option>
                                    <option value="subscribe"><?php esc_html_e( 'Subscribe (Recurring payment)', 'learndash-testing-toolkit' ); ?></option>
                                    <option value="closed"><?php esc_html_e( 'Closed (Admin enrollment only)', 'learndash-testing-toolkit' ); ?></option>
                                </select>
                                <p class="description"><?php esc_html_e( 'Course access type. Leave as Mixed to create variety.', 'learndash-testing-toolkit' ); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button( __( 'Create Courses', 'learndash-testing-toolkit' ) ); ?>
                </form>
            </div>
            
            <!-- Lessons Tab -->
            <div id="lessons" class="ldtt-tab-content <?php echo $current_tab === 'lessons' ? 'active' : ''; ?>">
                <h2><?php esc_html_e( 'Create Test Lessons', 'learndash-testing-toolkit' ); ?></h2>
                <form method="post" action="">
                    <?php wp_nonce_field( 'ldtt_action', 'ldtt_nonce' ); ?>
                    <input type="hidden" name="ldtt_action" value="create_lessons">
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="lesson_count"><?php esc_html_e( 'Number of Lessons', 'learndash-testing-toolkit' ); ?></label>
                            </th>
                            <td>
                                <input type="number" id="lesson_count" name="count" value="10" min="1" max="100" class="small-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="lesson_course"><?php esc_html_e( 'Course ID', 'learndash-testing-toolkit' ); ?></label>
                            </th>
                            <td>
                                <input type="number" id="lesson_course" name="course_id" min="1" class="small-text">
                                <p class="description"><?php esc_html_e( 'ID of the course to add lessons to. Leave empty to distribute lessons across all available courses.', 'learndash-testing-toolkit' ); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button( __( 'Create Lessons', 'learndash-testing-toolkit' ) ); ?>
                </form>
            </div>
            
            <!-- Topics Tab -->
            <div id="topics" class="ldtt-tab-content <?php echo $current_tab === 'topics' ? 'active' : ''; ?>">
                <h2><?php esc_html_e( 'Create Test Topics', 'learndash-testing-toolkit' ); ?></h2>
                <form method="post" action="">
                    <?php wp_nonce_field( 'ldtt_action', 'ldtt_nonce' ); ?>
                    <input type="hidden" name="ldtt_action" value="create_topics">
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="topic_count"><?php esc_html_e( 'Number of Topics', 'learndash-testing-toolkit' ); ?></label>
                            </th>
                            <td>
                                <input type="number" id="topic_count" name="count" value="5" min="1" max="50" class="small-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="topic_lesson"><?php esc_html_e( 'Lesson ID (Optional)', 'learndash-testing-toolkit' ); ?></label>
                            </th>
                            <td>
                                <input type="number" id="topic_lesson" name="lesson_id" min="1" class="small-text">
                                <p class="description"><?php esc_html_e( 'Leave empty to spread topics across all available lessons. Enter a specific ID to add all topics to one lesson.', 'learndash-testing-toolkit' ); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button( __( 'Create Topics', 'learndash-testing-toolkit' ) ); ?>
                </form>
            </div>
            
            <!-- Quizzes Tab -->
            <div id="quizzes" class="ldtt-tab-content <?php echo $current_tab === 'quizzes' ? 'active' : ''; ?>">
                <h2><?php esc_html_e( 'Create Test Quizzes & Questions', 'learndash-testing-toolkit' ); ?></h2>
                
                <div class="ldtt-section">
                    <h3><?php esc_html_e( 'Create Quizzes', 'learndash-testing-toolkit' ); ?></h3>
                    <form method="post" action="">
                        <?php wp_nonce_field( 'ldtt_action', 'ldtt_nonce' ); ?>
                        <input type="hidden" name="ldtt_action" value="create_quizzes">
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="quiz_count"><?php esc_html_e( 'Number of Quizzes', 'learndash-testing-toolkit' ); ?></label>
                                </th>
                                <td>
                                    <input type="number" id="quiz_count" name="count" value="5" min="1" max="50" class="small-text">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="questions_per_quiz"><?php esc_html_e( 'Questions per Quiz', 'learndash-testing-toolkit' ); ?></label>
                                </th>
                                <td>
                                    <input type="number" id="questions_per_quiz" name="questions" value="5" min="1" max="20" class="small-text">
                                    <p class="description"><?php esc_html_e( 'Number of questions to add to each quiz.', 'learndash-testing-toolkit' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="quiz_course"><?php esc_html_e( 'Course ID', 'learndash-testing-toolkit' ); ?></label>
                                </th>
                                <td>
                                    <input type="number" id="quiz_course" name="course_id" min="1" class="small-text">
                                    <p class="description"><?php esc_html_e( 'Optional. Leave empty to distribute quizzes across all courses.', 'learndash-testing-toolkit' ); ?></p>
                                </td>
                            </tr>
                        </table>
                        
                        <?php submit_button( __( 'Create Quizzes', 'learndash-testing-toolkit' ) ); ?>
                    </form>
                </div>
                
                <div class="ldtt-section">
                    <h3><?php esc_html_e( 'Create Questions', 'learndash-testing-toolkit' ); ?></h3>
                    <form method="post" action="">
                        <?php wp_nonce_field( 'ldtt_action', 'ldtt_nonce' ); ?>
                        <input type="hidden" name="ldtt_action" value="create_questions">
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="questions_count"><?php esc_html_e( 'Number of Questions', 'learndash-testing-toolkit' ); ?></label>
                                </th>
                                <td>
                                    <input type="number" id="questions_count" name="count" value="5" min="1" max="50" class="small-text">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="questions_quiz"><?php esc_html_e( 'Quiz ID', 'learndash-testing-toolkit' ); ?></label>
                                </th>
                                <td>
                                    <input type="number" id="questions_quiz" name="quiz_id" min="1" class="small-text" required>
                                    <p class="description"><?php esc_html_e( 'ID of the quiz to add questions to.', 'learndash-testing-toolkit' ); ?></p>
                                </td>
                            </tr>
                        </table>
                        
                        <?php submit_button( __( 'Create Questions', 'learndash-testing-toolkit' ) ); ?>
                    </form>
                </div>
            </div>
            
            <!-- Enrollment Tab -->
            <div id="enrollment" class="ldtt-tab-content <?php echo $current_tab === 'enrollment' ? 'active' : ''; ?>">
                <h2><?php esc_html_e( 'Enrollment Management', 'learndash-testing-toolkit' ); ?></h2>
                
                <div class="ldtt-section">
                    <h3><?php esc_html_e( 'Course Enrollment', 'learndash-testing-toolkit' ); ?></h3>
                    <form method="post" action="">
                        <?php wp_nonce_field( 'ldtt_action', 'ldtt_nonce' ); ?>
                        <input type="hidden" name="ldtt_action" value="course_enrollment">
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="enrollment_users"><?php esc_html_e( 'Number of Users', 'learndash-testing-toolkit' ); ?></label>
                                </th>
                                <td>
                                    <input type="number" id="enrollment_users" name="users" value="10" min="1" max="100" class="small-text">
                                    <p class="description"><?php esc_html_e( 'Number of users to enroll in courses.', 'learndash-testing-toolkit' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="enrollment_courses"><?php esc_html_e( 'Number of Courses', 'learndash-testing-toolkit' ); ?></label>
                                </th>
                                <td>
                                    <input type="number" id="enrollment_courses" name="courses" value="3" min="1" max="20" class="small-text">
                                    <p class="description"><?php esc_html_e( 'Number of courses each user should be enrolled in.', 'learndash-testing-toolkit' ); ?></p>
                                </td>
                            </tr>
                        </table>
                        
                        <?php submit_button( __( 'Enroll Users in Courses', 'learndash-testing-toolkit' ) ); ?>
                    </form>
                </div>
                
                <div class="ldtt-section">
                    <h3><?php esc_html_e( 'Group Enrollment', 'learndash-testing-toolkit' ); ?></h3>
                    <form method="post" action="">
                        <?php wp_nonce_field( 'ldtt_action', 'ldtt_nonce' ); ?>
                        <input type="hidden" name="ldtt_action" value="group_enrollment">
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="group_enroll_users"><?php esc_html_e( 'Number of Users', 'learndash-testing-toolkit' ); ?></label>
                                </th>
                                <td>
                                    <input type="number" id="group_enroll_users" name="users" value="10" min="1" max="100" class="small-text">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="group_enroll_id"><?php esc_html_e( 'Group ID', 'learndash-testing-toolkit' ); ?></label>
                                </th>
                                <td>
                                    <input type="number" id="group_enroll_id" name="group_id" min="1" class="small-text">
                                    <p class="description"><?php esc_html_e( 'Leave empty to distribute users across all groups.', 'learndash-testing-toolkit' ); ?></p>
                                </td>
                            </tr>
                        </table>
                        
                        <?php submit_button( __( 'Enroll Users in Groups', 'learndash-testing-toolkit' ) ); ?>
                    </form>
                </div>
            </div>
            
            <!-- Users Tab -->
            <div id="users" class="ldtt-tab-content <?php echo $current_tab === 'users' ? 'active' : ''; ?>">
                <h2><?php esc_html_e( 'Enhanced User Distribution', 'learndash-testing-toolkit' ); ?></h2>
                
                <div class="notice notice-info">
                    <p><?php esc_html_e( 'Create a complete test environment with users, course enrollments, group assignments, and realistic progress data in one operation.', 'learndash-testing-toolkit' ); ?></p>
                </div>
                
                <form method="post" action="">
                    <?php wp_nonce_field( 'ldtt_action', 'ldtt_nonce' ); ?>
                    <input type="hidden" name="ldtt_action" value="enhanced_user_distribution">
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="dist_total_users"><?php esc_html_e( 'Total Users', 'learndash-testing-toolkit' ); ?></label>
                            </th>
                            <td>
                                <input type="number" id="dist_total_users" name="total_users" value="100" min="10" max="1000" class="small-text">
                                <p class="description"><?php esc_html_e( 'Total number of users to create and distribute.', 'learndash-testing-toolkit' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="dist_group_leaders"><?php esc_html_e( 'Group Leaders %', 'learndash-testing-toolkit' ); ?></label>
                            </th>
                            <td>
                                <input type="number" id="dist_group_leaders" name="group_leaders" value="5" min="0" max="100" step="0.1" class="small-text">
                                <p class="description"><?php esc_html_e( 'Percentage of users to assign as group leaders.', 'learndash-testing-toolkit' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="dist_group_members"><?php esc_html_e( 'Group Members %', 'learndash-testing-toolkit' ); ?></label>
                            </th>
                            <td>
                                <input type="number" id="dist_group_members" name="group_members" value="20" min="0" max="100" step="0.1" class="small-text">
                                <p class="description"><?php esc_html_e( 'Percentage of users to assign as group members.', 'learndash-testing-toolkit' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="dist_course_enrolled"><?php esc_html_e( 'Course Enrolled %', 'learndash-testing-toolkit' ); ?></label>
                            </th>
                            <td>
                                <input type="number" id="dist_course_enrolled" name="course_enrolled" value="50" min="0" max="100" step="0.1" class="small-text">
                                <p class="description"><?php esc_html_e( 'Percentage of users to enroll in courses.', 'learndash-testing-toolkit' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label><?php esc_html_e( 'User Mode', 'learndash-testing-toolkit' ); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="radio" name="user_mode" value="create_new" checked>
                                    <?php esc_html_e( 'Create New Users', 'learndash-testing-toolkit' ); ?>
                                </label><br>
                                <label>
                                    <input type="radio" name="user_mode" value="use_existing">
                                    <?php esc_html_e( 'Use Existing Users', 'learndash-testing-toolkit' ); ?>
                                </label>
                                <p class="description"><?php esc_html_e( 'Choose whether to create new test users or use existing users from your site.', 'learndash-testing-toolkit' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label><?php esc_html_e( 'Options', 'learndash-testing-toolkit' ); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" name="create_progress" value="1" checked>
                                    <?php esc_html_e( 'Create realistic course progress', 'learndash-testing-toolkit' ); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button( __( 'Create User Distribution', 'learndash-testing-toolkit' ) ); ?>
                </form>
            </div>
            
            <!-- Groups Tab -->
            <div id="groups" class="ldtt-tab-content <?php echo $current_tab === 'groups' ? 'active' : ''; ?>">
                <h2><?php esc_html_e( 'Group Management', 'learndash-testing-toolkit' ); ?></h2>
                
                <div class="ldtt-section">
                    <h3><?php esc_html_e( 'Create Groups', 'learndash-testing-toolkit' ); ?></h3>
                    <form method="post" action="">
                        <?php wp_nonce_field( 'ldtt_action', 'ldtt_nonce' ); ?>
                        <input type="hidden" name="ldtt_action" value="create_groups">
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="group_count"><?php esc_html_e( 'Number of Groups', 'learndash-testing-toolkit' ); ?></label>
                                </th>
                                <td>
                                    <input type="number" id="group_count" name="count" value="5" min="1" max="50" class="small-text">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="group_courses"><?php esc_html_e( 'Courses per Group', 'learndash-testing-toolkit' ); ?></label>
                                </th>
                                <td>
                                    <input type="number" id="group_courses" name="courses" value="0" min="0" max="20" class="small-text">
                                    <p class="description"><?php esc_html_e( 'Number of courses to assign to each group. Leave 0 for no courses.', 'learndash-testing-toolkit' ); ?></p>
                                </td>
                            </tr>
                        </table>
                        
                        <?php submit_button( __( 'Create Groups', 'learndash-testing-toolkit' ) ); ?>
                    </form>
                </div>
                
                <div class="ldtt-section">
                    <h3><?php esc_html_e( 'Assign Group Leaders', 'learndash-testing-toolkit' ); ?></h3>
                    <form method="post" action="">
                        <?php wp_nonce_field( 'ldtt_action', 'ldtt_nonce' ); ?>
                        <input type="hidden" name="ldtt_action" value="group_leaders">
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="leader_count"><?php esc_html_e( 'Number of Leaders', 'learndash-testing-toolkit' ); ?></label>
                                </th>
                                <td>
                                    <input type="number" id="leader_count" name="count" value="5" min="1" max="50" class="small-text">
                                    <p class="description"><?php esc_html_e( 'Number of users to promote to group leaders.', 'learndash-testing-toolkit' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="leader_groups"><?php esc_html_e( 'Groups per Leader', 'learndash-testing-toolkit' ); ?></label>
                                </th>
                                <td>
                                    <input type="number" id="leader_groups" name="groups" value="2" min="1" max="10" class="small-text">
                                </td>
                            </tr>
                        </table>
                        
                        <?php submit_button( __( 'Assign Group Leaders', 'learndash-testing-toolkit' ) ); ?>
                    </form>
                </div>
            </div>
            
            <!-- Progress Tab -->
            <div id="progress" class="ldtt-tab-content <?php echo $current_tab === 'progress' ? 'active' : ''; ?>">
                <h2><?php esc_html_e( 'Progress Management', 'learndash-testing-toolkit' ); ?></h2>
                
                <div class="ldtt-section">
                    <h3><?php esc_html_e( 'Assign Progress to Enrolled Users', 'learndash-testing-toolkit' ); ?></h3>
                    <form method="post" action="">
                        <?php wp_nonce_field( 'ldtt_action', 'ldtt_nonce' ); ?>
                        <input type="hidden" name="ldtt_action" value="assign_progress">
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label><?php esc_html_e( 'Course Selection', 'learndash-testing-toolkit' ); ?></label>
                                </th>
                                <td>
                                    <label>
                                        <input type="radio" name="course_selection" value="all" checked>
                                        <?php esc_html_e( 'All Courses', 'learndash-testing-toolkit' ); ?>
                                    </label><br>
                                    <label>
                                        <input type="radio" name="course_selection" value="specific">
                                        <?php esc_html_e( 'Specific Course:', 'learndash-testing-toolkit' ); ?>
                                        <input type="number" name="course_id" min="1" class="small-text">
                                    </label>
                                    <p class="description"><?php esc_html_e( 'Apply progress to all courses or a specific course.', 'learndash-testing-toolkit' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="progress_min"><?php esc_html_e( 'Minimum Progress %', 'learndash-testing-toolkit' ); ?></label>
                                </th>
                                <td>
                                    <input type="number" id="progress_min" name="min_progress" value="20" min="0" max="100" class="small-text">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="progress_max"><?php esc_html_e( 'Maximum Progress %', 'learndash-testing-toolkit' ); ?></label>
                                </th>
                                <td>
                                    <input type="number" id="progress_max" name="max_progress" value="95" min="0" max="100" class="small-text">
                                    <p class="description"><?php esc_html_e( 'Progress will be randomly assigned between these percentages.', 'learndash-testing-toolkit' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label><?php esc_html_e( 'Options', 'learndash-testing-toolkit' ); ?></label>
                                </th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="overwrite" value="1">
                                        <?php esc_html_e( 'Overwrite existing progress', 'learndash-testing-toolkit' ); ?>
                                    </label>
                                    <p class="description"><?php esc_html_e( 'Check to replace existing progress data.', 'learndash-testing-toolkit' ); ?></p>
                                </td>
                            </tr>
                        </table>
                        
                        <?php submit_button( __( 'Assign Progress', 'learndash-testing-toolkit' ) ); ?>
                    </form>
                </div>
                
                <div class="ldtt-section">
                    <h3><?php esc_html_e( 'Add User Progress', 'learndash-testing-toolkit' ); ?></h3>
                    <form method="post" action="">
                        <?php wp_nonce_field( 'ldtt_action', 'ldtt_nonce' ); ?>
                        <input type="hidden" name="ldtt_action" value="add_user_progress">
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="progress_user"><?php esc_html_e( 'User ID', 'learndash-testing-toolkit' ); ?></label>
                                </th>
                                <td>
                                    <input type="number" id="progress_user" name="user_id" min="1" class="small-text" required>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="progress_course"><?php esc_html_e( 'Course ID', 'learndash-testing-toolkit' ); ?></label>
                                </th>
                                <td>
                                    <input type="number" id="progress_course" name="course_id" min="1" class="small-text" required>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="progress_percent"><?php esc_html_e( 'Progress Percentage', 'learndash-testing-toolkit' ); ?></label>
                                </th>
                                <td>
                                    <input type="number" id="progress_percent" name="progress" value="50" min="0" max="100" class="small-text">
                                    <p class="description"><?php esc_html_e( 'Percentage of course to mark as complete.', 'learndash-testing-toolkit' ); ?></p>
                                </td>
                            </tr>
                        </table>
                        
                        <?php submit_button( __( 'Add Progress', 'learndash-testing-toolkit' ) ); ?>
                    </form>
                </div>
            </div>
            
            <!-- Cleanup Tab -->
            <div id="cleanup" class="ldtt-tab-content <?php echo $current_tab === 'cleanup' ? 'active' : ''; ?>">
                <h2><?php esc_html_e( 'Cleanup Test Data', 'learndash-testing-toolkit' ); ?></h2>
                
                <div class="notice notice-warning">
                    <p><?php esc_html_e( 'Warning: These actions will permanently delete data. Use with caution!', 'learndash-testing-toolkit' ); ?></p>
                </div>
                
                <form method="post" action="">
                    <?php wp_nonce_field( 'ldtt_action', 'ldtt_nonce' ); ?>
                    <input type="hidden" name="ldtt_action" value="cleanup">
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Select Items to Delete', 'learndash-testing-toolkit' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="delete_courses" value="1">
                                    <?php esc_html_e( 'Delete all test courses', 'learndash-testing-toolkit' ); ?>
                                </label><br>
                                <label>
                                    <input type="checkbox" name="delete_lessons" value="1">
                                    <?php esc_html_e( 'Delete all test lessons', 'learndash-testing-toolkit' ); ?>
                                </label><br>
                                <label>
                                    <input type="checkbox" name="delete_topics" value="1">
                                    <?php esc_html_e( 'Delete all test topics', 'learndash-testing-toolkit' ); ?>
                                </label><br>
                                <label>
                                    <input type="checkbox" name="delete_quizzes" value="1">
                                    <?php esc_html_e( 'Delete all test quizzes', 'learndash-testing-toolkit' ); ?>
                                </label><br>
                                <label>
                                    <input type="checkbox" name="delete_users" value="1">
                                    <?php esc_html_e( 'Delete all test users', 'learndash-testing-toolkit' ); ?>
                                </label><br>
                                <label>
                                    <input type="checkbox" name="delete_groups" value="1">
                                    <?php esc_html_e( 'Delete all test groups', 'learndash-testing-toolkit' ); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="confirm_delete"><?php esc_html_e( 'Confirm Deletion', 'learndash-testing-toolkit' ); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" id="confirm_delete" name="confirm" value="1" required>
                                    <?php esc_html_e( 'I understand this will permanently delete data', 'learndash-testing-toolkit' ); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button( __( 'Delete Selected Items', 'learndash-testing-toolkit' ), 'delete' ); ?>
                </form>
            </div>
        </div>
        <?php
    }
    
    /**
     * Show admin notices
     */
    private function show_notices() {
        if ( isset( $_GET['ldtt_message'] ) ) {
            $message = sanitize_text_field( $_GET['ldtt_message'] );
            $type = isset( $_GET['ldtt_type'] ) ? sanitize_text_field( $_GET['ldtt_type'] ) : 'success';
            ?>
            <div class="notice notice-<?php echo esc_attr( $type ); ?> is-dismissible">
                <p><?php echo esc_html( urldecode( $message ) ); ?></p>
            </div>
            <?php
        }
    }
    
    /**
     * Handle form submissions
     */
    public function handle_form_submissions() {
        if ( ! isset( $_POST['ldtt_action'] ) ) {
            return;
        }
        
        if ( ! wp_verify_nonce( $_POST['ldtt_nonce'], 'ldtt_action' ) ) {
            wp_die( __( 'Security check failed', 'learndash-testing-toolkit' ) );
        }
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Insufficient permissions', 'learndash-testing-toolkit' ) );
        }
        
        $action = sanitize_text_field( $_POST['ldtt_action'] );
        $result = false;
        $message = '';
        
        switch ( $action ) {
            case 'create_courses':
                $count = intval( $_POST['count'] );
                $prefix = sanitize_text_field( $_POST['prefix'] ?? 'Test Course' );
                $access_mode = sanitize_text_field( $_POST['access_mode'] ?? '' );
                $result = $this->create_test_courses( $count, $prefix, $access_mode );
                $message = sprintf( __( 'Created %d test courses', 'learndash-testing-toolkit' ), $count );
                break;
                
            case 'create_lessons':
                $count = intval( $_POST['count'] );
                $course_id = isset( $_POST['course_id'] ) && $_POST['course_id'] ? intval( $_POST['course_id'] ) : null;
                $result = $this->create_test_lessons( $count, $course_id );
                $message = sprintf( __( 'Created %d test lessons', 'learndash-testing-toolkit' ), $count );
                break;
                
            case 'create_topics':
                $count = intval( $_POST['count'] );
                $lesson_id = isset( $_POST['lesson_id'] ) && $_POST['lesson_id'] ? intval( $_POST['lesson_id'] ) : null;
                $result = $this->create_test_topics( $count, $lesson_id );
                $message = sprintf( __( 'Created %d test topics', 'learndash-testing-toolkit' ), $count );
                break;
                
            case 'create_quizzes':
                $count = intval( $_POST['count'] );
                $questions = intval( $_POST['questions'] ?? 5 );
                $course_id = isset( $_POST['course_id'] ) && $_POST['course_id'] ? intval( $_POST['course_id'] ) : null;
                $result = $this->create_test_quizzes( $count, $course_id, $questions );
                $message = sprintf( __( 'Created %d test quizzes with %d questions each', 'learndash-testing-toolkit' ), $count, $questions );
                break;
                
            case 'create_questions':
                $count = intval( $_POST['count'] );
                $quiz_id = intval( $_POST['quiz_id'] );
                $result = $this->create_test_questions( $count, $quiz_id );
                $message = sprintf( __( 'Created %d test questions', 'learndash-testing-toolkit' ), $count );
                break;
                
            case 'create_users':
                $count = intval( $_POST['count'] );
                $role = sanitize_text_field( $_POST['role'] );
                $result = $this->create_test_users( $count, $role );
                $message = sprintf( __( 'Created %d test users', 'learndash-testing-toolkit' ), $count );
                break;
                
            case 'enroll_users':
                $count = intval( $_POST['count'] );
                $course_id = intval( $_POST['course_id'] );
                $result = $this->enroll_users( $count, $course_id );
                $message = sprintf( __( 'Enrolled %d users in course', 'learndash-testing-toolkit' ), $count );
                break;
                
            case 'create_groups':
                $count = intval( $_POST['count'] );
                $courses = intval( $_POST['courses'] );
                $result = $this->create_test_groups( $count, $courses );
                $message = sprintf( __( 'Created %d test groups', 'learndash-testing-toolkit' ), $count );
                break;
                
            case 'course_enrollment':
                $users = intval( $_POST['users'] );
                $courses = intval( $_POST['courses'] );
                $result = $this->handle_course_enrollment( $users, $courses );
                $message = sprintf( __( 'Enrolled %d users in %d courses', 'learndash-testing-toolkit' ), $users, $courses );
                break;
                
            case 'group_enrollment':
                $users = intval( $_POST['users'] );
                $group_id = isset( $_POST['group_id'] ) && $_POST['group_id'] ? intval( $_POST['group_id'] ) : null;
                $result = $this->handle_group_enrollment( $users, $group_id );
                $message = __( 'Users enrolled in groups successfully', 'learndash-testing-toolkit' );
                break;
                
            case 'group_leaders':
                $count = intval( $_POST['count'] );
                $groups = intval( $_POST['groups'] );
                $result = $this->assign_group_leaders( $count, $groups );
                $message = sprintf( __( 'Assigned %d group leaders', 'learndash-testing-toolkit' ), $count );
                break;
                
            case 'enhanced_user_distribution':
                $total_users = intval( $_POST['total_users'] );
                $group_leaders = floatval( $_POST['group_leaders'] );
                $group_members = floatval( $_POST['group_members'] );
                $course_enrolled = floatval( $_POST['course_enrolled'] );
                $create_progress = isset( $_POST['create_progress'] ) ? true : false;
                $user_mode = sanitize_text_field( $_POST['user_mode'] ?? 'create_new' );
                $result = $this->handle_enhanced_user_distribution( $total_users, $group_leaders, $group_members, $course_enrolled, $create_progress, $user_mode );
                $message = sprintf( __( 'Processed %d users with distribution', 'learndash-testing-toolkit' ), $total_users );
                break;
                
            case 'assign_progress':
                $min_progress = intval( $_POST['min_progress'] );
                $max_progress = intval( $_POST['max_progress'] );
                $course_selection = sanitize_text_field( $_POST['course_selection'] ?? 'all' );
                $course_id = ( $course_selection === 'specific' && isset( $_POST['course_id'] ) ) ? intval( $_POST['course_id'] ) : null;
                $overwrite = isset( $_POST['overwrite'] ) ? true : false;
                $result = $this->assign_progress_to_enrolled( $min_progress, $max_progress, $course_id, $overwrite );
                $message = __( 'Progress assigned to enrolled users', 'learndash-testing-toolkit' );
                break;
                
            case 'add_user_progress':
                $user_id = intval( $_POST['user_id'] );
                $course_id = intval( $_POST['course_id'] );
                $progress = intval( $_POST['progress'] );
                $result = $this->add_user_progress( $user_id, $course_id, $progress );
                $message = __( 'User progress updated', 'learndash-testing-toolkit' );
                break;
                
            case 'cleanup':
                $result = $this->cleanup_test_data( $_POST );
                $message = __( 'Cleanup completed', 'learndash-testing-toolkit' );
                break;
        }
        
        $type = $result ? 'success' : 'error';
        if ( ! $result && empty( $message ) ) {
            $message = __( 'Operation failed', 'learndash-testing-toolkit' );
        }
        
        wp_redirect( add_query_arg( array(
            'page' => 'ldtt-cli-commands',
            'ldtt_message' => urlencode( $message ),
            'ldtt_type' => $type,
        ), admin_url( 'admin.php' ) ) );
        exit;
    }
    
    /**
     * Create test courses
     */
    private function create_test_courses( $count, $prefix = 'Test Course', $access_mode = '' ) {
        // Get author ID with fallback to current user
        $author_id = class_exists( 'LDTT_Helper' ) ? LDTT_Helper::get_author_id() : get_current_user_id();
        if ( ! $author_id ) {
            $author_id = 1; // Absolute fallback
        }
        
        // Access modes for cycling
        $access_modes = array( 'open', 'free', 'paynow', 'subscribe', 'closed' );
        $mode_index = 0;
        
        for ( $i = 1; $i <= $count; $i++ ) {
            // Determine access mode
            if ( $access_mode && in_array( $access_mode, $access_modes ) ) {
                $current_mode = $access_mode;
            } else {
                // Cycle through modes
                $current_mode = $access_modes[ $mode_index % count( $access_modes ) ];
                $mode_index++;
            }
            
            $course_data = array(
                'post_title'   => sprintf( '%s %d', $prefix, time() + $i ),
                'post_content' => 'This is test course content.',
                'post_status'  => 'publish',
                'post_type'    => 'sfwd-courses',
                'post_author'  => $author_id,
            );
            
            $course_id = wp_insert_post( $course_data );
            
            if ( $course_id ) {
                // Set LearnDash course settings based on access mode
                $price_type = 'free';
                $price = '0';
                
                if ( $current_mode === 'paynow' ) {
                    $price_type = 'paynow';
                    $price = '49.99';
                } elseif ( $current_mode === 'subscribe' ) {
                    $price_type = 'subscribe';
                    $price = '19.99';
                } elseif ( $current_mode === 'closed' ) {
                    $price_type = 'closed';
                }
                
                update_post_meta( $course_id, '_sfwd-courses', array(
                    'sfwd-courses_course_price_type' => $price_type,
                    'sfwd-courses_course_price' => $price,
                ) );
                
                // Set access mode meta
                update_post_meta( $course_id, 'course_access_mode', $current_mode );
            }
        }
        
        return true;
    }
    
    /**
     * Create test lessons
     */
    private function create_test_lessons( $count, $course_id = null ) {
        // Get author ID with fallback to current user
        $author_id = class_exists( 'LDTT_Helper' ) ? LDTT_Helper::get_author_id() : get_current_user_id();
        if ( ! $author_id ) {
            $author_id = 1; // Absolute fallback
        }
        
        // If no course_id provided, get all available courses
        if ( ! $course_id ) {
            $courses = get_posts( array(
                'post_type' => 'sfwd-courses',
                'numberposts' => -1,
                'post_status' => 'publish',
                'fields' => 'ids',
            ) );
            
            if ( empty( $courses ) ) {
                return false; // No courses available
            }
        }
        
        for ( $i = 1; $i <= $count; $i++ ) {
            $lesson_data = array(
                'post_title'   => sprintf( 'Test Lesson %d', time() + $i ),
                'post_content' => 'This is test lesson content.',
                'post_status'  => 'publish',
                'post_type'    => 'sfwd-lessons',
                'post_author'  => $author_id,
            );
            
            $lesson_id = wp_insert_post( $lesson_data );
            
            if ( $lesson_id ) {
                if ( $course_id ) {
                    // Associate with specific course
                    update_post_meta( $lesson_id, 'course_id', $course_id );
                    // Associate lesson with course using LearnDash meta
                    $course_lessons = get_post_meta( $course_id, 'ld_course_lessons', true );
                    if ( ! is_array( $course_lessons ) ) {
                        $course_lessons = array();
                    }
                    $course_lessons[] = $lesson_id;
                    update_post_meta( $course_id, 'ld_course_lessons', $course_lessons );
                } else {
                    // Distribute across all courses
                    $selected_course = $courses[ $i % count( $courses ) ];
                    update_post_meta( $lesson_id, 'course_id', $selected_course );
                    // Associate lesson with course using LearnDash meta
                    $course_lessons = get_post_meta( $selected_course, 'ld_course_lessons', true );
                    if ( ! is_array( $course_lessons ) ) {
                        $course_lessons = array();
                    }
                    $course_lessons[] = $lesson_id;
                    update_post_meta( $selected_course, 'ld_course_lessons', $course_lessons );
                }
            }
        }
        
        return true;
    }
    
    /**
     * Create test topics
     */
    private function create_test_topics( $count, $lesson_id = null ) {
        // Get author ID with fallback to current user
        $author_id = class_exists( 'LDTT_Helper' ) ? LDTT_Helper::get_author_id() : get_current_user_id();
        if ( ! $author_id ) {
            $author_id = 1; // Absolute fallback
        }
        
        // If no specific lesson_id, get all available lessons
        if ( ! $lesson_id ) {
            $lessons = get_posts( array(
                'post_type' => 'sfwd-lessons',
                'numberposts' => -1,
                'post_status' => 'publish',
                'fields' => 'ids',
            ) );
            
            if ( empty( $lessons ) ) {
                return false; // No lessons available
            }
        } else {
            // Use specific lesson
            $lessons = array( $lesson_id );
        }
        
        // Get topic titles from helper if available
        $titles = array(
            'Introduction and Overview', 'Core Concepts', 'Advanced Techniques',
            'Practical Applications', 'Case Studies', 'Best Practices',
            'Common Mistakes', 'Troubleshooting', 'Tips and Tricks',
            'Summary and Review', 'Hands-on Exercise', 'Real-world Examples'
        );
        
        for ( $i = 1; $i <= $count; $i++ ) {
            // Select lesson for this topic (round-robin distribution)
            if ( $lesson_id ) {
                $selected_lesson = $lesson_id;
            } else {
                // Distribute topics evenly across lessons
                $lesson_index = ( $i - 1 ) % count( $lessons );
                $selected_lesson = $lessons[ $lesson_index ];
            }
            
            $course_id = get_post_meta( $selected_lesson, 'course_id', true );
            
            // Get a title
            $title_index = ( $i - 1 ) % count( $titles );
            $topic_title = $titles[ $title_index ] . ' ' . ( time() + $i );
            
            $topic_data = array(
                'post_title'   => $topic_title,
                'post_content' => 'This is test topic content for: ' . $topic_title,
                'post_status'  => 'publish',
                'post_type'    => 'sfwd-topic',
                'post_author'  => $author_id,
            );
            
            $topic_id = wp_insert_post( $topic_data );
            
            if ( $topic_id ) {
                // Associate with lesson and course
                update_post_meta( $topic_id, 'course_id', $course_id );
                update_post_meta( $topic_id, 'lesson_id', $selected_lesson );
                
                // Use LearnDash functions if available
                if ( function_exists( 'learndash_update_setting' ) ) {
                    learndash_update_setting( $topic_id, 'course', $course_id );
                    learndash_update_setting( $topic_id, 'lesson', $selected_lesson );
                }
            }
        }
        
        return true;
    }
    
    /**
     * Create test quizzes
     */
    private function create_test_quizzes( $count, $course_id = null, $questions_per_quiz = 5 ) {
        // Get author ID with fallback to current user
        $author_id = class_exists( 'LDTT_Helper' ) ? LDTT_Helper::get_author_id() : get_current_user_id();
        if ( ! $author_id ) {
            $author_id = 1; // Absolute fallback
        }
        
        for ( $i = 1; $i <= $count; $i++ ) {
            $quiz_data = array(
                'post_title'   => sprintf( 'Test Quiz %d', time() + $i ),
                'post_content' => 'This is test quiz content.',
                'post_status'  => 'publish',
                'post_type'    => 'sfwd-quiz',
                'post_author'  => $author_id,
            );
            
            $quiz_id = wp_insert_post( $quiz_data );
            
            if ( $quiz_id ) {
                // Basic quiz settings
                update_post_meta( $quiz_id, '_sfwd-quiz', array(
                    'sfwd-quiz_passingpercentage' => '70',
                    'sfwd-quiz_quiz_pro' => $quiz_id,
                ) );
                
                // Associate with course if provided
                if ( $course_id ) {
                    update_post_meta( $quiz_id, 'course_id', $course_id );
                }
                
                // Create questions for this quiz
                if ( $questions_per_quiz > 0 ) {
                    $this->create_test_questions( $questions_per_quiz, $quiz_id );
                }
            }
        }
        
        return true;
    }
    
    /**
     * Create test users
     */
    private function create_test_users( $count, $role ) {
        for ( $i = 1; $i <= $count; $i++ ) {
            $username = 'testuser_' . time() . '_' . $i;
            $email = $username . '@example.com';
            
            $user_id = wp_create_user( $username, 'password123', $email );
            
            if ( ! is_wp_error( $user_id ) ) {
                $user = new WP_User( $user_id );
                $user->set_role( $role );
            }
        }
        
        return true;
    }
    
    /**
     * Enroll users in course
     */
    private function enroll_users( $count, $course_id ) {
        $users = get_users( array(
            'role' => 'subscriber',
            'number' => $count,
            'orderby' => 'ID',
            'order' => 'DESC',
        ) );
        
        foreach ( $users as $user ) {
            // Enroll user in course
            update_user_meta( $user->ID, 'course_' . $course_id . '_access_from', time() );
        }
        
        return true;
    }
    
    /**
     * Create test groups
     */
    private function create_test_groups( $count, $courses_per_group = 0 ) {
        // Get author ID with fallback to current user
        $author_id = class_exists( 'LDTT_Helper' ) ? LDTT_Helper::get_author_id() : get_current_user_id();
        if ( ! $author_id ) {
            $author_id = 1; // Absolute fallback
        }
        
        for ( $i = 1; $i <= $count; $i++ ) {
            $group_data = array(
                'post_title'   => sprintf( 'Test Group %d', time() + $i ),
                'post_content' => 'This is test group content.',
                'post_status'  => 'publish',
                'post_type'    => 'groups',
                'post_author'  => $author_id,
            );
            
            $group_id = wp_insert_post( $group_data );
        }
        
        return true;
    }
    
    /**
     * Cleanup test data
     */
    private function cleanup_test_data( $options ) {
        if ( empty( $options['confirm'] ) ) {
            return false;
        }
        
        global $wpdb;
        
        // Delete courses
        if ( ! empty( $options['delete_courses'] ) ) {
            $courses = get_posts( array(
                'post_type' => 'sfwd-courses',
                'numberposts' => -1,
                'post_status' => 'any',
            ) );
            
            foreach ( $courses as $course ) {
                if ( strpos( $course->post_title, 'Test Course' ) !== false ) {
                    wp_delete_post( $course->ID, true );
                }
            }
        }
        
        // Delete lessons
        if ( ! empty( $options['delete_lessons'] ) ) {
            $lessons = get_posts( array(
                'post_type' => 'sfwd-lessons',
                'numberposts' => -1,
                'post_status' => 'any',
            ) );
            
            foreach ( $lessons as $lesson ) {
                if ( strpos( $lesson->post_title, 'Test Lesson' ) !== false ) {
                    wp_delete_post( $lesson->ID, true );
                }
            }
        }
        
        // Delete topics
        if ( ! empty( $options['delete_topics'] ) ) {
            $topics = get_posts( array(
                'post_type' => 'sfwd-topic',
                'numberposts' => -1,
                'post_status' => 'any',
            ) );
            
            foreach ( $topics as $topic ) {
                if ( strpos( $topic->post_title, 'Test Topic' ) !== false ) {
                    wp_delete_post( $topic->ID, true );
                }
            }
        }
        
        // Delete quizzes
        if ( ! empty( $options['delete_quizzes'] ) ) {
            $quizzes = get_posts( array(
                'post_type' => 'sfwd-quiz',
                'numberposts' => -1,
                'post_status' => 'any',
            ) );
            
            foreach ( $quizzes as $quiz ) {
                if ( strpos( $quiz->post_title, 'Test Quiz' ) !== false ) {
                    wp_delete_post( $quiz->ID, true );
                }
            }
        }
        
        // Delete users
        if ( ! empty( $options['delete_users'] ) ) {
            $users = get_users( array(
                'search' => 'testuser_*',
                'search_columns' => array( 'user_login' ),
            ) );
            
            foreach ( $users as $user ) {
                if ( strpos( $user->user_login, 'testuser_' ) === 0 ) {
                    wp_delete_user( $user->ID );
                }
            }
        }
        
        // Delete groups
        if ( ! empty( $options['delete_groups'] ) ) {
            $groups = get_posts( array(
                'post_type' => 'groups',
                'numberposts' => -1,
                'post_status' => 'any',
            ) );
            
            foreach ( $groups as $group ) {
                if ( strpos( $group->post_title, 'Test Group' ) !== false ) {
                    wp_delete_post( $group->ID, true );
                }
            }
        }
        
        return true;
    }
    
    /**
     * Create test questions
     */
    private function create_test_questions( $count, $quiz_id ) {
        // Basic implementation - questions would need ProQuiz integration
        for ( $i = 1; $i <= $count; $i++ ) {
            // This is a simplified version - actual implementation would need ProQuiz
            update_post_meta( $quiz_id, '_question_' . $i, array(
                'question' => sprintf( 'Test Question %d', $i ),
                'answers' => array( 'Answer A', 'Answer B', 'Answer C', 'Answer D' ),
                'correct' => 0,
            ) );
        }
        return true;
    }
    
    /**
     * Handle course enrollment
     */
    private function handle_course_enrollment( $user_count, $course_count ) {
        $users = get_users( array(
            'role__not_in' => array( 'administrator' ),
            'number' => $user_count,
            'orderby' => 'ID',
            'order' => 'DESC',
        ) );
        
        $courses = get_posts( array(
            'post_type' => 'sfwd-courses',
            'numberposts' => -1,
            'post_status' => 'publish',
            'fields' => 'ids',
        ) );
        
        if ( empty( $users ) || empty( $courses ) ) {
            return false;
        }
        
        foreach ( $users as $user ) {
            // Randomly select courses for each user
            $selected_courses = array_rand( array_flip( $courses ), min( $course_count, count( $courses ) ) );
            if ( ! is_array( $selected_courses ) ) {
                $selected_courses = array( $selected_courses );
            }
            
            foreach ( $selected_courses as $course_id ) {
                // Enroll user in course
                update_user_meta( $user->ID, 'course_' . $course_id . '_access_from', time() );
            }
        }
        
        return true;
    }
    
    /**
     * Handle group enrollment
     */
    private function handle_group_enrollment( $user_count, $group_id = null ) {
        $users = get_users( array(
            'role__not_in' => array( 'administrator' ),
            'number' => $user_count,
            'orderby' => 'ID',
            'order' => 'DESC',
        ) );
        
        if ( $group_id ) {
            // Enroll in specific group
            foreach ( $users as $user ) {
                // Add user to group
                $group_users = get_post_meta( $group_id, 'learndash_group_users', true );
                if ( ! is_array( $group_users ) ) {
                    $group_users = array();
                }
                $group_users[] = $user->ID;
                update_post_meta( $group_id, 'learndash_group_users', array_unique( $group_users ) );
            }
        } else {
            // Distribute across all groups
            $groups = get_posts( array(
                'post_type' => 'groups',
                'numberposts' => -1,
                'post_status' => 'publish',
                'fields' => 'ids',
            ) );
            
            if ( empty( $groups ) ) {
                return false;
            }
            
            foreach ( $users as $index => $user ) {
                $selected_group = $groups[ $index % count( $groups ) ];
                // Add user to group
                $group_users = get_post_meta( $selected_group, 'learndash_group_users', true );
                if ( ! is_array( $group_users ) ) {
                    $group_users = array();
                }
                $group_users[] = $user->ID;
                update_post_meta( $selected_group, 'learndash_group_users', array_unique( $group_users ) );
            }
        }
        
        return true;
    }
    
    /**
     * Assign group leaders
     */
    private function assign_group_leaders( $count, $groups_per_leader ) {
        $users = get_users( array(
            'role' => 'subscriber',
            'number' => $count,
            'orderby' => 'ID',
            'order' => 'DESC',
        ) );
        
        $groups = get_posts( array(
            'post_type' => 'groups',
            'numberposts' => -1,
            'post_status' => 'publish',
            'fields' => 'ids',
        ) );
        
        if ( empty( $users ) || empty( $groups ) ) {
            return false;
        }
        
        foreach ( $users as $user ) {
            // Promote to group leader
            $user_obj = new WP_User( $user->ID );
            $user_obj->set_role( 'group_leader' );
            
            // Assign to groups
            $selected_groups = array_rand( array_flip( $groups ), min( $groups_per_leader, count( $groups ) ) );
            if ( ! is_array( $selected_groups ) ) {
                $selected_groups = array( $selected_groups );
            }
            
            foreach ( $selected_groups as $group_id ) {
                // Assign group leader
                $group_leaders = get_post_meta( $group_id, 'learndash_group_leaders', true );
                if ( ! is_array( $group_leaders ) ) {
                    $group_leaders = array();
                }
                $group_leaders[] = $user->ID;
                update_post_meta( $group_id, 'learndash_group_leaders', array_unique( $group_leaders ) );
            }
        }
        
        return true;
    }
    
    /**
     * Handle enhanced user distribution
     */
    private function handle_enhanced_user_distribution( $total_users, $group_leaders_pct, $group_members_pct, $course_enrolled_pct, $create_progress, $user_mode = 'create_new' ) {
        // Calculate user counts
        $leader_count = max( 1, round( $total_users * ( $group_leaders_pct / 100 ) ) );
        $member_count = max( 1, round( $total_users * ( $group_members_pct / 100 ) ) );
        $enrolled_count = max( 1, round( $total_users * ( $course_enrolled_pct / 100 ) ) );
        
        // Create users
        $created_users = array();
        for ( $i = 1; $i <= $total_users; $i++ ) {
            $username = 'testuser_' . time() . '_' . $i;
            $email = $username . '@example.com';
            $user_id = wp_create_user( $username, 'password123', $email );
            
            if ( ! is_wp_error( $user_id ) ) {
                $created_users[] = $user_id;
            }
        }
        
        if ( empty( $created_users ) ) {
            return false;
        }
        
        // Assign roles and enrollments
        $groups = get_posts( array(
            'post_type' => 'groups',
            'numberposts' => -1,
            'fields' => 'ids',
        ) );
        
        $courses = get_posts( array(
            'post_type' => 'sfwd-courses',
            'numberposts' => -1,
            'fields' => 'ids',
        ) );
        
        // Assign group leaders
        for ( $i = 0; $i < $leader_count && $i < count( $created_users ); $i++ ) {
            $user = new WP_User( $created_users[$i] );
            $user->set_role( 'group_leader' );
            
            if ( ! empty( $groups ) ) {
                $group_id = $groups[ $i % count( $groups ) ];
                // Assign group leader
                $group_leaders = get_post_meta( $group_id, 'learndash_group_leaders', true );
                if ( ! is_array( $group_leaders ) ) {
                    $group_leaders = array();
                }
                $group_leaders[] = $created_users[$i];
                update_post_meta( $group_id, 'learndash_group_leaders', array_unique( $group_leaders ) );
            }
        }
        
        // Assign group members
        for ( $i = $leader_count; $i < $leader_count + $member_count && $i < count( $created_users ); $i++ ) {
            if ( ! empty( $groups ) ) {
                $group_id = $groups[ $i % count( $groups ) ];
                // Add user to group
                $group_users = get_post_meta( $group_id, 'learndash_group_users', true );
                if ( ! is_array( $group_users ) ) {
                    $group_users = array();
                }
                $group_users[] = $created_users[$i];
                update_post_meta( $group_id, 'learndash_group_users', array_unique( $group_users ) );
            }
        }
        
        // Enroll in courses
        for ( $i = 0; $i < $enrolled_count && $i < count( $created_users ); $i++ ) {
            if ( ! empty( $courses ) ) {
                $num_courses = rand( 1, min( 3, count( $courses ) ) );
                $selected_courses = array_rand( array_flip( $courses ), $num_courses );
                if ( ! is_array( $selected_courses ) ) {
                    $selected_courses = array( $selected_courses );
                }
                
                foreach ( $selected_courses as $course_id ) {
                    // Enroll user in course
                    update_user_meta( $created_users[$i], 'course_' . $course_id . '_access_from', time() );
                    
                    // Add progress if requested
                    if ( $create_progress ) {
                        $this->add_user_progress( $created_users[$i], $course_id, rand( 20, 95 ) );
                    }
                }
            }
        }
        
        return true;
    }
    
    /**
     * Assign progress to enrolled users
     */
    private function assign_progress_to_enrolled( $min_progress, $max_progress, $course_id = null, $overwrite = false ) {
        global $wpdb;
        
        if ( $course_id ) {
            // Specific course
            $enrollments = $wpdb->get_results( $wpdb->prepare( "
                SELECT user_id, %d as course_id 
                FROM {$wpdb->usermeta} 
                WHERE meta_key = %s 
                AND user_id NOT IN (SELECT ID FROM {$wpdb->users} WHERE user_login = 'admin')
            ", $course_id, 'course_' . $course_id . '_access_from' ) );
        } else {
            // All courses
            $enrollments = $wpdb->get_results( "
                SELECT user_id, SUBSTRING_INDEX(SUBSTRING_INDEX(meta_key, '_', 2), '_', -1) as course_id 
                FROM {$wpdb->usermeta} 
                WHERE meta_key LIKE 'course_%_access_from' 
                AND user_id NOT IN (SELECT ID FROM {$wpdb->users} WHERE user_login = 'admin')
            " );
        }
        
        foreach ( $enrollments as $enrollment ) {
            // Check if user already has progress (if not overwriting)
            if ( ! $overwrite ) {
                $existing_progress = get_user_meta( $enrollment->user_id, 'course_' . $enrollment->course_id . '_progress', true );
                if ( $existing_progress ) {
                    continue; // Skip if already has progress
                }
            }
            
            $progress = rand( $min_progress, $max_progress );
            $this->add_user_progress( $enrollment->user_id, $enrollment->course_id, $progress );
        }
        
        return true;
    }
    
    /**
     * Add user progress
     */
    private function add_user_progress( $user_id, $course_id, $progress_percentage ) {
        // Get course lessons
        $lessons = get_post_meta( $course_id, 'ld_course_lessons', true );
        if ( ! is_array( $lessons ) ) {
            $lessons = array();
        }
        
        // Convert to array of objects if needed
        $lesson_objects = array();
        foreach ( $lessons as $lesson_id ) {
            $lesson_objects[] = array( 'post' => get_post( $lesson_id ) );
        }
        $lessons = $lesson_objects;
        
        if ( empty( $lessons ) ) {
            return false;
        }
        
        $total_steps = count( $lessons );
        $steps_to_complete = round( $total_steps * ( $progress_percentage / 100 ) );
        
        // Mark lessons as complete
        for ( $i = 0; $i < $steps_to_complete && $i < count( $lessons ); $i++ ) {
            if ( isset( $lessons[$i]['post'] ) && $lessons[$i]['post'] ) {
                $lesson_id = $lessons[$i]['post']->ID;
                // Mark lesson as complete for user
                update_user_meta( $user_id, 'learndash_course_' . $course_id . '_lesson_' . $lesson_id, time() );
            }
        }
        
        return true;
    }
}