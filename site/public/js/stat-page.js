$('td').click(function () {
    let table_id = 0;
    switch ($(this).attr('id')) {
        case 'user_sort':
            table_id = 0;
            break;
        case 'total_posts_sort':
            table_id = 1;
            break;
        case 'total_threads_sort':
            table_id = 2;
            break;
        case 'total_deleted_sort':
            table_id = 3;
            break;
        default:
            table_id = 0;
    }

    if ($(this).html().indexOf(' ↓') > -1) {
        sortTable(table_id, true);
    } 
    else {
        sortTable(table_id, false);
    }
});

$('button').click(function () {

    const action = $(this).data('action');
    const posts = $(this).data('posts');
    const ids = $(this).data('id');
    const timestamps = $(this).data('timestamps');
    const thread_ids = $(this).data('thread_id');
    const thread_titles = $(this).data('thread_titles');
    if (action == 'expand') {


        for (let i = 0; i < posts.length; i++) {
            let post_string = posts[i];
            post_string = escapeSpecialChars(post_string);
            let thread_title = thread_titles[i]['title'];
            thread_title = escapeSpecialChars(thread_title);
            $(this).parent().parent().parent().append(`<tr id=${ids[i]}><td></td><td>${timestamps[i]}</td><td style = "cursor:pointer;" data-type = "thread" data-thread_id=${thread_ids[i]}><pre class="pre_forum" style="white-space: pre-wrap;">  ${thread_title} </pre></td><td colspan = "2" style = "cursor:pointer;" align = "left" data-type = "post" data-thread_id= ${thread_ids[i]} ><pre class="pre_forum" style="white-space: pre-wrap;">${ post_string }</pre></td></tr> `);

        }
        $(this).html('Collapse');
        $(this).data('action', 'collapse');
        $('td').click(function () {

            if ($(this).data('type') == 'post' || $(this).data('type') == 'thread') {
                const id = $(this).data('thread_id');
                const url = buildCourseUrl(['forum', 'threads', id]);
                window.open(url);
            }
        });
    } 
    else {
        for (let j = 0; j < ids.length; j++) {
            const item = document.getElementById(ids[j]);
            item.remove();
        }
        $(this).html('Expand');
        $(this).data('action', 'expand');
    }
    return false;
});
