#!/usr/bin/python3
import sys
sys.path.append("/usr/local/submitty/SubmittyAnalysisTools/")
import lang.parser
paths = lang.parser.leaf_paths(lang.parser.python(open(sys.argv[1]).read()))
print(max([len([x for x in path if x.name in ["for", "while"]]) for path in paths]))
