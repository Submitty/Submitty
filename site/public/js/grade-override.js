/* global loadOverriddenGrades */

function setCookie(name, value, days) {
    Cookies.set(name, value, { expires: days, path: '/' });
}

function getCookie(name) {
    return Cookies.get(name) || '';
}

function checkSelectedGradeable() {
    const selectedGradable = getCookie('selectedGradable');
    if (selectedGradable) {
        $('#g_id').val(selectedGradable);
        loadOverriddenGrades(selectedGradable);
    }
}

$(document).ready(() => {
    checkSelectedGradeable();
    $('#g_id').change(function () {
        const selectedGradable = $(this).val();
        if (selectedGradable) {
            setCookie('selectedGradable', selectedGradable, 365);
            loadOverriddenGrades(selectedGradable);
        }
    });
});
