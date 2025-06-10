import os
import sys
import nbformat
from nbconvert.preprocessors import ExecutePreprocessor, CellExecutionError
from nbconvert import HTMLExporter
import matplotlib.pyplot as plt
import matplotlib.image as img
import base64

# Execution API: https://nbconvert.readthedocs.io/en/latest/execute_api.html
def execute_notebook(notebook_path, timeout=600):

    notebook_filename = os.path.split(notebook_path)[-1]
    notebook_filename_out = ''.join(notebook_filename.split('.')[0]) + '_executed.ipynb'

    with open(notebook_path) as f:
        # Load the notebook and do not convert it to a specific version
        nb = nbformat.read(f, as_version=nbformat.NO_CONVERT)

    # Sets preprocessor with the following parameters:
    # - timeout: maximum time in seconds to execute a cell
    # - kernel_name: the kernel to use for execution (e.g., 'python3')
    # - allow_errors: if True, allows the execution to continue even if a cell raises an error
    ep = ExecutePreprocessor(timeout=timeout, kernel_name='python3', allow_errors=False)

    try:
        out = ep.preprocess(nb, {'metadata': {'path': os.path.join(*os.path.split(notebook_path)[:-1])}})
    # Only if we do not set allow_errors=True, we get a CellExecutionError
    except CellExecutionError:
        out = None
        msg = 'Error executing the notebook "%s".\n\n' % notebook_filename
        msg += 'See notebook "%s" for the traceback.' % notebook_filename_out
        print(msg)
        raise
    
    with open(notebook_filename_out, mode='w', encoding='utf-8') as f:  
        nbformat.write(nb, f)

    return notebook_filename_out

# Different cell types: https://nbformat.readthedocs.io/en/latest/format_description.html#cell-types
def parse_notebook(notebook_path):
    with open(notebook_path, 'r', encoding='utf-8') as f:
        nb = nbformat.read(f, as_version=nbformat.NO_CONVERT)

    outputs = []
    for cell in nb.cells:
        if cell.cell_type == 'code':
            cell_outputs = []
            for output in cell.get('outputs', []):
                if output.output_type == 'stream':
                    cell_outputs.append(output.get('text', ''))
                elif output.output_type == 'execute_result' or output.output_type == 'display_data':
                    cell_outputs.append(output.get('data', {}))
                elif output.output_type == 'error':
                    cell_outputs.append(output.get('traceback', []))
            outputs.append(cell_outputs)

    return outputs

def check_output(output_cells):
    # jupyter notebook parsing includes newline and print also creates a newline
    print(output_cells[0][0].strip(), end='')

    img_one = base64.b64decode(output_cells[1][0]['image/png'])
    with open("one_submitted.png", "wb") as f:
        f.write(img_one)

    img_two = base64.b64decode(output_cells[2][0]['image/png'])
    with open("two_submitted.png", "wb") as f:
        f.write(img_two)

    graph = base64.b64decode(output_cells[3][0]['image/png'])
    with open("graph_submitted.png", "wb") as f:
        f.write(graph)

    circuit = base64.b64decode(output_cells[4][0]['image/png'])
    with open("circuit_submitted.png", "wb") as f:
        f.write(circuit)

    histogram = base64.b64decode(output_cells[5 ][0]['image/png'])
    with open("histogram_submitted.png", "wb") as f:
        f.write(histogram)

if __name__ == "__main__":
    notebook_path = sys.argv[1]
    notebook_executed = execute_notebook(notebook_path)
    output_cells = parse_notebook(notebook_executed)
    check_output(output_cells)