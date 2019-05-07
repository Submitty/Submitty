import os
import sys
import argparse
import json
import traceback

# This is the agreed upon name for the input data to the custom validator.
# This is identical to the validation blob for this validator, plus the
# value 'testcase_prefix', which denotes the testcase that is to be processed. 
GLOBAL_INPUT_JSON_PATH = 'custom_validator_input.json'

'''
A simple argument parsing function. This is not necessary, but can be used 
as a template to help process command line arguments.
'''
def parse_args():
  parser = argparse.ArgumentParser()
  parser.add_argument("-n", "--numbers", required=True, help="The number of numbers we expect", type=int)
  return parser.parse_args()

'''
A helper function written to load in actual files. 
To find actual files, we look for all of the files
listed in the 'actual_file' section of this validator.
'''
def get_actual_files():
  try:
    with open(GLOBAL_INPUT_JSON_PATH) as json_file:  
      tc = json.load(json_file)
      #Grab the folder housing the files.
      prefix = tc['testcase_prefix']
  except Exception as e:
    return_error('Could not custom_validator_input.json')

  # There can be either one actual file (a string) or 
  # a list of actual files.
  if isinstance(tc['actual_file'], str):
    actual_files = list([os.path.join(prefix, tc['actual_file']),])
  else:
    actual_files = list()
    for file in tc['actual_file']:
      actual_files.append(os.path.join(prefix, file))
  return actual_files

'''
For a file and a number of numbers, see if they sum correctly.
'''
def do_the_grading(file, number_of_numbers):
  data = list()
  try:
    with open(file) as f:
      numbers = f.readlines()
    numbers = [x.strip() for x in numbers]
    numbers[-1] = numbers[-1].split()

    if not 'total' in numbers[-1]:
      return_result(score=0, message="ERROR: total is not included", status='failure')

    numbers[-1] = numbers[-1][-1]
    numbers = [int(x) for x in numbers]

    if sum(numbers[:-1]) != numbers[-1]:
      return_result(score=0, message="ERROR: The numbers do not sum correctly", status='failure')
    elif len(numbers[:-1]) != number_of_numbers:
      return_result(score=0, message="ERROR: Incorrect number of numbers ({0} instead of {1})".format(len(numbers[:-1]), number_of_numbers), status='failure')
  except Exception as e:
    return_result(score=0, message="ERROR: Could not open output file.",status='failure')
  return numbers

#This function should be used to return grading results to a student.
def return_result(score,message,status):
  # Create response to student. Status success means the grader worked as intended (no validator failures)
  # Data contains the students score (range from 0 to 1), a message to the student, and the status (color) for 
  # the message. Status can be 'information', 'failure', 'warning', or 'success'.  
  result = {
              'status' : "success",
              'data': {
                        'score' : score, 
                        'message' : message,
                        'status':status
                      }
          }
  #print the json to stdout so that it can be read by submitty.
  print(json.dumps(result, indent=4))
  sys.exit(0)

#This function should be used to return an error message if the validator crashes.
def return_error(error_message):
  # Create response to student. Status failure means the validator failed to process the student submission.
  # Message contains an error message to be output for instructor/student debugging. This submission will
  # receive a score of zero.  
  result = {
              'status':"fail",
              'message':error_message
           }
  print(json.dumps(result, indent=4))
  sys.exit(0)

def main():
  try:
    #Parse command line arguments
    args = parse_args()
  except Exception as e:
    #If we can't parse the command line arguments, fail validation.
    return_error(message='ERROR: Incorrect arguments to custom validator')
  number_of_numbers = args.numbers
  
  #Grab all of the actual files specified for this validator.
  actual_files = get_actual_files()

  #This helps us see if the student's code is actually random.
  prev_data = None

  #For every student file
  for file in actual_files:
    #Make sure that the output is good.
    data = do_the_grading(file, number_of_numbers)
    if prev_data == None:
      prev_data = data
    else:
      # If two runs of the student program yield the same random output, then the program
      # is probably not actually random.
      if data == prev_data:
        return_result(score=0.6, message="ERROR: Program is not random.", status='failure')
  #If we make it all the way to the end, the student had the correct output.
  return_result(score=1.0, message="Success: numbers summed correctly.", status='success')

if __name__ == '__main__':
  main()
