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
VALID=0
INVALID=1
ALWAYS_VALID=2

def main():
    print("Content-type: text/html")
    print()

    arguments = cgi.FieldStorage()
    usernames = json.loads(arguments['usernames'].value)
    valid_usernames = []
    always_valid = False
    for username in usernames:
        check = valid_username(username)
        if check == ALWAYS_VALID:
            always_valid = True
            break
        if check == VALID:
            valid_usernames.append(username)
    if always_valid:
        print(json.dumps({"always_valid": True}))
    else:
        print(json.dumps({"usernames": valid_usernames}))

def valid_username(username):
    if os.path.exists(VALIDATE_USERNAME_SCRIPT):
        result = subprocess.run([VALIDATE_USERNAME_SCRIPT, username], capture_output=True, text=True)

        if result.stdout.strip() == 'valid':
            return VALID
        else:
            return INVALID
    else:
        return ALWAYS_VALID

if __name__ == "__main__":
    main()
