#!/usr/bin/env python3
"""
course_api.py

Wrapper file for course creation. Runs the PHP database-creation API
call and the Python filesystem-provisioning step.
"""

import requests # pylint: disable=import-error

from create_course import (
    CONF_DIR,
    CourseIdentity,
    check_root,
    load_config,
    load_json,
    parse_args,
    validate,
    build_course_filesystem,
    print_success,
    die,
)


def call_php_api(base_url: str, api_key: str, identity: CourseIdentity):
    resp = requests.post(
        f"{base_url}/api/courses",
        data={
            "course_semester": identity.semester,
            "course_title": identity.course,
            "head_instructor": identity.instructor,
            "group_name": identity.ta_group,
        },
        headers={"Authorization": f"Bearer {api_key}"},
        timeout=30,
    )
    body = resp.json()
    if body.get("status") != "success":
        raise RuntimeError(f"PHP API call failed: {body.get('message', 'Unknown error')}")
    return body.get("data")


def main():
    check_root()
    cfg = load_config()
    args = parse_args()
    validate(args, cfg)
    print("All user/group validation checks passed.")

    identity = CourseIdentity(args.semester, args.course, args.instructor, args.ta_www_group)
    api_config = load_json(CONF_DIR / "submitty_api.json")

    try:
        call_php_api(api_config["base_url"], api_config["api_key"], identity)
    except (requests.RequestException, RuntimeError) as e:
        die(f"Course database creation failed: {e}")

    try:
        course_dir = build_course_filesystem(cfg, identity)
    except (OSError, FileExistsError, KeyError) as e:
        die(f"Filesystem provisioning failed after DB creation succeeded: {e}")

    print_success(cfg, identity, course_dir)


if __name__ == "__main__":
    main()
