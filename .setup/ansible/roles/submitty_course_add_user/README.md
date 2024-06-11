# Add User Role

This role adds a new user to a course in the Submitty course management system.

## Required Variables

You need to set the following variables in your playbook to ensure the user is added as expected:

- `submitty_course_add_user_username`: The username for the user. This needs to be a unique identifier for the user.
- `submitty_course_add_user_course`: The course to add the user to.
- `submitty_course_add_user_firstname`: The first name of the user.
- `submitty_course_add_user_lastname`: The last name of the user.
- `submitty_course_add_user_email`: The email of the user.
- `submitty_course_add_user_password`: The password for the user. Please ensure this is a strong, unique password.
- `submitty_course_add_user_term`: The term the user is being added for.
- `submitty_course_add_user_user_group`: The user group the user belongs to. The default is set to 4 since that is the user group for students.

**Important: All the above values need to be passed as strings.**

Please replace the placeholder values in the playbook with your specific values before running the playbook.
