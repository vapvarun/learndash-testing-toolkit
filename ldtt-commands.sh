#!/bin/bash

# ============================================================================
# LEARNDASH TESTING TOOLKIT - COMPLETE COMMANDS REFERENCE
# Version: 1.2.0 (Production Ready)
# ============================================================================

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

echo -e "${CYAN}=================================================="
echo -e "LEARNDASH TESTING TOOLKIT - COMMANDS REFERENCE"
echo -e "Version: 1.2.0 (Production Ready)"
echo -e "==================================================${NC}"

# ============================================================================
# 🎯 BASIC CONTENT CREATION COMMANDS
# ============================================================================

echo -e "\n${GREEN}🎯 BASIC CONTENT CREATION COMMANDS${NC}"
echo -e "${BLUE}───────────────────────────────────────${NC}"

echo -e "\n${YELLOW}📚 CREATE COURSES${NC}"
echo "# Basic course creation"
echo "wp ldtt create-courses --count=10 --prefix='Training Course'"
echo ""
echo "# Create courses with specific access mode"
echo "wp ldtt create-courses --count=5 --access_mode=free --prefix='Free Course'"
echo "wp ldtt create-courses --count=3 --access_mode=paynow --prefix='Premium Course'"
echo "wp ldtt create-courses --count=4 --access_mode=subscribe --prefix='Subscription Course'"
echo ""
echo "# Create many courses (cycles through all access modes)"
echo "wp ldtt create-courses --count=25 --prefix='Mixed Course'"

echo -e "\n${YELLOW}📖 CREATE LESSONS${NC}"
echo "# Basic lesson creation (assigns to random courses)"
echo "wp ldtt create-lessons --count=50"
echo ""
echo "# Create lessons for specific course"
echo "wp ldtt create-lessons --count=20 --course_id=123"
echo ""
echo "# Create many lessons with specific author"
echo "wp ldtt create-lessons --count=100 --author_id=1"

echo -e "\n${YELLOW}📝 CREATE TOPICS${NC}"
echo "# Basic topic creation (assigns to random lessons)"
echo "wp ldtt create-topics --count=100"
echo ""
echo "# Create topics for specific lesson"
echo "wp ldtt create-topics --count=15 --lesson_id=456"
echo ""
echo "# Create many topics"
echo "wp ldtt create-topics --count=500 --author_id=1"

echo -e "\n${YELLOW}🧩 CREATE QUIZZES${NC}"
echo "# Create quizzes with questions"
echo "wp ldtt create-quizzes --count=10 --questions=5"
echo ""
echo "# Create advanced quizzes"
echo "wp ldtt create-quizzes --count=5 --questions=10"

echo -e "\n${YELLOW}❓ CREATE QUESTIONS${NC}"
echo "# Create questions for existing quiz"
echo "wp ldtt create-questions --quiz_id=789 --count=10"

# ============================================================================
# 🎯 ENHANCED USER DISTRIBUTION (NEW!)
# ============================================================================

echo -e "\n${GREEN}🚀 ENHANCED USER DISTRIBUTION (DUAL MODE)${NC}"
echo -e "${BLUE}────────────────────────────────────────────────${NC}"

echo -e "\n${YELLOW}🆕 CREATE NEW USERS MODE (Default)${NC}"
echo "# Basic distribution with new users"
echo "wp ldtt enhanced-user-distribution --total_users=100 --group_leaders=1 --group_members=2 --course_enrolled=5 --create_progress"
echo ""
echo "# Large enterprise setup"
echo "wp ldtt enhanced-user-distribution --total_users=500 --group_leaders=1.5 --group_members=3 --course_enrolled=8 --create_progress"
echo ""
echo "# Explicitly create new users"
echo "wp ldtt enhanced-user-distribution --total_users=200 --group_leaders=1 --group_members=2 --course_enrolled=5 --create_progress --create_new"

echo -e "\n${YELLOW}🔄 USE EXISTING USERS MODE (New!)${NC}"
echo "# Randomly assign roles to existing users"
echo "wp ldtt enhanced-user-distribution --total_users=50 --group_leaders=2 --group_members=4 --course_enrolled=10 --create_progress --use_existing"
echo ""
echo "# Large corporate training with existing employees"
echo "wp ldtt enhanced-user-distribution --total_users=300 --group_leaders=1 --group_members=3 --course_enrolled=12 --create_progress --use_existing"
echo ""
echo "# Use existing users without creating progress"
echo "wp ldtt enhanced-user-distribution --total_users=75 --group_leaders=3 --group_members=5 --course_enrolled=15 --use_existing"

