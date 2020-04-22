import os
import sys
import json
import traceback
import re
import tzlocal
from datetime import datetime, timedelta

GLOBAL_INPUT_JSON_PATH = 'custom_validator_input.json'

def return_result(score, message, status):

  # Create response to student.
  # the message. Status can be 'information', 'failure', 'warning', or 'success'.
  result = {
              # Status success means the grader did not crash (no validator failures).
              'status' : "success",
              'data': {
                        # Score is on a range from zero (no credit) to 1 (full credit)
                        'score' : score,
                        # A message to the student
                        'message' : message,
                        # The status of the submission (indicates if the
                        # student succeeded at the testcase).
                        # Values can be 'information', 'failure', 'warning'
                        # or 'success'.
                        'status':status
                      }
          }
  # Print the result json to stdout so that it can be read by submitty.
  print(json.dumps(result, indent=4))
  # End the program, because we have returned a result.
  sys.exit(0)


def do_the_grading():

  # grab the prefix/directory for this test case
  with open(GLOBAL_INPUT_JSON_PATH) as json_file:
    testcase = json.load(json_file)
    prefix = testcase['testcase_prefix']
    allowed_minutes = testcase['allowed_minutes']
    penalty_per_minute = testcase['penalty_per_minute']
    max_penalty = testcase['max_penalty']

  # get the active version
  my_queue_file = os.path.join(prefix, "queue_file.json")
  with open(my_queue_file) as queue_file:
    queue = json.load(queue_file)
  active_version = queue["version"]

  # get the timestamp of first gradeable access/load
  first_access_timestamp_string = ""
  my_access_file = os.path.join(prefix, "user_assignment_access.json")
  if os.path.exists(my_access_file):
    with open(my_access_file) as access_file:
      access = json.load(access_file)
      first_access_timestamp_string = access["page_load_history"][0]["time"]
      
  # FIXME: The access date string is currently misformatted
  #    mm-dd-yyyy, but we want yyyy-mm-dd.  Also it is missing
  #    the common name timezone string, e.g., "America/NewYork".
  #    We should standardize this logging eventually, but
  #    keeping it as is because we are mid-semester with this
  #    new feature and I don't want to break things.
  words = first_access_timestamp_string.split(' ')
  date_parts = words[0].split('-')
  if len(date_parts[0]) == 2:
    words[0] = date_parts[2]+'-'+date_parts[0]+'-'+date_parts[1]
    first_access_timestamp_string = words[0] + ' ' + words[1]
  first_access_timestamp = datetime.strptime(first_access_timestamp_string, '%Y-%m-%d %H:%M:%S%z')

  # get the submission timestamp of this version
  submission_timestamp_string = ""
  my_submission_file = os.path.join(prefix, "user_assignment_settings.json")
  with open(my_submission_file) as submission_file:
      submission = json.load(submission_file)
      for item in submission["history"]:
        if str(item["version"]) == active_version:
          submission_timestamp_string = item["time"]
  words = submission_timestamp_string.split(' ')
  submission_timestamp_string = words[0] + ' ' + words[1]
  submission_timestamp = datetime.strptime(submission_timestamp_string, '%Y-%m-%d %H:%M:%S%z')

  # compute the difference in seconds
  access_duration = int((submission_timestamp-first_access_timestamp).total_seconds())

  # prepare a nice print string of the duration
  hours = int(access_duration / 3600)
  minutes = int((access_duration % 3600) / 60)
  seconds = access_duration % 60
  if hours > 0:
    duration_string = str(hours) + " hours " + str(minutes) + " minutes " + str(seconds) + " seconds"
  elif minutes > 0:
    duration_string = str(minutes) + " minutes " + str(seconds) + " seconds"
  else:
    duration_string = str(seconds) + " seconds"

  penalty_minutes = hours * 60 + minutes
  if seconds > 0:
    penalty_minutes = penalty_minutes+1

  penalty_minutes = penalty_minutes - allowed_minutes

  my_score = 0

  if penalty_minutes > 0:
    my_score = float(penalty_minutes) / (-1 * max_penalty)
  if my_score > 1:
    my_score = 1

  my_message = "version " + active_version +\
    " : first access@" + str(first_access_timestamp) +\
    " : submission@" + str(submission_timestamp) +\
    " : duration " + duration_string +\
    "penalty_minutes" + str(penalty_minutes) +\
    " score " + str(my_score)

  if my_score > 0:
    return_result(score=my_score,
                  message=my_message,
                  status='failure')
  else:
    return_result(score=my_score,
                  message=my_message,
                  status='success')


if __name__ == '__main__':
  do_the_grading()
