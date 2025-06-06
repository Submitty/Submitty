import json
import os

from . import generate_pdf_images


def main(folder, redactions):
    # loop over all submitters in folder and regrade their active version
    for submitter in os.listdir(folder):
        # check if the file is a directory
        if os.path.isdir(os.path.join(folder, submitter)):
            # Read user_assignment_settings.json to get the active version
            settings_path = os.path.join(
                folder, submitter, "user_assignment_settings.json"
            )
            with open(settings_path, "r") as f:
                settings = json.load(f)
                active_version = settings.get("active_version", None)
                if active_version is not None:
                    active_version_path = os.path.join(
                        folder, submitter, str(active_version)
                    )
                    # Check if the active version is a directory
                    if os.path.isdir(active_version_path):
                        # Run the generate_pdf_images job on the active version
                        generate_pdf_images.main(
                            os.path.join(active_version_path, "upload.pdf"),
                            active_version_path.replace("submissions", "results"),
                            redactions,
                        )
