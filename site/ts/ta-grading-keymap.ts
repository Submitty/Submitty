/**
 * References:
 * https://developer.mozilla.org/en-US/docs/Web/API/KeyboardEvent
 */

// -----------------------------------------------------------------------------
// Keyboard shortcut handling
declare global {
    interface Window {
        showSettings(): void;
        restoreAllHotkeys(): void;
        removeAllHotkeys(): void;
        remapHotkey(i: number): void;
        remapUnset(i: number): void;
    }
}

// eslint-disable-next-line no-var
var keymap: KeymapEntry<unknown>[] = [];
// eslint-disable-next-line no-var
var remapping = {
    active: false,
    index: 0,
};
type KeymapEntry<T> = {
    name: string;
    code: string;
    fn?: (e: KeyboardEvent, options?: T) => void;
    originalCode?: string;
    options?: T;
};

type SettingsData = {
    id: string;
    name: string;
    values: {
        name: string;
        storageCode: string;
        options: Record<string, string>;
        optionsGenerator?: (() => Record<string, string>);
        default: string;
        currValue?: string;
    }[];
}[];

export const settingsData: SettingsData = [
    {
        id: 'general-setting-list',
        name: 'General',
        values: [
            {
                name: 'Prev/Next student arrow functionality',
                storageCode: 'general-setting-arrow-function',
                options: {
                    'Prev/Next Student': 'default',
                    'Prev/Next Ungraded Student': 'ungraded',
                    'Prev/Next Itempool Student': 'itempool',
                    'Prev/Next Ungraded Itempool Student': 'ungraded-itempool',
                    'Prev/Next Grade Inquiry': 'inquiry',
                    'Prev/Next Active Grade Inquiry': 'active-inquiry',
                },
                default: 'Prev/Next Student',
            },
            {
                name: 'Prev/Next buttons navigate through',
                storageCode: 'general-setting-navigate-assigned-students-only',
                options: {},
                optionsGenerator: function (): Record<string, string> {
                    if ($('#ta-grading-settings-list').attr('data-full_access') !== 'true') {
                        return {};
                    }
                    return {
                        'All students': 'false',
                        'Only students in assigned registration/rotation sections': 'true',
                    };
                },
                default: 'Only students in assigned registration/rotation sections',
            },
        ],
    },
    {
        id: 'notebook-setting-list',
        name: 'Notebook',
        values: [
            {
                name: 'Expand files in notebook file submission on page load',
                storageCode: 'notebook-setting-file-submission-expand',
                options: {
                    No: 'false',
                    Yes: 'true',
                },
                default: 'No',
            },
        ],
    },
];

window.onkeyup = function (e) {
    if (remapping.active) {
        remapFinish(remapping.index, eventToKeyCode(e));
        e.preventDefault();
        return;
    }
};

window.onkeydown = function (e) {
    if (remapping.active) {
        e.preventDefault();
        return;
    }

    // Disable hotkeys in the menu so we don't accidentally press anything
    if (isSettingsVisible()) {
        return;
    }

    const target = e.target as HTMLElement;
    if (target.tagName === 'TEXTAREA' || (target.tagName === 'INPUT' && (target as HTMLInputElement).type !== 'checkbox') || target.tagName === 'SELECT') {
        return;
    } // disable keyboard event when typing to textarea/input

    const codeName = eventToKeyCode(e);

    for (let i = 0; i < keymap.length; i++) {
        if (keymap[i].code === codeName) {
            keymap[i].fn?.(e, keymap[i].options);
        }
    }
};

/**
 * Register a function to be called when a key is pressed.
 * @param {object} parameters Parameters object, contains:
 *     {string} name - Display name of the action
 *     {string} code Keycode, e.g. "KeyA" or "ArrowUp" or "Ctrl KeyR", see KeyboardEvent.code
 *     Note the alphabetical order of modifier keys: Alt Control Meta Shift
 * @param {Function} fn Function / callable
 */
export function registerKeyHandler<T>(parameters: KeymapEntry<T>, fn: (e: KeyboardEvent, options?: T) => void) {
    parameters.originalCode = parameters.code || 'Unassigned';
    parameters.fn = fn;

    const storedCode = remapGetLS(parameters.name);
    if (storedCode !== null) {
        parameters.code = storedCode;
    }
    else {
        parameters.code = parameters.originalCode;
    }

    keymap.push(parameters as KeymapEntry<unknown>);
}
// eslint-disable-next-line @typescript-eslint/no-unsafe-function-type
window.registerKeyHandler = function (parameters: object, fn: Function) {
    registerKeyHandler(parameters as KeymapEntry<unknown>, fn as (e: KeyboardEvent, options?: unknown) => void);
};

function isSettingsVisible() {
    return $('#settings-popup').is(':visible');
}

window.showSettings = function () {
    generateSettingList();
    generateHotkeysList();
    $('#settings-popup').show();
    captureTabInModal('settings-popup');
};

window.Twig.twig({
    id: 'HotkeyList',
    href: '/templates/grading/settings/HotkeyList.twig',
    async: true,
});

window.restoreAllHotkeys = function () {
    keymap.forEach((hotkey, index) => {
        updateKeymapAndStorage(index, hotkey.originalCode!);
    });
};

window.removeAllHotkeys = function () {
    keymap.forEach((hotkey, index) => updateKeymapAndStorage(index, 'Unassigned'));
};

/**
 * Generate list of hotkeys on the ui
 */
