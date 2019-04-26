import json
from jsonschema import validate
import os
import traceback
import argparse

'''
Grab the config and the schema to be tested if called from the command line.
'''
def parse_arguments():
  parser = argparse.ArgumentParser(description='The Submitty config.json validator.')
  parser.add_argument('--config', required=True, help='The path to the configuration to be validated')
  parser.add_argument('--schema', required=True, help='The path to the schema to be validated against')
  return parser.parse_args()


'''
Validates a config json piece by piece.
'''
def submitty_validate_schemas(config_json, schema):
  submitty_validate_schema(config_json, schema['properties'], 'autograding', 'autograding', descriptive_title='global autograding block')
  submitty_validate_schema(config_json, schema['properties'], 'autograding_method', 'autograding_method', descriptive_title='global autograding method')
  submitty_validate_schema(config_json, schema['properties'], 'container_options', 'container_options', descriptive_title='global container options')
  submitty_validate_schema(config_json, schema['properties'], 'resource_limits', 'resource_limits', descriptive_title='global resource limits')

  #test all of the global pieces of the config.
  testcases = config_json["testcases"]
  testcase_schema = schema['definitions']['testcase']
  testcase_properties_schema = schema['definitions']['testcase']['properties']
  submission_limit_schema = schema['definitions']['submission_limit']
  container_schema = schema['definitions']['container']
  abstract_validation_object_schema = schema['definitions']['abstract_validation_object']
  specific_validation_object_schema = schema['definitions']['validator_definitions']

  testcase_num = 1
  #validate each testcase one piece at a time.
  for testcase in testcases:

    try:
      #A hack because a testcase can be either a submission_limit or a regular testcase. We check submission limit first then short circuit.
      submitty_validate_schema(testcase, submission_limit_schema, None, None, descriptive_title='', required=False)
      testcase_num += 1
      continue
    except: 
      pass

    #validate all of the different chunks of the testcase
    submitty_validate_schema(testcase, testcase_properties_schema, 'dispatcher_actions', 'dispatcher_actions', descriptive_title='testcase {0} dispatcher actions'.format(testcase_num))
    submitty_validate_schema(testcase, testcase_properties_schema, 'actions', 'actions', descriptive_title='testcase {0} graphics actions'.format(testcase_num))
    submitty_validate_schema(testcase, testcase_properties_schema, 'points', 'points', descriptive_title='testcase {0} points'.format(testcase_num))
    submitty_validate_schema(testcase, testcase_properties_schema, 'type', 'type', descriptive_title='testcase {0} testcase type'.format(testcase_num))
    submitty_validate_schema(testcase, testcase_properties_schema, 'pre_commands', 'pre_commands', descriptive_title='testcase {0} pre-commands'.format(testcase_num))
    submitty_validate_schema(testcase, testcase_properties_schema, 'single_port_per_container', 'single_port_per_container', descriptive_title='testcase {0} single_port_per_container option'.format(testcase_num))
    submitty_validate_schema(testcase, testcase_properties_schema, 'use_router', 'use_router', descriptive_title='testcase {0} use_router option'.format(testcase_num))
    submitty_validate_schema(testcase, testcase_properties_schema, 'title', 'title', descriptive_title='testcase {0} title'.format(testcase_num))

    containers = testcase['containers']
    container_num = 0
    #validate each container in the testcase
    for container in containers:
      submitty_validate_schema(container, container_schema, None, None, descriptive_title='testcase {0} container {1}'.format(testcase_num, container_num))
      container_num +=1

    validators = testcase['validation']
    validator_num = 0
    #validate each validation object in the testcase
    for validator in validators:
      submitty_validate_schema(validator, abstract_validation_object_schema, None, None, descriptive_title='testcase {0} validator {1} (generic)'.format(testcase_num, validator_num))
      submitty_validate_schema(validator, specific_validation_object_schema, None, None, descriptive_title='testcase {0} validator {1} (specific)'.format(testcase_num, validator_num))
      validator_num += 1

    testcase_num+=1
  #Finally, validate the config as a whole.
  submitty_validate_schema(config_json, schema, None, None, descriptive_title='whole schema')

#We validate a key within a config and a schema. If no key is given, we evaluate the whole config or schema.
def submitty_validate_schema(config_json, schema, config_json_key, schema_key, descriptive_title='', required=True):
  
  my_json_chunk = config_json.get(config_json_key, None) if config_json_key != None else config_json
  my_schema = schema.get(schema_key, None) if schema_key != None else schema

  #If we are attempting to validate an item not specified in the schema, fail.
  if my_schema == None:
    raise Exception('ERROR: There is no specification for {0} in the schema. Please add a specification.'.format(schema_key))

  if my_json_chunk == None:
    print('WARNING: could not identify {0} ({1})'.format(descriptive_title, config_json_key))
    return
 
  try:
    #validate.
    validate(instance=my_json_chunk, schema=my_schema)
  except Exception as e:
    #If the item was required, print an error. Otherwise, the exception will be suppressed later, so don't be loud. 
    if required:
      traceback.print_exc()
    if descriptive_title != '':
      print('ERROR: {0} was not properly formatted.'.format(descriptive_title))
    raise Exception(e.message)


def main():

  args = parse_arguments()

  with open(args.schema,'r') as infile:
    schema = json.load(infile)

  with open(args.config) as infile:
    config_json = json.load(infile)

  try:
    submitty_validate_schemas(config_json, schema)
    print()
    print('SUCCESS: your configuration conforms to the Submitty configuration JSON schema.')
  except Exception as e:
    print('ERROR: {0}'.format(e))
    pass

if __name__ == '__main__':
  main()