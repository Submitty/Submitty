#!/usr/bin/env python3

"""
The expected COURSE_PATH is:
<BASE_PATH>/courses/<SEMESTER>/<COURSES>

Expected directory structures:
<COURSE_PATH>/submissions/<TESTNOTES GRADEABLE>/<WHO>/<VERSION#>
and
<COURSE_PATH>/results/<TESTNOTES GRADEABLE>/<WHO>/<VERSION#>

This script will find all submissions that have a produced test_template.pdf (passed
test case 2 in the test_notes_upload or test_notes_upload_3page configs), and copy them
to an output directory of your choosing with each copy renamed to the USERID.pdf of the
user who submitted the test notes.
USAGE:
    extract_notes_page.py  <COURSE_PATH> <TESTNOTES GRADABLE ID> <OUTPUT DIRECTORY>
"""

import os
import sys
import json
import shutil

if len(sys.argv) != 4:
    print("Proper usage is", sys.argv[0],
          "[course path] [note sheet gradeable ID] [output directory]")
    sys.exit(-1)

course_path = sys.argv[1]
gradeable = sys.argv[2]
submission_dir = os.path.join(course_path, "submissions", gradeable)
result_dir = os.path.join(course_path, "results", gradeable)
output_dir = sys.argv[3]

if not os.path.isdir(submission_dir):
    print("Could not find submission directory {}".format(submission_dir))

if not os.path.isdir(result_dir):
    print("Could not find result directory {}".format(result_dir))

if not os.path.isdir(output_dir):
    os.mkdir(output_dir)

extracted_files = 0

for s in os.listdir(submission_dir):
    # Avoid . and ..
    if s[0] == ".":
        continue

    uas_path = os.path.join(submission_dir, s, "user_assignment_settings.json")
    if not os.path.isfile(uas_path):
        print("Warning, ", s,
              " does not have assignment settings, maybe hasn't submitted.")
        continue

    with open(uas_path, "r") as uad:
        data = json.load(uad)
        version = data["active_version"]
        if version == 0:
            print("Warning, {} does not have an active version, skipping".format(s))
            continue

    pdf_results_path = os.path.join(result_dir, s, str(version), "details", "test02")
    if not os.path.isdir(pdf_results_path):
        print("Warning, {} does not have results for their active version".format(s))
        continue

    pdf_template_path = os.path.join(pdf_results_path, "test_template.pdf")
    if not os.path.isfile(pdf_template_path):
        print("Warning, {} does not have a PDF for their active version".format(s))
        continue

    shutil.copy(pdf_template_path, os.path.join(output_dir, s + ".pdf"))
    extracted_files = extracted_files + 1

print("Finished, extracted {} PDF file(s).".format(extracted_files))
