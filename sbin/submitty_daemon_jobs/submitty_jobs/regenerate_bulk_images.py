import json
import os
import time
from pathlib import Path
from concurrent.futures import ThreadPoolExecutor, as_completed

from . import generate_pdf_images


def process_single_submission(submitter_dir_str, redactions):
    """Process a single submission and return its status without shared state."""
    submitter_dir = Path(submitter_dir_str)
    try:
        # Read user_assignment_settings.json to get the active version
        settings_path = submitter_dir / "user_assignment_settings.json"

        if not settings_path.exists():
            return ('skipped', 0, f"Skipped {submitter_dir.name} - no settings")

        with open(settings_path, "r") as f:
            settings = json.load(f)
            active_version = settings.get("active_version", None)

            if active_version is None:
                return ('skipped', 0, f"Skipped {submitter_dir.name} - no active version")

            active_version_path = submitter_dir / str(active_version)
            # Check if the active version is a directory
            if not active_version_path.is_dir():
                return ('skipped', 0, f"Skipped {submitter_dir.name} - invalid active version path")

            # Check if PDF exists
            pdf_path = active_version_path / "upload.pdf"
            if not pdf_path.exists():
                return ('skipped', 0, f"Skipped {submitter_dir.name} - no PDF file")

            # Run the generate_pdf_images job on the active version
            results_path = str(active_version_path).replace("submissions", "submissions_processed")
            start_time = time.time()

            generate_pdf_images.main(
                str(pdf_path),
                results_path,
                redactions,
            )

            elapsed = time.time() - start_time
            return ('processed', elapsed, f"Processed {submitter_dir.name} in {elapsed:.2f}s")

    except Exception as e:
        return ('errors', 0, f"Error processing {submitter_dir.name}: {str(e)}")


def main(folder, redactions, max_workers=None):
    """Main function with parallel processing support."""
    start_time = time.time()

    # Convert folder to Path object
    folder_path = Path(folder)

    # Get all submitter directories
    submitter_dirs = [str(d) for d in folder_path.iterdir() if d.is_dir()]

    if not submitter_dirs:
        print("No submitter directories found")
        return

    print(f"Found {len(submitter_dirs)} submissions to process")

    # Statistics tracking
    stats = {
        'processed': 0,
        'skipped': 0,
        'errors': 0,
        'total_time': 0
    }

    # Determine optimal number of workers (default to CPU count, but cap at 4 to avoid overwhelming system)
    if max_workers is None:
        max_workers = min(4, os.cpu_count() or 2)

    print(f"Using {max_workers} parallel workers")

    # Process submissions in parallel
    with ThreadPoolExecutor(max_workers=max_workers) as executor:
        # Submit all tasks
        future_to_submission = {
            executor.submit(process_single_submission, submitter_dir, redactions): submitter_dir
            for submitter_dir in submitter_dirs
        }

        # Process completed tasks
        for future in as_completed(future_to_submission):
            status, elapsed, message = future.result()
            print(message)
            stats[status] += 1
            if status == 'processed':
                stats['total_time'] += elapsed

    # Print final statistics
    total_elapsed = time.time() - start_time
    print(f"\nBulk regeneration completed in {total_elapsed:.2f} seconds")
    print(f"Processed: {stats['processed']}")
    print(f"Skipped: {stats['skipped']}")
    print(f"Errors: {stats['errors']}")
    if stats['processed'] > 0:
        avg_time = stats['total_time'] / stats['processed']
        print(f"Average time per submission: {avg_time:.2f} seconds")
        print(f"Parallel efficiency: {(stats['total_time'] / total_elapsed):.2f}x")
