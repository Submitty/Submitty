import '../../ts/sort-table-by-column';

/**
 * Build a table with id "test-table". The first column is the sort key "name",
 * the second column holds the value under test with sort key "value".
 * @param {string[]} values the values to place in the "value" column, in order
 * @returns {void}
 */
function buildTable(values) {
    document.body.innerHTML = `
    <table id="test-table">
        <thead>
            <tr>
                <th><a class="sortable-header" data-sort-key="name"><i class="fas fa-sort"></i></a></th>
                <th><a class="sortable-header" data-sort-key="value"><i class="fas fa-sort"></i></a></th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>`;
    const tbody = document.querySelector('#test-table tbody');
    values.forEach((value, i) => {
        const row = document.createElement('tr');
        for (const text of [`row${i}`, value]) {
            const cell = document.createElement('td');
            cell.textContent = text;
            row.appendChild(cell);
        }
        tbody.appendChild(row);
    });
}

/**
 * @returns {string[]} the text of the "value" column, top to bottom
 */
function columnValues() {
    return Array.from(document.querySelectorAll('#test-table tbody tr'))
        .map((row) => row.children[1].textContent.trim());
}

/**
 * @param {string} key the data-sort-key of the header
 * @returns {HTMLElement} the header link with that key
 */
function header(key) {
    return document.querySelector(`#test-table a[data-sort-key="${key}"]`);
}

beforeEach(() => {
    sessionStorage.clear();
});

describe('string columns', () => {
    test('sort alphabetically', () => {
        buildTable(['banana', 'apple', 'cherry']);
        window.sortTableByColumn('test-table', 'value', 'string');
        expect(columnValues()).toEqual(['apple', 'banana', 'cherry']);
    });

    test('use natural order for embedded numbers', () => {
        buildTable(['item10', 'item2', 'item1']);
        window.sortTableByColumn('test-table', 'value', 'string');
        expect(columnValues()).toEqual(['item1', 'item2', 'item10']);
    });
});

describe('number columns', () => {
    test('sort numerically, not alphabetically', () => {
        buildTable(['10', '2', '1', '-3']);
        window.sortTableByColumn('test-table', 'value', 'number');
        expect(columnValues()).toEqual(['-3', '1', '2', '10']);
    });

    test('ignore the MB suffix on file sizes', () => {
        buildTable(['1.5MB', '0.25MB', '10MB']);
        window.sortTableByColumn('test-table', 'value', 'number');
        expect(columnValues()).toEqual(['0.25MB', '1.5MB', '10MB']);
    });

    test('treat non-numeric values as 0', () => {
        buildTable(['5', 'N/A', '-1']);
        window.sortTableByColumn('test-table', 'value', 'number');
        expect(columnValues()).toEqual(['-1', 'N/A', '5']);
    });
});

describe('date columns', () => {
    test('sort chronologically and ignore "@" and "EST"', () => {
        buildTable([
            '03/15/2026 @ 11:59 PM EST',
            '01/02/2026 @ 09:00 AM EST',
            '03/15/2026 @ 08:00 AM EST',
        ]);
        window.sortTableByColumn('test-table', 'value', 'date');
        expect(columnValues()).toEqual([
            '01/02/2026 @ 09:00 AM EST',
            '03/15/2026 @ 08:00 AM EST',
            '03/15/2026 @ 11:59 PM EST',
        ]);
    });

    test('put invalid dates first when ascending', () => {
        buildTable(['03/15/2026', 'never', '01/02/2026']);
        window.sortTableByColumn('test-table', 'value', 'date');
        expect(columnValues()).toEqual(['never', '01/02/2026', '03/15/2026']);
    });
});

describe('sort direction', () => {
    test('clicking the same column twice toggles ASC then DESC', () => {
        buildTable(['b', 'c', 'a']);
        window.sortTableByColumn('test-table', 'value', 'string');
        expect(columnValues()).toEqual(['a', 'b', 'c']);
        window.sortTableByColumn('test-table', 'value', 'string');
        expect(columnValues()).toEqual(['c', 'b', 'a']);
        window.sortTableByColumn('test-table', 'value', 'string');
        expect(columnValues()).toEqual(['a', 'b', 'c']);
    });

    test('switching to a different column starts at ASC', () => {
        buildTable(['b', 'c', 'a']);
        window.sortTableByColumn('test-table', 'value', 'string');
        window.sortTableByColumn('test-table', 'value', 'string');
        window.sortTableByColumn('test-table', 'name', 'string');
        const saved = JSON.parse(sessionStorage.getItem('test-table-sorting-data'));
        expect(saved.sort_key).toBe('name');
        expect(saved.sort_direction).toBe('ASC');
    });
});

