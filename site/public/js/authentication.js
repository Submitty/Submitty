/* exported showRequirements  */
/* imported displayErrorMessage */
function showRequirements(id_string) {
    $(`#${id_string}-helper`).toggle();
}

function checkPasswordsMatch() {
    console.log($('#password-input').val() == $('#confirm-password-input').val() );
    if ($('#password-input').val() != $('#confirm-password-input').val()) {
        $('#confirm-password-input').val('');
        displayErrorMessage('Passwords do not match')
    }
}