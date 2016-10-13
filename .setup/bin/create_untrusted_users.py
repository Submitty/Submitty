#!/usr/bin/env python

import os
import pwd

for i in range(0, 60):
    user = "untrusted" + str(i).zfill(2)
    try:
        pwd.getpwnam(user)
    except KeyError:
        uuid = 900 + i
        os.system("addgroup {} --gid {}".format(user, uuid))
        os.system("adduser {} --home /tmp --no-create-home --uid {} --gid {} "
                  "--disabled-password --gecos 'untrusted'".format(user, uuid, uuid))
