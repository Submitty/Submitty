/* global sortTable, escapeSpecialChars, buildCourseUrl */
$(document).ready(function() {
    $("th").click(function(){
        var table_id = 0;
        switch ($(this).attr('id')) {
            case "user_sort":
                table_id = 0;
                break;
            case "total_posts_sort":
                table_id = 1;
                break;
            case "total_threads_sort":
                table_id = 2;
                break;
            case "total_deleted_sort":
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
    $("button").click(function(){
        var action = $(this).data('action');
        var posts = $(this).data('posts');
        var ids = $(this).data('id');
        var timestamps = $(this).data('timestamps');
        var thread_ids = $(this).data('thread_id');
        var thread_titles = $(this).data('thread_titles');
        if(action=="expand"){
            for(var i=0;i<posts.length;i++){
                var post_string = posts[i];
                post_string = escapeSpecialChars(post_string);
                var thread_title = thread_titles[i];
                thread_title = escapeSpecialChars(thread_title);
                $(this).parent().parent().parent().append('<tr id="'+ids[i]+'"><td></td><td>'+timestamps[i]+'</td><td style = "cursor:pointer;" data-type = "thread" data-thread_id="'+thread_ids[i]+'"><pre class="pre_forum" style="white-space: pre-wrap;">'+thread_title+'</pre></td><td colspan = "2" style = "cursor:pointer;" align = "left" data-type = "post" data-thread_id="'+thread_ids[i]+'"><pre class="pre_forum" style="white-space: pre-wrap;">'+post_string+'</pre></td></tr> ');
            }
            $(this).html("Collapse");
            $(this).data('action',"collapse");
            $("td").click(function(){
                if($(this).data('type')=="post" || $(this).data('type')=="thread"){
                    var id = $(this).data('thread_id');
                    var url = buildCourseUrl(['forum', 'threads', id]);
                    window.open(url);
                }
            });
        }
        else{
            for(var i=0;i<ids.length;i++){
                var item = document.getElementById(ids[i]);
                item.remove();
            }
            $(this).html("Expand");
            $(this).data('action',"expand");
        }
        return false;
    });
});
