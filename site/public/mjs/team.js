export function showUpdateTeamName() {
    $('.popup-form').css('display', 'none');
    const form = $('#edit-team-name-form');
    form.css('display', 'block');
    form.find('.form-body').scrollTop(0);
}

export function init() {
    document.getElementById('team-name-change-icon').addEventListener('click', showUpdateTeamName);
}

document.addEventListener('DOMContentLoaded', () => init());
