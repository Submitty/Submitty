// See TableHeaderSort.twig

// New data types to be used when making column maps of sortable tables
export enum colDataTypes {
    String,
    Number,
    Date,
}
export type colMapType = {
    [ k: string ]: {
        colIndex: number;
        colDataType: colDataTypes;
    };
};

// Example colMap from docker interface table
/*
{
    name: { colIndex: 0, colDataType: string },
    size: { colIndex: 4, colDataType: file_size },
    created: { colIndex: 5, colDataType: date },
}
*/

export function sortTableByColumn(tableId: string, colMap: colMapType, sortKey: string) {
    const table: HTMLElement | null = document.getElementById(tableId);
    if (table === null || table.dataset.sortKey === undefined) {
        return;
    }
    // const currentSort = Cookies.get('docker_table_key');
    // const currentDirection = Cookies.get('docker_table_direction') || 'ASC';
    const currentSortKey: string = table.dataset.sortKey;
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

    applySort(tableId, colMap, sortKey, newDirection);
    updateSortIcons(tableId, sortKey, newDirection);
}

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

function applySort(tableId: string, colMap: colMapType, sortKey: string, direction: string) {
    const tbody: HTMLTableSectionElement = document.querySelector(`#${tableId} tbody`)!;
    /*
    TODO: allow row groups? this is used on the stat-page, I think for the sections
    */
    const rows = Array.from(tbody.querySelectorAll('tr'));
    const colIndex = colMap[sortKey]['colIndex'];
    const colDataType: colDataTypes = colMap[sortKey]['colDataType'];

    rows.sort((rowA, rowB) => {
        const aText = rowA.children[colIndex].textContent.trim();
        const bText = rowB.children[colIndex].textContent.trim();
        let cmp = 0;

        switch (colDataType) {
            case colDataTypes.Number: {
                // parsing for when comparing file size
                const valA = parseFloat(aText.replace('MB', ''));
                const valB = parseFloat(bText.replace('MB', ''));
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
