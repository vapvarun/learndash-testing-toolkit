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

Create courses with a specified prefix, access mode, and count.

```bash
wp ldtt create-courses --prefix="My Course" --count=20 --access_mode=free
```

- **prefix**: (optional) The prefix to be used for course titles.
- **count**: (optional) The number of courses to create. Default is 20.
- **access_mode**: (optional) The access mode for the courses. Options are `open`, `free`, `buy-now`, `recurring`, `closed`. If not specified, the command will cycle through all modes.

### Create Lessons

Create lessons under a specified course.

```bash
wp ldtt create-lessons --course="Sample Course" --lessons=5
```

- **course**: The name of the course to create lessons under.
- **lessons**: The number of lessons to create. Default is 3.

### Create Topics

Create topics under a specified lesson.

```bash
wp ldtt create-topics --lesson="Sample Lesson" --topics=5
```

- **lesson**: The name of the lesson to create topics under.
- **topics**: The number of topics to create. Default is 3.

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
