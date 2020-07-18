/**
 * This file provides helper functions to be used with notebook builder.
 */

/**
 * Asynchronous upload of configuration dependency file.
 * These would be files that go in the 'test_input' or 'test_output' directory
 *
 * @param file File to be uploaded
 * @param g_id Gradeable ID
 * @param dependency_type Type of dependency, can be either 'input' or 'output'.  Used to decide which folder the file
 *                        should go in.
 * @returns {Promise}
 */
async function uploadFile(file, g_id, dependency_type) {
    const url = buildCourseUrl(['notebook_builder', 'file']);

    const form_data = new FormData();
    form_data.append('file', file, file.name);
    form_data.append('csrf_token', csrfToken);
    form_data.append('g_id', g_id);
    form_data.append('dependency_type', dependency_type);
    form_data.append('operation', 'upload');

    const response = await fetch(url, {method: 'POST', body: form_data});
    const result = await response.json();

    if (result.status === 'success') {
        console.log('File upload successful.');
    }
    else {
        displayErrorMessage(`An error occurred uploading file ${file.name}`);
        console.error(result.message);
    }
}
