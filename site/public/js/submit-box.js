/* exported updateSubmitButtonStatus */
/* global file_array, changed, previous_files */

const orig_setButtonStatus = setButtonStatus;
function setButtonStatus(inactive_version = false) {
    updateSubmitButtonStatus();
    orig_setButtonStatus(inactive_version);
}

function updateSubmitButtonStatus() {
    let valid = false;
    // check if new files added
    for (let i = 0; i < file_array.length; i++) {
        if (file_array[i].length !== 0) {
            valid = true;
        }
    }
    // check if files from previous submission changed
    if (!valid && changed) {
        for (let j = 0; j < previous_files.length; j++) {
            if (previous_files[j] !== 0) {
                valid = true;
            }
        }
    }
    if (!valid && Object.prototype.hasOwnProperty.call(window, 'is_notebook')) {
        valid = true;
    }
    const submit_button = document.getElementById('submit');
    if (!valid) {
        submit_button.classList.add('disable-submit');
    }
    else {
        submit_button.classList.remove('disable-submit');
    }
}
