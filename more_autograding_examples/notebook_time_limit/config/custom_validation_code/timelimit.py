# ==========================================================
# NOTE: Currently implemented as an instructor provided
# custom validator, but will likely be available as a built
# in validation option in the future.
# ==========================================================


import os
import sys
import json
import traceback
import re
import tzlocal
from datetime import datetime, timedelta


def return_result(score, message, status):
  result = {
    'status' : "success",
    'data': {
      'score': score,
      'message': message,
      'status': status
    }
  }
  with open('validation_results.json', 'w') as outfile:
    json.dump(result, outfile, indent=4)
  sys.exit(0)


def log_line(line):
  mode = 'a' if os.path.exists('validation_logfile.txt') else 'w'
  with open('validation_logfile.txt', mode) as outfile:
    outfile.write(line+"\n")


def do_the_grading():

  # read the testcase configuration variables
  with open('custom_validator_input.json') as json_file:
    testcase = json.load(json_file)
    prefix = testcase['testcase_prefix']
    allowed_minutes = testcase['allowed_minutes']
    penalty_per_minute = testcase['penalty_per_minute']
    max_penalty = testcase['max_penalty']

    # get the active version and user id
    my_queue_file = os.path.join(prefix, "queue_file.json")
    with open(my_queue_file) as queue_file:
      queue = json.load(queue_file)
      active_version = queue["version"]
      user_id = queue["user"]

    # look for an override for this user
    if 'override' in testcase:
      for e in testcase['override']:
        if user_id == e['user']:
          allowed_minutes = e['allowed_minutes']

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
  my_submission_file = os.path.join(prefix, ".submit.timestamp")
  with open(my_submission_file) as submission_file:
    contents = submission_file.read()
    submission_timestamp_string = contents
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

  # compute the number of minutes (round up) in excess of the allowed time
  penalty_minutes = hours * 60 + minutes
  if seconds > 0:
    penalty_minutes = penalty_minutes+1
  penalty_minutes = penalty_minutes - allowed_minutes
  if penalty_minutes < 0:
    penalty_minutes = 0

  # compute the score (0 is full credit, positive numbers will apply a penalty)
  my_score = 0
  if penalty_minutes > 0:
    my_score = float(penalty_minutes) / (-1 * max_penalty)
  if my_score > 1:
    my_score = 1

  my_message = "version: " + str(active_version) +\
    "\nfirst access: " + str(first_access_timestamp) +\
    "\nsubmission: " + str(submission_timestamp) +\
    "\nallowed minutes: " + str(allowed_minutes) +\
    "\nduration: " + duration_string +\
    "\npenalty minutes: " + str(penalty_minutes) +\
    "\npenalty score: " + str(my_score)

  if my_score > 0:
    # red message when there is a penalty
    return_result(score=my_score,
                  message=my_message,
                  status='failure')
  else:
    # blue message when full credit
    return_result(score=my_score,
                  message=my_message,
                  status='information')


if __name__ == '__main__':
  do_the_grading()
