import common
from common import Status, Field, check_status, check_label, set_status


def main():
    items = common.get_items()
    for item in items:
        new_status = None
        if check_label(item, "Abandoned PR - Needs New Owner"):
            set_status(item, Status.Abandoned)
        elif not check_label(item, "Abandoned PR - Needs New Owner") and check_status(
            item, Status.Abandoned
        ):
            set_status(item, Status.WIP)


if __name__ == "__main__":
    main()
