import {
    closeComponent,
    CUSTOM_MARK_ID,
    getComponentIdByOrder,
    getFirstOpenComponentId,
    getMarkIdFromOrder,
    getNextComponentId,
    getPrevComponentId,
    NO_COMPONENT_ID,
    onToggleEditMode,
    scrollToComponent,
    scrollToOverallComment,
    toggleCommonMark,
    toggleComponent as oldToggleComponent,
} from '../../../ts/ta-grading-rubric';
import { gotoNextStudent, gotoPrevStudent } from '../../../ts/ta-grading-toolbar';

declare global {
    interface Window {
        updateCheckpointCells: (cells: JQuery, score: number | null) => void;
    }
}

export type KeymapEntry<T> = {
    name: string;
    code: string;
    fn?: (e: KeyboardEvent, options?: T) => void;
    originalCode?: string;
    options?: T;
    error?: boolean;
};

export type Remapping = {
    active: boolean;
    index: number;
};

/**
 * Convert a KeyboardEvent into a (generally) user-readable string
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

export function updateKeymapAndStorage(keymap: KeymapEntry<unknown>[], index: number, code: string) {
    keymap[index].code = code;
    const keymapObject = keymap.reduce((obj, hotkey) => {
        obj[hotkey.name] = {
            code: hotkey.code,
            originalCode: hotkey.originalCode || '',
        };
        return obj;
    }, {} as Record<string, { code: string; originalCode: string }>);
    localStorage.setItem('keymap', JSON.stringify(keymapObject));
}

// Finish remapping
export function remapFinish(keymap: KeymapEntry<unknown>[], remapping: Remapping, index: number, code: string) {
    const duplicate = keymap.some((k, i) => i !== index && k.code === code && code !== 'Unassigned');
    if (duplicate) {
        keymap[index].error = true;
        keymap[index].code = 'Enter Unique Key...';
        return;
    }
    keymap[index].error = false;
    updateKeymapAndStorage(keymap, index, code);
    remapping.active = false;
}

// Keyup listener
export function handleKeyUp(e: KeyboardEvent, keymap: KeymapEntry<unknown>[], remapping: Remapping) {
    console.log('Keyup event:', e, 'Remapping:', remapping);
    if (remapping.active) {
        remapFinish(keymap, remapping, remapping.index, eventToKeyCode(e));
        e.preventDefault();
        return;
    }
}

export function handleKeyDown(e: KeyboardEvent, keymap: KeymapEntry<unknown>[], remapping: Remapping, visible: boolean) {
    console.log('Keydown event:', e, 'Remapping:', remapping, 'Visible:', visible);
    if (remapping.active && e.code !== 'Escape') {
        e.preventDefault();
        return;
    }

    if (visible) {
        return;
    }

    const target = e.target as HTMLElement;
    // disable keyboard event when typing to textarea/input
    if (target.tagName === 'TEXTAREA' || (target.tagName === 'INPUT' && (target as HTMLInputElement).type !== 'checkbox') || target.tagName === 'SELECT') {
        return;
    }
    console.log('got past target check with', target.tagName, (target as HTMLInputElement).type);

    const codeName = eventToKeyCode(e);

    for (let i = 0; i < keymap.length; i++) {
        if (keymap[i].code === codeName) {
            keymap[i].fn?.(e, keymap[i].options);
        }
    }
}

// Get hotkey from localStorage
export function remapGetLS(mapName: string): string | null {
    const lsKeymap = localStorage.getItem('keymap');
    if (!lsKeymap) {
        return null;
    }
    try {
        const parsed = JSON.parse(lsKeymap) as Record<string, { code: string; originalCode: string }>;
        return parsed[mapName]?.code ?? null;
    }
    catch {
        return null;
    }
}

function registerKeyHandler<T>(keymap: KeymapEntry<unknown>[], parameters: KeymapEntry<T>, fn: (e: KeyboardEvent, options?: T) => void) {
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

// ------ start of all functions reigstering hotkeys ------
function checkOpenComponentMark(index: number) {
    const component_id = getFirstOpenComponentId();
    if (component_id !== NO_COMPONENT_ID) {
        const mark_id = getMarkIdFromOrder(component_id, index);
        // TODO: Custom mark id is zero as well, should use something unique
        if (mark_id === CUSTOM_MARK_ID || mark_id === 0) {
            return;
        }
        void toggleCommonMark(component_id, mark_id).catch((err: { message: string }) => {
            console.error(err);
            alert(`Error toggling mark! ${err.message}`);
        });
    }
}

async function toggleComponent(component_id: number, saveChanges: boolean, edit_mode: boolean): Promise<void> {
    void edit_mode; // to avoid unused variable warning
    await oldToggleComponent(component_id, saveChanges);
}

export function initTaGradingHotkeys(
    keymap: KeymapEntry<unknown>[],
) {
    // Navigate to the prev / next student buttons
    registerKeyHandler(keymap, { name: 'Previous Student', code: 'ArrowLeft' }, () => {
        gotoPrevStudent();
    });
    registerKeyHandler(keymap, { name: 'Next Student', code: 'ArrowRight' }, () => {
        gotoNextStudent();
    });

    // Key handler / shorthand for toggling in between panels
    registerKeyHandler(keymap, { name: 'Toggle Autograding Panel', code: 'KeyA' }, () => {
        $('#autograding_results_btn button').trigger('click');
        window.updateCookies();
    });
    registerKeyHandler(keymap, { name: 'Toggle Rubric Panel', code: 'KeyG' }, () => {
        $('#grading_rubric_btn button').trigger('click');
        window.updateCookies();
    });
    registerKeyHandler(keymap, { name: 'Toggle Submissions Panel', code: 'KeyO' }, () => {
        $('#submission_browser_btn button').trigger('click');
        window.updateCookies();
    });
    registerKeyHandler(
        keymap,
        { name: 'Toggle Student Information Panel', code: 'KeyS' },
        () => {
            $('#student_info_btn button').trigger('click');
            window.updateCookies();
        },
    );
    registerKeyHandler(keymap, { name: 'Toggle Grade Inquiry Panel', code: 'KeyX' }, () => {
        $('#grade_inquiry_info_btn button').trigger('click');
        window.updateCookies();
    });
    registerKeyHandler(keymap, { name: 'Toggle Discussion Panel', code: 'KeyD' }, () => {
        $('#discussion_browser_btn button').trigger('click');
        window.updateCookies();
    });
    registerKeyHandler(keymap, { name: 'Toggle Peer Panel', code: 'KeyP' }, () => {
        $('#peer_info_btn button').trigger('click');
        window.updateCookies();
    });

    registerKeyHandler(keymap, { name: 'Toggle Notebook Panel', code: 'KeyN' }, () => {
        $('#notebook-view-btn button').trigger('click');
        window.updateCookies();
    });
    registerKeyHandler(
        keymap,
        { name: 'Toggle Solution/TA-Notes Panel', code: 'KeyT' },
        () => {
            $('#solution_ta_notes_btn button').trigger('click');
            window.updateCookies();
        },
    );

    registerKeyHandler(keymap, { name: 'Open Next Component', code: 'ArrowDown' }, (e: { preventDefault: () => void }) => {
        const openComponentId = getFirstOpenComponentId();
        const numComponents = $('#component-list').find(
            '.component-container',
        ).length;

        // Note: we use the 'toggle' functions instead of the 'open' functions
        //  Since the 'open' functions don't close any components
        if (openComponentId === NO_COMPONENT_ID) {
        // No component is open, so open the first one
            const componentId = getComponentIdByOrder(0);
            void toggleComponent(componentId, true, false).then(() => {
                scrollToComponent(componentId);
            });
        }
        else if (openComponentId === getComponentIdByOrder(numComponents - 1)) {
        // Last component is open, close it and then open and scroll to first component
            void closeComponent(openComponentId, true).then(() => {
                const componentId = getComponentIdByOrder(0);
                void toggleComponent(componentId, true, false).then(() => {
                    scrollToComponent(componentId);
                });
            });
        }
        else {
        // Any other case, open the next one
            const nextComponentId = getNextComponentId(openComponentId);
            void toggleComponent(nextComponentId, true, false).then(() => {
                scrollToComponent(nextComponentId);
            });
        }
        e.preventDefault();
    });

    registerKeyHandler(
        keymap,
        { name: 'Open Previous Component', code: 'ArrowUp' },
        (e: { preventDefault: () => void }) => {
            const openComponentId = getFirstOpenComponentId();
            const numComponents = $('#component-list').find(
                '.component-container',
            ).length;

            // Note: we use the 'toggle' functions instead of the 'open' functions
            //  Since the 'open' functions don't close any components
            if (openComponentId === NO_COMPONENT_ID) {
            // No Component is open, so open the overall comment
            // Targets the box outside of the container, can use tab to focus comment
            // TODO: Add "Overall Comment" focusing, control
                scrollToOverallComment();
            }
            else if (openComponentId === getComponentIdByOrder(0)) {
            // First component is open, close it and then open and scroll to the last one
                void closeComponent(openComponentId, true).then(() => {
                    const componentId = getComponentIdByOrder(numComponents - 1);
                    void toggleComponent(componentId, true, false).then(() => {
                        scrollToComponent(componentId);
                    });
                });
            }
            else {
            // Any other case, open the previous one
                const prevComponentId = getPrevComponentId(openComponentId);
                void toggleComponent(prevComponentId, true, false).then(() => {
                    scrollToComponent(prevComponentId);
                });
            }
            e.preventDefault();
        },
    );

    // -----------------------------------------------------------------------------
    // Misc rubric options
    registerKeyHandler(keymap, { name: 'Toggle Rubric Edit Mode', code: 'KeyE' }, () => {
        const editBox = $('#edit-mode-enabled');
        // eslint-disable-next-line vue/no-ref-object-reactivity-loss
        editBox.prop('checked', !editBox.prop('checked'));
        void onToggleEditMode();
        window.updateCookies();
    });

    // -----------------------------------------------------------------------------
    // Selecting marks

    registerKeyHandler(
        keymap,
        { name: 'Select Full/No Credit Mark', code: 'Digit0' },
        () => {
            checkOpenComponentMark(0);
        },
    );
    registerKeyHandler(keymap, { name: 'Select Mark 1', code: 'Digit1' }, () => {
        checkOpenComponentMark(1);
    });
    registerKeyHandler(keymap, { name: 'Select Mark 2', code: 'Digit2' }, () => {
        checkOpenComponentMark(2);
    });
    registerKeyHandler(keymap, { name: 'Select Mark 3', code: 'Digit3' }, () => {
        checkOpenComponentMark(3);
    });
    registerKeyHandler(keymap, { name: 'Select Mark 4', code: 'Digit4' }, () => {
        checkOpenComponentMark(4);
    });
    registerKeyHandler(keymap, { name: 'Select Mark 5', code: 'Digit5' }, () => {
        checkOpenComponentMark(5);
    });
    registerKeyHandler(keymap, { name: 'Select Mark 6', code: 'Digit6' }, () => {
        checkOpenComponentMark(6);
    });
    registerKeyHandler(keymap, { name: 'Select Mark 7', code: 'Digit7' }, () => {
        checkOpenComponentMark(7);
    });
    registerKeyHandler(keymap, { name: 'Select Mark 8', code: 'Digit8' }, () => {
        checkOpenComponentMark(8);
    });
    registerKeyHandler(keymap, { name: 'Select Mark 9', code: 'Digit9' }, () => {
        checkOpenComponentMark(9);
    });
}

// check if a cell is focused, then update value
function keySetCurrentCell(_: KeyboardEvent, options?: { score: number | null }) {
    console.log('keySetCurrentCell called with options:', options);
    if (!options) {
        return;
    }
    const cell = $('.cell-grade:focus');
    // eslint-disable-next-line vue/no-ref-object-reactivity-loss
    if (cell.length) {
        // eslint-disable-next-line vue/no-ref-object-reactivity-loss
        window.updateCheckpointCells(cell, options.score);
    }
}

// check if a cell is focused, then update the entire row
function keySetCurrentRow(_: KeyboardEvent, options?: { score: number | null }) {
    if (!options) {
        return;
    }
    const cell = $('.cell-grade:focus');
    // eslint-disable-next-line vue/no-ref-object-reactivity-loss
    if (cell.length) {
        // eslint-disable-next-line vue/no-ref-object-reactivity-loss
        window.updateCheckpointCells(cell.parent().find('.cell-grade'), options.score);
    }
}

export function initSimpleGradingHotkeys(
    keymap: KeymapEntry<unknown>[],
    type: 'lab' | 'numeric',
) {
    registerKeyHandler(keymap, { name: 'Search', code: 'Enter' }, () => {});
    registerKeyHandler(keymap, { name: 'Move Right', code: 'ArrowRight' }, () => {});
    registerKeyHandler(keymap, { name: 'Move Left', code: 'ArrowLeft' }, () => {});
    registerKeyHandler(keymap, { name: 'Move Up', code: 'ArrowUp' }, () => {});
    registerKeyHandler(keymap, { name: 'Move Down', code: 'ArrowDown' }, () => {});

    if (type === 'lab') {
        registerKeyHandler(keymap, { name: 'Set Cell to 0', code: 'KeyZ', options: { score: 0 } }, keySetCurrentCell);
        registerKeyHandler(keymap, { name: 'Set Cell to 0.5', code: 'KeyX', options: { score: 0.5 } }, keySetCurrentCell);
        registerKeyHandler(keymap, { name: 'Set Cell to 1', code: 'KeyC', options: { score: 1 } }, keySetCurrentCell);
        registerKeyHandler(keymap, { name: 'Cycle Cell Value', code: 'KeyV', options: { score: null } }, keySetCurrentCell);
        registerKeyHandler(keymap, { name: 'Set Row to 0', code: 'KeyA', options: { score: 0 } }, keySetCurrentRow);
        registerKeyHandler(keymap, { name: 'Set Row to 0.5', code: 'KeyS', options: { score: 0.5 } }, keySetCurrentRow);
        registerKeyHandler(keymap, { name: 'Set Row to 1', code: 'KeyD', options: { score: 1 } }, keySetCurrentRow);
        registerKeyHandler(keymap, { name: 'Cycle Row Value', code: 'KeyF', options: { score: null } }, keySetCurrentRow);
    }
}
