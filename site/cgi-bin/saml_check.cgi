#!/usr/bin/env python3

"""
This script is called to check if a provided name is a valid
SAML username or not.
"""
import cgi
import json
import re
import os
import subprocess

VALIDATE_USERNAME_SCRIPT='/usr/local/submitty/config/saml/validate'

def main():
    print("Content-type: text/html")
    print()

    arguments = cgi.FieldStorage()
    valid = False
    if 'username' in arguments:
        valid = valid_username(arguments['username'].value)
    print(json.dumps({"valid": valid}))

def valid_username(username):
    if os.path.exists(VALIDATE_USERNAME_SCRIPT):
        result = subprocess.run([VALIDATE_USERNAME_SCRIPT, username], capture_output=True, text=True)
        if result.stdout.strip() == 'valid':
            return True
        else:
            return False

if __name__ == "__main__":
    main()
