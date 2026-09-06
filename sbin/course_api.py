#!/usr/bin/env python3
"""
course_api.py

Command-line script for creating a course without a browser session.
Checks arguments then sends them to the /api/courses PHP endpoint.
That creates the course files and sets up the database.
"""

import subprocess
import requests  # pylint: disable=import-error

from create_course import (
    CourseIdentity,
    load_and_validate_identity,
    print_success,
    die,
)


def generate_api_token(install_dir, user_id: str) -> str:
    """PLACEHOLDER: mints a JWT for user_id via the existing api_token_generate.php,
    since course_api.py has no other way yet to obtain one. See PR notes on
    refactoring api_token_generate.php into Python / adding /api/admin/token."""
    result = subprocess.run(
        ["php", str(install_dir / "sbin" / "api_token_generate.php"), user_id],
        capture_output=True, text=True, check=False,
    )
    if result.returncode != 0:
        die(f"Failed to generate API token for {user_id}: {result.stderr.strip()}")
    return result.stdout.strip()


def call_php_api(base_url: str, token: str, identity: CourseIdentity):
    resp = requests.post(
        f"{base_url}/api/courses",
        data={
            "course_semester": identity.semester,
            "course_title": identity.course,
            "head_instructor": identity.instructor,
            "group_name": identity.ta_group,
        },
        headers={"Authorization": token},
        timeout=30,
    )
    body = resp.json()
    if body.get("status") != "success":
        raise RuntimeError(f"PHP API call failed: {body.get('message', 'Unknown error')}")
    return body.get("data")


def main():
    cfg, identity = load_and_validate_identity()
    course_dir = cfg["submitty_data_dir"] / "courses" / identity.semester / identity.course

    token = generate_api_token(cfg["submitty_install_dir"], identity.instructor)

    try:
        call_php_api(cfg["submission_url"], token, identity)
    except (requests.RequestException, RuntimeError) as e:
        die(f"Course creation failed: {e}")

    print_success(cfg, identity, course_dir)


if __name__ == "__main__":
    main()
