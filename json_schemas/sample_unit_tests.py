import unittest
import schema_validator
import json
import os

with open('complete_config_json_schema.json','r') as infile:
  SCHEMA = json.load(infile)

test_files = [
    'complete_config_01_simple_python',
    'complete_config_02_simple_cpp',
    'complete_config_03_multipart',
    'complete_config_04_python_static_analysis',
    'complete_config_05_cpp_static_analysis',
    'complete_config_06_loop_types',
    'complete_config_07_loop_depth',
    'complete_config_08_memory_debugging',
    'complete_config_09_java_testing',
    'complete_config_10_java_coverage',
    'complete_config_11_resources',
    'complete_config_12_system_calls',
    'complete_config_13_cmake_compilation'
]

class TestSchema(unittest.TestCase):
    def test_validate_schema(self):
        for test_file in test_files:
            with self.subTest(msg='Testing validation of test_file', test_file=test_file):
                with open(os.path.join('tutorial_configs', '{0}.json'.format(test_file)), 'r') as infile:
                    config_json = json.load(infile)
                try:
                    schema_validator.submitty_validate_schemas(config_json, SCHEMA)
                    success = True
                except Exception as e:
                    print(e)
                    success = False
                self.assertTrue(success)
