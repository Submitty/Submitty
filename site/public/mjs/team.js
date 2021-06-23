//import { buildCourseUrl, getCsrfToken, displayErrorMessage } from './server.js';

/*export function updateTeamName(event) {
    event.preventDefault();
    const team_name_element = document.getElementById('team-name-change');
    const team_name = team_name_element.value;
    if (team_name !== team_name_element.dataset.currentName) {
        const csrf = getCsrfToken();
        const url = buildCourseUrl(['gradeable', '', 'team', 'setname']);
        const data = new FormData();
        data.append('csrf_token', csrf);
        data.append('team_name', team_name);
        $.ajax({
            url,
            type: 'POST',
            data,
            processData: false,
            contentType: false,
            success: function (res) {
                const response = JSON.parse(res);
                console.log(response);
            },
        });
    }
    else {
        displayErrorMessage('No changes detected to team name');
    }
    $('.popup-form').css('display', 'none');
}*/

export function showUpdateTeamName() {
    $('.popup-form').css('display', 'none');
    const form = $('#edit-team-name-form');
    form.css('display', 'block');
    form.find('.form-body').scrollTop(0);
}

export function init() {
    document.getElementById('team-name-change-icon').addEventListener('click', showUpdateTeamName);
    //document.getElementById('team-name-form').addEventListener('submit', updateTeamName);
}

document.addEventListener('DOMContentLoaded', () => init());
