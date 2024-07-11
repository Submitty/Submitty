function setCookie(name, value, days) {
    const d = new Date();
    d.setTime(d.getTime() + (days * 24 * 60 * 60 * 1000));
    const expires = "expires=" + d.toUTCString();
    document.cookie = name + "=" + value + ";" + expires + ";path=/";
}

function getCookie(name) {
    const cname = name + "=";
    const decodedCookie = decodeURIComponent(document.cookie);
    const ca = decodedCookie.split(';');
    for (let i = 0; i < ca.length; i++) {
        let c = ca[i];
        while (c.charAt(0) === ' ') {
            c = c.substring(1);
        }
        if (c.indexOf(cname) === 0) {
            return c.substring(cname.length, c.length);
        }
    }
    return "";
}

function checkSelectedGradeable() {
    const selectedGradable = getCookie("selectedGradable");
    if (selectedGradable) {
        $('#g_id').val(selectedGradable);
        loadOverriddenGrades(selectedGradable);
    }
}

$(document).ready(function() {
    checkSelectedGradeable();
    $('#g_id').change(function() {
        const selectedGradable = $(this).val();
        if (selectedGradable) {
            setCookie("selectedGradable", selectedGradable, 365);
            loadOverriddenGrades(selectedGradable);
        }
    });
});
