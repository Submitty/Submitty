import json
from pathlib import Path

from . import generate_pdf_images


# Regenerate images for all submissions in a bulk upload
def main(folder, redactions):
    # Convert folder to Path object
    folder_path = Path(folder)

    # loop over all submitters in folder and regrade their active version
    for submitter_dir in [d for d in folder_path.iterdir() if d.is_dir()]:
        # Read user_assignment_settings.json to get the active version
        settings_path = submitter_dir / "user_assignment_settings.json"

        with open(settings_path, "r") as f:
            settings = json.load(f)
            active_version = settings.get("active_version", None)

            if active_version is None:
                continue

            active_version_path = submitter_dir / str(active_version)
            # Check if the active version is a directory
            if not active_version_path.is_dir():
                continue
            # Run the generate_pdf_images job on the active version
            pdf_path = active_version_path / "upload.pdf"
            results_path = str(active_version_path).replace("submissions", "submissions_processed")
            generate_pdf_images.main(
                str(pdf_path),
                results_path,
                redactions,
            )
