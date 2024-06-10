from enum import Enum
import subprocess
import json


PROJECT_ID = "PVT_kwDOAKRRkc4AfZil"


class Field(Enum):
    """Project item field IDs for gh cli."""
    def __str__(self):
        return self.value

    TITLE = "PVTF_lADOAKRRkc4AfZilzgUwVMk"
    ASSIGNEES = "PVTF_lADOAKRRkc4AfZilzgUwVMo"
    STATUS = "PVTSSF_lADOAKRRkc4AfZilzgUwVMs"
    LABELS = "PVTF_lADOAKRRkc4AfZilzgUwVMw"
    LINKED_PULL_REQUESTS = "PVTF_lADOAKRRkc4AfZilzgUwVM0"
    MILESTONE = "PVTF_lADOAKRRkc4AfZilzgUwVM4"
    REPOSITORY = "PVTF_lADOAKRRkc4AfZilzgUwVM8"
    REVIEWERS = "PVTF_lADOAKRRkc4AfZilzgUwVNM"


class _StatusNames:
    """Internal class for getting and storing status names."""
    def __get_status_names(self):
        return json.loads(
            subprocess.run(
                [
                    "gh",
                    "project",
                    "field-list",
                    "--owner",
                    "Submitty",
                    "1",
                    "--format",
                    "json",
                    "--jq",
                    '.fields | .[] | select(.id == "'
                    + str(Field.Status)
                    + '").options | map( { (.id): .name }) | add',
                ],
                capture_output=True,
                text=True,
            ).stdout
        )

    names = __get_status_names()


class Status(Enum):
    """Project item status IDs for gh cli."""
    def __str__(self):
        return self.value

    def Name(self):
        return _StatusNames.names[str(self)]

    ABANDONED = "bd56a271"
    WIP = "26e8e6b2"
    SEEKING_REVIEWER = "67583d20"
    IN_REVIEW = "618f8b62"
    AWAITING_MAINTAINER_REVIEW = "01f268c2"
    READY_TO_MERGE = "257693b8"
    DONE = "2873c116"


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


def set_status(item, status):
    if item["status"] == status.Name():
        return
    print(
        "Moving {}#{} {} ({} -> {})".format(
            item["repo"], item["number"], item["title"], item["status"], status.Name()
        )
    )
    subprocess.run(
        [
            "gh",
            "project",
            "item-edit",
            "--project-id",
            PROJECT_ID,
            "--id",
            item["id"],
            "--field-id",
            str(Field.STATUS),
            "--single-select-option-id",
            str(status),
        ],
        capture_output=True,
        text=True,
    )


def check_label(item, label):
    return item["labels"] and label in item["labels"]


def check_status(item, status):
    return item["status"] == status.Name()
