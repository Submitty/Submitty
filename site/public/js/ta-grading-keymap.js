//-----------------------------------------------------------------------------
// Keyboard shortcut handling

var keymap = {};

window.onkeydown = function(e) {
    if (e.target.tagName === "TEXTAREA" || (e.target.tagName === "INPUT" && e.target.type !== "checkbox") || e.target.tagName === "SELECT") return; // disable keyboard event when typing to textarea/input

    var codeName = e.code;

    //Apply modifiers to code name in reverse alphabetical order so they come out alphabetical
    if (e.shiftKey && (e.code !== "ShiftLeft" && e.code !== "ShiftRight")) {
        codeName = "Shift " + codeName;
    }
    if (e.metaKey && (e.code !== "MetaLeft" && e.code !== "MetaRight")) {
        codeName = "Meta " + codeName;
    }
    if (e.ctrlKey && (e.code !== "ControlLeft" && e.code !== "ControlRight")) {
        codeName = "Control " + codeName;
    }
    if (e.altKey && (e.code !== "AltLeft" && e.code !== "AltRight")) {
        codeName = "Alt " + codeName;
    }

    if (keymap.hasOwnProperty(codeName)) {
        keymap[codeName].fns.forEach(function (fn) {
            fn(e);
        });
    }
};

/**
 * Register a function to be called when a key is pressed.
 * @param {string} name Display name of the action
 * @param {string} code Keycode, e.g. "KeyA" or "ArrowUp" or "Ctrl KeyR", see KeyboardEvent.code
 * Note the alphabetical order of modifier keys: Alt Control Meta Shift
 * @param {Function} fn Function / callable
 */
function registerKeyHandler(name, code, fn) {
    if (keymap.hasOwnProperty(code)) {
        keymap[code].fns.append(fn);
    } else {
        keymap[code] = {
            fns: [fn]
        };
    }
}

/**
 * Unregister a key handler. Arguments are equivalent to registerKeyHandler()
 * @param code Keycode, see registerKeyHandler()
 * @param fn Function / callable
 */
function unregisterKeyHandler(code, fn) {
    if (keymap.hasOwnProperty(code)) {
        if (keymap[code].fns.indexOf(fn) !== -1) {
            //Delete the function from the list
            keymap[code].fns.splice(keymap[code].fns.indexOf(fn), 1);
        }
    } else {
        //Don't care if this key doesn't exist
    }
}
