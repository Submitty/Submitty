/**
 * Generate a 'submitty' codemirror.  This is simply a regular codemirror except we've bound accessibility keys to
 * make keyboard navigation better.  It also adds a small instructional message above the codemirror.
 *
 * @param {HTMLElement} attachment_elem The instructional message and codemirror will be appended to this element.
 * @param {Object} codemirror_config A javascript object which defines the configuration the codemirror should be
 *                                   instantiated with.
 * @returns {CodeMirror}
 */
function getSubmittyCodeMirror(attachment_elem, codemirror_config) {
    const accessibility_msg = document.createElement('i');
    accessibility_msg.innerText = 'Press TAB to indent. Press ESC to advance from answer area.';
    accessibility_msg.style.fontSize = '75%';
    attachment_elem.appendChild(accessibility_msg);

    const cm = CodeMirror(attachment_elem, codemirror_config);
    makeCodeMirrorAccessible(cm);
    return cm;
}

/**
 * Make codemirror boxes more accessible.  This function binds some extra key listeners to the passed in codemirror
 * object.  If the codemirror has focus, pressing escape will advance to the next focusable element outside the
 * codemirror, pressing 'Shift-Tab' will move to the previous.
 *
 * @param {CodeMirror} cm A codemirror object
 */
function makeCodeMirrorAccessible(cm) {
    cm.setOption("extraKeys", {
        'Esc': () => {
            const elements = getFocusableElements();
            let index = elements.indexOf(document.activeElement) + 1;

            if (index >= elements.length) {
                index = 0;
            }

            elements[index].focus();
        },
        'Shift-Tab': () => {
            const elements = getFocusableElements();
            let index = elements.indexOf(document.activeElement) - 1;

            if (index < 0) {
                index = 0;
            }

            elements[index].focus();
        }
    });
}

/**
 * Convert the desired number of rows (lines) to show in a codemirror box to pixels which can be used for css
 * styling.  The rows to pixels conversion is a rough estimation and may vary slightly between browsers.
 *
 * @param {int} rows Desired number of rows the codemirror should show.
 * @returns {number} A pixel value which can be used to control the height of the codemirror box.
 */
function rowsToPixels(rows) {
    return rows * 16;
}
