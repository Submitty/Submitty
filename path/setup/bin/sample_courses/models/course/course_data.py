# Complete code
"""
Module for course data.
"""
from typing import Dict

def get_course_data(course_id: int) -> Dict:
    """
    Returns the data for the course.

    Args:
        course_id (int): The ID of the course.

    Returns:
        Dict: The data for the course.
    """
    try:
        # Get the course data
        course_data = {
            'id': course_id,
            'name': 'Course Name',
            'gradeables': [
                {
                    'id': 1,
                    'label': 'Gradeable 1',
                    'status': 'in_progress'
                },
                {
                    'id': 2,
                    'label': 'Gradeable 2',
                    'status': 'complete'
                }
            ]
        }

        return course_data

    except Exception as e:
        # Handle any exceptions that occur
        print(f"An error occurred: {e}")
        return {}