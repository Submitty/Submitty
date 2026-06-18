/* global loadOverriddenGrades */

function checkSelectedGradeable() {
    const selectedGradable = Cookies.get('selectedGradable') || '';
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
            Cookies.set('selectedGradable', selectedGradable, { expires: 365, path: '/' });
            loadOverriddenGrades(selectedGradable);
        }
    });
});
