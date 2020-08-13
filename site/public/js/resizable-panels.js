/**
 * Initializes Resizables two panels mode
 * where width from one panel can be given out to another one i.e resizable widths
 * @param {string} leftPanelSel DOM selector for left panel
 * @param {string} dragBarSel DOM selector for drag-bar on which this resizing event will be attached
 */
function initializeResizablePanels (leftPanelSel, dragBarSel) {
    // Select all the DOM elements for dragging in two-panel-mode
    const leftPanel = document.querySelector(leftPanelSel);
    const panelCont = leftPanel.parentElement;
    const dragbar = document.querySelector(dragBarSel);

    let xPos = 0, leftPanelWidth = 0;

    // Width of left side
    const mouseDownHandler = (e) => {
        // Get the current mouse position
        xPos = e.clientX;
        leftPanelWidth = leftPanel.getBoundingClientRect().width;

        // Attach the listeners to `document`
        document.addEventListener('mousemove', mouseMoveHandler);
        document.addEventListener('mouseup', mouseUpHandler);
    };

    const mouseUpHandler = () => {
        // remove the dragging CSS props to go back to initial styling
        dragbar.style.removeProperty('cursor');
        document.body.style.removeProperty('cursor');
        document.body.style.removeProperty('user-select');
        document.body.style.removeProperty('pointer-events');
        dragbar.style.removeProperty('filter');

        // Remove the handlers of `mousemove` and `mouseup`
        document.removeEventListener('mousemove', mouseMoveHandler);
        document.removeEventListener('mouseup', mouseUpHandler);
    };

    const mouseMoveHandler = (e) => {
        const dx = e.clientX - xPos;
        const updateLeftPanelWidth = (leftPanelWidth + dx) * 100 / panelCont.getBoundingClientRect().width;
        leftPanel.style.width = `${updateLeftPanelWidth}%`;

        // consistent mouse pointer during dragging
        document.body.style.cursor = 'col-resize';
        // Disable text selection when dragging
        document.body.style.userSelect = 'none';
        document.body.style.pointerEvents = 'none';
        // Add blurry effect on drag-bar
        dragbar.style.filter = 'blur(5px)';
    };
    dragbar.addEventListener('mousedown', mouseDownHandler);
}
