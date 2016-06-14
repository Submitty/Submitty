#!/usr/bin/env python

"""
Given a filaname, try to open that file, which should contain a JSON object
containing a username and password, then test that against PAM printing out
a JSON object that is authenticated and is true or false
"""
import cgi
import cgitb; cgitb.enable() # for troubleshooting
import json
import os
import pam

success = "{'authenticated': true}"
fail = "{'authenticated': false}"

print "Content-type: text/html"
print

try:
    arguments = cgi.FieldStorage()
    # prevent a user from figuring out a way of passing a path instead of a filename
    f = os.path.basename(arguments['file'].value)
    with open("/tmp/pam/" + f, "r") as read_file:
        j = json.loads(read_file.read())
        p = pam.pam()
        if p.authenticate(j['username'], j['password']):
            print success
        else:
            print fail
except:
    print fail
