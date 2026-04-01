/* global escapeSpecialChars, buildCourseUrl */
const sortColumns = {
    user: 0,
    total_posts: 1,
    total_threads: 2,
    total_deleted: 3,
    total_upducks: 4,
};

function getForumStatsTable() {
    return document.getElementById('forum_stats_table');
}

function getGroupedRows(tbody) {
    const groups = [];
    let currentGroup = null;

    Array.from(tbody.children).forEach((row) => {
        if (row.classList.contains('user_stat')) {
            currentGroup = [row];
            groups.push(currentGroup);
        }
        else if (currentGroup !== null) {
            currentGroup.push(row);
        }
    });

    return groups;
}

function sortTableByColumn(sortKey) {
    const table = getForumStatsTable();
    if (table === null || sortColumns[sortKey] === undefined) {
        return;
    }

    const currentSort = table.dataset.sortKey;
    const currentDirection = table.dataset.sortDirection || 'ASC';

    let newDirection;
    if (currentSort === sortKey) {
        newDirection = (currentDirection === 'ASC' ? 'DESC' : 'ASC');
    }
    else {
        newDirection = 'ASC';
    }

    table.dataset.sortKey = sortKey;
    table.dataset.sortDirection = newDirection;

    applySort(sortKey, newDirection);
    updateSortIcons(sortKey, newDirection);
}

function applySort(sortKey, direction) {
    const table = getForumStatsTable();
    if (table === null) {
        return;
    }

    const tbody = table.querySelector('tbody');
    const groups = getGroupedRows(tbody);
    const colIndex = sortColumns[sortKey];

    groups.sort((groupA, groupB) => {
        const aText = groupA[0].children[colIndex].textContent.trim();
        const bText = groupB[0].children[colIndex].textContent.trim();
        let cmp = 0;

        if (sortKey === 'user') {
            cmp = aText.localeCompare(bText);
        }
        else {
            cmp = Number(aText) - Number(bText);
        }

        return direction === 'ASC' ? cmp : -cmp;
    });

    groups.forEach((group) => {
        group.forEach((row) => tbody.appendChild(row));
    });
}

function updateSortIcons(activeKey = null, direction = 'ASC') {
    document.querySelectorAll('#forum_stats_table .sortable-header').forEach((link) => {
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

$(document).ready(() => {
    const forumStatsTable = $('#forum_stats_table');

    updateSortIcons();

    forumStatsTable.on('click', 'button[data-action]', function (event) {
        event.preventDefault();

        const button = $(this);
        const action = $(this).data('action');
        const posts = $(this).data('posts');
        const ids = $(this).data('id');
        const timestamps = $(this).data('timestamps');
        const thread_ids = $(this).data('thread_id');
        const thread_titles = $(this).data('thread_titles');

        if (action === 'expand') {
            let currentRow = button.closest('tr');
            for (let i = 0; i < posts.length; i++) {
                let post_string = posts[i];
                post_string = escapeSpecialChars(post_string);
                let thread_title = thread_titles[i];
                thread_title = escapeSpecialChars(thread_title);
                const expandedRow = $(`
                    <tr id="${ids[i]}" class="forum-stats-detail-row">
                        <td></td>
                        <td>${timestamps[i]}</td>
                        <td style="cursor:pointer;" data-type="thread" data-thread_id="${thread_ids[i]}"><pre class="pre-forum" style="white-space: pre-wrap;">${thread_title}</pre></td>
                        <td colspan="3" style="cursor:pointer; text-align:left;" data-type="post" data-thread_id="${thread_ids[i]}"><pre class="pre-forum" style="white-space: pre-wrap;">${post_string}</pre></td>
                    </tr>
                `);
                currentRow.after(expandedRow);
                currentRow = expandedRow;
            }
            button.text('Collapse');
            button.data('action', 'collapse');
        }
        else {
            for (let i = 0; i < ids.length; i++) {
                const item = document.getElementById(ids[i]);
                if (item !== null) {
                    item.remove();
                }
            }
            button.text('Expand');
            button.data('action', 'expand');
        }
    });

    forumStatsTable.on('click', 'td[data-type="post"], td[data-type="thread"]', function () {
        const id = $(this).data('thread_id');
        const url = buildCourseUrl(['forum', 'threads', id]);
        window.open(url);
    });
});
