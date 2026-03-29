This fixture is intentionally broken.

It mirrors the multi-file `#include` structure used by
`more_autograding_examples/multifile_config_python_greeting`, but the
`broken_hidden_greeting_test.jsoninc` file is missing a comma inside the
validation object.

Use it when manually verifying that:

1. the autograding build log reports a JSON syntax error,
2. the reported line number points at the generated/preprocessed config, and
3. the UI exposes the generated config files needed to debug that error.
