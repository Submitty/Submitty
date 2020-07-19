/**
 * This file provides helper functions to be used with notebook builder.
 */

/**
 * Asynchronous upload of configuration dependency file.
 * These would be files that go in the 'test_input' or 'test_output' directory
 *
 * @param file File to be uploaded when operation is upload
 * @param file_name Name of the file to be deleted when operation is delete
 * @param g_id Gradeable ID
 * @param dependency_type Type of dependency, can be either 'input' or 'output'.  Used to decide which folder the file
 *                        should go in.
 * @param operation May be either 'upload' or 'delete'
 * @returns {Promise}
 */
async function syncFile(file, file_name, g_id, dependency_type, operation) {
    const url = buildCourseUrl(['notebook_builder', 'file']);

    const form_data = new FormData();
    form_data.append('csrf_token', csrfToken);
    form_data.append('g_id', g_id);
    form_data.append('dependency_type', dependency_type);
    form_data.append('operation', operation);

    if (operation === 'upload' && file) {
        form_data.append('file', file, file.name);
    }
    else if (operation === 'delete' && file_name) {
        form_data.append('file_name', file_name);
    }
    else {
        throw 'Invalid parameters! Ensure file is valid for uploads, or file_name is valid when deleting.';
    }

    const response = await fetch(url, {method: 'POST', body: form_data});
    const result = await response.json();

    if (result.status === 'success') {
        console.log('File operation successful.');
    }
    else {
        displayErrorMessage(`An error occurred performing ${operation} on file.`);
        console.error(result.message);
    }
}
