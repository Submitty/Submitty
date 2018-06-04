//-----------------------------------------------------------------------------
// Keyboard shortcut handling

var keymap = [];

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

    for (var i = 0; i < keymap.length; i++) {
        if (keymap[i].code === codeName) {
            keymap[i].fn(e);
        }
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
    keymap.push({
        name: name,
        code: code,
        fn: fn
    });
}

/**
 * Unregister a key handler. Arguments are equivalent to registerKeyHandler()
 * @param code Keycode, see registerKeyHandler()
 * @param fn Function / callable
 */
function unregisterKeyHandler(code, fn) {
    for (var i = 0; i < keymap.length; i++) {
        if (keymap[i].code === code || keymap[i].fn === fn) {
            //Delete 1 at i
            keymap.splice(i, 1);
            i--;
        }
    }
}

function generateHotkeysList() {
    var parent = $("#hotkeys-list");
    parent.children().remove();

    for (var i = 0; i < keymap.length; i++) {
        var hotkey = keymap[i];

        parent.append(
            "<tr><td>" + hotkey.name + "</td><td>" + hotkey.code  + "</td></tr>"
        )
    }
}

function showSettings() {
    generateHotkeysList();
    $("#settings-popup").show();
}
