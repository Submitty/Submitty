import GradingSettings from '../../vue/src/components/ta_grading/GradingSettings.vue';
import { registerKeyHandler, getKeymap } from '../../ts/ta-grading-keymap';

/**
 * Branches in GradingSettings.vue:
 * - v-if="visible" on outer popup-form          → covered by hidden / shown tests
 * - @click.self="close" on popup-box             → covered by overlay click test
 * - v-for="group in settings"                   → covered by settings group rendering test
 * - v-if="setting.options && Object.keys(...)"   → covered by fullAccess conditional rendering tests
 * - v-for="setting in group.values"             → covered by settings rendering test
 * - v-for="(optValue, optKey) in setting.options" → covered by settings options test
 * - :selected="optValue === setting.currValue"   → covered by setting change test
 * - v-for="(hotkey, index) in hotkeys"          → covered by hotkey rendering test
 * - :class ternary: 'btn-success' / 'btn-default' → covered by remap active / inactive tests
 * - ternary: 'Enter Key...' / hotkey.code        → covered by remap tests
 * - startRemap guard: if (remapActive.value) return    → covered by double-remap test
 * - onDocumentKeydown: if (!visible.value) return      → covered by hidden-Escape test
 * - onDocumentKeydown: if (e.key === 'Escape') close() → covered by Escape tests
 * - onDocumentKeyup guard: if (!visible.value || !remapActive.value) → covered by remap tests
 * - onDocumentKeyup: isKeyAlreadyBound guard → covered by conflict test
 */
