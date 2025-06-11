/* exported updateSubmitButtonStatus */
/* global file_array, changed, previous_files setButtonStatus */

const orig_setButtonStatus = window.setButtonStatus;

window.setButtonStatus = function (inactive_version = false) {
    orig_setButtonStatus(inactive_version);
    updateSubmitButtonStatus();
};

function updateSubmitButtonStatus() {
    const submit_button = document.getElementById('submit');
    if (submit_button.disabled) {
        submit_button.classList.add('disable-submit');
    }
    else {
        submit_button.classList.remove('disable-submit');
    }
}
