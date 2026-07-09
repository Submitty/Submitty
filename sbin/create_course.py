#!/usr/bin/env python3
"""
create_course.py

Filesystem and permission provisioning for a new Submitty course.
"""

import argparse
import json
import os
import sys
import grp
import pwd
import shutil
from pathlib import Path


CONF_DIR = Path(__file__).resolve().parent.parent / "config"


def load_json(path: Path):
    with path.open() as f:
        return json.load(f)


def die(msg: str):
    print(f"ERROR: {msg}")
    sys.exit(1)


def check_root():
    if os.geteuid() != 0:
        die("This script must be run by root or sudo")


def load_config():
    submitty_json = load_json(CONF_DIR / "submitty.json")
    users_json = load_json(CONF_DIR / "submitty_users.json")

    return {
        "submitty_data_dir": Path(submitty_json["submitty_data_dir"]),
        "submitty_install_dir": Path(submitty_json["submitty_install_dir"]),
        "submission_url": submitty_json["submission_url"],
        "php_user": users_json["php_user"],
        "daemon_user": users_json["daemon_user"],
        "cgi_user": users_json["cgi_user"],
        "course_builders_group": users_json["course_builders_group"],
    }


def parse_args():
    parser = argparse.ArgumentParser()
    parser.add_argument("--archive", action="store_true")
    parser.add_argument("--all-self-registration", action="store_true")
    parser.add_argument("--request-self-registration", action="store_true")
    parser.add_argument("semester")
    parser.add_argument("course")
    parser.add_argument("instructor")
    parser.add_argument("ta_www_group")
    args = parser.parse_args()

    args.self_registration_type = 0
    if args.all_self_registration:
        args.self_registration_type = 2
    elif args.request_self_registration:
        args.self_registration_type = 1

    print("\nCREATE COURSE:")
    print(f"  semester:     {args.semester}")
    print(f"  course:       {args.course}")
    print(f"  instructor:   {args.instructor}")
    print(f"  ta_www_group: {args.ta_www_group}")
    print(f"  archived:     {args.archive}")
    print(f"  self_registration_type: {args.self_registration_type}\n")

    return args


def user_in_group(user: str, group: str) -> bool:
    try:
        group_members = grp.getgrnam(group).gr_mem
        primary_gid = pwd.getpwnam(user).pw_gid
        return user in group_members or primary_gid == grp.getgrnam(group).gr_gid
    except KeyError:
        return False


def validate(args, cfg):
    try:
        pwd.getpwnam(args.instructor)
    except KeyError:
        die(f"{args.instructor} user does not exist")

    try:
        grp.getgrnam(args.ta_www_group)
    except KeyError:
        die(f"{args.ta_www_group} group does not exist")

    if not user_in_group(args.instructor, cfg["course_builders_group"]):
        die(f"{args.instructor} is not in group {cfg['course_builders_group']}")
    if not user_in_group(args.instructor, args.ta_www_group):
        die(f"{args.instructor} is not in group {args.ta_www_group}")
    for system_user in (cfg["php_user"], cfg["daemon_user"], cfg["cgi_user"]):
        if not user_in_group(system_user, args.ta_www_group):
            die(f"{system_user} is not in group {args.ta_www_group}")


def create_and_set(path: Path, permissions: int, owner: str, group: str):
    path.mkdir(parents=False, exist_ok=False)
    shutil.chown(path, user=owner, group=group)
    path.chmod(permissions)


def replace_fillin_variables(path: Path, replacements: dict):
    text = path.read_text()
    for key, value in replacements.items():
        text = text.replace(key, value)
    path.write_text(text)


def ensure_semester_directory(cfg, semester: str) -> Path:
    data_dir = cfg["submitty_data_dir"]
    courses_dir = data_dir / "courses"
    semester_dir = courses_dir / semester

    if not data_dir.is_dir():
        die(f"Submitty data directory {data_dir} does not exist")
    if not courses_dir.is_dir():
        die(f"Submitty courses directory {courses_dir} does not exist")
    if not semester_dir.is_dir():
        semester_dir.mkdir()
        shutil.chown(semester_dir, user="root", group=cfg["course_builders_group"])
        semester_dir.chmod(0o751)

    return semester_dir


