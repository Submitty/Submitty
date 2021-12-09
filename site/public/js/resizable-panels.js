/* exported initializeResizablePanels */

/**
 * callback attached to run after the initialization of resizeable panels
 *
 * @callback resizeCallback
 * @param {string} updatedVal updated width/height after the user is done with resizing
 * @param {boolean} isHorizontalResize helps in updating the value in callback function
 */

/**
 * Initializes Resizables two panels mode
 * where width (height, if horizontal resize) from one panel is be given out to another one i.e resizable widths/height
 * @param {string} panelSel DOM selector for left panel, or bottom panel if resizing is horizontal
 * @param {string} dragBarSel DOM selector for drag-bar on which this resizing event will be attached
 * @param {boolean} isHorizontalResize If set true, horizontal resizing is initiated (top and bottom) else resizing is vertical
 * @param {resizeCallback} callback
 */
function initializeResizablePanels (panelSel, dragBarSel, isHorizontalResize= false, callback = null) {
    // Select all the DOM elements for dragging in two-panel-mode
    const panelEle = document.querySelector(panelSel);
    const panelCont = panelEle.parentElement;
    const dragbar = document.querySelector(dragBarSel);

    $(dragbar).css('touch-action', 'none');

    let xPos = 0, yPos = 0, panelHeight = 0, panelWidth = 0;

    // Width of left side
    const mouseDownHandler = (e) => {
        // Get the current mouse position
        if (e.type == 'touchstart') {
            e = e.touches[0];
        }
        xPos = e.clientX;
        yPos = e.clientY;
        panelHeight = panelEle.getBoundingClientRect().height;
        panelWidth = panelEle.getBoundingClientRect().width;

        document.documentElement.style.setProperty(
            'cursor',
            dragBarSel === '.two-panel-drag-bar' || dragBarSel === '.plag-drag-bar' ? 'col-resize' : 'ns-resize',
            'important',
        );

        // Attach the listeners to `document`
        document.addEventListener('mousemove', mouseMoveHandler);
        document.addEventListener('mouseup', mouseUpHandler);
        document.addEventListener('touchmove', mouseMoveHandler);
        document.addEventListener('touchend', mouseUpHandler);
    };

    const mouseUpHandler = () => {
        // remove the dragging CSS props to go back to initial styling
        document.documentElement.style.removeProperty('cursor');
        document.body.style.removeProperty('user-select');
        document.body.style.removeProperty('pointer-events');
        dragbar.style.removeProperty('filter');

        // Remove the handlers of `mousemove` and `mouseup`
        document.removeEventListener('mousemove', mouseMoveHandler);
        document.removeEventListener('mouseup', mouseUpHandler);
        document.removeEventListener('touchmove', mouseMoveHandler);
        document.removeEventListener('touchend', mouseUpHandler);
    };

    const mouseMoveHandler = (e) => {
        if (e.type == 'touchmove') {
            e = e.touches[0];
        }
        let updateValue;
        if (isHorizontalResize) {
            const dy = e.clientY - yPos;
            updateValue = (panelHeight - dy) * 100 / panelCont.getBoundingClientRect().height;
            panelEle.style.height = `${updateValue}%`;
        }
        else {
            const dx = e.clientX - xPos;
            updateValue = (panelWidth + dx) * 100 / panelCont.getBoundingClientRect().width;
            panelEle.style.width = `${updateValue}%`;
        }

        // Disable text selection when dragging
        document.body.style.userSelect = 'none';
        document.body.style.pointerEvents = 'none';

        // Callback function
        if (typeof callback === 'function') {
            callback(`${updateValue}%`, isHorizontalResize);
        }
    };
    dragbar.addEventListener('mousedown', mouseDownHandler);
    dragbar.addEventListener('touchstart', mouseDownHandler);
}
