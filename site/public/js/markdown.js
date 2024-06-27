/* global enableTabsInTextArea */

$(document).ready(() => {
    $('textarea[id^="reply_box"]').each(function () {
        const markdownAreaId = $(this).attr('id');
        enableTabsInTextArea(`#${markdownAreaId}`);
        const markdown_area = $(`#${markdownAreaId}`).closest('.markdown-area');
        markdown_area.find('[data-initialize-preview="1"]').trigger('click');
    });

    $('.markdown-mode-tab').on('click', function () {
        $(this).addClass('active');
        const markdown_area = $(this).closest('.markdown-area');
        markdown_area.find('.markdown-mode-tab').not(this).removeClass('active');
    });

    $(document).ajaxComplete(() => {
        $('textarea[id^="reply_box"]').each(function () {
            const markdownAreaId = $(this).attr('id');
            enableTabsInTextArea(`#${markdownAreaId}`);
            const markdown_area = $(`#${markdownAreaId}`).closest('.markdown-area');
            markdown_area.find('[data-initialize-preview="1"]').trigger('click');
        });
    });
});
