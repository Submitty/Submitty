function sortTableByColumn(sortKey) {
    const currentSort = Cookies.get('pending_gradeable_table_key');
    const currentDirection = Cookies.get('pending_gradeable_table_direction') || 'ASC';

    let newDirection;
    if (currentSort === sortKey) {
        newDirection = (currentDirection === 'ASC' ? 'DESC' : 'ASC');
    }
    else {
        newDirection = 'ASC';
    }

    Cookies.set('pending_gradeable_table_key', sortKey, { path: '/superuser/gradeables' });
    Cookies.set('pending_gradeable_table_direction', newDirection, { path: '/superuser/gradeables' });

    applySort(sortKey, newDirection);
    updateSortIcons(sortKey, newDirection);
}

function applySort(sortKey, direction) {
    const table = document.getElementById('pending-gradeable-table');
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    const colMap = { course: 0, close_date: 2 };
    const colIndex = colMap[sortKey];

    rows.sort((rowA, rowB) => {
        const aText = rowA.children[colIndex].textContent.trim();
        const bText = rowB.children[colIndex].textContent.trim();
        let cmp = 0;
        if (sortKey === 'course') {
            const courseA = rowA.children[0].textContent.trim();
            const courseB = rowB.children[0].textContent.trim();
            cmp = courseA.localeCompare(courseB);
            if (cmp === 0) {
                const gradeableA = rowA.children[1].textContent.trim();
                const gradeableB = rowB.children[1].textContent.trim();
                cmp = gradeableA.localeCompare(gradeableB);
            }
        }
        else if (sortKey === 'close_date') {
            const dateA = new Date(aText);
            const dateB = new Date(bText);
            cmp = dateA - dateB;
        }
        return direction === 'ASC' ? cmp : -cmp;
    });
    rows.forEach((row) => tbody.appendChild(row));
}

function updateSortIcons(activeKey, direction) {
    document.querySelectorAll('.sortable-header').forEach((link) => {
        const icon = link.querySelector('i');
        const key = link.dataset.sortKey;

        icon.classList.remove('fa-sort-up', 'fa-sort-down');
        icon.classList.add('fa-sort');

        if (key === activeKey) {
            icon.classList.remove('fa-sort');
            icon.classList.add(direction === 'ASC' ? 'fa-sort-up' : 'fa-sort-down');
        }
    });
}

// Keeps the specified sort on reload
$(() => {
    const savedSort = Cookies.get('pending_gradeable_table_key');
    const savedDirection = Cookies.get('pending_gradeable_table_direction') || 'ASC';
    if (savedSort) {
        applySort(savedSort, savedDirection);
        updateSortIcons(savedSort, savedDirection);
    }
});