echo -e "\n${YELLOW}📊 CUSTOM DISTRIBUTION EXAMPLES${NC}"
echo "# High engagement scenario"
echo "wp ldtt enhanced-user-distribution --total_users=200 --group_leaders=2 --group_members=8 --course_enrolled=60 --create_progress --use_existing"
echo ""
echo "# Educational institution"
echo "wp ldtt enhanced-user-distribution --total_users=800 --group_leaders=1 --group_members=3 --course_enrolled=25 --create_progress --create_new"
echo ""
echo "# Membership site"
echo "wp ldtt enhanced-user-distribution --total_users=150 --group_leaders=0.5 --group_members=1 --course_enrolled=40 --create_progress --create_new"

# ============================================================================
# 📈 PROGRESS ASSIGNMENT COMMANDS
# ============================================================================

echo -e "\n${GREEN}📈 PROGRESS ASSIGNMENT COMMANDS${NC}"
echo -e "${BLUE}──────────────────────────────────────${NC}"

echo -e "\n${YELLOW}📋 ASSIGN PROGRESS TO EXISTING ENROLLED USERS${NC}"
echo "# Add progress to ALL enrolled users in ALL courses"
echo "wp ldtt assign-progress --all_courses"
echo ""
echo "# Add progress to enrolled users in specific course"
echo "wp ldtt assign-progress --course_id=123"
echo ""
echo "# Add progress with custom completion range"
echo "wp ldtt assign-progress --all_courses --min_progress=50 --max_progress=100"
echo ""
echo "# Add progress to specific course with high completion"
echo "wp ldtt assign-progress --course_id=456 --min_progress=75 --max_progress=90"
echo ""
echo "# Overwrite existing progress"
echo "wp ldtt assign-progress --course_id=123 --overwrite"

# ============================================================================
# 👥 USER AND GROUP MANAGEMENT
# ============================================================================

echo -e "\n${GREEN}👥 USER AND GROUP MANAGEMENT${NC}"
echo -e "${BLUE}─────────────────────────────────────${NC}"

echo -e "\n${YELLOW}👤 USER ENROLLMENT${NC}"
echo "# Create and enroll users in courses"
echo "wp ldtt enrollment --course_id=123 --count=25"
echo ""
echo "# Enroll many users in course"
echo "wp ldtt enrollment --course_id=456 --count=100"

echo -e "\n${YELLOW}👥 CREATE GROUPS${NC}"
echo "# Basic group creation"
echo "wp ldtt course-groups --count=5 --prefix='Training Group'"
echo ""
echo "# Create groups with course assignment"
echo "wp ldtt course-groups --count=3 --prefix='Department Group' --courses='123,456,789'"

echo -e "\n${YELLOW}👨‍💼 CREATE GROUP LEADERS${NC}"
echo "# Create leaders for existing group"
echo "wp ldtt group-leaders --group='Training Group 1' --leader='John Doe' --count=1"
echo ""
echo "# Create multiple leaders"
echo "wp ldtt group-leaders --group='Department Group' --leader='Team Leader' --count=3"

echo -e "\n${YELLOW}👨‍👩‍👧‍👦 GROUP ENROLLMENT${NC}"
echo "# Create and enroll users in groups"
echo "wp ldtt group-enrollment --group='Training Group 1' --users=20"
echo ""
echo "# Large group enrollment"
echo "wp ldtt group-enrollment --group='Main Group' --users=50"

# ============================================================================
# 🧹 CLEANUP COMMANDS
# ============================================================================

echo -e "\n${GREEN}🧹 CLEANUP COMMANDS${NC}"
echo -e "${BLUE}──────────────────────────${NC}"

echo -e "\n${YELLOW}🗑️ DELETE SPECIFIC CONTENT TYPES${NC}"
echo "# Delete only courses"
echo "wp ldtt delete-items --courses --confirm"
echo ""
echo "# Delete only lessons"
echo "wp ldtt delete-items --lessons --confirm"
echo ""
echo "# Delete only topics"
echo "wp ldtt delete-items --topics --confirm"
echo ""
echo "# Delete only quizzes (includes questions)"
echo "wp ldtt delete-items --quizzes --confirm"
echo ""
echo "# Delete only groups"
echo "wp ldtt delete-items --groups --confirm"
echo ""
echo "# Delete only test users"
echo "wp ldtt delete-items --users --confirm"

