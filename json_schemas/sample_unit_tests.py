import unittest
import schema_validator
import traceback
import jsonref
import os

# TODO: ADD pip3 install jsonschema migration!
# TODO: ADD pip3 install jsonref migration!
test_directories = ['sample', 'tutorial', 'development']

with open('complete_config_json_schema.json', 'r') as infile:
    SCHEMA = jsonref.load(infile)


class TestSchema(unittest.TestCase):
    def test_tutorial_configs(self):
        for directory in test_directories:
            for file in os.listdir(directory):
                test_file = os.path.join(directory, file)
                print('{0}: '.format(test_file))
                with self.subTest(msg='Testing {0}'.format(test_file),
                                  test_file=test_file):
                    with open(test_file, 'r') as infile:
                        config_json = jsonref.load(infile)
                    try:
                        schema_validator.complete_config_validator(config_json,
                                                                   SCHEMA,
                                                                   show_warnings=False)
                        success = True
                    except schema_validator.SubmittySchemaException as e:
                        e.print_human_readable_error()
                        success = False
                    except Exception:
                        traceback.print_exc()
                        success = False
                    self.assertTrue(success)
                print()
