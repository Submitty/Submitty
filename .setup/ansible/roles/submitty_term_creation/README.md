# Term Creation Role

This role creates a new term in the Submitty course management system.

## Required Variables

You need to set the following variables in your playbook to ensure the term is created as expected:

- `submitty_term_creation_key`: The key for the term. This needs to be a unique identifier for the term.
- `submitty_term_creation_name`: The name of the term. This is the display name for the term.
- `submitty_term_creation_start_date`: The start date for the term. This should be in the format MM/DD/YYYY.
- `submitty_term_creation_end_date`: The end date for the term. This should be in the format MM/DD/YYYY.

**Important: All the above values need to be passed as strings.**

Please replace the placeholder values in the playbook with your specific values before running the playbook.
