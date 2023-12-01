import json
import subprocess
import datetime
from datetime import timedelta

pr_json = "gh pr list --json updatedAt,labels,number,comments"
string = subprocess.check_output(pr_json, shell=True, text=True)
output = json.loads(string)

for x in output:
    already_warned = False
    string = x['updatedAt']
    for comment in x['comments']:
        if comment['body'] == 'This\
                         PR has been inactive (no commits and no review comments)\
                         for 12 days. If there is no new activity in the next 48 hours,\
                         this PR will be labeled as Abandoned PR - Needs New Owner and\
                         either another developer will finish the PR or it will be closed.':
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
    num = str(x['number'])
    already_abandoned = False
    for labels in x['labels']:
        if (labels['name'] == 'Abandoned PR - Needs New Owner'):
            already_abandoned = True

    if ((tdiff > twelve_days and not already_abandoned and not already_warned)):
        subprocess.run(['gh', 'pr', 'comment', num, '--body', 'This\
                         PR has been inactive (no commits and no review comments)\
                         for 12 days. If there is no new activity in the next 48 hours,\
                         this PR will be labeled as Abandoned PR - Needs New Owner and\
                         either another developer will finish the PR or it will be closed.'])
    if ((tdiff > two_weeks and not already_abandoned) or (tdiff > two_days and already_warned)):
        subprocess.run(['gh', 'pr', 'edit', num, '--add-label', 'Abandoned PR - Needs New Owner'])
