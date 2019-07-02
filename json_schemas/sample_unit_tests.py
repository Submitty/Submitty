import unittest
import schema_validator
import traceback
import json
import os

# TODO: ADD pip3 install jsonschema migration!

test_directories = ['tutorial_configs', 'more_autograding_examples']

with open('complete_config_json_schema.json', 'r') as infile:
    SCHEMA = json.load(infile)


class TestSchema(unittest.TestCase):
    def test_tutorial_configs(self):
        for directory in test_directories:
            for file in os.listdir(directory):
                test_file = os.path.join(directory, file)
                print('{0}: '.format(test_file))
                print()
                with self.subTest(msg='Testing {0}'.format(test_file),
                                  test_file=test_file):
                    with open(test_file, 'r') as infile:
                        config_json = json.load(infile)
                    try:
                        schema_validator.complete_config_validator(config_json,
                                                                   SCHEMA, show_warnings=False)
                        success = True
                    except Exception:
                        traceback.print_exc()
                        success = False
                    self.assertTrue(success)
                print()
