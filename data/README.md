# LearnDash Testing Toolkit - Sample Data

This directory contains comprehensive JSON datasets for creating realistic LearnDash test content.

## Available Datasets

### Main Dataset
- `learndash-sample-data.json` - Complete dataset with all titles and content

### Individual Datasets
- `course-titles.json` - 55 realistic course titles
- `lesson-titles.json` - 53 professional lesson titles  
- `topic-titles.json` - 54 topic titles for detailed content
- `group-titles.json` - 55 group/community titles
- `content-paragraphs.json` - Reusable content for descriptions

## Usage Examples

### PHP Implementation

```php
// Load the main dataset
$data = json_decode(file_get_contents(__DIR__ . '/data/learndash-sample-data.json'), true);

// Create course with random data
$course_titles = $data['courses']['titles'];
$course_descriptions = $data['courses']['descriptions'];

$random_title = $course_titles[array_rand($course_titles)];
$random_description = $course_descriptions[array_rand($course_descriptions)];

// Create lesson with random content
$lesson_titles = $data['lessons']['titles'];
$lesson_content = $data['lessons']['content'];

$random_lesson_title = $lesson_titles[array_rand($lesson_titles)];
$random_lesson_content = $lesson_content[array_rand($lesson_content)];
```

### WP-CLI Integration

```bash
# Example using the data in CLI commands
wp ldtt create-courses --count=10 --data-source=/path/to/learndash-sample-data.json
wp ldtt create-lessons --count=50 --data-source=/path/to/learndash-sample-data.json
wp ldtt create-groups --count=20 --data-source=/path/to/learndash-sample-data.json
```

## Data Structure

### Courses
- **Titles**: 55 professional course titles across various industries
- **Descriptions**: 10 detailed course descriptions (1-2 paragraphs each)

### Lessons  
- **Titles**: 53 lesson titles suitable for any course type
- **Content**: 10 lesson content templates (1-2 paragraphs each)

### Topics
- **Titles**: 54 specific topic titles for detailed learning
- **Content**: 8 topic content templates (1-2 paragraphs each)

### Groups
- **Titles**: 55 professional group/community names
- **Descriptions**: 5 group description templates

## Content Quality

All content is:
- ✅ Professional and realistic
- ✅ Suitable for live/production testing
- ✅ Industry-agnostic yet specific enough to be meaningful
- ✅ Optimized for variety and reusability
- ✅ Designed to look like real course content

## Integration with LDTT

Update your LDTT classes to use this data:

```php
class LDTT_Enhanced_Sample_Data extends LDTT_Sample_Data {
    
    private static function load_sample_data() {
        $file = plugin_dir_path(__FILE__) . '../data/learndash-sample-data.json';
        return json_decode(file_get_contents($file), true);
    }
    
    public static function get_random_course_title() {
        $data = self::load_sample_data();
        $titles = $data['courses']['titles'];
        return $titles[array_rand($titles)];
    }
    
    public static function get_random_course_description() {
        $data = self::load_sample_data();
        $descriptions = $data['courses']['descriptions'];
        return $descriptions[array_rand($descriptions)];
    }
    
    // Similar methods for lessons, topics, groups...
}
```

## Best Practices

1. **Randomization**: Always randomize content selection to ensure variety
2. **Combination**: Mix different content paragraphs for longer descriptions  
3. **Contextual**: Match content appropriately (don't use course descriptions for topics)
4. **Testing**: These datasets are perfect for load testing and UI testing
5. **Realistic**: All content looks professional and realistic for demos

## File Sizes
- Main dataset: ~15KB
- Individual files: 2-5KB each
- Total: ~25KB for all datasets

Perfect for version control and fast loading in testing environments.