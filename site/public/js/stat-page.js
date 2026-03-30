/* global escapeSpecialChars, buildCourseUrl */
$(document).ready(() => {
    const forumStatsTable = $('#forum_stats_table');
    const sortColumns = {
        user_sort: 0,
        total_posts_sort: 1,
        total_threads_sort: 2,
        total_deleted_sort: 3,
        total_upducks: 4,
    };

    function getGroupedRows() {
        const groups = [];
        let currentGroup = null;

        forumStatsTable.find('tbody > tr').each(function () {
            const row = $(this);
            if (row.hasClass('user_stat')) {
                currentGroup = [row];
                groups.push(currentGroup);
            }
            else if (currentGroup !== null) {
                currentGroup.push(row);
            }
        });

        return groups;
    }

    function resetSortIndicators() {
        forumStatsTable.find('thead th').each(function () {
            const header = $(this);
            const text = header.text();
            if (text.endsWith(' ↓') || text.endsWith(' ↑')) {
                header.text(text.slice(0, -2));
            }
        });
    }

    function sortForumStatsTable(sortElementIndex, reverse = false) {
        const tbody = forumStatsTable.find('tbody');
        const groups = getGroupedRows();

        groups.sort((groupA, groupB) => {
            const a = groupA[0].children('td').eq(sortElementIndex).text().trim();
            const b = groupB[0].children('td').eq(sortElementIndex).text().trim();

            let comparison = 0;
            if (sortElementIndex === 0) {
                comparison = a.localeCompare(b);
            }
            else {
                comparison = Number(a) - Number(b);
            }

            return reverse ? -comparison : comparison;
        });

        groups.forEach((group) => {
            group.forEach((row) => {
                tbody.append(row);
            });
        });

        resetSortIndicators();
        const activeHeader = forumStatsTable.find(`thead th:eq(${sortElementIndex})`);
        activeHeader.text(`${activeHeader.text()}${reverse ? ' ↑' : ' ↓'}`);
    }

    forumStatsTable.on('click', 'thead th.cursor-pointer', function () {
        const tableId = sortColumns[$(this).attr('id')];
        if (tableId === undefined) {
            return;
        }

        if ($(this).text().indexOf(' ↓') > -1) {
            sortForumStatsTable(tableId, true);
        }
        else {
            sortForumStatsTable(tableId, false);
        }
    });

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
