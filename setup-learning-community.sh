#!/bin/bash

# ============================================================================
# ACTIVE LEARNING COMMUNITY SETUP
# For 20 courses with existing 500 users, 20+ groups, leaders & progress
# ============================================================================

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

echo -e "${CYAN}=================================================="
echo -e "SETTING UP ACTIVE LEARNING COMMUNITY"
echo -e "Target: 20 courses, 500 existing users, 20+ groups"
echo -e "==================================================${NC}"

# ============================================================================
# STEP 1: VERIFY EXISTING USERS
# ============================================================================

echo -e "\n${GREEN}STEP 1: Verifying existing users...${NC}"
echo -e "${BLUE}────────────────────────────────────${NC}"

echo "# Check current user count"
wp user list --format=count

echo "# Check user roles distribution"
wp user list --field=roles | sort | uniq -c

# ============================================================================
# STEP 2: CREATE COURSE STRUCTURE (20 COURSES)
# ============================================================================

echo -e "\n${GREEN}STEP 2: Creating course structure...${NC}"
echo -e "${BLUE}─────────────────────────────────────${NC}"

echo "# Create 20 courses with mixed access modes"
wp ldtt create-courses --count=8 --access_mode=free --prefix='Free Course'
wp ldtt create-courses --count=7 --access_mode=paynow --prefix='Premium Course'
wp ldtt create-courses --count=5 --access_mode=subscribe --prefix='Subscription Course'

echo "# Create lessons (average 8-10 lessons per course)"
wp ldtt create-lessons --count=180

echo "# Create topics (average 3-4 topics per lesson)"
wp ldtt create-topics --count=600

echo "# Create quizzes for assessment"
wp ldtt create-quizzes --count=40 --questions=8

# ============================================================================
# STEP 3: CREATE GROUP STRUCTURE (20+ GROUPS)
# ============================================================================

echo -e "\n${GREEN}STEP 3: Creating group structure...${NC}"
echo -e "${BLUE}───────────────────────────────────${NC}"

echo "# Create 25 groups for comprehensive testing"
wp ldtt course-groups --count=25 --prefix='Learning Group'

# ============================================================================
# STEP 4: ENHANCED USER DISTRIBUTION WITH EXISTING USERS
# ============================================================================

echo -e "\n${GREEN}STEP 4: Distributing existing users across roles...${NC}"
echo -e "${BLUE}─────────────────────────────────────────────────${NC}"

echo "# Main distribution: Use existing 500 users"
echo "# - Group Leaders: 2% (10 users)"
echo "# - Group Members: 15% (75 users)" 
echo "# - Course Enrolled: 60% (300 users)"
echo "# - Remaining 115 users stay as regular subscribers"

wp ldtt enhanced-user-distribution \
    --total_users=385 \
    --group_leaders=2.6 \
    --group_members=19.5 \
    --course_enrolled=77.9 \
    --create_progress \
    --use_existing

# ============================================================================
# STEP 5: ADDITIONAL USER ASSIGNMENTS FOR COMPLETE COVERAGE
# ============================================================================

echo -e "\n${GREEN}STEP 5: Additional user assignments...${NC}"
echo -e "${BLUE}──────────────────────────────────────────${NC}"

echo "# Assign remaining users to ensure full distribution"
wp ldtt enhanced-user-distribution \
    --total_users=115 \
    --group_leaders=0 \
    --group_members=10 \
    --course_enrolled=90 \
    --create_progress \
    --use_existing

# ============================================================================
# STEP 6: ENSURE ALL ENROLLED USERS HAVE PROGRESS
# ============================================================================

echo -e "\n${GREEN}STEP 6: Ensuring comprehensive progress data...${NC}"
echo -e "${BLUE}─────────────────────────────────────────────────${NC}"

echo "# Add progress to any enrolled users who might not have it"
wp ldtt assign-progress --all_courses --min_progress=10 --max_progress=95

# ============================================================================
# STEP 7: VERIFICATION AND STATISTICS
# ============================================================================

