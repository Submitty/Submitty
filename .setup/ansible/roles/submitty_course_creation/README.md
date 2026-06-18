# Create Course Role

This role creates a new course in the Submitty course management system.

## Required Variables

You need to set the following variables in your playbook to ensure the course is created as expected:

- `submitty_course_creation_course`: The identifier for the course. This needs to be a unique identifier for the course.
- `submitty_course_creation_instructor`: The instructor for the course.
- `submitty_course_creation_username`: The username of the instructor.
- `submitty_course_creation_firstname`: The first name of the instructor.
- `submitty_course_creation_lastname`: The last name of the instructor.
- `submitty_course_creation_email`: The email of the instructor.
- `submitty_course_creation_semester`: The semester the course is being created for.
- `submitty_course_creation_user_group`: The user group the instructor belongs to. By default, this is set to '1', which corresponds to the instructor role.

**Important: All the above values, including the user group, need to be passed as strings.**

Please replace the placeholder values in the playbook with your specific values before running the playbook.