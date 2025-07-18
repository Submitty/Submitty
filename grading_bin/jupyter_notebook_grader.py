from pathlib import Path
import nbformat
from nbconvert.preprocessors import ClearOutputPreprocessor, ExecutePreprocessor, CellExecutionError
import base64

# Execution API: https://nbconvert.readthedocs.io/en/latest/execute_api.html
def execute_notebook(timeout=600):
    
    notebook_files = list(Path('.').glob('*.ipynb'))
    if not notebook_files:
        raise FileNotFoundError("No Jupyter notebook files found in the current directory.")
    if len(notebook_files) > 1:
        raise ValueError("Multiple Jupyter notebook files found. Please ensure only one notebook file is present in the current directory.")
    
    notebook_filename = Path(notebook_files[0])

    with open(notebook_filename) as f:
        # Load the notebook and do not convert it to a specific version
        nb = nbformat.read(f, as_version=nbformat.NO_CONVERT)

    # Clear output of notebook
    cop = ClearOutputPreprocessor()
    nb, _ = cop.preprocess(nb, {})

    # Sets preprocessor with the following parameters:
    # - timeout: maximum time in seconds to execute a cell
    # - kernel_name: the kernel to use for execution (e.g., 'python3')
    # - allow_errors: if True, allows the execution to continue even if a cell raises an error (Optional, defaults to False)
    ep = ExecutePreprocessor(timeout=timeout, kernel_name='python3', allow_errors=True)

    try:
        out = ep.preprocess(nb, {'metadata': {'path': notebook_filename.parent}})
    # CellExecutionError is raised if a cell execution fails and allow_errors is False
    except CellExecutionError:
        out = None
        msg = 'Error executing the notebook "%s".\n\n' % notebook_filename
        msg += 'See notebook executed.ipynb for the traceback.'
        print(msg)
        raise
    
    with open("executed.ipynb", mode='w', encoding='utf-8') as f:  
        nbformat.write(nb, f)

    for cell_idx, cell in enumerate(nb.cells):
        save_output(cell_idx, cell)
    
def save_output(cell_idx, cell):
    # If the cell has a metadata field 'grade_id', use it as the file name
    grade_id = cell.metadata.get('grade_id')
    if grade_id:
        file_name = f"{grade_id}"
    else:
        file_name = f"cell{cell_idx}"

    # Create an empty error file for the cell
    cell_err = Path(f"{file_name}.err")
    cell_err.write_text("", encoding='utf-8')

    # Handle different output types https://nbformat.readthedocs.io/en/latest/format_description.html#cell-types
    if cell.cell_type == 'markdown':
        cell_txt = Path(f"{file_name}.txt")
        cell_txt.write_text(cell.source.strip(), encoding='utf-8')

    elif cell.cell_type == 'code':
        source_code = cell.source.strip()
        if source_code:
            cell_txt = Path(f"{file_name}_source.txt")
            cell_txt.write_text(source_code, encoding='utf-8')

        for output in cell.get('outputs', []):
            if output.output_type == 'stream':
                if output.name == 'stdout':
                    cell_txt = Path(f"{file_name}_stdout.txt")
                    cell_txt.write_text(output.text.strip(), encoding='utf-8')

                if output.name == 'stderr':
                    cell_txt = Path(f"{file_name}_stderr.txt")
                    cell_txt.write_text(output.text.strip(), encoding='utf-8')

            elif output.output_type == 'execute_result' or output.output_type == 'display_data':
                data = output.get("data", {})
                if "text/plain" in data:
                    cell_txt = Path(f"{file_name}_stdout.txt")
                    cell_txt.write_text(data["text/plain"], encoding='utf-8')

                if "image/png" in data:
                    img_data = base64.b64decode(data["image/png"])
                    img = Path(f"{file_name}.png")
                    img.write_bytes(img_data)

            elif output.output_type == 'error':
                traceback = "\n".join(output.get("traceback", []))
                cell_err = Path(f"{file_name}.err")
                cell_err.write_text(traceback, encoding='utf-8')

if __name__ == "__main__":
    execute_notebook()