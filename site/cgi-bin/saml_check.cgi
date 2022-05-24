#!/usr/bin/env python3

"""
This script is called to check if a provided name is a valid
SAML username or not.
"""
import cgi
import json
import re

def main():
    print("Content-type: text/html")
    print()

    arguments = cgi.FieldStorage()
    valid = False
    if 'username' in arguments:
        valid = valid_username(arguments['username'].value)
    print(json.dumps({"valid": valid}))

def valid_username(username):
    if re.match('^[a-z]{2,6}[0-9]{0,2}$', username):
        return True
    else:
        return False

if __name__ == "__main__":
    main()
