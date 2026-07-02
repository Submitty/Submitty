declare global {
    interface Window {
        sortTableByColumn(tableId: string, colMap: colMapType, sortKey: string): void;
    }
}

// See TableHeaderSort.twig

// New data types to be used when making column maps of sortable tables
// enum colDataTypes {
//     String,
//     Number,
//     Date,
// };

// script tags cannot compile TS, so enums cannot be used to create column data
// within the twig files for the tables.
// For now, use the following strings:
// 'string', 'number', 'date'

type colMapType = {
    [ k: string ]: {
        colIndex: number;
        colDataType: string; // colDataTypes;
    };
};

// Example colMap from docker interface table
/*
{
    const colMap = {
        'registration_section': { colIndex: 1, colDataType: colDataTypes.Number },
        'user_id': { colIndex: 2, colDataType: colDataTypes.String },
        'first_name': { colIndex: 3, colDataType: colDataTypes.String },
        'last_name': { colIndex: 4, colDataType: colDataTypes.String },
        'rotating_section': { colIndex: 6, colDataType: colDataTypes.Number }
    }
}
*/

function sortTableByColumn(tableId: string, colMap: colMapType, sortKey: string) {
    console.log(`test 2: ${tableId} ${sortKey}`);
    console.log(colMap);
    const table: HTMLElement = document.getElementById(tableId)!;
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

    applySort(tableId, colMap, sortKey, newDirection);
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

function applySort(tableId: string, colMap: colMapType, sortKey: string, direction: string) {
    console.log('test 3');
    const tbody: HTMLTableSectionElement = document.querySelector(`#${tableId} tbody`)!;

    /*
    TODO: allow row groups? this is used on the stat-page, I think for the sections
    */

    // ignore section break rows during sorting
    const rows = Array.from(tbody.querySelectorAll('tr:not(.info)'));
    const colIndex: number = colMap[sortKey]['colIndex'];
    const colDataType: string = colMap[sortKey]['colDataType'];

    rows.sort((rowA, rowB) => {
        const aText = rowA.children[colIndex].textContent.trim();
        const bText = rowB.children[colIndex].textContent.trim();
        let cmp = 0;

        switch (colDataType) {
            case 'number': {
                // parsing for when comparing file size; || 0 because NaN is falsy
                const valA = parseFloat(aText.replace('MB', '')) || 0;
                const valB = parseFloat(bText.replace('MB', '')) || 0;
                cmp = valA - valB;
                break;
            }
            case 'date': {
                const cleanedA = aText.replace('@', '').replace('EST', '').trim();
                const cleanedB = bText.replace('@', '').replace('EST', '').trim();
                const dateA = new Date(cleanedA);
                const dateB = new Date(cleanedB);
                cmp = dateA.getTime() - dateB.getTime();
                break;
            }
            case 'string':
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
        console.log(cmp);
        return direction === 'ASC' ? cmp : -cmp;
    });
    rows.forEach((row) => tbody.appendChild(row));
}

function updateSortIcons(tableId: string, activeKey: string, direction: string) {
    console.log('test 4');
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
