/* global escapeSpecialChars, buildCourseUrl */

$(document).ready(() => {
    const forumStatsTable = $('#forum-stats-table');

    // updateSortIcons();

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
                        <td colspan="2" style="cursor:pointer; text-align:left;" data-type="post" data-thread_id="${thread_ids[i]}"><pre class="pre-forum" style="white-space: pre-wrap;">${post_string}</pre></td>
                        <td></td>
                    </tr>
                `);
                currentRow.after(expandedRow);
                currentRow = expandedRow;
            }
            button.text('Collapse');
            button.data('action', 'collapse');
            $('td').click(() => {
                if (button.data('type') === 'post' || button.data('type') === 'thread') {
                    const id = button.data('thread_id');
                    const url = buildCourseUrl(['forum', 'threads', id]);
                    window.open(url, '_blank');
                }
            });
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

    forumStatsTable.on('click', 'td[data-type="post"], td[data-type="thread"]', (event) => {
        const id = $(event.currentTarget).data('thread_id');
        const url = buildCourseUrl(['forum', 'threads', id]);
        window.open(url, '_blank');
    });
});
