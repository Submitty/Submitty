/* exported updateSubmitButtonStatus */
/* global file_array, changed, previous_files setButtonStatus */

const orig_setButtonStatus = window.setButtonStatus;

window.setButtonStatus = function (inactive_version = false) {
    console.log(0.5);
    orig_setButtonStatus(inactive_version);
    console.log(1);
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
