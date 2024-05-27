$(document).ready(() => {
    const MIN_HEIGHT = 100;
    const targetTextarea = $('#reply_box_1');

    const resizeTextarea = (textarea) => {
        // Temporarily reduce padding to measure the natural content height more accurately
        if (!(textarea instanceof Element)) {
            return;
        }
        const originalPadding = getComputedStyle(textarea).padding;
        textarea.style.padding = '0';
        textarea.style.height = `${MIN_HEIGHT}px`;
        let desiredHeight = Math.max(textarea.scrollHeight, MIN_HEIGHT);
        // Restore original padding
        textarea.style.padding = originalPadding;

        if (desiredHeight > MIN_HEIGHT) {
            desiredHeight += 5;
            textarea.style.height = `${desiredHeight}px`;
        }

        textarea.style.overflowY = 'hidden';
    };

    targetTextarea.on('input', function() {
        resizeTextarea(this);
    });

    $(document).ajaxComplete(() => {
        resizeTextarea(targetTextarea.get(0));
    });

    // Resize textareas when the content is dynamically added
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

    // Resize the specific textarea immediately when the page loads
    resizeTextarea(targetTextarea.get(0));
});
