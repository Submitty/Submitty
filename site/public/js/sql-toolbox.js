/* global buildCourseUrl, csrfToken */
/* exported runSqlQuery */
/* exported downloadSqlResult */

async function runSqlQuery() {
    document.getElementById('query-results').style.display = 'block';

    const form_data = new FormData();
    form_data.append('csrf_token', csrfToken);
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
            error_mesage.innerText = json.message;
            error.style.display = 'block';
            return;
        }

        error.style.display = 'none';

        const data = json.data;

        if (data.length === 0) {
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.innerText = 'No rows returned';
            row.appendChild(cell);
            table.appendChild(row);
            return;
        }

        const header = document.createElement('thead');
        const header_row = document.createElement('tr');
        const cell = document.createElement('td');
        cell.innerText = '#';
        header_row.appendChild(cell);
        Object.keys(data[0]).forEach((col) => {
            const cell = document.createElement('td');
            cell.innerText = col;
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
    }
}

async function downloadSqlResult(){
    const results = document.getElementById('query-results');
    let i;
    let j;
    let csv = '';
    //Add headers to CSV string
    for (i = 1; i < results.children.item(0).children.item(0).children.length; i++){
        csv += `"${results.children.item(0).children.item(0).children.item(i).textContent}",`;
    }
    csv += '\n';

    //Add data to CSV string
    for (i = 0; i < results.children.item(1).children.length; i++){
        for (j = 1; j < results.children.item(1).children.item(i).children.length; j++){
            csv += `"${results.children.item(1).children.item(i).children.item(j).textContent.replaceAll('"', '""')}",`;
        }
        csv += '\n';
    }
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
