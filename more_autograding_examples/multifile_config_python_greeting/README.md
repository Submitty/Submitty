This example shows a small Python gradeable whose `config.json` is split
across multiple files with `#include`.

The top-level file in `config/config.json` keeps the high-level structure,
while `config/includes/` holds the reusable fragments for the autograding
paths and individual testcases.

Submitty also accepts C/C++-style comments in `config.json`, because the file
is preprocessed before it is parsed.
