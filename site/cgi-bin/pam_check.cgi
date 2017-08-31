#!/usr/bin/env python3

"""
Given a filaname, try to open that file, which should contain a JSON object
containing a username and password, then test that against PAM printing out
a JSON object that is authenticated and is true or false
"""
import cgi
# If things are not working, then this should be enabled for better troubleshooting
# import cgitb; cgitb.enable()
import json
import os
import pam

print("Content-type: text/html")
print()

authenticated = False
try:
    arguments = cgi.FieldStorage()
    # prevent a user from figuring out a way of passing a path instead of a filename
    f = os.path.basename(arguments['file'].value)
    with open("/tmp/" + f, "r") as read_file:
        j = json.loads(read_file.read())
        p = pam.pam()
        authenticated = p.authenticate(j['username'], j['password'])
except:
    pass

print(json.dumps({"authenticated": authenticated}))
