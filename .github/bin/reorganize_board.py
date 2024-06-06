import sys
import json
import subprocess

# These IDs are hard-coded to avoid extraneous API calls. They do not change.

project_id = "PVT_kwDOAKRRkc4AfZil"

field_ids = {
    "Title": "PVTF_lADOAKRRkc4AfZilzgUwVMk",
    "Assignees": "PVTF_lADOAKRRkc4AfZilzgUwVMo",
    "Status": "PVTSSF_lADOAKRRkc4AfZilzgUwVMs",
    "Labels": "PVTF_lADOAKRRkc4AfZilzgUwVMw",
    "Linked pull requests": "PVTF_lADOAKRRkc4AfZilzgUwVM0",
    "Milestone": "PVTF_lADOAKRRkc4AfZilzgUwVM4",
    "Repository": "PVTF_lADOAKRRkc4AfZilzgUwVM8",
    "Reviewers": "PVTF_lADOAKRRkc4AfZilzgUwVNM",
}

status_ids = {
    "Abandoned - Needs New Owner": "bd56a271",
    "Work in Progress": "26e8e6b2",
    "Seeking (Additional) Reviewer": "67583d20",
    "In Review": "618f8b62",
    "Awaiting Maintainer Review": "01f268c2",
    "Ready to Merge": "257693b8",
    "Done": "2873c116",
}


def main():
    items = get_items()
    for item in items:
        new_status = None
        if (
            item["labels"]
            and "Abandoned PR - Needs New Owner" in item["labels"]
            and item["status"] != "Abandoned - Needs New Owner"
        ):
            new_status = "Abandoned - Needs New Owner"
        elif (
            item["labels"] is None
            or "Abandoned PR - Needs New Owner" not in item["labels"]
        ) and item["status"] == "Abandoned - Needs New Owner":
            new_status = "Work in Progress"
        else:
            continue

        change_status(item["id"], new_status)
        print(
            "Moved {}#{} {} ({} -> {})".format(
                item["repo"], item["number"], item["title"], item["status"], new_status
            )
        )


def get_items():
    return json.loads(
        subprocess.run(
            [
                "gh",
                "project",
                "item-list",
                "-L",
                "1000",
                "--owner",
                "Submitty",
                "1",
                "--format",
                "json",
                "--jq",
                "[.items[] | {id, labels, status, title: .content.title, repo: .content.repository, number: .content.number}]",
            ],
            capture_output=True,
            text=True,
        ).stdout
    )


def change_status(item_id, status):
    subprocess.run(
        [
            "gh",
            "project",
            "item-edit",
            "--project-id",
            project_id,
            "--id",
            item_id,
            "--field-id",
            field_ids["Status"],
            "--single-select-option-id",
            status_ids[status],
        ],
        capture_output=True,
        text=True,
    )


main()