describe('saved sort state', () => {
    test('sortTableByColumn saves the sort to sessionStorage', () => {
        buildTable(['b', 'a']);
        window.sortTableByColumn('test-table', 'value', 'number');
        expect(JSON.parse(sessionStorage.getItem('test-table-sorting-data'))).toEqual({
            table_id: 'test-table',
            sort_key: 'value',
            sort_direction: 'ASC',
            col_data_type: 'number',
            using_row_groups: false,
        });
    });

    test('restoreTableSort re-applies the saved sort', () => {
        buildTable(['1', '3', '2']);
        sessionStorage.setItem('test-table-sorting-data', JSON.stringify({
            table_id: 'test-table',
            sort_key: 'value',
            sort_direction: 'DESC',
            col_data_type: 'number',
            using_row_groups: false,
        }));
        window.restoreTableSort('test-table');
        expect(columnValues()).toEqual(['3', '2', '1']);
    });

    test('restoreTableSort leaves the table alone when nothing is saved', () => {
        buildTable(['1', '3', '2']);
        window.restoreTableSort('test-table');
        expect(columnValues()).toEqual(['1', '3', '2']);
    });
});

describe('headers and icons', () => {
    test('only the active header gets the active-sort class', () => {
        buildTable(['b', 'a']);
        window.sortTableByColumn('test-table', 'value', 'string');
        expect(header('value').classList.contains('active-sort')).toBe(true);
        expect(header('name').classList.contains('active-sort')).toBe(false);

        window.sortTableByColumn('test-table', 'name', 'string');
        expect(header('value').classList.contains('active-sort')).toBe(false);
        expect(header('name').classList.contains('active-sort')).toBe(true);
    });

    test('the active icon shows the sort direction and others reset', () => {
        buildTable(['b', 'a']);
        const valueIcon = header('value').querySelector('i');
        const nameIcon = header('name').querySelector('i');

        window.sortTableByColumn('test-table', 'value', 'string');
        expect(valueIcon.classList.contains('fa-sort-up')).toBe(true);
        expect(nameIcon.classList.contains('fa-sort')).toBe(true);

        window.sortTableByColumn('test-table', 'value', 'string');
        expect(valueIcon.classList.contains('fa-sort-down')).toBe(true);
        expect(valueIcon.classList.contains('fa-sort-up')).toBe(false);
    });
});

describe('special rows', () => {
    test('section break rows (class "info") are not moved', () => {
        document.body.innerHTML = `
        <table id="test-table">
            <thead><tr>
                <th><a class="sortable-header" data-sort-key="value"><i class="fas fa-sort"></i></a></th>
            </tr></thead>
            <tbody>
                <tr class="info"><td>Section 1</td></tr>
                <tr><td>b</td></tr>
                <tr><td>a</td></tr>
            </tbody>
        </table>`;
        window.sortTableByColumn('test-table', 'value', 'string');
        const texts = Array.from(document.querySelectorAll('#test-table tbody tr'))
            .map((row) => row.textContent.trim());
        expect(texts).toEqual(['Section 1', 'a', 'b']);
    });

    test('each tbody is sorted on its own', () => {
        document.body.innerHTML = `
        <table id="test-table">
            <thead><tr>
                <th><a class="sortable-header" data-sort-key="value"><i class="fas fa-sort"></i></a></th>
            </tr></thead>
            <tbody><tr><td>d</td></tr><tr><td>c</td></tr></tbody>
            <tbody><tr><td>b</td></tr><tr><td>a</td></tr></tbody>
        </table>`;
        window.sortTableByColumn('test-table', 'value', 'string');
        const bodies = Array.from(document.querySelectorAll('#test-table tbody'))
            .map((tbody) => Array.from(tbody.querySelectorAll('td')).map((td) => td.textContent));
        expect(bodies).toEqual([['c', 'd'], ['a', 'b']]);
    });

    test('row groups stay together when using_row_groups is true', () => {
        document.body.innerHTML = `
        <table id="test-table">
            <thead><tr>
                <th><a class="sortable-header" data-sort-key="value"><i class="fas fa-sort"></i></a></th>
            </tr></thead>
            <tbody>
                <tr class="user_stat"><td>bob</td></tr>
                <tr><td>bob post 1</td></tr>
                <tr class="user_stat"><td>alice</td></tr>
                <tr><td>alice post 1</td></tr>
                <tr><td>alice post 2</td></tr>
            </tbody>
        </table>`;
        window.sortTableByColumn('test-table', 'value', 'string', true);
        const texts = Array.from(document.querySelectorAll('#test-table tbody tr'))
            .map((row) => row.textContent.trim());
        expect(texts).toEqual(['alice', 'alice post 1', 'alice post 2', 'bob', 'bob post 1']);
    });
});
