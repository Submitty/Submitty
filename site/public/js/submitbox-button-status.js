/* exported updateSubmitButtonStatus */
/* global isValidSubmission, setButtonStatus */

const orig_setButtonStatus = window.setButtonStatus;

window.setButtonStatus = function (inactive_version = false) {
    orig_setButtonStatus(inactive_version);
    updateSubmitButtonStatus();
};

function updateSubmitButtonStatus() {
    const submit_button = document.getElementById('submit');
    if (!isValidSubmission()) {
        submit_button.classList.add('disable-submit');
    }
    else {
        submit_button.classList.remove('disable-submit');
    }
}
