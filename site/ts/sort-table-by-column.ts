declare global {
    interface Window {
        sortTableByColumn(table_id: string, sort_key: string, col_data_type: colDataTypes): void;
    }
}
// See TableHeaderSort.twig

enum colDataTypes {
    String = 'string',
    Number = 'number',
    Date = 'date',
};

/**
 * Sort the rows of a table by one column.
 * @param table_id the id of the table element
 * @param sort_key the header's value of data-sort-key (dataset.sortKey) of the column we're sorting
 * @param col_data_type the datatype of the column we're sorting (defines how rows are compared while sorting)
 * @returns none
 */
function sortTableByColumn(table_id: string, sort_key: string, col_data_type: colDataTypes): void {
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

    applySort(table_id, col_index, new_direction, col_data_type);
    updateSortIcons(table_id, sort_key, new_direction);
}
window.sortTableByColumn = sortTableByColumn;

// run when page initializes to restore sort, e.g. after reload
// $(() => {
export function restoreSort() {
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
}

function applySort(table_id: string, col_index: number, direction: string, col_data_type: colDataTypes) {
    // todo: sort them all as one and hide section headers if not sorted by sections?
    const tbodies: HTMLTableSectionElement[] = Array.from(document.querySelectorAll(`#${table_id} tbody`));
    for (const tbody of tbodies) {
        // TODO: allow row groups? this is used on the stat-page, I think for the sections
        // ignore section break rows during sorting
        const rows = Array.from(tbody.querySelectorAll('tr:not(.info)'));

        rows.sort((row_a, row_b) => {
            const text_a = row_a.children[col_index].textContent.trim();
            const text_b = row_b.children[col_index].textContent.trim();
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
                    cmp = date_a.getTime() - date_b.getTime();
                    break;
                }
                case colDataTypes.String:
                default:
                {
                    cmp = text_a.localeCompare(text_b);
                    break;
                    /*
                    TODO: add a setting to allow sort to look at next column over?
                    This looks like a common feature, see below 2 examples
                    */

                    // const courseA = rowA.children[0].textContent.trim();
                    // const courseB = rowB.children[0].textContent.trim();
                    // cmp = courseA.localeCompare(courseB);
                    // if (cmp === 0) {
                    //     const gradeableA = rowA.children[1].textContent.trim();
                    //     const gradeableB = rowB.children[1].textContent.trim();
                    //     cmp = gradeableA.localeCompare(gradeableB);
                    // }

                    // const nameA = rowA.children[0].textContent.trim();
                    // const nameB = rowB.children[0].textContent.trim();
                    // cmp = nameA.localeCompare(nameB);
                    // if (cmp === 0) {
                    //     const tagA = rowA.children[1].textContent.trim();
                    //     const tagB = rowB.children[1].textContent.trim();
                    //     const numA = parseFloat(tagA);
                    //     const numB = parseFloat(tagB);
                    //     const isNumA = !isNaN(numA);
                    //     const isNumB = !isNaN(numB);
                    //     // Tag is descending regardless of Image Name
                    //     if (isNumA && isNumB) {
                    //         cmp = numB - numA;
                    //     }
                    //     else {
                    //         cmp = tagB.localeCompare(tagA);
                    //     }
                    //     return cmp;
                    // }
                }
            }
            return direction === 'ASC' ? cmp : -cmp;
        });
        rows.forEach((row) => tbody.appendChild(row));
    }
}

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
