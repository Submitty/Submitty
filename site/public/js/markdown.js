$(document).ready(() => {
    const MIN_HEIGHT = 100;

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

    const applyResizeListeners = () => {
        $('textarea[id^="reply_box"]').each(function() {
            const targetTextarea = $(this);
            targetTextarea.off('input').on('input', function() {
                resizeTextarea(this);
            });
            resizeTextarea(targetTextarea.get(0));
        });
    };

    const initializeMarkdownArea = (markdownAreaId) => {
        enableTabsInTextArea(`#${markdownAreaId}`);
        const markdown_area = $(`#${markdownAreaId}`).closest('.markdown-area');
        markdown_area.find('[data-initialize-preview="1"]').trigger('click');
    };

    const applyMarkdownListeners = () => {
        $('textarea[id^="reply_box"]').each(function() {
            const targetTextarea = $(this);
            const markdownAreaId = targetTextarea.attr('id');
            initializeMarkdownArea(markdownAreaId);
        });

        $('.markdown-mode-tab').on('click', function() {
            $(this).addClass('active');
            const markdown_area = $(this).closest('.markdown-area');
            markdown_area.find('.markdown-mode-tab').not(this).removeClass('active');
        });
    };

    $(document).ajaxComplete(() => {
        applyResizeListeners();
        applyMarkdownListeners();
    });

    // Initial application of listeners when the page loads
    applyResizeListeners();
    applyMarkdownListeners();

    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (node.nodeType === 1 && node.matches('textarea[id^="reply_box"]')) {
                    resizeTextarea(node);
                    const markdownAreaId = node.id;
                    initializeMarkdownArea(markdownAreaId);
                }
            });
        });
    });

    const config = { childList: true, subtree: true };
    const container = document.getElementById('container');
    if (container) {
        observer.observe(container, config);
    }
});
