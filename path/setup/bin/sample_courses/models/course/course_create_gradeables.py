# Complete code
"""
Module for creating gradeables.
"""
from typing import Dict

def create_gradeable(course_data: Dict) -> Dict:
    """
    Creates a gradeable for the course.

    Args:
        course_data (Dict): The data for the course.

    Returns:
        Dict: The data for the gradeable.
    """
    try:
        # Get the label for the gradeable
        label = course_data.get('label')

        # Create the gradeable
        gradeable = {
            'id': course_data.get('id'),
            'label': label,
            'status': 'in_progress'
        }

        return gradeable

    except Exception as e:
        # Handle any exceptions that occur
        print(f"An error occurred: {e}")
        return {}