function generateHotkeysList() {
    const parent = $('#hotkeys-list');

    parent.replaceWith(window.Twig.twig({
        // @ts-expect-error @types/twig is not compatible with the current version of twig
        ref: 'HotkeyList',
    }).render({
        keymap: keymap.map((hotkey) => ({
            ...hotkey,
            code: hotkey.code || hotkey.originalCode || 'Unassigned',
        })),
    }));
}

window.Twig.twig({
    id: 'GeneralSettingList',
    href: '/templates/grading/settings/GeneralSettingList.twig',
    async: true,
});

/**
 * Generate list of settings on the ui
 */
function generateSettingList() {
    const parent = $('#ta-grading-general-settings');
    loadTAGradingSettingData();

    parent.replaceWith(window.Twig.twig({
        // @ts-expect-error @types/twig is not compatible with the current version of twig
        ref: 'GeneralSettingList',
    }).render({
        settings: settingsData,
    }));
}

export function loadTAGradingSettingData() {
    for (let i = 0; i < settingsData.length; i++) {
        for (let x = 0; x < settingsData[i].values.length; x++) {
            const generator = settingsData[i].values[x].optionsGenerator;
            if (generator) {
                settingsData[i].values[x].options = generator();
            }
            const inquiry = window.Cookies.get('inquiry_status');
            if (inquiry === 'on') {
                settingsData[i].values[x].currValue = 'active-inquiry';
            }
            else {
                if (localStorage.getItem(settingsData[i].values[x].storageCode) !== 'default' && localStorage.getItem(settingsData[i].values[x].storageCode) !== 'active-inquiry') {
                    settingsData[i].values[x].currValue = localStorage.getItem(settingsData[i].values[x].storageCode)!;
                }
                else {
                    settingsData[i].values[x].currValue = 'default';
                }
            }
            if (settingsData[i].values[x].currValue === null) {
                localStorage.setItem(settingsData[i].values[x].storageCode, settingsData[i].values[x].options[settingsData[i].values[x].default]);
                settingsData[i].values[x].currValue = settingsData[i].values[x].options[settingsData[i].values[x].default];
            }
        }
    }
}

/**
 * Start rebinding a hotkey
 * @param {int} i Index of hotkey to rebind
 */
window.remapHotkey = function (i: number) {
    if (remapping.active) {
        return;
    }

    const button = $(`#remap-${i}`);
    button.text('Enter Key...');
    remapping.active = true;
    remapping.index = i;

    $('.remap-disable').attr('disabled', 'disabled');
    $('#settings-close').attr('disabled', 'disabled');
    button.attr('disabled', null);
    button.addClass('btn-success');
};

function updateKeymapAndStorage(index: number, code: string) {
    keymap[index].code = code;
    const keymapObject = keymap.reduce((obj, hotkey) => {
        obj[hotkey.name] = {
            code: hotkey.code,
            originalCode: hotkey.originalCode!,
        };
        return obj;
    }, {} as Record<string, { code: string; originalCode: string }>);
    localStorage.setItem('keymap', JSON.stringify(keymapObject));
    generateHotkeysList();
}

/**
 * Called when remapping has finished and should save (or discard) a pressed key
 * @param {int} index Index of the hotkey
 * @param {string} code New keycode for the hotkey
 */
function remapFinish(index: number, code: string) {
    for (let i = 0; i < keymap.length; i++) {
        if (index !== i && keymap[i].code === code && code !== 'Unassigned') {
            const button = $(`#remap-${index}`);
            button.text('Enter Unique Key...');
            button.addClass('btn-danger');
            button.removeClass('btn-success');
            return;
        }
    }

    updateKeymapAndStorage(index, code);
    remapping.active = false;
    $('.remap-disable').attr('disabled', null);
    $('#settings-close').attr('disabled', null);
}

/**
 * Revert a hotkey to its original code
 * @param {int} i Index of hotkey
 */
window.remapUnset = function (index: number) {
    remapFinish(index, 'Unassigned');
};

/**
 * Get the keycode for a hotkey binding from localStorage
 * @param {string} mapName Name of hotkey binding
 * @returns {string|null} Code value if exists, else null
 */
function remapGetLS(mapName: string): string | null {
    const lsKeymap = localStorage.getItem('keymap');
    if (lsKeymap === null) {
        return null;
    }
    try {
        const parsedKeymap = JSON.parse(lsKeymap) as Record<string, { code: string; originalCode: string }>;
        if (!Object.prototype.hasOwnProperty.call(parsedKeymap, mapName)) {
            return null;
        }
        return parsedKeymap[mapName].code;
    }
    catch {
        return null;
    }
}

/**
 * Convert a KeyboardEvent into a (generally) user-readable string
 * @param {KeyboardEvent} e Event
 * @returns {string} String form of the event
 */
function eventToKeyCode(e: KeyboardEvent): string {
    let codeName = e.code;

    // Apply modifiers to code name in reverse alphabetical order so they come out alphabetical
    if (e.shiftKey && (e.code !== 'ShiftLeft' && e.code !== 'ShiftRight')) {
        codeName = `Shift ${codeName}`;
    }
    if (e.metaKey && (e.code !== 'MetaLeft' && e.code !== 'MetaRight')) {
        codeName = `Meta ${codeName}`;
    }
    if (e.ctrlKey && (e.code !== 'ControlLeft' && e.code !== 'ControlRight')) {
        codeName = `Control ${codeName}`;
    }
    if (e.altKey && (e.code !== 'AltLeft' && e.code !== 'AltRight')) {
        codeName = `Alt ${codeName}`;
    }

    return codeName;
}
