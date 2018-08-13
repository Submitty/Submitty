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
import pam

print("Content-type: text/html")
print()

arguments = cgi.FieldStorage(environ={'REQUEST_METHOD':'POST'})
p = pam.pam()
print(json.dumps({"authenticated": p.authenticate(arguments['username'].value, arguments['password'].value)}))
