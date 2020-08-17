/**
 * Generate a large codemirror.  This is simply a regular codemirror except we've bound accessibility keys to
 * make keyboard navigation better.  It also adds a small instructional message above the codemirror.
 *
 * @param {HTMLElement} attachment_elem The instructional message and codemirror will be appended to this element.
 * @param {Object} codemirror_config A javascript object which defines the configuration the codemirror should be
 *                                   instantiated with.
 * @returns {CodeMirror}
 */
function getLargeCodeMirror(attachment_elem, codemirror_config) {
    const accessibility_msg = document.createElement('i');
    accessibility_msg.innerText = 'Press TAB to indent. Press ESC to advance from answer area.';
    accessibility_msg.style.fontSize = '75%';
    attachment_elem.appendChild(accessibility_msg);

    // If no mode is set must explicitly set it to null otherwise codemirror will attempt to guess the language and
    // highlight.  This is not desirable when collecting plain text.
    if (!codemirror_config.mode) {
        codemirror_config.mode = null;
    }

    const cm = CodeMirror(attachment_elem, codemirror_config);
    makeCodeMirrorAccessible(cm, 'Esc');
    return cm;
}

/**
 * Generate a small codemirror.  This codemirror has been setup to look and behave like a default html
 * <input type="text">.
 *
 * @param {HTMLElement} attachment_elem The element the codemirror will be appended to.
 * @param {Object} codemirror_config A javascript object which defines the configuration the codemirror should be
 *                                   instantiated with.
 * @returns {CodeMirror}
 */
function getSmallCodeMirror(attachment_elem, codemirror_config) {
    codemirror_config.scrollbarStyle = null;
    codemirror_config.mode = null;

    const cm = CodeMirror(attachment_elem, codemirror_config);
    cm.setSize(150, 30);
    makeCodeMirrorAccessible(cm, 'Tab');
    disableEnterKey(cm);
    return cm;
}

/**
 * Make codemirror boxes more accessible.  This function binds some extra key listeners to the passed in codemirror
 * object.  If the codemirror has focus, pressing the advance_key will advance to the next focusable element outside the
 * codemirror, pressing 'Shift-Tab' will move to the previous.
 *
 * @param {CodeMirror} cm A codemirror object
 * @param {String} advance_key They key that when pressed, will advance focus from the codebox to the next element.
 *                             Will probably be 'Esc' or 'Tab'.
 */
function makeCodeMirrorAccessible(cm, advance_key) {
    const keys = {}

    keys['Shift-Tab'] = () => {
        const elements = getFocusableElements();
        let index = elements.indexOf(document.activeElement) - 1;

        if (index < 0) {
            index = 0;
        }

        elements[index].focus();
    }

    keys[advance_key] = () => {
        const elements = getFocusableElements();
        let index = elements.indexOf(document.activeElement) + 1;

        if (index >= elements.length) {
            index = 0;
        }

        elements[index].focus();
    }

    cm.setOption("extraKeys", keys);
}

/**
 * Disable the enter key from creating a new line in the passed in codemirror.
 *
 * @param {CodeMirror} cm
 */
function disableEnterKey(cm) {
    cm.addKeyMap({
        'Enter': () => { /** Pass */ }
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

    // Originally was 16 (set to look good on Linux ?)
    // return rows * 16;

    // 15 looks good on Mac Firefox
    // return rows * 15;

    // 17 looks good on Mac Chrome & Mac Safari
    return rows * 17;
}
