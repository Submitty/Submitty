import common
from common import Status, check_status, check_label, set_status


def main():
    items = common.get_items()
    for item in items:
        if check_label(item, "Abandoned PR - Needs New Owner"):
            set_status(item, Status.ABANDONED)
        elif not check_label(item, "Abandoned PR - Needs New Owner") and check_status(
            item, Status.ABANDONED
        ):
            set_status(item, Status.WIP)


if __name__ == "__main__":
    main()
