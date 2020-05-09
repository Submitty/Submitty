"""
DEPRECATED FEATURES:
  This grader writes its results to stdout, a feature which is now deprecated and will be
  removed in a following release. Output should now be dumped to validation_results.json.
  See the updated_grader.py for example usage.

UNUSED FEATURE:
  This grader does not write a logfile to the instructor, a new feature released on Submitty
  in a recent patch. For an example, see updated_grader.py.


This file presents an example of a python custom validator for use in your Submitty assignment.

In this assignment, the student has been asked to randomly generate
n numbers, output them, and then output their sum.

To test that the output is truly random, we run the student program multiple
times. For each run, we make sure that:
1. The student produced n numbers.
2. They are correctly summed.
3. Between each pair of runs we make sure that the generated numbers aren't
   identical (that they are random)

To read this file, begin at the bottom with do_the_grading, then progress to
grade_a_single_file. If you are interested, you may also examine the return_result
functions and the get_actual_files helper function or you may just copy them.

If you are interested in parsing command line arguments, examine the parse_args function.

"""

import os
import sys
import argparse
import json
import traceback

"""
This is the agreed upon name for the input data to the custom validator.
This is identical to the validation blob for this validator, plus the
value 'testcase_prefix', which denotes the testcase that is to be processed.
"""
GLOBAL_INPUT_JSON_PATH = 'custom_validator_input.json'


def parse_args():
  """
  A simple argument parsing function.

  This function is not necessary, but can be used as a template to help process command line arguments.
  """
  parser = argparse.ArgumentParser()
  parser.add_argument("-n", "--numbers", required=True, help="The number of numbers we expect", type=int)
  return parser.parse_args()

"""
Helper functions for returning a score to the student.
"""

def return_result(score, message, status):
  """
  Return a non-error result to the student.
  """

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

def return_error(error_message):
  """
  This function should be used to return an error if the validator crashes.

  If this function is called, the student will receive a score of zero.
  """


  result = {
              # Status fail means that the validator failed to process the
              # student submission.
              'status':"fail",
              # A message to be output to help the instructor/student to debug.
              'message':error_message
           }
  # Print the result json to stdout so that it can be read by submitty.
  print(json.dumps(result, indent=4))
  # End the program, because we have returned a result.
  sys.exit(0)

def get_actual_files():
  """
  A helper function written to load in actual files.

  To find actual files, we look for all of the files listed in the
  'actual_file' section of this validator.
  """
  try:
    # Open the custom_validator_input.json that we specified in our config.
    with open(GLOBAL_INPUT_JSON_PATH) as json_file:
      testcase = json.load(json_file)
      # Grab the folder housing the files.
      prefix = testcase['testcase_prefix']
  except Exception as e:
    return_error('Could not open custom_validator_input.json')

  # There can be either one actual file (a string) or a list of actual files.

  # If there is only one actual file (a string)
  if isinstance(testcase['actual_file'], str):
    # The actual file is the prefix (test##) + the filename
    #  (e.g. test01/my_file.txt)
    actual_file = [os.path.join(prefix, testcase['actual_file']),]
    # Add the actual file to the actual file list.
    actual_files = list(actual_file)
  else:
    # If there are many actual files (a list of them), iterate over them and
    # append them all to the actual file list.
    actual_files = list()
    for file in testcase['actual_file']:
      # The actual file is the prefix (test##) + the filename
      #  (e.g. test01/my_file.txt)
      actual_files.append(os.path.join(prefix, file))
  # Return a list of all the actual files.
  return actual_files

def grade_a_single_file(file, number_of_numbers):
  """
  For a file and a number of numbers, see if they sum correctly.
  """
  data = list()
  try:
    with open(file) as f:
      # Read in all of the lines of the file (there is one number on each line)
      numbers = f.readlines()
    # Remove newlines/spaces from all lines of the file.
    numbers = [x.strip() for x in numbers]
    # The last line of the file is of the form "total = #" so we split with space as our delimiter.
    numbers[-1] = numbers[-1].split()

    # Make sure that the last line had 'total' in it.
    if not 'total' in numbers[-1]:
      return_result(score=0, message="ERROR: total is not included", status='failure')

    # The last line was of the form "total = #". We split earlier, and now we
    # remove everything but the number.
    numbers[-1] = numbers[-1][-1]
    # Convert all of the numbers we read in from string to int.
    numbers = [int(x) for x in numbers]

    # Make sure that the 0 to n-1th numbers sum to the nth number.
    if sum(numbers[:-1]) != numbers[-1]:
      # If they do not, return zero credit with an error message.
      return_result(score=0, message="ERROR: The numbers do not sum correctly", status='failure')
    elif len(numbers[:-1]) != number_of_numbers:
      # If they do sum correctly, make sure that we have the desired number of numbers.
      return_result(score=0, message="ERROR: Incorrect number of numbers ({0} instead of {1})".format(len(numbers[:-1]), number_of_numbers), status='failure')
  except Exception as e:
    return_result(score=0, message="ERROR: Could not open output file.",status='failure')
  # If no exception occurred and the numbers sum, return them so that we can do one last processing step.
  return numbers

def do_the_grading():
  """
  Process a number of runs of the student program to make sure that
    1) All runs resulted in a correct output.
    2) All runs were different (and therefore were likely random).
  """

  try:
    # Parse command line arguments. In this assignment, this is how we learn
    # how many numbers the student was supposed to sum together.
    args = parse_args()
  except Exception as e:
    # If we can't parse the command line arguments, we must have done something
    # wrong, so we'll return a failure message.
    return_error(message='ERROR: Incorrect arguments to custom validator')
  number_of_numbers = args.numbers
  
  # Grab all of the files we are supposed to check.
  actual_files = get_actual_files()

  # This variable will hold the numbers summed in the previous file. That way,
  # we will be able to check that they are different in the next run.
  prev_data = None

  # For every student file
  for file in actual_files:
    # Make sure that the output in the file sums correctly
    data = grade_a_single_file(file, number_of_numbers)
    # If we are on the first file, save the this output so that we can check that the next
    # run is different (random).
    if prev_data == None:
      prev_data = data
    else:
      # If two runs of the student program yield the same random output, then the program
      # is probably not actually random, so return partial credit
      if data == prev_data:
        return_result(score=0.6, message="ERROR: Program is not random.", status='failure')
  # If we make it all the way to the end, the student had the correct output and it was random,
  # so return full credit.
  return_result(score=1.0, message="Success: numbers summed correctly.", status='success')

if __name__ == '__main__':
  """
  If this script is invoked directly, call the "do_the_grading" function (above).
  """
  do_the_grading()
