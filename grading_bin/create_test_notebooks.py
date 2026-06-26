import nbformat

# Normal instructor notebook
instructor_nb = nbformat.v4.new_notebook()
normal_cell = nbformat.v4.new_code_cell(source="print('student cell')")
normal_cell.id = "student123"
hidden_cell = nbformat.v4.new_code_cell(source="print('hidden grading cell')")
hidden_cell.id = "hidden123"
hidden_cell.metadata['submitty_id'] = 'checkpoint_1'
instructor_nb.cells = [normal_cell, hidden_cell]
instructor_nb.nbformat_minor = 5
with open('instructor.ipynb', 'w') as f:
    nbformat.write(instructor_nb, f)

# Normal student notebook (missing hidden cell)
student_nb = nbformat.v4.new_notebook()
student_cell = nbformat.v4.new_code_cell(source="print('student cell')")
student_cell.id = "student123"
student_nb.cells = [student_cell]
student_nb.nbformat_minor = 5
with open('student_normal.ipynb', 'w') as f:
    nbformat.write(student_nb, f)

# Student with grade_id set
student_gradeid_nb = nbformat.v4.new_notebook()
bad_cell = nbformat.v4.new_code_cell(source="print('student cell')")
bad_cell.id = "student123"
bad_cell.metadata['grade_id'] = 'checkpoint_1'
student_gradeid_nb.cells = [bad_cell]
student_gradeid_nb.nbformat_minor = 5
with open('student_gradeid.ipynb', 'w') as f:
    nbformat.write(student_gradeid_nb, f)

# Instructor notebook with cell missing submitty_id
instructor_nosid_nb = nbformat.v4.new_notebook()
no_sid_cell = nbformat.v4.new_code_cell(source="print('no submitty_id')")
no_sid_cell.id = "nosid123"
instructor_nosid_nb.cells = [no_sid_cell]
instructor_nosid_nb.nbformat_minor = 5
with open('instructor_nosid.ipynb', 'w') as f:
    nbformat.write(instructor_nosid_nb, f)