describe('GradingSettings', () => {
    beforeEach(() => {
        // Clear module-level keymap so each test starts fresh
        getKeymap().length = 0;

        // Set up window globals that loadTAGradingSettingData depends on
        cy.window().then((win) => {
            win.Cookies = {
                get: cy.stub().returns(undefined),
                set: cy.stub(),
            };
            // Clear any prior global state
            delete win.__settingsPopupVisible;
        });

        // Register test hotkeys
        registerKeyHandler({ name: 'Previous Student', code: 'KeyA' }, () => {});
        registerKeyHandler({ name: 'Next Student', code: 'KeyS' }, () => {});
        registerKeyHandler({ name: 'Save', code: 'KeyD' }, () => {});
    });

    describe('visibility', () => {
        it('is hidden by default', () => {
            cy.mount(GradingSettings, { props: { fullAccess: false } });
            cy.get('[data-testid="settings-popup"]').should('not.exist');
        });

        it('shows when toggle-settings-popup fires with show: true', () => {
            cy.mount(GradingSettings, { props: { fullAccess: false } });
            cy.document().then((doc) => {
                doc.dispatchEvent(new CustomEvent('toggle-settings-popup', { detail: { show: true } }));
            });
            cy.get('[data-testid="settings-popup"]').should('be.visible');
            cy.get('[data-testid="close-button"]').should('be.visible');
        });

        it('closes when the close button is clicked', () => {
            cy.mount(GradingSettings, { props: { fullAccess: false } });
            cy.document().then((doc) => {
                doc.dispatchEvent(new CustomEvent('toggle-settings-popup', { detail: { show: true } }));
            });
            cy.get('[data-testid="close-button"]').click();
            cy.get('[data-testid="settings-popup"]').should('not.exist');
        });

        it('closes when Escape is pressed', () => {
            cy.mount(GradingSettings, { props: { fullAccess: false } });
            cy.document().then((doc) => {
                doc.dispatchEvent(new CustomEvent('toggle-settings-popup', { detail: { show: true } }));
            });
            cy.document().trigger('keydown', { key: 'Escape' });
            cy.get('[data-testid="settings-popup"]').should('not.exist');
        });

        it('closes when the overlay is clicked', () => {
            cy.mount(GradingSettings, { props: { fullAccess: false } });
            cy.document().then((doc) => {
                doc.dispatchEvent(new CustomEvent('toggle-settings-popup', { detail: { show: true } }));
            });
            cy.get('[data-testid="popup-overlay"]').then(($el) => {
                $el[0].dispatchEvent(new MouseEvent('click', { bubbles: true }));
            });
            cy.get('[data-testid="settings-popup"]').should('not.exist');
        });

        it('hides when a second toggle fires with show: false', () => {
            cy.mount(GradingSettings, { props: { fullAccess: false } });
            cy.document().then((doc) => {
                doc.dispatchEvent(new CustomEvent('toggle-settings-popup', { detail: { show: true } }));
            });
            cy.get('[data-testid="settings-popup"]').should('be.visible');
            cy.document().then((doc) => {
                doc.dispatchEvent(new CustomEvent('toggle-settings-popup', { detail: { show: false } }));
            });
            cy.get('[data-testid="settings-popup"]').should('not.exist');
        });
    });

    describe('settings rendering', () => {
        it('renders all setting groups from settingsData', () => {
            cy.mount(GradingSettings, { props: { fullAccess: false } });
            cy.document().then((doc) => {
                doc.dispatchEvent(new CustomEvent('toggle-settings-popup', { detail: { show: true } }));
            });
            cy.contains('h2', 'General').should('be.visible');
            cy.contains('h2', 'Notebook').should('be.visible');
            cy.contains('h2', 'Hotkeys').should('be.visible');
        });

        it('shows setting selects with correct options', () => {
            cy.mount(GradingSettings, { props: { fullAccess: false } });
            cy.document().then((doc) => {
                doc.dispatchEvent(new CustomEvent('toggle-settings-popup', { detail: { show: true } }));
            });
            cy.get('[data-testid="ta-grading-setting-option-general-setting-arrow-function"]')
                .should('be.visible')
                .find('option')
                .should('have.length.at.least', 4);
        });

        it('hides conditional settings when fullAccess is false', () => {
            cy.mount(GradingSettings, { props: { fullAccess: false } });
            cy.document().then((doc) => {
                doc.dispatchEvent(new CustomEvent('toggle-settings-popup', { detail: { show: true } }));
            });
            cy.get(
                '[data-testid="ta-grading-setting-option-general-setting-navigate-assigned-students-only"]',
            ).should('not.exist');
        });

        it('shows conditional settings when fullAccess is true', () => {
            cy.mount(GradingSettings, { props: { fullAccess: true } });
            cy.document().then((doc) => {
                doc.dispatchEvent(new CustomEvent('toggle-settings-popup', { detail: { show: true } }));
            });
            cy.get(
                '[data-testid="ta-grading-setting-option-general-setting-navigate-assigned-students-only"]',
            ).should('be.visible');
        });
    });

    describe('settings interaction', () => {
        it('changes a General section setting and updates localStorage', () => {
            cy.mount(GradingSettings, { props: { fullAccess: false } });
            cy.document().then((doc) => {
                doc.dispatchEvent(new CustomEvent('toggle-settings-popup', { detail: { show: true } }));
            });
            // Spy on localStorage before changing
            cy.window().then((win) => {
                const setItem = cy.stub(win.localStorage, 'setItem').as('localStorageSet');
            });
            cy.get('[data-testid="ta-grading-setting-option-general-setting-arrow-function"]')
                .select('ungraded');
            cy.get('@localStorageSet').should('have.been.calledWith', 'general-setting-arrow-function', 'ungraded');
        });

        it('changes a Notebook section setting and updates localStorage', () => {
            cy.mount(GradingSettings, { props: { fullAccess: false } });
            cy.document().then((doc) => {
                doc.dispatchEvent(new CustomEvent('toggle-settings-popup', { detail: { show: true } }));
            });
            cy.window().then((win) => {
                const setItem = cy.stub(win.localStorage, 'setItem').as('localStorageSet');
            });
            cy.get('[data-testid="ta-grading-setting-option-notebook-setting-file-submission-expand"]')
                .select('true');
            cy.get('@localStorageSet').should('have.been.calledWith', 'notebook-setting-file-submission-expand', 'true');
        });

        it('dispatches settings-changed event on window when a setting changes', () => {
            cy.mount(GradingSettings, { props: { fullAccess: false } });
            cy.document().then((doc) => {
                doc.dispatchEvent(new CustomEvent('toggle-settings-popup', { detail: { show: true } }));
            });
            const onSettingsChanged = cy.stub().as('settingsChanged');
            cy.window().then((win) => {
                win.addEventListener('settings-changed', onSettingsChanged);
            });
            cy.get('[data-testid="ta-grading-setting-option-general-setting-arrow-function"]')
                .select('ungraded');
            cy.get('@settingsChanged').should('have.been.calledOnce');
            cy.get('@settingsChanged').should('have.been.calledWithMatch', { detail: { storageCode: 'general-setting-arrow-function', value: 'ungraded' } });
        });
    });

    describe('hotkeys', () => {
        it('renders the hotkey entries', () => {
            cy.mount(GradingSettings, { props: { fullAccess: false } });
            cy.document().then((doc) => {
                doc.dispatchEvent(new CustomEvent('toggle-settings-popup', { detail: { show: true } }));
            });
            cy.get('[data-testid="hotkeys-list"]').should('be.visible');
            cy.get('[data-testid="hotkeys-list"]').find('tr').should('have.length', 4);
        });

        it('enters remap mode when a remap button is clicked', () => {
            cy.mount(GradingSettings, { props: { fullAccess: false } });
            cy.document().then((doc) => {
                doc.dispatchEvent(new CustomEvent('toggle-settings-popup', { detail: { show: true } }));
            });
            cy.get('[data-testid="remap-0"]').click();
            cy.get('[data-testid="remap-0"]').should('contain', 'Enter Key...');
            cy.get('[data-testid="remap-0"]').should('have.class', 'btn-success');
        });

        it('captures a keypress during remap and updates the hotkey', () => {
            cy.mount(GradingSettings, { props: { fullAccess: false } });
            cy.document().then((doc) => {
                doc.dispatchEvent(new CustomEvent('toggle-settings-popup', { detail: { show: true } }));
            });
            cy.get('[data-testid="remap-0"]').click();
            cy.get('[data-testid="remap-0"]').should('contain', 'Enter Key...');
            cy.document().trigger('keyup', { key: 'b', code: 'KeyB' });
            cy.get('[data-testid="remap-0"]').should('contain', 'KeyB');
            cy.get('[data-testid="remap-0"]').should('have.class', 'btn-default');
        });

        it('does not enter remap when already remapping (startRemap guard)', () => {
            cy.mount(GradingSettings, { props: { fullAccess: false } });
            cy.document().then((doc) => {
                doc.dispatchEvent(new CustomEvent('toggle-settings-popup', { detail: { show: true } }));
            });
            cy.get('[data-testid="remap-0"]').click();
            cy.get('[data-testid="remap-0"]').should('contain', 'Enter Key...');
            cy.get('[data-testid="remap-1"]').click();
            cy.get('[data-testid="remap-1"]').should('not.contain', 'Enter Key...');
        });

        it('rejects a key already bound to another hotkey (isKeyAlreadyBound guard)', () => {
            cy.mount(GradingSettings, { props: { fullAccess: false } });
            cy.document().then((doc) => {
                doc.dispatchEvent(new CustomEvent('toggle-settings-popup', { detail: { show: true } }));
            });
            cy.get('[data-testid="remap-1"]').click();
            cy.document().trigger('keyup', { key: 'a', code: 'KeyA' });
            cy.get('[data-testid="remap-1"]').should('contain', 'Enter Key...');
        });

        it('unsets a hotkey when the unset button is clicked', () => {
            cy.mount(GradingSettings, { props: { fullAccess: false } });
            cy.document().then((doc) => {
                doc.dispatchEvent(new CustomEvent('toggle-settings-popup', { detail: { show: true } }));
            });
            cy.get('[data-testid="remap-unset-0"]').click();
            cy.get('[data-testid="remap-0"]').should('contain', 'Unassigned');
        });

        it('removes all hotkeys when Remove All is clicked', () => {
            cy.mount(GradingSettings, { props: { fullAccess: false } });
            cy.document().then((doc) => {
                doc.dispatchEvent(new CustomEvent('toggle-settings-popup', { detail: { show: true } }));
            });
            cy.get('[data-testid="remove-all-hotkeys"]').click();
            cy.get('[data-testid="remap-0"]').should('contain', 'Unassigned');
            cy.get('[data-testid="remap-1"]').should('contain', 'Unassigned');
            cy.get('[data-testid="remap-2"]').should('contain', 'Unassigned');
        });

        it('restores all hotkeys when Restore Default is clicked', () => {
            cy.mount(GradingSettings, { props: { fullAccess: false } });
            cy.document().then((doc) => {
                doc.dispatchEvent(new CustomEvent('toggle-settings-popup', { detail: { show: true } }));
            });
            cy.get('[data-testid="remove-all-hotkeys"]').click();
            cy.get('[data-testid="remap-0"]').should('contain', 'Unassigned');
            cy.get('[data-testid="restore-all-hotkeys"]').click();
            cy.get('[data-testid="remap-0"]').should('contain', 'KeyA');
            cy.get('[data-testid="remap-1"]').should('contain', 'KeyS');
            cy.get('[data-testid="remap-2"]').should('contain', 'KeyD');
        });
    });

    describe('edge cases', () => {
        it('ignores Escape when the popup is hidden', () => {
            cy.mount(GradingSettings, { props: { fullAccess: false } });
            cy.document().trigger('keydown', { key: 'Escape' });
            cy.get('[data-testid="settings-popup"]').should('not.exist');
            cy.get('[data-testid="close-button"]').should('not.exist');
        });

        it('closes popup even during active remap when Escape is pressed', () => {
            cy.mount(GradingSettings, { props: { fullAccess: false } });
            cy.document().then((doc) => {
                doc.dispatchEvent(new CustomEvent('toggle-settings-popup', { detail: { show: true } }));
            });
            cy.get('[data-testid="remap-0"]').click();
            cy.get('[data-testid="remap-0"]').should('contain', 'Enter Key...');
            cy.document().trigger('keydown', { key: 'Escape' });
            cy.get('[data-testid="settings-popup"]').should('not.exist');
        });

        it('sets window.__settingsPopupVisible when shown and clears when closed', () => {
            cy.mount(GradingSettings, { props: { fullAccess: false } });
            cy.window().should('not.have.property', '__settingsPopupVisible');
            cy.document().then((doc) => {
                doc.dispatchEvent(new CustomEvent('toggle-settings-popup', { detail: { show: true } }));
            });
            cy.window().should('have.prop', '__settingsPopupVisible', true);
            cy.get('[data-testid="close-button"]').click();
            cy.window().should('have.prop', '__settingsPopupVisible', false);
        });
    });
});
