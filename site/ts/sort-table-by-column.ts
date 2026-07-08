// sortTableByColumn function to be used for all sortable tables
// See SortableColumn.vue

export enum colDataTypes {
    String = 'string',
    Number = 'number',
    Date = 'date',
};

/**
 * Sort the rows of a table by the contents of one of its columns.
 * @param table_id the id of the table element
 * @param sort_key the header's value of data-sort-key (dataset.sortKey) of the column we're sorting
 * @param col_data_type the datatype of the column we're sorting (defines how rows are compared while sorting)
 * @param using_row_groups whether the table to be sorted contains row groups that must be preserved while sorting
 * @returns none
 */
export function sortTableByColumn(table_id: string, sort_key: string, col_data_type: colDataTypes, using_row_groups: boolean = false): void {
    const table: HTMLElement = document.querySelector(`#${table_id}`)!;
    if (table === null) {
        return;
    }

    // const currentSort = Cookies.get('docker_table_key');
    // const currentDirection = Cookies.get('docker_table_direction') || 'ASC';
    const currentsort_key: string | undefined = table.dataset.sortKey;
    const currentDirection: string = table.dataset.sortDirection || 'ASC';

    // determine direction; toggle when clicking same key twice
    let new_direction: string;
    if (sort_key === currentsort_key) {
        new_direction = (currentDirection === 'ASC' ? 'DESC' : 'ASC');
    }
    else {
        new_direction = 'ASC';
    }

    // Cookies.set('docker_table_key', sort_key, { path: '/admin/docker' });
    // Cookies.set('docker_table_direction', new_direction, { path: '/admin/docker' });
    table.dataset.sortKey = sort_key;
    table.dataset.sortDirection = new_direction;

    // get the index of the column we're sorting by (necessary for togglable columns)
    const table_headers: HTMLElement[] = Array.from(table.querySelectorAll('thead tr th'));
    const header_keys: string[] = table_headers.map((el) => {
        const header_link: HTMLElement | null = el.querySelector('a.sortable-header');
        if (header_link === null || header_link.dataset.sortKey === undefined) {
            return '';
        }
        // update styling
        header_link.classList.remove('active-sort');
        const header_key = header_link.dataset.sortKey;
        if (header_key === sort_key) {
            header_link.classList.add('active-sort');
        }
        return header_key;
    });
    const col_index: number = header_keys.indexOf(sort_key);

    applySort(table_id, col_index, new_direction, col_data_type, using_row_groups);
    updateSortIcons(table_id, sort_key, new_direction);
}

// run when page initializes to restore sort, e.g. after reload
// $(() => {
// export function restoreSort() {
// const table: HTMLElement | null = document.getElementById(table_id);
// if (table === null || table.dataset.sortKey === undefined) {
//     return;
// }
//     const savedsort_key = Cookies.get('pending_gradeable_table_key');
//     const savedDirection = Cookies.get('pending_gradeable_table_direction') || 'ASC';
//     if (savedSort) {
//         applySort(savedSort, savedDirection);
//         updateSortIcons(savedSort, savedDirection);
//     }
// }

// Reorder the rows of the table to be sorted according to one row
function applySort(table_id: string, col_index: number, direction: string, col_data_type: colDataTypes, using_row_groups: boolean = false) {
    // todo: sort them all as one and hide section headers if not sorted by sections?
    const tbodies: HTMLTableSectionElement[] = Array.from(document.querySelectorAll(`#${table_id} tbody`));
    for (const tbody of tbodies) {
        // If the table has row groups (only StatPage.twig), we need to keep these groups intact
        if (using_row_groups) {
            const row_groups: HTMLTableRowElement[][] = getRowGroups(tbody);
            row_groups.sort((group_a, group_b) => {
                const text_a = group_a[0].children[col_index].textContent.trim();
                const text_b = group_b[0].children[col_index].textContent.trim();
                return compareFn(text_a, text_b, direction, col_data_type);
            });
            row_groups.forEach((group) => {
                group.forEach((row) => tbody.appendChild(row));
            });
        }
        // This is the default behavior, stuff above are special cases for unique tables
        else {
            // ignore section break rows during sorting
            const rows: HTMLTableRowElement[] = Array.from(tbody.querySelectorAll(':scope > tr:not(.info)'));
            rows.sort((row_a, row_b) => {
                const text_a = row_a.children[col_index].textContent.trim();
                const text_b = row_b.children[col_index].textContent.trim();
                return compareFn(text_a, text_b, direction, col_data_type);
            });
            rows.forEach((row) => tbody.appendChild(row));
        }
    }
}

/**
 * FIXME: Currently it is required that we hardcode sorting by row groups
 * for the table in StatPage.twig within the applySort() and getRowGroups() functions here,
 * but once the tables have been converted to Vue we should probably find a
 * better way to allow sorting by row groups. For example, we could make it so the
 * expanded forum post rows are collapsed whenever a sortable header is clicked,
 * possibly using a Vue emit.
 */

// Returns the row groups in the table as an array of row arrays
function getRowGroups(tbody: HTMLTableSectionElement) {
    const row_groups: HTMLTableRowElement[][] = [];
    let current_row_group: HTMLTableRowElement[] | null = null;

    const rows: HTMLTableRowElement[] = Array.from(tbody.querySelectorAll(':scope > tr:not(.info)'));

    rows.forEach((row) => {
        if (row.classList.contains('user_stat')) {
            current_row_group = [row];
            row_groups.push(current_row_group);
        }
        else if (current_row_group !== null) {
            current_row_group.push(row);
        }
    });

    return row_groups;
}

// Comparison function run during row sorting
function compareFn(text_a: string, text_b: string, direction: string, col_data_type: colDataTypes) {
    let cmp = 0;
    switch (col_data_type) {
        case colDataTypes.Number: {
            // parsing for when comparing file size; || 0 because NaN is falsy
            const val_a = parseFloat(text_a.replace('MB', '')) || 0;
            const val_b = parseFloat(text_b.replace('MB', '')) || 0;
            cmp = val_a - val_b;
            break;
        }
        case colDataTypes.Date: {
            const cleaned_a = text_a.replace('@', '').replace('EST', '').trim();
            const cleaned_b = text_b.replace('@', '').replace('EST', '').trim();
            const date_a = new Date(cleaned_a);
            const date_b = new Date(cleaned_b);
            // https://stackoverflow.com/questions/56612302/sort-array-by-date-in-javascript
            // sorting and comparing dates when one may be invalid
            cmp = (date_a.getTime() || -Infinity) - (date_b.getTime() || -Infinity);
            console.log(`${date_a.toString()} vs ${date_b.toString()} = ${cmp}`);
            break;
        }
        case colDataTypes.String:
        default: {
            cmp = text_a.localeCompare(text_b);
            break;
        }
    }
    return direction === 'ASC' ? cmp : -cmp;
}

// Updates the arrow icons next to each sortable column showing whether
// that column is being sorted in ascending or descending order
function updateSortIcons(table_id: string, activeKey: string, direction: string) {
    document.querySelectorAll<HTMLElement>(`#${table_id} a.sortable-header`)
        .forEach((el) => {
            const icon = el.querySelector('i');
            const key = el.dataset.sortKey; // accesses data-sort-key from TableHeaderSort.twig

            if (icon === null || key === undefined) {
                return;
            }

            icon.classList.remove('fa-sort-up', 'fa-sort-down');
            icon.classList.add('fa-sort');

            if (key === activeKey) {
                icon.classList.remove('fa-sort');
                icon.classList.add(direction === 'ASC' ? 'fa-sort-up' : 'fa-sort-down');
            }
        });
}
