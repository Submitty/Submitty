
export function applySettings() {
    const grad_acc = Date.parse(document.getElementById('gradeable_access_date').value);
    const grad_sub = Date.parse(document.getElementById('gradeable_submission_date').value);
    const forum_view = Date.parse(document.getElementById('forum_view_date').value);
    const forum_post = Date.parse(document.getElementById('forum_post_date').value);
    const num_poll = parseInt(document.getElementById('num_poll_responses').value);
    const off_hours = Date.parse(document.getElementById('office_hours_queue_date').value);
    const course_mat = Date.parse(document.getElementById('course_materials_access').value);
    const data = JSON.parse(document.getElementById('data').getAttribute('data-original'));
    const table = document.getElementById('data-table');
    const rows = table.rows;
    for (let i = 0; i < data.length; i++) {
        const s_grad_acc = data[i].gradeable_access;
        const s_grad_sub = data[i].gradeable_submission;
        const s_forum_view = data[i].forum_view;
        const s_forum_post = data[i].forum_post;
        let s_num_polls = data[i].num_poll_responses;
        const s_off_hours = data[i].office_hours_queue;
        const s_course_mat = data[i].course_materials_access;

        if (s_num_polls === null) {
            s_num_polls = 0;
        }

        let flag = false;
        // eslint-disable-next-line eqeqeq
        if ((!Number.isNaN(grad_acc) && s_grad_acc == null) || Date.parse(s_grad_acc) < grad_acc) {
            flag = true;
        }
        // eslint-disable-next-line eqeqeq
        else if ((!Number.isNaN(grad_sub) && s_grad_sub == null) || Date.parse(s_grad_sub) < grad_sub) {
            flag = true;
        }
        // eslint-disable-next-line eqeqeq
        else if ((!Number.isNaN(forum_view) && s_forum_view == null) || Date.parse(s_forum_view) < forum_view) {
            flag = true;
        }
        // eslint-disable-next-line eqeqeq
        else if ((!Number.isNaN(forum_post) && s_forum_post == null) || Date.parse(s_forum_post) < forum_post) {
            flag = true;
        }
        else if (!Number.isNaN(num_poll) && parseInt(s_num_polls) < num_poll) {
            flag = true;
        }
        // eslint-disable-next-line eqeqeq
        else if ((!Number.isNaN(off_hours) && s_off_hours == null) || Date.parse(s_off_hours) < off_hours) {
            flag = true;
        }
        // eslint-disable-next-line eqeqeq
        else if ((!Number.isNaN(course_mat) && s_course_mat == null) || Date.parse(s_course_mat) < course_mat) {
            flag = true;
        }
        else {
            rows[i + 1].getElementsByTagName('TD')[11].innerText = 'False';
            document.getElementById(data[i].user_id).style.backgroundColor = 'green';
        }
        if (flag) {
            document.getElementById(data[i].user_id).style.backgroundColor = 'red';
            rows[i + 1].getElementsByTagName('TD')[11].innerText = 'True';
        }
    }
}

export function clearFields() {
    document.getElementById('gradeable_access_date').value = '';
    document.getElementById('gradeable_submission_date').value = '';
    document.getElementById('forum_view_date').value = '';
    document.getElementById('forum_post_date').value = '';
    document.getElementById('num_poll_responses').value = '';
    document.getElementById('office_hours_queue_date').value = '';
    document.getElementById('course_materials_access').value = '';
    applySettings();
    const table = document.getElementById('data-table');
    const data = JSON.parse(document.getElementById('data').getAttribute('data-original'));
    const rows = table.rows;
    for (let i = 0; i < data.length; i++) {
        rows[i + 1].getElementsByTagName('TD')[11].innerText = '';
        document.getElementById(data[i].user_id).style.backgroundColor = '';
    }
}

export function init() {
    document.getElementById('clear-btn').addEventListener('click', () => clearFields());
    document.getElementById('apply-btn').addEventListener('click', () => applySettings());
}

document.addEventListener('DOMContentLoaded', () => init());
