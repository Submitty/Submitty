// sortTableByColumn function to be used for all sortable tables
// See SortableTableHeader.vue

/**
 * Enum that stores all possible table column data types by which we'd need to sort.
 * If a table column requires a sorting procedure that does not fit with any of these
 * types, then that type should be added to the enum, and the new sorting procedure
 * should be added to compareFn() in sort-table-by-column.ts.
 * @property {string} String "standard alphanumeric sorting"
 * @property {string} Number "numeric sorting, removes MB for file size sorting"
 * @property {string} Date "date/time sorting, removes @ and EST, uses js Date class"
 */
export enum colDataTypes {
    String = 'string',
    Number = 'number',
    Date = 'date',
};

// used when reading data from and saving data to SessionStorage
interface SavedSortData {
    table_id: string;
    sort_key: string;
    sort_direction: string;
    col_data_type: colDataTypes;
    using_row_groups: boolean;
}

/**
 * Sort the rows of a table by the contents of one of its columns.
 * @param table_id the id of the table element
 * @param sort_key the header's value of data-sort-key (dataset.sortKey) of the column we're sorting
 * @param col_data_type the datatype of the column we're sorting (defines how rows are compared while sorting)
 * @param options additional options used for sorting special tables
 * @returns none
 */
export function sortTableByColumn(table_id: string, sort_key: string, col_data_type: colDataTypes, using_row_groups: boolean = false): void {
    // retrieve the previous sort data from session storage
    let prev_sort_key: string | undefined = undefined;
    let prev_sort_direction: string = 'ASC';

    // retrieve the previous sort data from session storage
    const prev_sort_data_string: string | null = sessionStorage.getItem(`${table_id}-sorting-data`);
    let prev_sort_data: SavedSortData;
    if (prev_sort_data_string !== null) {
        prev_sort_data = JSON.parse(prev_sort_data_string) as SavedSortData;
        prev_sort_key = prev_sort_data?.sort_key;
        prev_sort_direction = prev_sort_data?.sort_direction;
    }

    // determine sort_direction; toggle when clicking same key twice
    let sort_direction: string;
    if (sort_key === prev_sort_key) {
        sort_direction = (prev_sort_direction === 'ASC' ? 'DESC' : 'ASC');
    }
    else {
        sort_direction = 'ASC';
    }

    const new_sort_data: SavedSortData = {
        table_id: table_id,
        sort_key: sort_key,
        sort_direction: sort_direction,
        col_data_type: col_data_type,
        using_row_groups: using_row_groups,
    };
    // save the updated sort data to session storage
    sessionStorage.setItem(`${table_id}-sorting-data`, JSON.stringify(new_sort_data));

    applySortToTable(new_sort_data);
}

/**
 * Update the table with the sorting data stored in Local Storage for this table.
 * @param table_id the id of the table whose sorting data you want to restore
 * @returns none
 */
export function restoreSort(table_id: string) {
    const sort_data_string: string | null = sessionStorage.getItem(`${table_id}-sorting-data`);
    if (sort_data_string === null) {
        return;
    }
    const sort_data: SavedSortData = JSON.parse(sort_data_string) as SavedSortData;
    applySortToTable(sort_data);
}

// Make necessary style and DOM changes to the table to represent the new sorting
function applySortToTable(sort_data: SavedSortData) {
    const col_index: number = updateSortingHeader(sort_data.table_id, sort_data.sort_key);
    sortTableRows(sort_data.table_id, col_index, sort_data.sort_direction, sort_data.col_data_type, sort_data.using_row_groups);
    updateSortIcons(sort_data.table_id, sort_data.sort_key, sort_data.sort_direction);
}

// Return the index of the active sorting header in the table header row
// side-effect: updates the class of the active header for styling purposes
function updateSortingHeader(table_id: string, sort_key: string): number {
    const table: HTMLElement = document.querySelector(`#${table_id}`)!;

    // get the index of the column we're sorting by (necessary for togglable columns)
    const table_headers: HTMLElement[] = Array.from(table.querySelectorAll('thead tr th'));
    const header_keys: string[] = table_headers.map((el) => {
        const header_link: HTMLElement | null = el.querySelector('a.sortable-header');
        if (header_link === null || header_link.dataset.sortKey === undefined) {
            return '';
        }
        // update styling for active header
        header_link.classList.remove('active-sort');
        const header_key = header_link.dataset.sortKey;
        if (header_key === sort_key) {
            header_link.classList.add('active-sort');
        }
        return header_key;
    });
    // return the active header's index in the table header row
    const col_index: number = header_keys.indexOf(sort_key);
    return col_index;
}

// Reorder the rows of the table to be sorted according to one row
function sortTableRows(table_id: string, col_index: number, sort_direction: string, col_data_type: colDataTypes, using_row_groups: boolean = false) {
    // TODO: for tables divided by sections, be able to sort between sections (combining all rows in one tbody)
    const tbodies: HTMLTableSectionElement[] = Array.from(document.querySelectorAll(`#${table_id} tbody`));
    for (const tbody of tbodies) {
        // If the table has row groups (only StatPage.twig), we need to keep these groups intact
        if (using_row_groups) {
            const row_groups: HTMLTableRowElement[][] = getRowGroups(tbody);
            row_groups.sort((group_a, group_b) => {
                const text_a = group_a[0].children[col_index].textContent.trim();
                const text_b = group_b[0].children[col_index].textContent.trim();
                return compareFn(text_a, text_b, sort_direction, col_data_type);
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
                // I am not sure why row_a and row_b are possibly being labeled as undefined
                const text_a = row_a.children[col_index].textContent.trim();
                const text_b = row_b.children[col_index].textContent.trim();
                return compareFn(text_a, text_b, sort_direction, col_data_type);
            });
            rows.forEach((row) => tbody.appendChild(row));
        }
    }
}

/**
 * FIXME: Currently it is required that we hardcode sorting by row groups
 * for the table in StatPage.twig within the sortTableRows() and getRowGroups() functions here,
 * but once the tables have been converted to Vue we should probably find a
 * better way to allow sorting by row groups. For example, we could make it so the
 * expanded forum post rows are collapsed whenever a sortable header is clicked,
 * possibly using a Vue emit.
 */

// Return the row groups in the table as an array of row arrays
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
function compareFn(text_a: string, text_b: string, sort_direction: string, col_data_type: colDataTypes) {
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
            break;
        }
        case colDataTypes.String:
        default: {
            cmp = text_a.localeCompare(text_b);
            break;
        }
    }
    return sort_direction === 'ASC' ? cmp : -cmp;
}

// Expose on window so Twig event handlers can call these functions
(window as unknown as Record<string, unknown>).sortTableByColumn = sortTableByColumn;
(window as unknown as Record<string, unknown>).restoreSort = restoreSort;

// Update the arrow icons next to each sortable column showing whether
// that column is being sorted in ascending or descending order
function updateSortIcons(table_id: string, activeKey: string, sort_direction: string) {
    document.querySelectorAll<HTMLElement>(`#${table_id} a.sortable-header`)
        .forEach((el) => {
            const icon = el.querySelector('i');
            const key = el.dataset.sortKey;

            if (icon === null || key === undefined) {
                return;
            }

            icon.classList.remove('fa-sort-up', 'fa-sort-down');
            icon.classList.add('fa-sort');

            if (key === activeKey) {
                icon.classList.remove('fa-sort');
                icon.classList.add(sort_direction === 'ASC' ? 'fa-sort-up' : 'fa-sort-down');
            }
        });
}
