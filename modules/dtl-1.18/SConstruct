import os

# install script

prefix  = ARGUMENTS.get('prefix', '/usr/local')
headers = Glob('dtl/*.hpp')
Alias('install', Install(os.path.join(prefix, 'dtl', 'include'), headers))
