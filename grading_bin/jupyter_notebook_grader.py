"""
Script to execute Jupyter notebooks and save their outputs, intended
for automated grading of Jupyter notebook assignments.

It saves the contents of each cell (source code, markdown, outputs, errors)
into individual files named according to the cell's metadata or index.
"""

import argparse
import base64
import glob
from pathlib import Path

# pylint: disable=import-error
import nbformat
from nbconvert.preprocessors import ClearOutputPreprocessor
from nbconvert.preprocessors import ExecutePreprocessor
from nbconvert.preprocessors import CellExecutionError


# Execution API: https://nbconvert.readthedocs.io/en/latest/execute_api.html
def execute_notebook(notebook_path, output_path, timeout=600):
    """
    Execute a Jupyter notebook and save the outputs.
    Args:
        notebook_path (str): The path to the Jupyter notebook file.
        output_path (str): The path to the output file.
        timeout (int): The maximum time in seconds to execute a cell.
    """
    notebook_filename = Path(notebook_path)

    with open(notebook_filename, encoding='utf-8') as f:
        # Load the notebook and do not convert it to a specific version
        nb = nbformat.read(f, as_version=nbformat.NO_CONVERT)

    # Clear output of notebook
    cop = ClearOutputPreprocessor()
    nb, _ = cop.preprocess(nb, {})

    # Sets preprocessor with the following parameters:
    # - timeout: maximum time in seconds to execute a cell
    # - kernel_name: the kernel to use for execution (e.g., 'python3')
    # - allow_errors: if True, allows the execution to continue even
    #                 if a cell raises an error (Optional, defaults to False)
    ep = ExecutePreprocessor(
        timeout=timeout, kernel_name='python3', allow_errors=True
    )

    try:
        ep.preprocess(nb, {'metadata': {'path': notebook_filename.parent}})
    # Raised if a cell execution fails and allow_errors is False
    except CellExecutionError:
        msg = f'Error executing the notebook "{notebook_filename}".\n\n'
        msg += 'See notebook executed.ipynb for the traceback.'
        print(msg)
        raise

    with open(output_path, mode='w', encoding='utf-8') as f:
        nbformat.write(nb, f)

    for cell_idx, cell in enumerate(nb.cells):
        save_output(cell_idx, cell)


def save_code_output_cell(output, file_name):
    """
    Save the output of a code cell to files.
    Args:
        output (nbformat.NotebookNode): The output of the code cell.
        file_name (str): The base name for the output files.
    """
    if output.output_type == 'stream':
        if output.name == 'stdout':
            cell_txt = Path(f"{file_name}_stdout.txt")
            cell_txt.write_text(output.text.strip(), encoding='utf-8')

        if output.name == 'stderr':
            cell_txt = Path(f"{file_name}_stderr.txt")
            cell_txt.write_text(output.text.strip(), encoding='utf-8')

    elif output.output_type in ('execute_result', 'display_data'):
        data = output.get("data", {})
        if "text/plain" in data:
            cell_txt = Path(f"{file_name}_result.txt")
            cell_txt.write_text(data["text/plain"], encoding='utf-8')

        if "image/png" in data:
            img_data = base64.b64decode(data["image/png"])
            img = Path(f"{file_name}.png")
            img.write_bytes(img_data)

    elif output.output_type == 'error':
        traceback = "\n".join(output.get("traceback", []))
        cell_err = Path(f"{file_name}.err")
        cell_err.write_text(traceback, encoding='utf-8')


def save_output(cell_idx, cell):
    """
    Save the output of a notebook cell to files.
    Args:
        cell_idx (int): The index of the cell in the notebook.
        cell (nbformat.NotebookNode): The cell to save.
    """
    # If the cell has a metadata field 'grade_id', use it as the file name
    grade_id = cell.metadata.get('grade_id')
    if grade_id:
        file_name = f"{grade_id}"
    else:
        file_name = f"cell{cell_idx}"

    # Create an empty error file for the cell
    cell_err = Path(f"{file_name}.err")
    cell_err.write_text("", encoding='utf-8')

    # Handle different output types
    # https://nbformat.readthedocs.io/en/latest/format_description.html#cell-types
    if cell.cell_type == 'markdown':
        cell_txt = Path(f"{file_name}.txt")
        cell_txt.write_text(cell.source.strip(), encoding='utf-8')

    elif cell.cell_type == 'code':
        source_code = cell.source.strip()
        if source_code:
            cell_txt = Path(f"{file_name}_source.txt")
            cell_txt.write_text(source_code, encoding='utf-8')

        for output in cell.get('outputs', []):
            save_code_output_cell(output, file_name)


if __name__ == "__main__":
    parser = argparse.ArgumentParser(
        description="Execute a Jupyter notebook and save outputs."
    )
    parser.add_argument(
        '-i', '--input',
        required=True,
        help="Path to Jupyter notebook file to execute."
    )

    parser.add_argument(
        '-o', '--output',
        required=True,
        help="Path to save the executed notebook."
    )

    parser.add_argument(
        '-t', '--timeout',
        type=int,
        default=600,
        help="Maximum time in seconds to execute a cell"
    )

    args = parser.parse_args()

    notebook_files = glob.glob(args.input)

    if not notebook_files:
        raise FileNotFoundError(f"No Jupyter notebook files found named {args.input}")
    if len(notebook_files) > 1:
        raise ValueError(f"Multiple Jupyter notebook files found for '{args.input}'. "
                         "Please ensure there is only one file matching the pattern.")

    execute_notebook(notebook_files[0], args.output, args.timeout)
