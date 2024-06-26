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

    $('textarea[id^="reply_box"]').each(function() {
        const targetTextarea = $(this);
        targetTextarea.on('input', function() {
            resizeTextarea(this);
        });

        $(document).ajaxComplete(() => {
            resizeTextarea(targetTextarea.get(0));
        });

        // Resize the specific textarea immediately when the page loads
        resizeTextarea(targetTextarea.get(0));
    });

    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (node.nodeType === 1 && node.matches('textarea[id^="reply_box"]')) {
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
});
