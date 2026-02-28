#!/usr/bin/env python3

import os
import re
import subprocess
import sys
from datetime import datetime, timedelta, timezone
from pathlib import Path


MIGRATION_DIRS = [
    Path("migration/migrator/migrations/course"),
    Path("migration/migrator/migrations/system"),
    Path("migration/migrator/migrations/master"),
]

MIGRATION_PATTERN = re.compile(r'^(\d{14})_.*\.py$')
DATESTAMP_FORMAT = "%Y%m%d%H%M%S"
MAX_AGE_DAYS = int(os.environ.get('MAX_MIGRATION_AGE_DAYS', '7'))


def parse_migration_filename(filename):
    match = MIGRATION_PATTERN.match(filename)
    if not match:
        return None

    try:
        dt = datetime.strptime(match.group(1), DATESTAMP_FORMAT)
        return dt.replace(tzinfo=timezone.utc)
    except ValueError:
        return None


def get_changed_migration_files():
    try:
        base_ref = os.environ.get('GITHUB_BASE_REF', 'main')
        result = subprocess.run(
            ['git', 'diff', '--name-only', '--diff-filter=AM', f'origin/{base_ref}...HEAD'],
            capture_output=True,
            text=True,
            check=True
        )

        files = result.stdout.strip().split('\n')
        migration_files = []

        for filepath in files:
            # Normalize path for comparison (git always uses forward slashes)
            normalized_path = filepath.replace('\\', '/')
            for migration_dir in MIGRATION_DIRS:
                migration_dir_str = str(migration_dir).replace('\\', '/')
                if (normalized_path.startswith(migration_dir_str) and
                        normalized_path.endswith('.py')):
                    filename = os.path.basename(filepath)
                    if filename != "__init__.py":
                        migration_files.append((filepath, filename))

        return migration_files

    except subprocess.CalledProcessError as e:
        print(f"Error: Could not get git diff: {e}")
        print("Make sure you're running this in a git repository with proper remote setup.")
        sys.exit(1)


def get_current_time():
    """Get current UTC time (extracted for easier testing)."""
    return datetime.now(timezone.utc)


def verify_migration_freshness():
    """Checks all changed migrations and reports stale or invalid files."""
    changed_files = get_changed_migration_files()

    if not changed_files:
        print("No new or modified migration files to verify")
        return True

    print(f"🔍 Checking {len(changed_files)} migration file(s) for freshness...\n")

    now = get_current_time()
    max_age_threshold = now - timedelta(days=MAX_AGE_DAYS)

    stale_migrations = []
    invalid_migrations = []
    valid_migrations = []

    for filepath, filename in changed_files:
        migration_type = os.path.basename(os.path.dirname(filepath))
        print(f"Checking: {migration_type}/{filename}")

        datestamp = parse_migration_filename(filename)

        if datestamp is None:
            invalid_migrations.append((filepath, filename))
            print("Invalid datestamp format")
            continue

        age_days = (now - datestamp).days
        age_str = datestamp.strftime('%Y-%m-%d %H:%M:%S')

        print(f"Migration date: {age_str}")
        print(f"Age: {age_days} day(s)")

        if datestamp < max_age_threshold:
            stale_migrations.append((filepath, filename, datestamp, age_days))
            print(f"STALE: Migration is more than {MAX_AGE_DAYS} days old!")
        else:
            valid_migrations.append((filepath, filename, datestamp))
            print(f"Fresh (within {MAX_AGE_DAYS} days)")

        print()

    print("=" * 70)
    print("SUMMARY")
    print("=" * 70)

    if valid_migrations:
        print(f"\nValid migrations: {len(valid_migrations)}")

    if invalid_migrations:
        print(f"\nINVALID FORMAT ({len(invalid_migrations)}):")
        for filepath, filename in invalid_migrations:
            print(f"  - {filepath}")
        print("\nExpected format: YYYYMMDDHHMMSS_description.py")

    if stale_migrations:
        print(f"\nSTALE MIGRATIONS ({len(stale_migrations)}):")
        for filepath, filename, datestamp, age_days in stale_migrations:
            print(f"  - {filepath}")
            print(f"    Date: {datestamp.strftime('%Y-%m-%d %H:%M:%S')}")
            print(f"    Age: {age_days} days (max allowed: {MAX_AGE_DAYS} days)")

        print("\n" + "=" * 70)
        print(" MIGRATION DATESTAMP CHECK FAILED")
        print("=" * 70)
        print(f"\nMigrations must be ≤{MAX_AGE_DAYS} days old to prevent out-of-order execution.")
        print("\nTo fix, rename the file with today's datestamp:")
        for filepath, filename, datestamp, age_days in stale_migrations:
            migration_type = os.path.basename(os.path.dirname(filepath))
            description = filename.split('_', 1)[1] if '_' in filename else 'description.py'
            new_datestamp = now.strftime(DATESTAMP_FORMAT)
            new_filename = f"{new_datestamp}_{description}"
            new_path = f"migration/migrator/migrations/{migration_type}/{new_filename}"
            print(f"  git mv {filepath} {new_path}")

        print("\nMore info: https://submitty.org/developer/development_instructions/migrations\n")

        return False

    if invalid_migrations:
        print("\nPlease fix the invalid migration filename format.")
        return False

    print("\nAll migration files have fresh datestamps!")
    return True


if __name__ == "__main__":
    success = verify_migration_freshness()
    sys.exit(0 if success else 1)