echo -e "\n${YELLOW}🗑️ DELETE MULTIPLE TYPES${NC}"
echo "# Delete courses and lessons"
echo "wp ldtt delete-items --courses --lessons --confirm"
echo ""
echo "# Delete all content except users"
echo "wp ldtt delete-items --courses --lessons --topics --quizzes --groups --confirm"
echo ""
echo "# Delete everything (DANGEROUS!)"
echo "wp ldtt delete-items --courses --lessons --topics --quizzes --groups --users --confirm"

# ============================================================================
# 🏗️ COMPLETE WORKFLOW EXAMPLES
# ============================================================================

echo -e "\n${GREEN}🏗️ COMPLETE WORKFLOW EXAMPLES${NC}"
echo -e "${BLUE}─────────────────────────────────────${NC}"

echo -e "\n${YELLOW}🏢 SCENARIO 1: Corporate Training Environment${NC}"
echo "# Step 1: Create course structure"
echo "wp ldtt create-courses --count=15 --prefix='Corporate Training'"
echo "wp ldtt create-lessons --count=75"
echo "wp ldtt create-topics --count=225"
echo "wp ldtt create-quizzes --count=30 --questions=8"
echo ""
echo "# Step 2: Create groups and structure"
echo "wp ldtt course-groups --count=5 --prefix='Department'"
echo ""
echo "# Step 3: Use existing employees (300 people)"
echo "wp ldtt enhanced-user-distribution --total_users=300 --group_leaders=1.5 --group_members=4 --course_enrolled=20 --create_progress --use_existing"

echo -e "\n${YELLOW}🎓 SCENARIO 2: Educational Institution${NC}"
echo "# Step 1: Create academic structure"
echo "wp ldtt create-courses --count=25 --prefix='Course'"
echo "wp ldtt create-lessons --count=125"
echo "wp ldtt create-topics --count=375"
echo "wp ldtt create-quizzes --count=50 --questions=10"
echo ""
echo "# Step 2: Mixed user approach"
echo "wp ldtt enhanced-user-distribution --total_users=200 --group_leaders=1 --group_members=3 --course_enrolled=15 --create_progress --use_existing"
echo "wp ldtt enhanced-user-distribution --total_users=600 --group_leaders=0.5 --group_members=2 --course_enrolled=25 --create_progress --create_new"

echo -e "\n${YELLOW}💻 SCENARIO 3: Online Learning Platform${NC}"
echo "# Step 1: Create premium content"
echo "wp ldtt create-courses --count=20 --access_mode=subscribe --prefix='Premium'"
echo "wp ldtt create-courses --count=10 --access_mode=free --prefix='Free'"
echo "wp ldtt create-lessons --count=150"
echo "wp ldtt create-topics --count=450"
echo ""
echo "# Step 2: Create diverse user base"
echo "wp ldtt enhanced-user-distribution --total_users=500 --group_leaders=0.5 --group_members=1 --course_enrolled=30 --create_progress --create_new"

echo -e "\n${YELLOW}🔄 SCENARIO 4: Gradual Rollout Strategy${NC}"
echo "# Phase 1: Pilot with existing power users"
echo "wp ldtt enhanced-user-distribution --total_users=25 --group_leaders=4 --group_members=8 --course_enrolled=60 --create_progress --use_existing"
echo ""
echo "# Phase 2: Expand to more existing users"
echo "wp ldtt enhanced-user-distribution --total_users=75 --group_leaders=2 --group_members=5 --course_enrolled=40 --create_progress --use_existing"
echo ""
echo "# Phase 3: Create new users for growth"
echo "wp ldtt enhanced-user-distribution --total_users=100 --group_leaders=1 --group_members=3 --course_enrolled=25 --create_progress --create_new"
echo ""
echo "# Phase 4: Add progress to any remaining enrolled users"
echo "wp ldtt assign-progress --all_courses"

# ============================================================================
# 🔍 VERIFICATION AND MONITORING COMMANDS
# ============================================================================

echo -e "\n${GREEN}🔍 VERIFICATION AND MONITORING${NC}"
echo -e "${BLUE}────────────────────────────────────${NC}"

echo -e "\n${YELLOW}📊 CHECK PLUGIN STATUS${NC}"
echo "# Check if LearnDash is detected"
echo "wp eval \"var_dump(ldtt_is_learndash_available());\""
echo ""
echo "# Get test data statistics"
echo "wp eval \"print_r(ldtt_get_test_statistics());\""
echo ""
echo "# Check plugin status"
echo "wp eval \"print_r(ldtt()->get_plugin_info());\""

