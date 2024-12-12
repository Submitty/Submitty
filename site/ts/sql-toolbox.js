import { buildCourseUrl, getCsrfToken } from './utils/server';

export async function runSqlQuery() {
    document.getElementById('query-results').style.display = 'block';
    document.getElementById('download-sql-btn').disabled = true;

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
            cell.textContent = idx + 1;
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
    }
}

export function generateCSV(id) {
    const results = document.getElementById(id);
    let csv = '';
    // Add headers to CSV string
    const header = results.children.item(0);
    const row = header.children.item(0);
    for (let i = 1; i < row.children.length; i++) {
        csv += `"${row.children.item(i).textContent.split('"').join('""')}",`;
    }
    csv += '\n';

    // Add data to CSV string
    const data = results.children.item(1);
    for (let i = 0; i < data.children.length; i++) {
        const row = data.children.item(i);
        for (let j = 1; j < row.children.length; j++) {
            csv += `"${row.children.item(j).textContent.split('"').join('""')}",`;
        }
        csv += '\n';
    }
    return csv;
}

export async function downloadSqlResult(id) {
    const csv = generateCSV(id);
    // Encode and download the CSV string
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

async function getQueries() {
    try {
        const response = await fetch(buildCourseUrl(['getQueries']), {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            },
        });

        const json = await response.json();
        if (!response.ok) {
            console.log(json);
            throw new Error(`HTTP error! Status: ${response.status}`);
        }
        console.log(json.data);

        const buttonContainer = document.getElementById('saved-queries');
        buttonContainer.innerHTML = '';
        // Create buttons for each retrieved query
        for (let i = 0; i < json.data.length; i += 2) {
            const name = json.data[i];
            const query = json.data[i + 1];
            console.log(name);
            const button = document.createElement('button');
            button.className = 'btn btn-primary';
            button.textContent = json.data[i];
            button.dataset.value = json.data[i + 1];
            button.addEventListener('click', () => {
                const associatedQuery = button.dataset.value;
                document.querySelector('[name="sql"]').textContent = associatedQuery;
            });
            buttonContainer.appendChild(button);
        }
    }
    catch (error) {
        /*console.error('Error fetching queries: ', error);*/
    }
}

export function init() {
    getQueries();
    document.getElementById('run-sql-btn').addEventListener('click', () => runSqlQuery());
    document.getElementById('sql-database-schema').addEventListener('click', () => {
        document.getElementById('sql-database-schema-content').style.display
            = document.getElementById('sql-database-schema-content').style.display === 'block'
                ? 'none'
                : 'block';
    });
    document.querySelectorAll('.sql-database-table').forEach((elem) => {
        elem.addEventListener('click', (e) => {
            e.target.nextElementSibling.style.display
                = e.target.nextElementSibling.style.display === 'block'
                    ? 'none'
                    : 'block';
        });
    });
    window.addEventListener('DOMContentLoaded', () => {
        document.getElementById('save-sql-btn').addEventListener('click', async () => {
            const form_data = new FormData();
            form_data.append('csrf_token', getCsrfToken());
            form_data.append('sql', document.querySelector('[name="saved-sql"]').value);
            form_data.append('name', document.querySelector('[name="saved-name"]').value);
            try {
                const resp = await fetch(
                    buildCourseUrl(['saveQuery']),
                    {
                        method: 'POST',
                        body: form_data,
                    },
                );
            }
            catch (exc) {
                console.error(exc);
            }
            getQueries();
        });
        document.getElementById('remove-sql-btn').addEventListener('click', async () => {
            const form_data = new FormData();
            form_data.append('csrf_token', getCsrfToken());
            form_data.append('name', document.querySelector('[name="removal-name"]').value);
            try {
                const resp = await fetch(
                    buildCourseUrl(['removeQuery']),
                    {
                        method: 'POST',
                        body: form_data,
                    },
                );
            }
            catch (exc) {
                console.error(exc);
            }
            getQueries();
        });
    });
    document.getElementById('download-sql-btn').addEventListener('click', () => downloadSqlResult('query-results'));
}

/* istanbul ignore next */
document.addEventListener('DOMContentLoaded', () => init());
