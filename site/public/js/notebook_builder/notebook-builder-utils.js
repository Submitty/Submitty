/**
 * This file provides helper functions to be used with notebook builder.
 */

/**
 * Asynchronous upload of configuration dependency file.
 * These would be files that go in the 'test_input' or 'test_output' directory
 *
 * @param {File} file File to be uploaded
 * @param {string} g_id Gradeable ID
 * @param {string} dependency_type Type of dependency, can be either 'input' or 'output'.  Used to decide which folder
 *                                 the file should go in.
 * @returns {Promise}
 */
async function uploadFile(file, g_id, dependency_type) {
    await syncFile(file, null, g_id, dependency_type, 'upload');
}

/**
 * Asynchronous deletion of configuration dependency file.
 * These would be files that go in the 'test_input' or 'test_output' directory
 *
 * @param {string} file_name File name of file to be deleted
 * @param {string} g_id Gradeable ID
 * @param {string} dependency_type Type of dependency, can be either 'input' or 'output'.  Used to decide which folder
 *                                 the file is in.
 * @returns {Promise}
 */
async function deleteFile(file_name, g_id, dependency_type) {
    await syncFile(null, file_name, g_id, dependency_type, 'delete');
}

/**
 * Asynchronous sync of configuration dependency file.
 * These would be files that go in the 'test_input' or 'test_output' directory
 *
 * Avoid calling this function directly and instead call one of the wrapper functions uploadFile() or deleteFile().
 *
 * @param {File|null} file The file to be uploaded, or may be null if the operation is delete
 * @param {string|null} file_name The file name of the file to be deleted, or may be null if the operation is upload
 * @param {string} g_id Gradealbe ID
 * @param {string} dependency_type Type of dependency, can be either 'input' or 'output'.  Used to decide which folder
 *                                 the file should go in.
 * @param {string} operation The operation to perform, may be either 'upload' or 'delete'.
 * @returns {Promise}
 */
async function syncFile(file, file_name, g_id, dependency_type, operation) {
    const url = buildCourseUrl(['notebook_builder', 'file']);
    let display_file_name;

    const form_data = new FormData();
    form_data.append('csrf_token', csrfToken);
    form_data.append('g_id', g_id);
    form_data.append('dependency_type', dependency_type);
    form_data.append('operation', operation);

    if (operation === 'upload' && file) {
        form_data.append('file', file, file.name);
        display_file_name = file.name;
    }
    else if (operation === 'delete' && file_name) {
        form_data.append('file_name', file_name);
        display_file_name = file_name;
    }
    else {
        throw 'Invalid parameters! Ensure file is valid for uploads, or file_name is valid when deleting.';
    }

    const response = await fetch(url, {method: 'POST', body: form_data});
    const result = await response.json();

    if (result.status === 'success') {
        console.log(`Operation '${operation}' performed successfully on file ${display_file_name}.`);
    }
    else {
        displayErrorMessage(`An error occurred performing ${operation} on file ${display_file_name}.`);
        console.error(result.message);
    }
}
