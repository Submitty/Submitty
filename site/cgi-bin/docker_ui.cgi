#!/usr/bin/env python3

import cgi
import json

print("Content-type: text/html")
print()



def collectDockerInfo():

    return {"success" : True, "error": False}


#right now this is our only job
response = collectDockerInfo()

print(json.dumps(response))
