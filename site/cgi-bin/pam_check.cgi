#!/usr/bin/env python3

"""
This script is called via POST passing in a username and password which is
then checked against the system PAM. Depending on how PAM is configured,
you will need to give submitty_cgi user any additional permissions it needs
to access files (e.g. if using default setup, submitty_cgi needs to be in
shadow group to access /etc/shadow). The script returns with a JSON object
contained a singular key "authenticated" which is either True or False depending
on if:
- Username and Password were present in the POST payload
- PAM returns true for the authentication request
"""
import cgi
# If things are not working, then this should be enabled for better troubleshooting
# import cgitb; cgitb.enable()
import json
import pam

print("Content-type: text/html")
print()

arguments = cgi.FieldStorage(environ={'REQUEST_METHOD':'POST'})
authenticated = False
if 'username' in arguments and 'password' in arguments:
    p = pam.pam()
    authenticated = p.authenticate(arguments['username'].value, arguments['password'].value)
print(json.dumps({"authenticated": authenticated}))
