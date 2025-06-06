import { buildCourseUrl, getCsrfToken } from './utils/server';

interface SqlQueryResult {
    status: string;
    message: string | null;
    data: { [key: string]: number | string | null }[];
}

export async function runSqlQuery() {
    const queryResultsTable = document.getElementById('query-results') as HTMLTableElement;
    const downloadSqlButton = document.getElementById('download-sql-btn') as HTMLButtonElement;
    queryResultsTable.style.display = 'block';
    downloadSqlButton.disabled = true;

    const form_data = new FormData();
    form_data.append('csrf_token', getCsrfToken());
    const textarea = document.getElementById('toolbox-textarea') as HTMLTextAreaElement;
    form_data.append('sql', textarea.value);

    try {
        const resp = await fetch(
            buildCourseUrl(['sql_toolbox']),
            {
                method: 'POST',
                body: form_data,
            },
        );

        const json = await resp.json() as SqlQueryResult;
        const error = document.getElementById('query-results-error') as HTMLDivElement;
        const error_mesage = document.getElementById('query-results-error-message') as HTMLPreElement;
        const table = queryResultsTable;
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
        data.forEach((row, idx: number) => {
            const bodyRow = document.createElement('tr');
            const cell = document.createElement('td');
            cell.textContent = (idx + 1).toString();
            bodyRow.appendChild(cell);
            Object.values(row).forEach((val) => {
                const cell = document.createElement('td');
                cell.textContent = val !== null ? String(val) : '';
                bodyRow.appendChild(cell);
            });
            body.appendChild(bodyRow);
        });
        table.appendChild(body);
        downloadSqlButton.disabled = false;
    }
    catch (exc) {
        console.error(exc);
        alert((exc as Error).toString());
    }
}

export function generateCSV(id: string) {
    const results = document.getElementById(id) as HTMLTableElement;
    let csv = '';
    // Add headers to CSV string
    const header = results.children.item(0) as HTMLTableSectionElement;
    const row = header.children.item(0) as HTMLTableRowElement;
    for (let i = 1; i < row.children.length; i++) {
        csv += `"${(row.children.item(i) as HTMLTableCellElement).textContent!.split('"').join('""')}",`;
    }
    csv += '\n';

    // Add data to CSV string
    const data = results.children.item(1) as HTMLTableSectionElement;
    for (let i = 0; i < data.children.length; i++) {
        const row = data.children.item(i) as HTMLTableRowElement;
        for (let j = 1; j < row.children.length; j++) {
            csv += `"${(row.children.item(j) as HTMLTableCellElement).textContent!.split('"').join('""')}",`;
        }
        csv += '\n';
    }
    return csv;
}

export function downloadSqlResult(id: string) {
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

export function init() {
    (document.getElementById('run-sql-btn') as HTMLButtonElement).addEventListener('click', () => void runSqlQuery());
    (document.getElementById('sql-database-schema') as HTMLButtonElement).addEventListener('click', () => {
        const schemaContent = document.getElementById('sql-database-schema-content') as HTMLDivElement;
        schemaContent.style.display = schemaContent.style.display === 'block' ? 'none' : 'block';
    });

    document.querySelectorAll('.sql-database-table').forEach((elem) => {
        elem.addEventListener('click', (e) => {
            const target = e.target as HTMLAnchorElement;
            if (target && target.nextElementSibling) {
                const nextSibling = target.nextElementSibling as HTMLDivElement;
                nextSibling.style.display = nextSibling.style.display === 'block' ? 'none' : 'block';
            }
        });
    });

    (document.getElementById('download-sql-btn') as HTMLButtonElement).addEventListener('click', () => downloadSqlResult('query-results'));
}

/* istanbul ignore next */
document.addEventListener('DOMContentLoaded', () => init());
