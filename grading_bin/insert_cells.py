#!/usr/bin/python3
"""
Script to insert missing checkpoint cells from an instructor notebook into a student's 
submitted notebook.

This script compares the cells in both notebooks based on their IDs and inserts any missing 
cells from the instructor notebook into the student notebook.

Student cells are preserved if they exist in both notebooks. Extra student cells
missing from the instructor notebook are not added to the final output notebook for
consistency.
"""

import argparse
import nbformat


def insert_cells(instructor_path, student_path, output_path):
    """
    Insert missing cells from the instructor notebook into the student notebook.
    The function reads both notebooks, compares their cells by ID, and inserts any missing
    cells from the instructor notebook into the student notebook.
    Args:
        instructor_path (str): Path to the instructor notebook.
        student_path (str): Path to the student's submitted notebook.
        output_path (str): Path where the final notebook will be saved.
    """
    # Load instructor and student notebooks
    try: 
        with open(instructor_path, 'r', encoding='utf-8') as f:
            instructor_nb = nbformat.read(f, as_version=nbformat.NO_CONVERT)
        print(f"Loaded instructor notebook from '{instructor_path}'")
        with open(student_path, 'r', encoding='utf-8') as f:
            student_nb = nbformat.read(f, as_version=nbformat.NO_CONVERT)
        print(f"Loaded student notebook from '{student_path}'")
    except FileNotFoundError as e:
        print(f"Error: {e}")
        return

    student_ids = {cell.id: cell for cell in student_nb.cells if 'id' in cell}

    new_cells = []
    inserted_count = 0

    print(f"Inserting missing checkpoint cells from instructor notebook into student notebook...")
    for cell in instructor_nb.cells:
        # Compares cell IDs to see if the cell exists in the student notebook
        if 'id' in cell and cell.id in student_ids:
            # If the cell exists in the student notebook, keep it
            new_cells.append(student_ids[cell.id])
        else:
            # Otherwise, keep the instructor version
            grade_id = cell.metadata.get('grade_id', 'N/A')
            if grade_id == 'N/A':
                print(f"\tWarning: Cell with id '{cell.id}' has no 'grade_id' metadata. Skipping.")
                continue
            print(f"\tInserting missing checkpoint cell (grade_id: {grade_id}).")
            new_cells.append(cell)
            inserted_count += 1

    final_nb = nbformat.v4.new_notebook(
        metadata=instructor_nb.metadata,
        cells=new_cells
    )

    with open(output_path, 'w', encoding='utf-8') as nb:
        nbformat.write(final_nb, nb)

    print(f"\nSuccessfully inserted {inserted_count} cells.")
    print(f"Saved final notebook to '{output_path}'")


if __name__ == "__main__":
    parser = argparse.ArgumentParser(
        description="Build notebook from an instructor and student version." \
                    "Ensure IDs are the same in both notebooks."
    )
    parser.add_argument(
        "-i", "--instructor", required=True, help="Path to the complete instructor notebook."
    )
    parser.add_argument(
        "-s", "--student", nargs="+", required=True, help="Path to the student's submitted notebook."
    )
    parser.add_argument(
        "-o", "--output", required=True, help="Path for the output notebook."
    )
    args = parser.parse_args()

    student_files = [file for file in args.student if file != args.instructor]

    if not student_files:
        raise FileNotFoundError(f"No Jupyter notebook files found named {args.student}")
    if len(student_files) > 1:
        raise ValueError(f"Multiple Jupyter notebook files found for '{args.student}'. "
                         "Please ensure there is only one file matching the pattern.")

    insert_cells(args.instructor, student_files[0], args.output)