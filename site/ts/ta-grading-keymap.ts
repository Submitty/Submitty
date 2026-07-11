/**
 * References:
 * https://developer.mozilla.org/en-US/docs/Web/API/KeyboardEvent
 */

// -----------------------------------------------------------------------------
// Keyboard shortcut handling

type _Listener = (visible: boolean) => void;
let _visible = false;
const _listeners: _Listener[] = [];

export function notifySettingsVisibility(visible: boolean): void {
    if (_visible === visible) {
        return;
    }
    _visible = visible;
    _listeners.forEach((fn) => fn(visible));
}

export function onSettingsVisibilityChange(fn: _Listener): () => void {
    _listeners.push(fn);
    fn(_visible);
    return () => {
        const idx = _listeners.indexOf(fn);
        if (idx >= 0) {
            _listeners.splice(idx, 1);
        }
    };
}

export function isSettingsVisible(): boolean {
    return _visible;
}

declare global {
    interface Window {
        showSettings(): void;
        hideSettings(): void;
        registerKeyHandler(parameters: object, fn: (...args: unknown[]) => unknown): void;
    }
}

const keymap: KeymapEntry<unknown>[] = [];
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
        optionsGenerator?: ((fullAccess?: boolean) => Record<string, string>);
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
                optionsGenerator: function (fullAccess?: boolean): Record<string, string> {
                    if (!fullAccess) {
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

function getKeyCode(name: string): string {
    const stored = remapGetLS(name);
    if (stored !== null) {
        return stored;
    }
    for (let i = 0; i < keymap.length; i++) {
        if (keymap[i].name === name) {
            return keymap[i].originalCode ?? 'Unassigned';
        }
    }
    return 'Unassigned';
}

window.onkeydown = function (e) {
    // Don't fire hotkeys when the settings popup is open
    if (isSettingsVisible()) {
        return;
    }

    const target = e.target as HTMLElement;
    if (target.tagName === 'TEXTAREA' || (target.tagName === 'INPUT' && (target as HTMLInputElement).type !== 'checkbox') || target.tagName === 'SELECT') {
        return;
    } // disable keyboard event when typing to textarea/input

    const codeName = eventToKeyCode(e);

    for (let i = 0; i < keymap.length; i++) {
        const code = getKeyCode(keymap[i].name);
        if (code === codeName) {
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

export function showSettings() {
    notifySettingsVisibility(true);
}
window.showSettings = showSettings;

export function hideSettings() {
    notifySettingsVisibility(false);
}
window.hideSettings = hideSettings;

export function getKeymap(): KeymapEntry<unknown>[] {
    return keymap;
}

export function updateKeymapAndStorage(index: number, code: string): void {
    keymap[index].code = code;
    const keymapObject = keymap.reduce((obj, hotkey) => {
        obj[hotkey.name] = {
            code: hotkey.code,
            originalCode: hotkey.originalCode!,
        };
        return obj;
    }, {} as Record<string, { code: string; originalCode: string }>);
    localStorage.setItem('keymap', JSON.stringify(keymapObject));
}

export function loadTAGradingSettingData(fullAccess?: boolean): void {
    for (let i = 0; i < settingsData.length; i++) {
        for (let x = 0; x < settingsData[i].values.length; x++) {
            const generator = settingsData[i].values[x].optionsGenerator;
            if (generator) {
                settingsData[i].values[x].options = generator(fullAccess);
            }
            const inquiry = window.Cookies?.get('inquiry_status');
            if (inquiry === 'on') {
                settingsData[i].values[x].currValue = 'active-inquiry';
            }
            else {
                const stored = localStorage.getItem(settingsData[i].values[x].storageCode);
                if (stored !== null && stored !== 'default' && stored !== 'active-inquiry') {
                    settingsData[i].values[x].currValue = stored;
                }
                else {
                    settingsData[i].values[x].currValue = 'default';
                }
            }
            if (settingsData[i].values[x].currValue === null) {
                const defVal = settingsData[i].values[x].options[settingsData[i].values[x].default];
                localStorage.setItem(settingsData[i].values[x].storageCode, defVal);
                settingsData[i].values[x].currValue = defVal;
            }
        }
    }
}

/**
 * Apply a setting change: persist to localStorage and trigger side effects.
 * Returns the new value string.
 */
export function applySettingChange(storageCode: string, value: string): string {
    localStorage.setItem(storageCode, value);

    if (storageCode === 'general-setting-navigate-assigned-students-only') {
        if (value === 'true') {
            window.Cookies?.set('view', 'assigned', { path: '/' });
        }
        else {
            window.Cookies?.set('view', 'all', { path: '/' });
        }
    }

    if (value !== 'active-inquiry') {
        window.Cookies?.set('inquiry_status', 'off');
    }
    else {
        window.Cookies?.set('inquiry_status', 'on');
    }

    window.dispatchEvent(new CustomEvent('settings-changed', { detail: { storageCode, value } }));
    return value;
}

/**
 * Check if a given key code is already bound to another hotkey (by index)
 */
export function isKeyAlreadyBound(index: number, code: string): boolean {
    for (let i = 0; i < keymap.length; i++) {
        if (index !== i && keymap[i].code === code && code !== 'Unassigned') {
            return true;
        }
    }
    return false;
}

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
export function eventToKeyCode(e: KeyboardEvent): string {
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
