import json
import subprocess

pr_json = "gh pr list --json number, comments"
terminal_output = subprocess.check_output(pr_json, shell=True, text=True)
json_output = json.loads(terminal_output)

first_issue_comment = "Thank you for your interest in the Submitty open source project.\n"\
    "We welcome contributions from new developers!\n"\
    "However we do not use the Github issue 'assign' feature for first time prospective contributors.\n"\
    "Please read our documentation on [how to get started with Submitty](https://submitty.org/developer/getting_started/index), "\
    "specifically our pages on [setting up your development environment](https://submitty.org/developer/getting_started/vm_install_using_vagrant) "\
    "and [making a pull request](https://submitty.org/developer/getting_started/make_a_pull_request). "\
    "You do not need to be assigned to an issue to create a pull request that will be "\
    "reviewed by our team and then merged if it appropriately resolves the issue. "\
    "We also encourage you to join our [Zulip](https://submitty.org/index/contact) server to discuss technical questions."

for pr in json_output:
    num = str(pr['number'])
    replied_to = []
    for comment in pr:
        author = comment['author']['login']
        if comment['authorAssociation'] == "NONE" and author not in replied_to:
            # subprocess.run(['gh', 'pr', 'comment', num, '--body', "Hi @" + author + "\n" + first_issue_comment])
            replied_to.insert(author)
