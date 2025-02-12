"""File to add course_materials_upload_limit_mb to config.json"""
import json
import os

def up(config, database, semester, course):
    # Path to the course's config.json file
    config_path = os.path.join(config.submitty['submitty_data_dir'], 'courses', semester, course, 'config', 'config.json')
    print(f"Checking {config_path}")

    # Check if config.json exists
    if os.path.exists(config_path):
        with open(config_path, 'r') as file:
            course_config = json.load(file)

        # Ensure "course_details" exists
        if "course_details" in course_config:
            # Add the key inside "course_details" if not already present
            if "course_materials_upload_limit_mb" not in course_config["course_details"]:
                course_config["course_details"]["course_materials_upload_limit_mb"] = 10  # Set default limit to 10MB

                # Save the updated config.json
                with open(config_path, 'w') as file:
                    json.dump(course_config, file, indent=2)

                print(f"Updated {config_path} with course_materials_upload_limit_mb = 10MB inside course_details")
        else:
            print(f"Skipping {config_path}, 'course_details' section not found.")
    else:
        print(f"Skipping {config_path}, file does not exist.")


def down(config, database, semester, course):
    pass