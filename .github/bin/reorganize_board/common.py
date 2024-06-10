from enum import Enum
import subprocess
import json


project_id = "PVT_kwDOAKRRkc4AfZil"


class Field(Enum):
    def __str__(self):
        return self.value

    Title = "PVTF_lADOAKRRkc4AfZilzgUwVMk"
    Assignees = "PVTF_lADOAKRRkc4AfZilzgUwVMo"
    Status = "PVTSSF_lADOAKRRkc4AfZilzgUwVMs"
    Labels = "PVTF_lADOAKRRkc4AfZilzgUwVMw"
    LinkedPullRequests = "PVTF_lADOAKRRkc4AfZilzgUwVM0"
    Milestone = "PVTF_lADOAKRRkc4AfZilzgUwVM4"
    Repository = "PVTF_lADOAKRRkc4AfZilzgUwVM8"
    Reviewers = "PVTF_lADOAKRRkc4AfZilzgUwVNM"


_Status__status_names = {}


def __get_status_names():
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


class Status(Enum):
    def __str__(self):
        return self.value

    def Name(self):
        return __status_names[str(self)]

    Abandoned = "bd56a271"
    WIP = "26e8e6b2"
    SeekingReviewer = "67583d20"
    InReview = "618f8b62"
    AwaitingMaintainerReview = "01f268c2"
    ReadyToMerge = "257693b8"
    Done = "2873c116"


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
            project_id,
            "--id",
            item["id"],
            "--field-id",
            str(Field.Status),
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


_Status__status_names = __get_status_names()
