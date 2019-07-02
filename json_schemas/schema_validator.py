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
    parser.add_argument('-w', '--show_warnings', required=False,
                        action='store_true', help='Display warnings')
    return parser.parse_args()


def complete_config_validator(j_, s_, show_warnings=True):
    """
    Given a complete config and a complete config schema, validate
    the complete config piecemeal
    """
    warn = show_warnings
    s_prop = s_['properties']
    validate_schema(j_, s_prop, 'autograding', 'global', warn=warn)
    validate_schema(j_, s_prop, 'autograding_method', 'global', warn=warn)
    validate_schema(j_, s_prop, 'container_options', 'global', warn=warn)
    validate_schema(j_, s_prop, 'resource_limits', 'global', warn=warn)

    # test all of the global pieces of the config.
    testcases = j_["testcases"]
    p_s = s_['definitions']['testcase']['properties']
    sub_limit_schema = s_['definitions']['submission_limit']
    filecheck_schema = s_['definitions']['filecheck']
    c_schema = s_['definitions']['container']
    abs_v_schema = s_['definitions']['abstract_validation_object']
    spec_v_schema = s_['definitions']['validator_definitions']

    testcase_num = 1
    # validate each testcase one piece at a time.
    for t in testcases:
        # A hack because a testcase can be either a submission_limit, a
        # filecheck, or a regular testcase. We check sub-limit first and
        # short circuit.
        success = False
        for extra_schema in [sub_limit_schema, filecheck_schema]:
            try:
                validate_schema(t, extra_schema, required=False, warn=warn)
                testcase_num += 1
                success = True
            except Exception:
                pass
        if success:
            continue

        # validate all of the different chunks of the testcase
        t_name = 'testcase_{0}'.format(testcase_num)
        validate_schema(t, p_s, 'dispatcher_actions', t_name, warn=warn)
        validate_schema(t, p_s, 'actions', t_name, warn=warn)
        validate_schema(t, p_s, 'points', t_name, warn=warn)
        validate_schema(t, p_s, 'type', t_name, warn=warn)
        validate_schema(t, p_s, 'pre_commands', t_name, warn=warn)
        validate_schema(t, p_s, 'single_port_per_container', t_name, warn=warn)
        validate_schema(t, p_s, 'use_router', t_name, warn=warn)
        validate_schema(t, p_s, 'title', t_name, warn=warn)
        containers = t['containers']
        # validate each container in the testcase
        for c in containers:
            validate_schema(c, c_schema, prefix=t_name, warn=warn)

        validators = t['validation']
        validator_num = 0
        # Validate each validation object in the testcase
        for v in validators:
            validate_schema(v, abs_v_schema, prefix=t_name, warn=warn)
            validate_schema(v, spec_v_schema, prefix=t_name, warn=warn)
            validator_num += 1

        testcase_num += 1
    # Finally, validate the config as a whole.
    validate_schema(j_, s_, prefix='whole schema', warn=warn)


def validate_schema(j_, s_, key='', prefix='', required=True, warn=False):
    """
    We validate a key within a config and a schema.
    If no key is given, we evaluate the whole config or schema.
    """
    descriptive_title = '{0} {1}'.format(prefix, key)
    if key != '':
        my_json_chunk = j_.get(key, None) if j_ is not None else j_
        my_schema = s_.get(key, None) if s_ is not None else s_
    else:
        my_json_chunk = j_
        my_schema = s_
    # If attempting to validate an item not specified in the schema, fail.
    if my_schema is None:
        raise Exception(("ERROR: There is no specification for "
                         "{0} in the schema. Please "
                         "add a specification.").format(key))

    if my_json_chunk is None:
        if warn:
            print(("WARNING: could not identify "
                   "{0} ({1})").format(descriptive_title, key))
        return
    try:
        # validate.
        validate(instance=my_json_chunk, schema=my_schema)
    except Exception:
        if descriptive_title.strip() != '':
            print(('ERROR: {0} was not '
                   'properly formatted.').format(descriptive_title))
        raise


def main():

    args = parse_arguments()
    with open(args.schema, 'r') as infile:
        schema = json.load(infile)

    with open(args.config, 'r') as infile:
        config_json = json.load(infile)

    try:
        complete_config_validator(config_json, schema,
                                  show_warnings=args.show_warnings)
        print()
        print(("SUCCESS: your configuration conforms to the Submitty"
               "configuration JSON schema."))
    except Exception:
        traceback.print_exc()


if __name__ == '__main__':
    main()
