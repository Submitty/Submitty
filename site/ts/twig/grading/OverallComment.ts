import { open_overall_comment_tab } from '../../grading/rubric';

$(() => {
    $(document).on('click', "input[id^='overall-comment-tab-']", function() {
        const userId = $(this).attr('data-user-id') as string;
        open_overall_comment_tab(userId);
    });

    $(document).on('change', '#attachment-upload', () => {
        uploadAttachment();
    });

    $('.overall-comment-other').each(function() {
        const content = $(this).html().trim();
        const url = buildUrl(['markdown']);
        renderMarkdown($(this), url, content);
    });

    const grader = $('overall-comments').attr('data-logged-in-user-id');
    $(document).on('input', `#overall-comment-${grader}`, () => {
        const currentOverallComment  = $(`textarea#overall-comment-${grader}`).val();
        const previousOverallComment = $(`textarea#overall-comment-${grader}`).data('previous-comment');
        if (currentOverallComment != previousOverallComment) {
            $('.overall-comment-status').text('Unsaved Changes');
        }
        else {
            $('.overall-comment-status').text('All Changes Saved');
        }
    });
});
