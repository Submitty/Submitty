
import { buildCourseUrl, getCsrfToken } from './server.js';

export async function runSqlQuery() {
    document.getElementById('query-results').style.display = 'block';

    const form_data = new FormData();
    form_data.append('csrf_token', getCsrfToken());
    form_data.append('sql', document.querySelector('[name="sql"]').value);

    try {
        const resp = await fetch(
            buildCourseUrl(['sql_toolbox']),
            {
                method: 'POST',
                body: form_data,
            },
        );

        const json = await resp.json();
        const error = document.getElementById('query-results-error');
        const error_mesage = document.getElementById('query-results-error-message');
        const table = document.getElementById('query-results');
        table.innerHTML = '';

        if (json.status !== 'success') {
            error_mesage.textContent = json.message;
            error.style.display = 'block';
            return;
        }

        error.style.display = 'none';

        const data = json.data;

        if (data.length === 0) {
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.textContent = 'No rows returned';
            row.appendChild(cell);
            table.appendChild(row);
            return;
        }

        const header = document.createElement('thead');
        const header_row = document.createElement('tr');
        const cell = document.createElement('td');
        cell.textContent = '#';
        header_row.appendChild(cell);
        Object.keys(data[0]).forEach((col) => {
            const cell = document.createElement('td');
            cell.textContent = col;
            header_row.appendChild(cell);
        });
        header.appendChild(header_row);
        table.appendChild(header);
        const body = document.createElement('tbody');
        data.forEach((row, idx) => {
            const bodyRow = document.createElement('tr');
            const cell = document.createElement('td');
            cell.textContent = idx+1;
            bodyRow.appendChild(cell);
            Object.values(row).forEach((val) => {
                const cell = document.createElement('td');
                cell.textContent = val;
                bodyRow.appendChild(cell);
            });
            body.appendChild(bodyRow);
        });
        table.appendChild(body);
    }
    catch (exc) {
        console.error(exc);
        alert(exc.toString());
    }
}

export function init() {
    document.getElementById('run-sql-btn').addEventListener('click', () => runSqlQuery());
}

document.addEventListener('DOMContentLoaded', () => init());
