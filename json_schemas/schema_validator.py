import json
from jsonschema import validate
import traceback
import argparse


def parse_arguments():
    """
    Parses command-line arguments to acquire the config to be tested
    and the config to validate it against.
    """
    parser = argparse.ArgumentParser(description='The Submitty config.json\
                                                  validator.')
    parser.add_argument('--config', required=True,
                        help='The path to the configuration to be validated')
    parser.add_argument('--schema', required=True,
                        help='The path to the schema to be validated against')
    return parser.parse_args()


def complete_config_validator(j_, s_):
    """
    Given a complete config and a complete config schema, validate
    the complete config piecemeal
    """
    s_prop = s_['properties']
    submitty_validate_schema(j_, s_prop, 'autograding_options', 'global')
    submitty_validate_schema(j_, s_prop, 'autograding_method', 'global')
    submitty_validate_schema(j_, s_prop, 'container_options', 'global')
    submitty_validate_schema(j_, s_prop, 'resource_limits', 'global')

    # test all of the global pieces of the config.
    testcases = j_["testcases"]
    p_s = s_['definitions']['testcase']['properties']
    sub_limit_schema = s_['definitions']['submission_limit']
    c_schema = s_['definitions']['container']
    abs_v_schema = s_['definitions']['abstract_validation_object']
    spec_v_schema = s_['definitions']['validator_definitions']

    testcase_num = 1
    # validate each testcase one piece at a time.
    for t in testcases:
        try:
            # A hack because a testcase can be either a submission_limit or a
            # regular testcase. We check sub-limit first and short circuit.
            submitty_validate_schema(t, sub_limit_schema, required=False)
            testcase_num += 1
            continue
        except Exception:
            pass

        # validate all of the different chunks of the testcase
        t_name = 'testcase_{0}'.format(testcase_num)
        submitty_validate_schema(t, p_s, 'dispatcher_actions', t_name)
        submitty_validate_schema(t, p_s, 'actions', t_name)
        submitty_validate_schema(t, p_s, 'points', t_name)
        submitty_validate_schema(t, p_s, 'type', t_name)
        submitty_validate_schema(t, p_s, 'pre_commands', t_name)
        submitty_validate_schema(t, p_s, 'single_port_per_container', t_name)
        submitty_validate_schema(t, p_s, 'use_router', t_name)
        submitty_validate_schema(t, p_s, 'title', t_name)

        containers = t['containers']
        # validate each container in the testcase
        for c in containers:
            submitty_validate_schema(c, c_schema, prefix=t_name)

        validators = t['validation']
        validator_num = 0
        # Validate each validation object in the testcase
        for v in validators:
            submitty_validate_schema(v, abs_v_schema, prefix=t_name)
            submitty_validate_schema(v, spec_v_schema, prefix=t_name)
            validator_num += 1

        testcase_num += 1
    # Finally, validate the config as a whole.
    submitty_validate_schema(j_, s_, prefix='whole schema')


def submitty_validate_schema(j_, s_, key='', prefix='', required=True):
    """
    We validate a key within a config and a schema.
    If no key is given, we evaluate the whole config or schema.
    """
    descriptive_title = '{0} {1}'.format(prefix, key)
    my_json_chunk = j_.get(key, None) if j_ is not None else j_
    my_schema = s_.get(key, None) if s_ is not None else s_

    # If attempting to validate an item not specified in the schema, fail.
    if my_schema is None:
        raise Exception(("ERROR: There is no specification for "
                         "{0} in the schema. Please "
                         "add a specification.").format(key))

    if my_json_chunk is None:
        print(('WARNING: could not identify ',
               '{0} ({1})'.format(descriptive_title, key)))
        return
    try:
        # validate.
        validate(instance=my_json_chunk, schema=my_schema)
    except Exception:
        # If the item was required, print an error. Otherwise,
        # the exception will be suppressed later, so don't be loud.
        if required:
            traceback.print_exc()
        if descriptive_title != '':
            print(('ERROR: {0} was not '.format(descriptive_title),
                   'properly formatted.'.format(descriptive_title)))
        raise


def main():

    args = parse_arguments()

    with open(args.schema, 'r') as infile:
        schema = json.load(infile)

    with open(args.config, 'r') as infile:
        config_json = json.load(infile)

    try:
        complete_config_validator(config_json, schema)
        print()
        print('SUCCESS: your configuration conforms to the Submitty\
              configuration JSON schema.')
    except Exception as e:
        print('ERROR: {0}'.format(e))
        pass


if __name__ == '__main__':
    main()