echo -e "\n${YELLOW}📈 CHECK USER DISTRIBUTION RESULTS${NC}"
echo "# Count users by type"
echo "wp eval \""
echo "echo 'Group Leaders: ' . count(get_users(array('role' => 'group_leader'))) . \"\\n\";"
echo "echo 'Group Members: ' . count(get_users(array('meta_key' => '_ldtt_user_type', 'meta_value' => 'group_member'))) . \"\\n\";"
echo "echo 'Course Enrolled: ' . count(get_users(array('meta_key' => '_ldtt_user_type', 'meta_value' => 'course_enrolled'))) . \"\\n\";"
echo "echo 'With Progress: ' . count(get_users(array('meta_key' => '_ldtt_progress_created'))) . \"\\n\";"
echo "\""

echo -e "\n${YELLOW}🎯 CHECK COURSE RELATIONSHIPS${NC}"
echo "# List all courses and their content count"
echo "wp post list --post_type=sfwd-courses --format=table --fields=ID,post_title"
echo ""
echo "# Check lessons for specific course"
echo "wp post list --post_type=sfwd-lessons --meta_key=_sfwd-lessons --format=table"
echo ""
echo "# Check enrollments per course"
echo "wp eval \""
echo "\\$courses = get_posts(array('post_type' => 'sfwd-courses', 'fields' => 'ids'));"
echo "foreach(\\$courses as \\$course_id) {"
echo "    \\$users = learndash_get_users_for_course(\\$course_id);"
echo "    \\$title = get_the_title(\\$course_id);"
echo "    echo \"Course: {\\$title} ({\\$course_id}) - \" . count(\\$users) . \" enrolled users\\n\";"
echo "}"
echo "\""

echo -e "\n${YELLOW}💾 BACKUP AND RECOVERY${NC}"
echo "# Create backup of test data"
echo "wp eval \"print_r(ldtt_create_backup());\""
echo ""
echo "# Check existing users before using --use_existing"
echo "wp user list --role=subscriber --format=count"
echo "wp user list --role__not_in=administrator --format=count"

# ============================================================================
# ⚡ PERFORMANCE AND MAINTENANCE
# ============================================================================

echo -e "\n${GREEN}⚡ PERFORMANCE AND MAINTENANCE${NC}"
echo -e "${BLUE}─────────────────────────────────────${NC}"

echo -e "\n${YELLOW}🚀 PERFORMANCE TESTING${NC}"
echo "# Create large dataset for performance testing"
echo "wp ldtt create-courses --count=50"
echo "wp ldtt create-lessons --count=250"
echo "wp ldtt create-topics --count=750"
echo "wp ldtt enhanced-user-distribution --total_users=500 --course_enrolled=15 --create_progress --create_new"

echo -e "\n${YELLOW}⏱️ MONITORING DURING OPERATIONS${NC}"
echo "# Monitor memory usage (run in separate terminal)"
echo "watch -n 1 'wp eval \"echo \\\"Memory: \\\" . ldtt_format_bytes(memory_get_usage(true)) . \\\" / Peak: \\\" . ldtt_format_bytes(memory_get_peak_usage(true));\"'"
echo ""
echo "# Time a large operation"
echo "time wp ldtt enhanced-user-distribution --total_users=500 --course_enrolled=10 --create_progress --create_new"

echo -e "\n${YELLOW}🔧 BATCH PROCESSING FOR LARGE DATASETS${NC}"
echo "# Instead of creating 1000 users at once, batch it:"
echo "for i in {1..4}; do"
echo "    wp ldtt enhanced-user-distribution --total_users=250 --course_enrolled=10 --create_progress --create_new"
echo "    sleep 5"
echo "done"

echo -e "\n${YELLOW}🧹 MAINTENANCE COMMANDS${NC}"
echo "# Clean up test data regularly"
echo "wp ldtt delete-items --users --confirm"
echo ""
echo "# Complete cleanup for fresh start (CAUTION!)"
echo "wp ldtt delete-items --courses --lessons --topics --quizzes --groups --users --confirm"

# ============================================================================
# 💡 BEST PRACTICES AND TIPS
# ============================================================================

echo -e "\n${GREEN}💡 BEST PRACTICES AND TIPS${NC}"
echo -e "${BLUE}───────────────────────────────────${NC}"

echo -e "\n${YELLOW}✅ RECOMMENDED PRACTICES${NC}"
echo "1. Always start small and scale up:"
echo "   wp ldtt enhanced-user-distribution --total_users=10 --course_enrolled=50 --create_progress --use_existing"
echo ""
echo "2. Check existing users before using --use_existing:"
echo "   wp user list --format=count"
echo ""
echo "3. Monitor resource usage for large operations:"
echo "   # Increase PHP limits: memory_limit = 512M, max_execution_time = 300"
echo ""
echo "4. Verify results after running commands:"
echo "   wp eval \"print_r(ldtt_get_test_statistics());\""
echo ""
echo "5. Use specific targeting for focused testing:"
echo "   wp ldtt create-lessons --count=20 --course_id=123"
echo "   wp ldtt enrollment --course_id=123 --count=30"

