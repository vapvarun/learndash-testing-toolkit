# LearnDash Testing Toolkit

The LearnDash Testing Toolkit is a powerful WordPress CLI plugin designed to streamline the process of creating and managing LearnDash courses, lessons, topics, quizzes, and more. This toolkit is particularly useful for developers and administrators who need to set up multiple courses and related components quickly and efficiently.

## Features

- Create courses with different access modes: Open, Free, Buy Now, Recurring, and Closed.
- Supports batch creation of courses, with an option to specify the number of courses.
- Allows specifying a particular access mode or cycling through all available modes.
- Create lessons, topics, quizzes, and questions under specified courses.
- Enroll users into courses or groups via CLI.

## Installation

1. Download the plugin files and place them in your `wp-content/plugins` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Make sure WP-CLI is installed and accessible from your terminal.

## Usage

### Create Courses

Create multiple LearnDash courses with various access modes.

**Command:**

```bash
wp ldtt create-courses [--count=<number>] [--access_mode=<mode>]
```

**Parameters:**

- `count` (optional): Number of courses to create. Default is 5.
- `access_mode` (optional): Access mode for the courses. If not specified, courses will be created with a random access mode. Available modes: `open`, `free`, `paynow`, `subscribe`, `closed`.

### Create Lessons

Create multiple LearnDash lessons and assign them to available courses.

**Command:**

```bash
wp ldtt create-lessons [--count=<number>] [--course_id=<id>] [--author_id=<id>]
```

**Parameters:**

- `count` (optional): Number of lessons to create. Default is 50.
- `course_id` (optional): Course ID to assign lessons to. If not provided, lessons will be assigned to random courses.
- `author_id` (optional): User ID to set as the author of the lessons. Defaults to an admin user.

### Create Topics

Create multiple LearnDash topics and assign them to available lessons.

**Command:**

```bash
wp ldtt create-topics [--count=<number>] [--lesson_id=<id>] [--author_id=<id>]
```

**Parameters:**

- `count` (optional): Number of topics to create. Default is 50.
- `lesson_id` (optional): Lesson ID to assign topics to. If not provided, topics will be assigned to random lessons.
- `author_id` (optional): User ID to set as the author of the topics. Defaults to an admin user.

### Create Quizzes

Create quizzes under a specified lesson or topic.

```bash
wp ldtt create-quizzes --lesson="Sample Lesson" --quizzes=3
```

- **lesson**: The name of the lesson to create quizzes under. (optional if topic is specified)
- **topic**: The name of the topic to create quizzes under. (optional if lesson is specified)
- **quizzes**: The number of quizzes to create. Default is 1.

### Create Questions

Create questions under a specified quiz.

```bash
wp ldtt create-questions --quiz="Sample Quiz" --questions=5
```

- **quiz**: The name of the quiz to create questions under.
- **questions**: The number of questions to create. Default is 5.

### Enroll Users

Enroll users into a course or group.

#### Enroll Users into a Course

```bash
wp ldtt enroll-users --course="Sample Course" --users=10
```

- **course**: The name of the course to enroll users in.
- **users**: The number of users to enroll. Default is 5.

#### Enroll Users into a Group

```bash
wp ldtt enroll-users --group="Sample Group" --users=10
```

- **group**: The name of the group to enroll users in.
- **users**: The number of users to enroll. Default is 5.

## Contributing

If you would like to contribute to this plugin, please fork the repository and submit a pull request. Your contributions are greatly appreciated.

## License

This plugin is licensed under the GPL v2 or later.

## Support

For support, please contact the plugin author or refer to the plugin documentation.
