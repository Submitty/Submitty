#!/usr/bin/env python3
"""
create_course.py

Filesystem and permission provisioning for a new Submitty course.
"""

import argparse
import json
import os
import subprocess
import sys
import grp
import pwd
import shutil
from pathlib import Path
from dataclasses import dataclass


CONF_DIR = Path(__file__).resolve().parent.parent / "config"


@dataclass
class CourseIdentity:
    """
    A simple data class to hold the identity of a course.
    """
    semester: str
    course: str
    instructor: str
    ta_group: str
    self_registration_type: int = 0
    archived: bool = False


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
    database_json = load_json(CONF_DIR / "database.json")

    return {
        "submitty_data_dir": Path(submitty_json["submitty_data_dir"]),
        "submitty_install_dir": Path(submitty_json["submitty_install_dir"]),
        "submitty_repository": Path(submitty_json["submitty_repository"]),
        "submission_url": submitty_json["submission_url"],
        "php_user": users_json["php_user"],
        "daemon_user": users_json["daemon_user"],
        "cgi_user": users_json["cgi_user"],
        "course_builders_group": users_json["course_builders_group"],
        "database_host": database_json["database_host"],
        "database_port": database_json["database_port"],
        "database_user": database_json["database_user"],
        "database_password": database_json["database_password"],
        "database_course_user": database_json["database_course_user"],
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


def create_directory_tree(course_dir: Path, cfg, identity: CourseIdentity):
    instructor = identity.instructor
    ta_group = identity.ta_group
    php_user = cfg["php_user"]
    daemon_user = cfg["daemon_user"]

    writable_perm = 0o2770  # u=rwx,g=rwxs,o=
    readable_perm = 0o2750  # u=rwx,g=rxs,o=

    create_and_set(course_dir, writable_perm, instructor, ta_group)

    for sub in ("build", "config", "config/build", "config/form",
                "bin", "provided_code", "instructor_solution",
                "test_input", "test_output", "custom_validation_code",
                "reports", "reports/summary_html"):
        create_and_set(course_dir / sub, writable_perm, instructor, ta_group)

    for sub in ("submissions", "forum_attachments", "annotations",
                "config_upload", "site"):
        create_and_set(course_dir / sub, readable_perm, php_user, ta_group)

    for sub in ("submissions_processed",):
        create_and_set(course_dir / sub, writable_perm, daemon_user, ta_group)

    for sub in ("results", "generated_output", "results_public", "checkout"):
        create_and_set(course_dir / sub, readable_perm, daemon_user, ta_group)

    for sub in ("uploads", "uploads/bulk_pdf", "uploads/polls",
                "uploads/student_images", "uploads/student_images/tmp",
                "uploads/course_materials"):
        create_and_set(course_dir / sub, readable_perm, php_user, ta_group)

    for sub in ("uploads/split_pdf", "lichen"):
        create_and_set(course_dir / sub, writable_perm, daemon_user, ta_group)

    for sub in ("uploads/seating", "rainbow_grades",
                "reports/all_grades", "reports/seating", "reports/polls"):
        create_and_set(course_dir / sub, writable_perm, php_user, ta_group)


def copy_and_template_files(course_dir: Path, cfg, identity: CourseIdentity):
    semester = identity.semester
    course = identity.course
    instructor = identity.instructor
    ta_group = identity.ta_group
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


def build_course_filesystem(cfg, identity: CourseIdentity) -> Path:
    """
    Runs the full filesystem-provisioning sequence for a course.
    Raises on any failure (mkdir on an existing dir, missing users, etc.).
    Returns the created course_dir.
    """
    course_dir = cfg["submitty_data_dir"] / "courses" / identity.semester / identity.course
    if course_dir.exists():
        die(f"specific course directory {course_dir} already exists")

    ensure_semester_directory(cfg, identity.semester)
    create_directory_tree(course_dir, cfg, identity)
    copy_and_template_files(course_dir, cfg, identity)
    return course_dir


def run_psql(cfg, database: str, sql: str) -> int:
    """
    Runs a single psql command against the given database. Output is not
    captured, so it flows through to this script's own stdout/stderr.
    Returns the psql process's exit code.
    """
    env = dict(os.environ, PGPASSWORD=cfg["database_password"])
    result = subprocess.run(
        ["psql", "-h", cfg["database_host"], "-U", cfg["database_user"],
         "-p", str(cfg["database_port"]), "-d", database, "-c", sql],
        env=env, check=False,
    )
    return result.returncode


def build_course_database(cfg, identity: CourseIdentity):
    """
    Creates the course's own database, registers it in the master
    database, runs its initial migration, and seeds default forum
    categories. Dies on any failure, rolling back the master courses
    row if migration fails so a retry doesn't hit a stale row.
    """
    semester = identity.semester
    course = identity.course
    database_name = f"submitty_{semester}_{course}"
    course_user = cfg["database_course_user"]

    print(f"\nCreating database {database_name}\n")
    if run_psql(cfg, "postgres", f"CREATE DATABASE {database_name}") != 0:
        die(f"Failed to create database {database_name}")

    if run_psql(
        cfg, database_name,
        f"ALTER DEFAULT PRIVILEGES IN SCHEMA public "
        f"GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO {course_user};"
    ) != 0:
        die("Failed to grant table privileges to course database user")

    if run_psql(
        cfg, database_name,
        f"ALTER DEFAULT PRIVILEGES IN SCHEMA public "
        f"GRANT SELECT, UPDATE ON SEQUENCES TO {course_user};"
    ) != 0:
        die("Failed to grant sequence privileges to course database user")

    insert_course_sql = (
        "INSERT INTO courses (term, course, group_name, owner_name, self_registration_type) "
        f"VALUES ('{semester}', '{course}', '{identity.ta_group}', '{identity.instructor}', "
        f"{identity.self_registration_type});"
    )
    if run_psql(cfg, "submitty", insert_course_sql) != 0:
        print("HINT:  'insert or update on table \"courses\" violates foreign key constraint...'")
        print(f"       may indicate that term {semester} does not exist in master DB.")
        print("       To fix, try running 'create_term.sh'.")
        die("Failed to add this course to the master Submitty database.")

    migrator = cfg["submitty_repository"] / "migration" / "run_migrator.py"
    migrate_result = subprocess.run(
        ["python3", str(migrator), "-e", "course", "--course",
         semester, course, "migrate", "--initial"], check=False,
    )
    if migrate_result.returncode != 0:
        run_psql(cfg, "submitty",
                 f"DELETE FROM courses WHERE term='{semester}' AND course='{course}';")
        die(f"Failed to create tables within database {database_name}")

    sql_prefix = "INSERT INTO categories_list (category_desc, rank, visible_date) VALUES "
    forum_categories_sql = [
        f"{sql_prefix}('General Questions', 0, NULL);",
        f"{sql_prefix}('Homework Help', 1, NULL);",
        f"{sql_prefix}('Quizzes', 2, NULL);",
        f"{sql_prefix}('Tests', 3, NULL);",
    ]
    if run_psql(cfg, database_name, forum_categories_sql) != 0:
        die("Failed create default discussion forum categories.")

    if identity.archived:
        run_psql(cfg, "submitty",
                 f"UPDATE courses SET status=2 WHERE term='{semester}' AND course='{course}';")
        print(f"Archived Course {course}")


def print_success(cfg, identity: CourseIdentity, course_dir: Path):
    print("\nSUCCESS!\n")
    print(f"SUCCESS!  new course   {identity.course} {identity.semester}   "
          f"CREATED HERE:   {course_dir}")
    print(f"SUCCESS!  course page url  "
          f"{cfg['submission_url']}/{identity.semester}/{identity.course}")


def load_and_validate_identity():
    """
    Loads config, parses args, and validates them against the system.
    Returns (cfg, identity) for the requested course.
    """
    cfg = load_config()
    args = parse_args()
    validate(args, cfg)
    print("All user/group validation checks passed.")
    identity = CourseIdentity(
        args.semester, args.course, args.instructor, args.ta_www_group,
        args.self_registration_type, args.archive
    )
    return cfg, identity


def main():
    check_root()
    cfg, identity = load_and_validate_identity()
    course_dir = build_course_filesystem(cfg, identity)
    build_course_database(cfg, identity)
    print_success(cfg, identity, course_dir)


if __name__ == "__main__":
    main()
