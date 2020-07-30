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

function rowsToPixels(rows) {
    return rows * 16;
}