def create_directory_tree(course_dir: Path, cfg, instructor: str, ta_group: str):
    php_user = cfg["php_user"]
    daemon_user = cfg["daemon_user"]

    W = 0o2770  # u=rwx,g=rwxs,o=
    R = 0o2750  # u=rwx,g=rxs,o=

    create_and_set(course_dir, W, instructor, ta_group)

    for sub in ("build", "config", "config/build", "config/form",
                "bin", "provided_code", "instructor_solution",
                "test_input", "test_output", "custom_validation_code",
                "reports", "reports/summary_html"):
        create_and_set(course_dir / sub, W, instructor, ta_group)

    for sub in ("submissions", "forum_attachments", "annotations",
                "config_upload", "site"):
        create_and_set(course_dir / sub, R, php_user, ta_group)

    for sub in ("submissions_processed",):
        create_and_set(course_dir / sub, W, daemon_user, ta_group)

    for sub in ("results", "generated_output", "results_public", "checkout"):
        create_and_set(course_dir / sub, R, daemon_user, ta_group)

    for sub in ("uploads", "uploads/bulk_pdf", "uploads/polls",
                "uploads/student_images", "uploads/student_images/tmp",
                "uploads/course_materials"):
        create_and_set(course_dir / sub, R, php_user, ta_group)

    for sub in ("uploads/split_pdf", "lichen"):
        create_and_set(course_dir / sub, W, daemon_user, ta_group)

    for sub in ("uploads/seating", "rainbow_grades",
                "reports/all_grades", "reports/seating", "reports/polls"):
        create_and_set(course_dir / sub, W, php_user, ta_group)


def copy_and_template_files(course_dir: Path, cfg, semester: str, course: str,
                             instructor: str, ta_group: str):
    install_dir = cfg["submitty_install_dir"]
    php_user = cfg["php_user"]
    database_name = f"submitty_{semester}_{course}"

    fillins = {
        "__CREATE_COURSE__FILLIN__SUBMITTY_INSTALL_DIR__": str(install_dir),
        "__CREATE_COURSE__FILLIN__SUBMITTY_DATA_DIR__": str(cfg["submitty_data_dir"]),
        "__CREATE_COURSE__FILLIN__SUBMISSION_URL__": cfg["submission_url"],
        "__CREATE_COURSE__FILLIN__SEMESTER__": semester,
        "__CREATE_COURSE__FILLIN__COURSE__": course,
        "__CREATE_COURSE__FILLIN__DATABASE_NAME__": database_name,
    }

    build_script = course_dir / f"BUILD_{course}.sh"
    shutil.copy(install_dir / "sbin" / "build_course.sh", build_script)
    shutil.chown(build_script, user=instructor, group=ta_group)
    build_script.chmod(0o770)
    replace_fillin_variables(build_script, fillins)

    config_json = course_dir / "config" / "config.json"
    shutil.copy(install_dir / "site" / "config" / "course_template.json", config_json)
    shutil.chown(config_json, user=php_user, group=ta_group)
    config_json.chmod(0o660)
    replace_fillin_variables(config_json, fillins)


def build_course_filesystem(cfg, semester: str, course: str, instructor: str, ta_group: str) -> Path:
    """
    Runs the full filesystem-provisioning sequence for a course.
    Raises on any failure (mkdir on an existing dir, missing users, etc.).
    Returns the created course_dir.
    """
    course_dir = cfg["submitty_data_dir"] / "courses" / semester / course
    if course_dir.exists():
        die(f"specific course directory {course_dir} already exists")

    ensure_semester_directory(cfg, semester)
    create_directory_tree(course_dir, cfg, instructor, ta_group)
    copy_and_template_files(course_dir, cfg, semester, course, instructor, ta_group)
    return course_dir


def main():
    check_root()
    cfg = load_config()
    args = parse_args()
    validate(args, cfg)
    print("All user/group validation checks passed.")

    course_dir = build_course_filesystem(
        cfg, args.semester, args.course, args.instructor, args.ta_www_group
    )

    print("\nSUCCESS!\n")
    print(f"SUCCESS!  new course   {args.course} {args.semester}   CREATED HERE:   {course_dir}")
    print(f"SUCCESS!  course page url  {cfg['submission_url']}/{args.semester}/{args.course}")


if __name__ == "__main__":
    main()
