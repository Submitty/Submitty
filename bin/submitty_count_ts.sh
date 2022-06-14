#!/bin/bash

echo "here in submitty_count_ts"

/usr/bin/node /usr/local/submitty/SubmittyAnalysisToolsTS/dist/index.js "$@"

echo "finished running node ts"
