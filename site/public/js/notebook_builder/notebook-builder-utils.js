/**
 * Asynchronous upload of configuration dependency file.
 * These would typically be files that go in the 'test_input' or 'test_output' directory
 *
 * @param {File} file The file to be uploaded.
 * @param {string} g_id Gradeable ID
 * @param {string} directory The directory the file should be uploaded into.  Example would be 'test_input' or
 *                           'test_output'.
 * @returns {Promise}
 */
async function uploadFile(file, g_id, directory) {
    const url = buildCourseUrl(['notebook_builder', 'file']);

    const form_data = new FormData();
    form_data.append('csrf_token', csrfToken);
    form_data.append('g_id', g_id);
    form_data.append('directory', directory);
    form_data.append('operation', 'upload');
    form_data.append('file', file, file.name);

    const response = await fetch(url, {method: 'POST', body: form_data});
    const result = await response.json();

    if (result.status === 'success') {
        console.log(`Successfully uploaded ${file.name}.`);
    }
    else {
        displayErrorMessage(`An error occurred uploading ${file.name}.`);
        console.error(result.message);
    }
}

/**
 * From an array of html file selector inputs, collect files that have been selected by the user, and upload them
 * asynchronously to the server.
 *
 * @param {Array.<HTMLInputElement>} file_selectors
 * @param {string} g_id Gradeable ID
 * @param {string} directory A subdirectory inside the autograding configuration directory the file should be uploaded
 *                           to.
 * @returns {Promise}
 */
async function uploadFiles(file_selectors, g_id, directory) {
    for (const file_input of file_selectors) {
        if (file_input.files[0]) {
            await uploadFile(file_input.files[0], g_id, directory);
        }
    }
}

/**
 * Determine if all itempool item widgets contain a non-blank and unique 'item_name'.
 *
 * @returns {String[]} Return an array containing invalid item names, or an empty array of none were found.
 */
function getBadItemNames() {
    const used_item_names = new Set();
    const bad_item_names = new Set();

    const item_name_inputs = document.querySelectorAll('.item-name-input');
    item_name_inputs.forEach(item_name_input => {
        if (item_name_input.value === '' || used_item_names.has(item_name_input.value)) {
            bad_item_names.add(item_name_input.value);
        }

        used_item_names.add(item_name_input.value);
    });

    return Array.from(bad_item_names);
}
