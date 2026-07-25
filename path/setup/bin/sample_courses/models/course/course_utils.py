# Complete code
"""
Module for utility functions related to course data.
"""
from typing import Dict, List

def get_autograding_status_data(course_data: Dict) -> Dict:
    """
    Returns the data for the autograding status page.

    Args:
        course_data (Dict): The data for the course.

    Returns:
        Dict: The data for the autograding status page.
    """
    try:
        # Get the gradeables for the course
        gradeables = course_data.get('gradeables', [])

        # Initialize the data for the autograding status page
        autograding_status_data = {
            'course': course_data.get('name'),
            'gradeables': []
        }

        # Iterate over the gradeables and add them to the data
        for gradeable in gradeables:
            # Get the label for the gradeable
            label = gradeable.get('label')

            # Add the gradeable to the data
            autograding_status_data['gradeables'].append({
                'id': gradeable.get('id'),
                'label': label,
                'status': gradeable.get('status')
            })

        return autograding_status_data

    except Exception as e:
        # Handle any exceptions that occur
        print(f"An error occurred: {e}")
        return {}