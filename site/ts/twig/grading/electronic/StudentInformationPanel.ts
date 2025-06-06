function checkTaVersionChange() {
    const message = 'You are overriding the student\'s chosen submission. Are you sure you want to continue?';
    return confirm(message);
}

$(() => {
    $('#student-info-ta-version-form').on('submit', () => {
        return checkTaVersionChange();
    });
});