echo -e "\n${YELLOW}⚠️ SAFETY GUIDELINES${NC}"
echo "1. Always use --confirm flag for cleanup operations"
echo "2. Create backups before major operations"
echo "3. Test with small numbers first"
echo "4. Use existing users mode for real environments"
echo "5. Use new users mode for isolated testing"

echo -e "\n${YELLOW}🎯 PARAMETER GUIDELINES${NC}"
echo "# Total Users: 10-1000 (start small, scale up)"
echo "# Group Leaders: 0.1-10% (typically 1-2%)"
echo "# Group Members: 0.1-20% (typically 2-5%)"  
echo "# Course Enrolled: 0.1-50% (typically 5-25%)"
echo "# Progress: Always recommended for realistic testing"

# ============================================================================
# 🆘 TROUBLESHOOTING COMMANDS
# ============================================================================

echo -e "\n${GREEN}🆘 TROUBLESHOOTING COMMANDS${NC}"
echo -e "${BLUE}─────────────────────────────────────${NC}"

echo -e "\n${YELLOW}🔧 DIAGNOSTIC COMMANDS${NC}"
echo "# Check if plugin is loaded properly"
echo "wp eval \"var_dump(class_exists('LearnDash_Testing_Toolkit'));\""
echo ""
echo "# Check core instance"
echo "wp eval \"var_dump(ldtt_core() !== null);\""
echo ""
echo "# Check available commands"
echo "wp cli cmd-dump ldtt"
echo ""
echo "# Verify file structure"
echo "ls -la wp-content/plugins/learndash-testing-toolkit/includes/class-ldtt-*.php"

echo -e "\n${YELLOW}🚨 EMERGENCY RESET${NC}"
echo "# If something goes wrong, emergency cleanup:"
echo "wp ldtt delete-items --courses --lessons --topics --quizzes --groups --users --confirm"
echo ""
echo "# Reset plugin settings"
echo "wp option delete ldtt_settings"
echo "wp option delete ldtt_bypass_learndash_check"
echo ""
echo "# Reactivate plugin"
echo "wp plugin deactivate learndash-testing-toolkit"
echo "wp plugin activate learndash-testing-toolkit"

# ============================================================================
# 📚 COMMAND REFERENCE SUMMARY
# ============================================================================

echo -e "\n${GREEN}📚 QUICK COMMAND REFERENCE${NC}"
echo -e "${BLUE}────────────────────────────${NC}"

echo -e "\n${PURPLE}Content Creation:${NC}"
echo "wp ldtt create-courses [--count=N] [--prefix=text] [--access_mode=mode]"
echo "wp ldtt create-lessons [--count=N] [--course_id=ID]"
echo "wp ldtt create-topics [--count=N] [--lesson_id=ID]"
echo "wp ldtt create-quizzes [--count=N] [--questions=N]"

echo -e "\n${PURPLE}User Distribution:${NC}"
echo "wp ldtt enhanced-user-distribution [--total_users=N] [--group_leaders=N] [--group_members=N] [--course_enrolled=N] [--create_progress] [--use_existing|--create_new]"

echo -e "\n${PURPLE}Progress Management:${NC}"
echo "wp ldtt assign-progress [--course_id=ID|--all_courses] [--min_progress=N] [--max_progress=N] [--overwrite]"

echo -e "\n${PURPLE}User Management:${NC}"
echo "wp ldtt enrollment [--course_id=ID] [--count=N]"
echo "wp ldtt course-groups [--count=N] [--prefix=text]"
echo "wp ldtt group-leaders [--group=name] [--leader=name] [--count=N]"
echo "wp ldtt group-enrollment [--group=name] [--users=N]"

echo -e "\n${PURPLE}Cleanup:${NC}"
echo "wp ldtt delete-items [--courses] [--lessons] [--topics] [--quizzes] [--groups] [--users] --confirm"

echo -e "\n${CYAN}=================================================="
echo -e "LEARNDASH TESTING TOOLKIT - COMMANDS COMPLETE"
echo -e "For support: https://github.com/vapvarun/learndash-testing-toolkit"
echo -e "==================================================${NC}"

# Save this file as: ldtt-commands.sh
# Make executable: chmod +x ldtt-commands.sh
# Run: ./ldtt-commands.sh