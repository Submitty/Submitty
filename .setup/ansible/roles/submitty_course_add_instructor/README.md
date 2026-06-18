# Add Instructor Role

This role adds an instructor to a course in the Submitty course management system. It sets the instructor's username, groups, course, users they are responsible for, first name, last name, email, term they are teaching, user group, and password.

## Required Variables

You need to set the following variables in your playbook to ensure the installation proceeds as expected:

- `submitty_course_add_instructor_username`: The username of the instructor.
- `submitty_course_add_instructor_groups`: The groups the instructor belongs to.
- `submitty_course_add_instructor_course`: The course the instructor is teaching.
- `submitty_course_add_instructor_users`: The users the instructor is responsible for.
- `submitty_course_add_instructor_firstname`: The first name of the instructor.
- `submitty_course_add_instructor_lastname`: The last name of the instructor.
- `submitty_course_add_instructor_email`: The email of the instructor.
- `submitty_course_add_instructor_term`: The term the instructor is teaching.
- `submitty_course_add_instructor_user_group`: The user group the instructor belongs to. The default is set to 1 since that is the user group for instructors. 
- `submitty_course_add_instructor_password`: The password for the instructor. Please ensure this is a strong, unique password.

**Important: All the above values need to be passed as strings.**

Please replace the placeholder values in the playbook with your specific values before running the playbook.
