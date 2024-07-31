/* global enableTabsInTextArea */

$(document).ready(() => {
    $('textarea[id^="reply_box"]').each(function () {
        const markdownAreaId = $(this).attr('id');
        if (markdownAreaId !== 'reply_box_1') {
            enableTabsInTextArea(`#${markdownAreaId}`);
            const markdown_area = $(`#${markdownAreaId}`).closest('.markdown-area');
            markdown_area.find('[data-initialize-preview="1"]').trigger('click');
        }
    });

    $('.markdown-mode-tab').on('click', function () {
        $(this).addClass('active');
        const markdown_area = $(this).closest('.markdown-area');
        markdown_area.find('.markdown-mode-tab').not(this).removeClass('active');
    });

    $(document).ajaxComplete(() => {
        $('textarea[id^="reply_box"]').each(function () {
            const markdownAreaId = $(this).attr('id');
            if (markdownAreaId !== 'reply_box_1') {
                enableTabsInTextArea(`#${markdownAreaId}`);
                const markdown_area = $(`#${markdownAreaId}`).closest('.markdown-area');
                markdown_area.find('[data-initialize-preview="1"]').trigger('click');
            }
        });
    });
});
$(document).ready(() => {
    const MIN_HEIGHT = 100;
    const targetTextarea = $('#reply_box_1');
    const resizeTextarea = (textarea) => {
        if (!(textarea instanceof Element)) {
            return;
        }
        const originalPadding = getComputedStyle(textarea).padding;
        textarea.style.padding = '0';
        textarea.style.height = `${MIN_HEIGHT}px`;
        let desiredHeight = Math.max(textarea.scrollHeight, MIN_HEIGHT);
        textarea.style.padding = originalPadding;
        if (desiredHeight > MIN_HEIGHT) {
            desiredHeight += 5;
            textarea.style.height = `${desiredHeight}px`;
        }
        textarea.style.overflowY = 'hidden';
    };

    targetTextarea.on('input', function () {
        resizeTextarea(this);
    });

    $(document).ajaxComplete(() => {
        resizeTextarea(targetTextarea.get(0));
    });
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (node.nodeType === 1 && node.matches('#reply_box_0')) {
                    resizeTextarea(node);
                }
            });
        });
    });
    const config = { childList: true, subtree: true };
    const container = document.getElementById('container');
    if (container) {
        observer.observe(container, config);
    }
    resizeTextarea(targetTextarea.get(0));
});
