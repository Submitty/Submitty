#!/usr/bin/env python3

"""
Given a filaname, try to open that file, which should contain a JSON object
containing a username and password, then test that against PAM printing out
a JSON object that is authenticated and is true or false
"""
import cgi
# If things are not working, then this should be enabled for better troubleshooting
import cgitb; cgitb.enable()
import json
import os
import pam

print("Content-type: text/html")
print()

current_dir = os.path.dirname(os.path.realpath(__file__))
with open(os.path.join(current_dir, '..', '..', 'config', 'submitty.json'), 'r') as open_file:
    info = json.load(open_file)
tmp_path = os.path.join(info['submitty_data_dir'], 'tmp', 'pam')

authenticated = False
try:
    arguments = cgi.FieldStorage()
    # prevent a user from figuring out a way of passing a path instead of a filename
    f = os.path.basename(arguments['file'].value)
    with open(os.path.join(tmp_path, f), "r") as read_file:
        j = json.loads(read_file.read())
        p = pam.pam()
        authenticated = p.authenticate(j['username'], j['password'])
except:
    pass

print(json.dumps({"authenticated": authenticated}))
