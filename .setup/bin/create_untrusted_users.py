#!/usr/bin/env python3
"""
This creates untrusted users for the system starting at untrusted00 and going up to untrusted59.
We use these users to then run our autograding tests against student submissions in a tmp directory.
These users have very little permissions and access to the system at large.
"""
import os
import pwd


for i in range(0, 60):
    user = "untrusted" + str(i).zfill(2)
    try:
        pwd.getpwnam(user)
    except KeyError:
        uuid = 900 + i
        os.system("addgroup {} --gid {}".format(user, uuid))
        os.system("useradd --home /tmp -M --uid {} --gid {} "
                  "-c 'untrusted' {}".format(uuid, uuid, user))
