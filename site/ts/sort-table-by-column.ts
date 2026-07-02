declare global {
    interface Window {
        sortTableByColumn(tableId: string, sortKey: string, colMap: colMapType): void;
    }
}
// See TableHeaderSort.twig

enum colDataTypes {
    String = 'string',
    Number = 'number',
    Date = 'date',
};
type colMapType = {
    [ k: string ]: colDataTypes;
};

function sortTableByColumn(tableId: string, sortKey: string, colMap: colMapType) {
    const table: HTMLElement = document.querySelector('#tableId')!;
    if (table === null) {
        return;
    }
    // const currentSort = Cookies.get('docker_table_key');
    // const currentDirection = Cookies.get('docker_table_direction') || 'ASC';
    const currentSortKey: string | undefined = table.dataset.sortKey;
    const currentDirection: string = table.dataset.sortDirection || 'ASC';

    // determine direction; toggle when clicking same key twice
    let newDirection: string;
    if (sortKey === currentSortKey) {
        newDirection = (currentDirection === 'ASC' ? 'DESC' : 'ASC');
    }
    else {
        newDirection = 'ASC';
    }

    // Cookies.set('docker_table_key', sortKey, { path: '/admin/docker' });
    // Cookies.set('docker_table_direction', newDirection, { path: '/admin/docker' });
    table.dataset.sortKey = sortKey;
    table.dataset.sortDirection = newDirection;

    // get the index of the column we're sorting by
    const table_headers: HTMLElement[] = Array.from(table.querySelectorAll('thead tr th'));
    const header_keys: string[] = table_headers.map((el) => el.dataset.sortKey) as string[];
    const colIndex: number = header_keys.indexOf(sortKey);
    // and get its datatype from the colMap (defines how rows are compared while sorting)
    const colDataType: colDataTypes = colMap[sortKey];

    applySort(tableId, colIndex, colDataType, newDirection);
    updateSortIcons(tableId, sortKey, newDirection);
}
window.sortTableByColumn = sortTableByColumn;

// run when page initializes to restore sort, e.g. after reload
// $(() => {
export function restoreSort() {
    // const table: HTMLElement | null = document.getElementById(tableId);
    // if (table === null || table.dataset.sortKey === undefined) {
    //     return;
    // }
//     const savedSortKey = Cookies.get('pending_gradeable_table_key');
//     const savedDirection = Cookies.get('pending_gradeable_table_direction') || 'ASC';
//     if (savedSort) {
//         applySort(savedSort, savedDirection);
//         updateSortIcons(savedSort, savedDirection);
//     }
}

function applySort(tableId: string, colIndex: number, colDataType: colDataTypes, direction: string) {
    const tbody: HTMLTableSectionElement = document.querySelector(`#${tableId} tbody`)!;
    // TODO: allow row groups? this is used on the stat-page, I think for the sections
    // ignore section break rows during sorting
    const rows = Array.from(tbody.querySelectorAll('tr:not(.info)'));

    rows.sort((rowA, rowB) => {
        const aText = rowA.children[colIndex].textContent.trim();
        const bText = rowB.children[colIndex].textContent.trim();
        let cmp = 0;

        switch (colDataType) {
            case colDataTypes.Number: {
                // parsing for when comparing file size; || 0 because NaN is falsy
                const valA = parseFloat(aText.replace('MB', '')) || 0;
                const valB = parseFloat(bText.replace('MB', '')) || 0;
                cmp = valA - valB;
                break;
            }
            case colDataTypes.Date: {
                const cleanedA = aText.replace('@', '').replace('EST', '').trim();
                const cleanedB = bText.replace('@', '').replace('EST', '').trim();
                const dateA = new Date(cleanedA);
                const dateB = new Date(cleanedB);
                cmp = dateA.getTime() - dateB.getTime();
                break;
            }
            case colDataTypes.String:
            default:
            {
                cmp = aText.localeCompare(bText);
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

function updateSortIcons(tableId: string, activeKey: string, direction: string) {
    document.querySelectorAll<HTMLElement>(`#${tableId} .sortable-header`)
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
