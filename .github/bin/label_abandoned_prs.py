import json
import subprocess
import datetime
from datetime import timedelta

pr_json = "gh pr list --json updatedAt,labels,number,comments,reviews"
terminal_output = subprocess.check_output(pr_json, shell=True, text=True)
json_output = json.loads(terminal_output)

inactive_comment = "This PR has been inactive (no commits and no review comments)"\
    " for 12 days. If there is no new activity in the next 48 hours,"\
    " this PR will be labeled as Abandoned PR - Needs New Owner and"\
    " either another developer will finish the PR or it will be closed."

for json_data in json_output:
    already_warned = False
    string = json_data['updatedAt']
    for comment in json_data['comments']:
        if comment['body'] == inactive_comment:
            already_warned = True

    json_time = datetime.datetime.fromisoformat(string.replace('Z', '+00:00'))
    eastern = datetime.timezone(datetime.timedelta(hours=-5))
    et_time_update = json_time.astimezone(eastern)

    today = datetime.datetime.now()
    two_weeks = timedelta(weeks=2)
    twelve_days = timedelta(days=12)
    two_days = timedelta(days=2)
    et_today = today.astimezone(eastern)
    tdiff = et_today - et_time_update

    num = str(json_data['number'])
    already_abandoned = False
    for labels in json_data['labels']:
        if labels['name'] == 'Abandoned PR - Needs New Owner':
            already_abandoned = True

    for review in json_data['reviews']:
        if review["state"] == "APPROVED":
            approved = True
        if review["state"] == "CHANGES_REQUESTED":
            approved = False

    if tdiff > twelve_days and not already_abandoned and not already_warned and not approved:
        subprocess.run(['gh', 'pr', 'comment', num, '--body', inactive_comment])
    if ((tdiff > two_weeks and not already_abandoned) or (tdiff > two_days and already_warned)) and not approved:
        subprocess.run(['gh', 'pr', 'edit', num, '--add-label', 'Abandoned PR - Needs New Owner'])
    if approved:
        subprocess.run(['gh', 'pr', 'edit', num, '--remove-label', 'Abandoned PR - Needs New Owner'])
