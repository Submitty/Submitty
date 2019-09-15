"""Autograde the iclicker remote submission."""
import os
import sys
import json


GLOBAL_INPUT_JSON_PATH = 'custom_validator_input.json'


def get_tc_json():
    """Get the json for this testcase."""
    try:
        with open(GLOBAL_INPUT_JSON_PATH) as json_file:
            tc = json.load(json_file)
    except Exception:
        return_error('Could not custom_validator_input.json')
    return tc


def get_actual_files():
    """Load the actual files.

    To find actual files, we look for all of the files
    listed in the 'actual_file' section of this validator.
    """
    try:
        with open(GLOBAL_INPUT_JSON_PATH) as json_file:
            tc = json.load(json_file)
            # Grab the folder housing the files.
            prefix = tc['testcase_prefix']
    except Exception:
        return_error('Could not custom_validator_input.json')

    # There can be either one actual file (a string) or
    # a list of actual files.
    if isinstance(tc['actual_file'], str):
        actual_files = list([os.path.join(prefix, tc['actual_file']), ])
    else:
        actual_files = list()
    for file in tc['actual_file']:
        actual_files.append(os.path.join(prefix, file))
    return actual_files


def return_result(score, message, status):
    """Create response to student.

    This function should be used to return grading results to a student.

    Status success means the grader worked as intended (no validator
    failures) Data contains the students score (range from 0 to 1), a
    message to the student, and the status (color) for the
    message. Status can be 'information', ' 'warning', or 'success'.
    """
    result = {
        'status': "success",
        'data': {
            'score': score,
            'message': message,
            'status': status
        }
    }

    # print the json to stdout so that it can be read by submitty.
    print(json.dumps(result, indent=4))
    sys.exit(0)


def return_error(error_message):
    """Create response to student.

    This function should be used to return an error message if the validator crashes.

    Status failure means the validator failed to process the student
    submission.  Message contains an error message to be output for
    instructor/student debugging. This submission will receive a score of zero.
    """
    result = {
      'status': "fail",
      'message': error_message
    }
    print(json.dumps(result, indent=4))
    sys.exit(0)


def main():
    """Do the main thing."""
    actual_files = get_actual_files()
    for file in actual_files:
        try:
            with open(file) as f:
                iclicker_string = f.read()
                iclicker_string = iclicker_string.replace('\n', '')
                iclicker_ids = iclicker_string.split(',')

                for my_id in iclicker_ids:
                    if "T24" in my_id:
                        return_result(score=0,
                                      message="ERROR: '" + str(my_id) +
                                              "' looks like model number",
                                      status='failure')
                    if len(my_id) != 8:
                        return_result(score=0,
                                      message="ERROR: '" + str(my_id) +
                                              "' is not 8 digits",
                                      status='failure')

                return_result(score=1, message="success!", status='success')

        except Exception:
            return_result(score=0,
                          message="ERROR: Could not open output file."+str(file),
                          status='failure')


if __name__ == '__main__':
    main()
