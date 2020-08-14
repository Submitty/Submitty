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
