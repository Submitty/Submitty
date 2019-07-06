"""For use in validating submitty configuration objects against json schemas."""


import json
from jsonschema import validate
import jsonref


class SubmittySchemaException(Exception):
    """An exception capable of printing helpful information about schema errors."""

    def __init__(self, config_json, schema, message, title, schema_error):
        """Use to aid in printing helpful information about schema errors."""
        super(SubmittySchemaException, self).__init__(schema_error.message)
        self.config_json = config_json
        self.schema = schema
        self.my_message = message
        self.title = title
        self.schema_message = schema_error.message

    def print_human_readable_error(self):
        """Use to print a human readable version of this exception."""
        print()
        print('{0}:'.format(self.my_message))
        if self.schema_message is not None:
            print(self.schema_message)
            print(("The portion of your configuration that caused "
                   "the error is:"))
            print(json.dumps(self.config_json, indent=4))


def complete_config_validator(j_, s_, show_warnings=True):
    """
    Validate a complete configuration against a schema.

    Given a complete config and a complete config schema, validate
    the complete config one piece at a time. On error, raise a
    SubmittySchemaException, which is capable of printing a
    human readable error message.
    """
    warn = show_warnings
    s_prop = s_['properties']
    try:
        validate_schema(j_, s_prop, 'autograding', 'global', warn=warn)
        validate_schema(j_, s_prop, 'autograding_method', 'global', warn=warn)
        validate_schema(j_, s_prop, 'container_options', 'global', warn=warn)
        validate_schema(j_, s_prop, 'resource_limits', 'global', warn=warn)
    except SubmittySchemaException:
        raise
    # test all of the global pieces of the config.
    testcases = j_["testcases"]
    t_s = s_['definitions']['testcase']
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
            except SubmittySchemaException:
                pass
        if success:
            continue

        # validate all of the different chunks of the testcase
        t_name = 'testcase_{0}'.format(testcase_num)
        try:
            validate_schema(t, p_s, 'dispatcher_actions', t_name, warn=warn)
            validate_schema(t, p_s, 'actions', t_name, warn=warn)
            validate_schema(t, p_s, 'points', t_name, warn=warn)
            validate_schema(t, p_s, 'type', t_name, warn=warn)
            validate_schema(t, p_s, 'pre_commands', t_name, warn=warn)
            validate_schema(t, p_s, 'single_port_per_container', t_name,
                            warn=warn)
            validate_schema(t, p_s, 'use_router', t_name, warn=warn)
            validate_schema(t, p_s, 'title', t_name, warn=warn)
            validate_schema(t, p_s, 'hidden', t_name, warn=warn)
            validate_schema(t, p_s, 'extra_credit', t_name, warn=warn)
            validate_schema(t, p_s, 'input_generation_commands', t_name,
                            warn=warn)
            validate_schema(t, p_s, 'executable_name', t_name, warn=warn)
            validate_schema(t, p_s, 'testcase_label', t_name, warn=warn)

        except Exception:
            raise
        containers = t['containers']
        # validate each container in the testcase
        for c in containers:
            try:
                validate_schema(c, c_schema, prefix=t_name, warn=warn)
            except SubmittySchemaException:
                raise

        validators = t['validation']
        validator_num = 0
        # Validate each validation object in the testcase
        for v in validators:
            try:
                validate_schema(v, abs_v_schema, prefix=t_name, warn=warn)
                validate_schema(v, spec_v_schema, prefix=t_name, warn=warn)
            except SubmittySchemaException:
                raise
            validator_num += 1
        testcase_num += 1

        try:
            validate_schema(t, t_s, prefix=t_name, warn=warn)
        except SubmittySchemaException:
            raise
    # Finally, validate the config as a whole.
    try:
        validate_schema(j_, s_, prefix='Your config json', warn=warn)
    except SubmittySchemaException:
        raise


def validate_schema(j_, s_, key='', prefix='', required=True, warn=False):
    """
    Validate a configuration against a schema.

    A function which validates a key within a config and a schema.
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
        raise SubmittySchemaException(j_,
                                      s_,
                                      ("ERROR: There is no specification for "
                                       "{0} in the schema. Please "
                                       "add a specification.").format(key),
                                      descriptive_title,
                                      None)
    if my_json_chunk is None:
        if warn:
            print(("WARNING: could not identify "
                   "{0} ({1})").format(descriptive_title, key))
        return
    try:
        # validate.
        validate(instance=my_json_chunk, schema=my_schema)
    except Exception as e:
        raise SubmittySchemaException(my_json_chunk,
                                      my_schema,
                                      ('ERROR: {0} was not properly '
                                       'formatted')
                                      .format(descriptive_title),
                                      descriptive_title,
                                      e)


def validate_complete_config_schema_using_filenames(config_path,
                                                    schema_path,
                                                    show_warnings=True):
    """
    Call validate_config after reading in config and schema files.

    Given a path to a complete configuration and a schema, this function
    loads both and validates the configuration against the schema. On
    failure, an exception is thrown, else, nothing is returned.
    """
    with open(schema_path, 'r') as infile:
        schema = jsonref.load(infile)

    with open(config_path, 'r') as infile:
        config_json = jsonref.load(infile)

    complete_config_validator(config_json, schema, show_warnings=show_warnings)
