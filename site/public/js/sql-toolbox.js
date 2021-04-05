/* global buildCourseUrl, csrfToken */
/* exported runSqlQuery */

async function runSqlQuery() {
    document.getElementById('query-results').style.display = 'block';

    const formData = new FormData();
    formData.append('csrf_token', csrfToken);
    formData.append('sql', document.querySelector('[name="sql"]').value);

    try {
        const resp = await fetch(
            buildCourseUrl(['sql_toolbox']),
            {
                method: 'POST',
                body: formData,
            },
        );

        const json = await resp.json();
        console.log(json);
        const error = document.getElementById('query-results-error');
        const table = document.getElementById('query-results');
        table.innerHTML = '';

        if (json.status !== 'success') {
            error.innerText = json.message;
            error.style.display = 'block';
            return;
        }

        error.style.display = 'none';

        const data = json.data;

        const header = document.createElement('thead');
        const headerRow = document.createElement('tr');
        Object.keys(data[0]).forEach((col) => {
            const cell = document.createElement('td');
            cell.innerText = col;
            headerRow.appendChild(cell);
        });
        header.appendChild(headerRow);
        table.appendChild(header);
        const body = document.createElement('tbody');
        data.forEach((row) => {
            const bodyRow = document.createElement('tr');
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
    }
}
