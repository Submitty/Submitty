import unittest
import schema_validator as submitty_json_validator
import json
import os 

with open('complete_config_json_schema.json','r') as infile:
  SCHEMA = json.load(infile)

class TestSchema(unittest.TestCase):

  def test_simple_python(self):
    config_name = 'complete_config_01_simple_python.json'
    print('Testing {0}'.format(config_name))
    with open(os.path.join('tutorial_configs', config_name), 'r') as infile:
      config_json = json.load(infile)

    try:
      submitty_json_validator.submitty_validate_schemas(config_json,SCHEMA)
      success = True
    except Exception as e:
      print(e)
      success = False

    self.assertTrue(success)


  def test_simple_cpp(self):
    config_name = 'complete_config_02_simple_cpp.json'
    print('Testing {0}'.format(config_name))
    with open(os.path.join('tutorial_configs', config_name), 'r') as infile:
      config_json = json.load(infile)

    try:
      submitty_json_validator.submitty_validate_schemas(config_json,SCHEMA)
      success = True
    except Exception as e:
      print(e)
      success = False

    self.assertTrue(success)

  def test_multipart(self):
    config_name = 'complete_config_03_multipart.json'
    print('Testing {0}'.format(config_name))
    with open(os.path.join('tutorial_configs', config_name), 'r') as infile:
      config_json = json.load(infile)

    try:
      submitty_json_validator.submitty_validate_schemas(config_json,SCHEMA)
      success = True
    except Exception as e:
      print(e)
      success = False

    self.assertTrue(success)

  def test_python_static_analysis(self):
    config_name = 'complete_config_04_python_static_analysis.json'
    print('Testing {0}'.format(config_name))
    with open(os.path.join('tutorial_configs', config_name), 'r') as infile:
      config_json = json.load(infile)

    try:
      submitty_json_validator.submitty_validate_schemas(config_json,SCHEMA)
      success = True
    except Exception as e:
      print(e)
      success = False

    self.assertTrue(success)

  def test_cpp_static_analysis(self):
    config_name = 'complete_config_05_cpp_static_analysis.json'
    print('Testing {0}'.format(config_name))
    with open(os.path.join('tutorial_configs', config_name), 'r') as infile:
      config_json = json.load(infile)

    try:
      submitty_json_validator.submitty_validate_schemas(config_json,SCHEMA)
      success = True
    except Exception as e:
      print(e)
      success = False

    self.assertTrue(success)

  def test_loop_types(self):
    config_name = 'complete_config_06_loop_types.json'
    print('Testing {0}'.format(config_name))
    with open(os.path.join('tutorial_configs', config_name), 'r') as infile:
      config_json = json.load(infile)

    try:
      submitty_json_validator.submitty_validate_schemas(config_json,SCHEMA)
      success = True
    except Exception as e:
      print(e)
      success = False

    self.assertTrue(success)

  def test_loop_depth(self):
    config_name = 'complete_config_07_loop_depth.json'
    print('Testing {0}'.format(config_name))
    with open(os.path.join('tutorial_configs', config_name), 'r') as infile:
      config_json = json.load(infile)

    try:
      submitty_json_validator.submitty_validate_schemas(config_json,SCHEMA)
      success = True
    except Exception as e:
      print(e)
      success = False

    self.assertTrue(success)

  def test_memory_debugging(self):
    config_name = 'complete_config_08_memory_debugging.json'
    print('Testing {0}'.format(config_name))
    with open(os.path.join('tutorial_configs', config_name), 'r') as infile:
      config_json = json.load(infile)

    try:
      submitty_json_validator.submitty_validate_schemas(config_json,SCHEMA)
      success = True
    except Exception as e:
      print(e)
      success = False

    self.assertTrue(success)

  def test_java_testing(self):
    config_name = 'complete_config_09_java_testing.json'
    print('Testing {0}'.format(config_name))
    with open(os.path.join('tutorial_configs', config_name), 'r') as infile:
      config_json = json.load(infile)

    try:
      submitty_json_validator.submitty_validate_schemas(config_json,SCHEMA)
      success = True
    except Exception as e:
      print(e)
      success = False

    self.assertTrue(success)

  def test_java_coverage(self):
    config_name = 'complete_config_10_java_coverage.json'
    print('Testing {0}'.format(config_name))
    with open(os.path.join('tutorial_configs', config_name), 'r') as infile:
      config_json = json.load(infile)

    try:
      submitty_json_validator.submitty_validate_schemas(config_json,SCHEMA)
      success = True
    except Exception as e:
      print(e)
      success = False

    self.assertTrue(success)

  def test_resources(self):
    config_name = 'complete_config_11_resources.json'
    print('Testing {0}'.format(config_name))
    with open(os.path.join('tutorial_configs', config_name), 'r') as infile:
      config_json = json.load(infile)

    try:
      submitty_json_validator.submitty_validate_schemas(config_json,SCHEMA)
      success = True
    except Exception as e:
      print(e)
      success = False

    self.assertTrue(success)

  def test_system_calls(self):
    config_name = 'complete_config_12_system_calls.json'
    print('Testing {0}'.format(config_name))
    with open(os.path.join('tutorial_configs', config_name), 'r') as infile:
      config_json = json.load(infile)

    try:
      submitty_json_validator.submitty_validate_schemas(config_json,SCHEMA)
      success = True
    except Exception as e:
      print(e)
      success = False

    self.assertTrue(success)

  def test_cmake_compilation(self):
    config_name = 'complete_config_13_cmake_compilation.json'
    print('Testing {0}'.format(config_name))
    with open(os.path.join('tutorial_configs', config_name), 'r') as infile:
      config_json = json.load(infile)

    try:
      submitty_json_validator.submitty_validate_schemas(config_json,SCHEMA)
      success = True
    except Exception as e:
      print(e)
      success = False

    self.assertTrue(success)