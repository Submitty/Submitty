import json
import subprocess
import datetime
from datetime import date
import pytz

pr_json = "gh pr list --json updatedAt,labels,number,comments"
string = subprocess.check_output(pr_json, shell=True, text=True)
output = json.loads(string)
eastern = pytz.timezone('US/Eastern')
today = datetime.datetime.now()
d = datetime.timedelta(days=14)
a = today - d
a = str(a)
year = int(a[0:4])
month = int(a[5:7])
day = int(a[8:10])
twoWeeks = date(year, month, day)

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
    et_time_update = json_time.astimezone(eastern)
    pr_year = int(str(et_time_update)[0:4])
    pr_month = int(str(et_time_update)[5:7])
    pr_day = int(str(et_time_update)[8:10])
    prDate = date(pr_year, pr_month, pr_day)
    num = str(x['number'])
    delta = prDate - twoWeeks
    numDays = int(str(delta)[0:2])
    already_abandoned = False
    for labels in x['labels']:
        if(labels['name'] == 'Abandoned PR - Needs New Owner'):
            already_abandoned = True

    if((numDays == 0 and not already_abandoned and not already_warned)):
        subprocess.run(['gh', 'pr', 'comment', num, '--body', 'This\
                         PR has been inactive (no commits and no review comments)\
                         for 12 days. If there is no new activity in the next 48 hours,\
                         this PR will be labeled as Abandoned PR - Needs New Owner and\
                         either another developer will finish the PR or it will be closed.'])
    if((numDays == 0 and not already_abandoned) or (numDays == 2 and already_warned)):
        subprocess.run(['gh', 'pr', 'edit', num, '--add-label', 'Abandoned PR - Needs New Owner'])
