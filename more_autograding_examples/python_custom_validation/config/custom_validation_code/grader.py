import os
import sys
import argparse
import json
import traceback

def parse_args():
  parser = argparse.ArgumentParser()
  parser.add_argument("-n", "--numbers", help="The number of numbers we expect", type=int)
  parser.add_argument("-a", "--actual_files", nargs='+', help="The files to process")
  return parser.parse_args()

def load_file(file, number_of_numbers):
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

def return_result(score,message,status):
  print(json.dumps({'status':"success",'data':{'score':score, 'message':message,'status':status}}, indent=4))
  sys.exit(0)

def return_error(error_message):
  json_str = json.dumps({'status':"fail",'message':error_message})
  print(json_str)
  sys.exit(0)

def main():
  try:
    args = parse_args()
  except Exception as e:
    return_error(message='ERROR: Incorrect arguments to custom validator')
  actual_files = args.actual_files
  number_of_numbers = args.numbers
  
  prev_data = None

  for file in actual_files:
    data = load_file(file, number_of_numbers)
    if prev_data == None:
      prev_data = data
    else:
      if data == prev_data:
        return_result(score=0.6, message="ERROR: Program is not random.", status='failure')

  return_result(score=1.0, message="Success: numbers summed correctly.", status='success')

if __name__ == '__main__':
  main()
