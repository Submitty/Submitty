Submitty Jobs Daemon
====================

This contains code to create a daemon process that does odd jobs for Submitty.

Queue Files
-----------
The system uses a concept of "queue files", which are JSON files that are
created within a specific directory that has a job name to use, and then
additional details necessary for the running of that job.

Queue File Example:
```json
{
    "job": "BuildConfig",
    "semester": "f18",
    "course": "sample",
    "gradeable": "test"
}
```

Jobs
----

### BuildConfig
Parameters:
* semester
* course
* gradeable

Builds a gradeable for a course

### RunLichen
Parameters:
* semester
* course
* gradeable

Runs Lichen over a given gradeable for a course

### DeleteLichenResult
Parameters:
* semester
* course
* gradeable

Delete Lichen results for a given gradeable for a course

### SendEmail
Parameters:
* email_type
* semester
* course
* thread_title
* thread_content

Sends out an email for a course. This calls a script that handles
actually gets the list of recipients for the email as well as
sending it via the smtp python library.

### BulkQRSplit
Parameters:
* semester
* course
* gradeable id
* version timestamp
* qr prefix
* qr suffix
