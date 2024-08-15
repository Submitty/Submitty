import json
import subprocess
import datetime
from datetime import timedelta

pr_json = "gh pr list -L 1000 --json updatedAt,labels,number,comments,reviews"
terminal_output = subprocess.check_output(pr_json, shell=True, text=True)
json_output = json.loads(terminal_output)

eastern = datetime.timezone(datetime.timedelta(hours=-5))
today = datetime.datetime.now()
two_weeks = timedelta(weeks=2)
et_today = today.astimezone(eastern)

for json_data in json_output:
    updated_at_string = json_data['updatedAt']
    json_time = datetime.datetime.fromisoformat(updated_at_string.replace('Z', '+00:00'))
    et_time_update = json_time.astimezone(eastern)

    tdiff = et_today - et_time_update

    num = str(json_data['number'])
    already_abandoned = False
    for labels in json_data['labels']:
        if labels['name'] == 'Abandoned PR - Needs New Owner':
            already_abandoned = True

    approved = False
    for review in json_data['reviews']:
        if review["state"] == "APPROVED":
            approved = True
        if review["state"] == "CHANGES_REQUESTED":
            approved = False

    if tdiff > two_weeks and not already_abandoned and not approved:
        subprocess.run(['gh', 'pr', 'edit', num, '--add-label', 'Abandoned PR - Needs New Owner'])
    if approved and already_abandoned:
        subprocess.run(['gh', 'pr', 'edit', num, '--remove-label', 'Abandoned PR - Needs New Owner'])