echo -e "\n${GREEN}STEP 7: Verification and statistics...${NC}"
echo -e "${BLUE}──────────────────────────────────────────${NC}"

echo "# Get comprehensive statistics"
wp eval "print_r(ldtt_get_test_statistics());"

echo "# Count users by role and type"
wp eval "
echo 'Total Users: ' . count(get_users()) . \"\n\";
echo 'Group Leaders: ' . count(get_users(array('role' => 'group_leader'))) . \"\n\";
echo 'Group Members: ' . count(get_users(array('meta_key' => '_ldtt_user_type', 'meta_value' => 'group_member'))) . \"\n\";
echo 'Course Enrolled: ' . count(get_users(array('meta_key' => '_ldtt_user_type', 'meta_value' => 'course_enrolled'))) . \"\n\";
echo 'Users with Progress: ' . count(get_users(array('meta_key' => '_ldtt_progress_created'))) . \"\n\";
"

echo "# List all courses created"
wp post list --post_type=sfwd-courses --format=table --fields=ID,post_title

echo "# Check group count"
wp eval "
\$groups = get_posts(array('post_type' => 'groups', 'numberposts' => -1));
echo 'Total Groups Created: ' . count(\$groups) . \"\n\";
"

# ============================================================================
# OPTIONAL: ADVANCED SCENARIOS FOR SPECIFIC TESTING
# ============================================================================

echo -e "\n${GREEN}OPTIONAL: Advanced scenario commands (run if needed)${NC}"
echo -e "${BLUE}────────────────────────────────────────────────────${NC}"

echo "# Create power users with high engagement"
echo "# wp ldtt enhanced-user-distribution --total_users=25 --group_leaders=4 --group_members=8 --course_enrolled=80 --create_progress --use_existing"

echo "# Create low-engagement test scenario"
echo "# wp ldtt enhanced-user-distribution --total_users=50 --group_leaders=0 --group_members=2 --course_enrolled=20 --create_progress --use_existing"

echo "# Add specific progress patterns for testing"
echo "# wp ldtt assign-progress --course_id=123 --min_progress=90 --max_progress=100 --overwrite"

# ============================================================================
# MONITORING AND MAINTENANCE
# ============================================================================

echo -e "\n${GREEN}MONITORING COMMANDS${NC}"
echo -e "${BLUE}─────────────────────${NC}"

echo "# Monitor course enrollments"
echo "wp eval \"
\\\$courses = get_posts(array('post_type' => 'sfwd-courses', 'fields' => 'ids'));
foreach(\\\$courses as \\\$course_id) {
    \\\$users = learndash_get_users_for_course(\\\$course_id);
    \\\$title = get_the_title(\\\$course_id);
    echo \\\"Course: {\\\$title} - \\\" . count(\\\$users) . \\\" enrolled users\\\n\\\";
}
\""

echo "# Check memory usage during operations"
echo "wp eval \"echo 'Memory Usage: ' . ldtt_format_bytes(memory_get_usage(true)) . ' / Peak: ' . ldtt_format_bytes(memory_get_peak_usage(true));\""

# ============================================================================
# CLEANUP COMMANDS (USE WITH CAUTION)
# ============================================================================

echo -e "\n${GREEN}CLEANUP COMMANDS (Use with caution!)${NC}"
echo -e "${BLUE}────────────────────────────────────────${NC}"

echo "# Reset only test content (keeps original users)"
echo "# wp ldtt delete-items --courses --lessons --topics --quizzes --groups --confirm"

echo "# Complete reset (removes test users too - DANGEROUS!)"
echo "# wp ldtt delete-items --courses --lessons --topics --quizzes --groups --users --confirm"

echo -e "\n${CYAN}=================================================="
echo -e "ACTIVE LEARNING COMMUNITY SETUP COMPLETE!"
echo -e "Results:"
echo -e "- 20 courses (mixed access modes)"
echo -e "- 180 lessons"
echo -e "- 600 topics" 
echo -e "- 40 quizzes"
echo -e "- 25 groups"
echo -e "- 500 existing users distributed with roles"
echo -e "- Comprehensive progress data"
echo -e "==================================================${NC}"