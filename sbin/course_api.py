#!/usr/bin/env python3
"""
course_api.py

Wrapper file for course creation. Runs the PHP database-creation API
call and the Python filesystem-provisioning step.
"""

import requests

from create_course import (
    CONF_DIR,
    check_root,
    load_config,
    load_json,
    parse_args,
    validate,
    build_course_filesystem,
    die,
)


def call_php_api(base_url: str, api_key: str, semester: str, course: str,
                 instructor: str, group_name: str):
    resp = requests.post(
        f"{base_url}/api/courses",
        data={
            "course_semester": semester,
            "course_title": course,
            "head_instructor": instructor,
            "group_name": group_name,
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

    api_config = load_json(CONF_DIR / "submitty_api.json")

    try:
        call_php_api(
            base_url=api_config["base_url"],
            api_key=api_config["api_key"],
            semester=args.semester,
            course=args.course,
            instructor=args.instructor,
            group_name=args.ta_www_group,
        )
    except (requests.RequestException, RuntimeError) as e:
        die(f"Course database creation failed: {e}")

    try:
        course_dir = build_course_filesystem(
            cfg, args.semester, args.course, args.instructor, args.ta_www_group
        )
    except Exception as e:
        die(f"Filesystem provisioning failed after DB creation succeeded: {e}")

    print("\nSUCCESS!\n")
    print(f"SUCCESS!  new course   {args.course} {args.semester}   CREATED HERE:   {course_dir}")
    print(f"SUCCESS!  course page url  {cfg['submission_url']}/{args.semester}/{args.course}")


if __name__ == "__main__":
    main()
