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
            document.getElementById('download-sql-btn').disabled = true;
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
        document.getElementById('download-sql-btn').disabled = false;
    }
    catch (exc) {
        console.error(exc);
        alert(exc.toString());
        document.getElementById('download-sql-btn').disabled = true;
    }
}

export function generateCSV(id){
    const results = document.getElementById(id);
    let csv = '';
    //Add headers to CSV string
    const header = results.children.item(0);
    const row = header.children.item(0);
    for (let i = 1; i < row.children.length; i++){
        csv += `"${row.children.item(i).textContent.split('"').join('""')}",`;
    }
    csv += '\n';

    //Add data to CSV string
    const data = results.children.item(1);
    for (let i = 0; i < data.children.length; i++){
        const row = data.children.item(i);
        for (let j = 1; j < row.children.length; j++){
            csv += `"${row.children.item(j).textContent.split('"').join('""')}",`;
        }
        csv += '\n';
    }
    return csv;
}

export async function downloadSqlResult(id){
    const csv = generateCSV(id);
    //Encode and download the CSV string
    const address = `data:text/csv;charset=utf-8,${encodeURIComponent(csv)}`;
    const filename = 'submitty.csv';
    const temp_element = document.createElement('a');
    temp_element.setAttribute('href', address);
    temp_element.setAttribute('download', filename);
    temp_element.style.display = 'none';
    document.body.appendChild(temp_element);
    temp_element.click();
    document.body.removeChild(temp_element);
}

export function init() {
    document.getElementById('run-sql-btn').addEventListener('click', () => runSqlQuery());
    document.getElementById('download-sql-btn').addEventListener('click', () => downloadSqlResult('query-results'));
}

/* istanbul ignore next */
document.addEventListener('DOMContentLoaded', () => init());
