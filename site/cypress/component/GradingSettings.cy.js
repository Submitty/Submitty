import GradingSettings from '../../vue/src/components/ta_grading/GradingSettings.vue';
import { registerKeyHandler, getKeymap, isSettingsVisible } from '../../ts/ta-grading-keymap';
import { mountWithEmitSpy } from '../support/component_test_utils.js';

function showPopup() {
    cy.get('#settings-btn').click();
}

describe('GradingSettings', () => {
    beforeEach(() => {
        getKeymap().length = 0;

        cy.window().then((win) => {
            win.Cookies = {
                get: cy.stub().returns(undefined),
                set: cy.stub(),
            };
        });

        registerKeyHandler({ name: 'Previous Student', code: 'KeyA' }, () => {});
        registerKeyHandler({ name: 'Next Student', code: 'KeyS' }, () => {});
        registerKeyHandler({ name: 'Save', code: 'KeyD' }, () => {});
    });

    describe('visibility', () => {
        it('is hidden by default', () => {
            mountWithEmitSpy(GradingSettings, 'setting-change', { fullAccess: false }, 'settingHandler');
            cy.get('#settings-popup').should('not.exist');
        });

        it('shows when the settings button is clicked', () => {
            mountWithEmitSpy(GradingSettings, 'setting-change', { fullAccess: false }, 'settingHandler');
            showPopup();
            cy.get('#settings-popup').should('be.visible');
            cy.get('[data-testid="close-button"]').should('be.visible');
        });

        it('closes when the close button is clicked', () => {
            mountWithEmitSpy(GradingSettings, 'setting-change', { fullAccess: false }, 'settingHandler');
            showPopup();
            cy.get('[data-testid="close-button"]').click();
            cy.get('#settings-popup').should('not.exist');
        });

        it('closes when Escape is pressed', () => {
            mountWithEmitSpy(GradingSettings, 'setting-change', { fullAccess: false }, 'settingHandler');
            showPopup();
            cy.get('body').trigger('keydown', { key: 'Escape' });
            cy.get('#settings-popup').should('not.exist');
        });

        it('closes when the overlay is clicked', () => {
            mountWithEmitSpy(GradingSettings, 'setting-change', { fullAccess: false }, 'settingHandler');
            showPopup();
            cy.get('.popup-box').click({ force: true });
            cy.get('#settings-popup').should('not.exist');
        });

        it('toggles closed when the settings button is clicked again', () => {
            mountWithEmitSpy(GradingSettings, 'setting-change', { fullAccess: false }, 'settingHandler');
            showPopup();
            cy.get('#settings-popup').should('be.visible');
            showPopup();
            cy.get('#settings-popup').should('not.exist');
        });
    });

    describe('settings rendering', () => {
        it('renders all setting groups from settingsData', () => {
            mountWithEmitSpy(GradingSettings, 'setting-change', { fullAccess: false }, 'settingHandler');
            showPopup();
            cy.contains('h2', 'General').should('be.visible');
            cy.contains('h2', 'Notebook').should('be.visible');
            cy.contains('h2', 'Hotkeys').should('be.visible');
        });

        it('shows setting selects with correct options', () => {
            mountWithEmitSpy(GradingSettings, 'setting-change', { fullAccess: false }, 'settingHandler');
            showPopup();
            cy.get('[data-testid="ta-grading-setting-option-general-setting-arrow-function"]')
                .should('be.visible')
                .find('option')
                .should('have.length.at.least', 4);
        });

        it('hides conditional settings when fullAccess is false', () => {
            mountWithEmitSpy(GradingSettings, 'setting-change', { fullAccess: false }, 'settingHandler');
            showPopup();
            cy.get(
                '[data-testid="ta-grading-setting-option-general-setting-navigate-assigned-students-only"]',
            ).should('not.exist');
        });

        it('shows conditional settings when fullAccess is true', () => {
            mountWithEmitSpy(GradingSettings, 'setting-change', { fullAccess: true }, 'settingHandler');
            showPopup();
            cy.get(
                '[data-testid="ta-grading-setting-option-general-setting-navigate-assigned-students-only"]',
            ).should('be.visible');
        });
    });

    describe('settings interaction', () => {
        it('emits setting-change when a General section setting changes', () => {
            mountWithEmitSpy(GradingSettings, 'setting-change', { fullAccess: false }, 'settingHandler');
            showPopup();
            cy.get('[data-testid="ta-grading-setting-option-general-setting-arrow-function"]')
                .select('ungraded');
            cy.get('@settingHandler').should('have.been.calledOnce');
            cy.get('@settingHandler').should('have.been.calledWith', { storageCode: 'general-setting-arrow-function', value: 'ungraded' });
        });

        it('emits setting-change when a Notebook section setting changes', () => {
            mountWithEmitSpy(GradingSettings, 'setting-change', { fullAccess: false }, 'settingHandler');
            showPopup();
            cy.get('[data-testid="ta-grading-setting-option-notebook-setting-file-submission-expand"]')
                .select('true');
            cy.get('@settingHandler').should('have.been.calledOnce');
            cy.get('@settingHandler').should('have.been.calledWith', { storageCode: 'notebook-setting-file-submission-expand', value: 'true' });
        });
    });

    describe('hotkeys', () => {
        it('renders the hotkey entries', () => {
            mountWithEmitSpy(GradingSettings, 'setting-change', { fullAccess: false }, 'settingHandler');
            showPopup();
            cy.get('[data-testid="hotkeys-list"]').should('be.visible');
            cy.get('[data-testid="hotkeys-list"]').find('tr').should('have.length', 4);
        });

        it('enters remap mode when a remap button is clicked', () => {
            mountWithEmitSpy(GradingSettings, 'setting-change', { fullAccess: false }, 'settingHandler');
            showPopup();
            cy.get('[data-testid="remap-0"]').click();
            cy.get('[data-testid="remap-0"]').should('contain', 'Enter Key...');
            cy.get('[data-testid="remap-0"]').should('have.class', 'btn-success');
        });

        it('captures a keypress during remap and emits hotkey-change', () => {
            mountWithEmitSpy(GradingSettings, 'hotkey-change', { fullAccess: false }, 'hotkeyHandler');
            showPopup();
            cy.get('[data-testid="remap-0"]').click();
            cy.get('[data-testid="remap-0"]').should('contain', 'Enter Key...');
            cy.get('#settings-popup').trigger('keyup', { key: 'b', code: 'KeyB' });
            cy.get('@hotkeyHandler').should('have.been.calledOnce');
            cy.get('@hotkeyHandler').should('have.been.calledWith', { index: 0, code: 'KeyB' });
        });

        it('does not enter remap when already remapping (startRemap guard)', () => {
            mountWithEmitSpy(GradingSettings, 'hotkey-change', { fullAccess: false }, 'hotkeyHandler');
            showPopup();
            cy.get('[data-testid="remap-0"]').click();
            cy.get('[data-testid="remap-0"]').should('contain', 'Enter Key...');
            cy.get('[data-testid="remap-1"]').click();
            cy.get('[data-testid="remap-1"]').should('not.contain', 'Enter Key...');
        });

        it('rejects a key already bound to another hotkey (isKeyAlreadyBound guard)', () => {
            mountWithEmitSpy(GradingSettings, 'hotkey-change', { fullAccess: false }, 'hotkeyHandler');
            showPopup();
            cy.get('[data-testid="remap-1"]').click();
            cy.get('#settings-popup').trigger('keyup', { key: 'a', code: 'KeyA' });
            cy.get('@hotkeyHandler').should('not.have.been.called');
        });

        it('emits hotkey-change with Unassigned when the unset button is clicked', () => {
            mountWithEmitSpy(GradingSettings, 'hotkey-change', { fullAccess: false }, 'hotkeyHandler');
            showPopup();
            cy.get('[data-testid="remap-unset-0"]').click();
            cy.get('@hotkeyHandler').should('have.been.calledWith', { index: 0, code: 'Unassigned' });
        });

        it('emits hotkey-change for all keys when Remove All is clicked', () => {
            mountWithEmitSpy(GradingSettings, 'hotkey-change', { fullAccess: false }, 'hotkeyHandler');
            showPopup();
            cy.get('[data-testid="remove-all-hotkeys"]').click();
            cy.get('@hotkeyHandler').should('have.callCount', 3);
            cy.get('@hotkeyHandler').should('have.been.calledWith', { index: 0, code: 'Unassigned' });
            cy.get('@hotkeyHandler').should('have.been.calledWith', { index: 1, code: 'Unassigned' });
            cy.get('@hotkeyHandler').should('have.been.calledWith', { index: 2, code: 'Unassigned' });
        });

        it('emits hotkey-change with original codes when Restore Default is clicked', () => {
            mountWithEmitSpy(GradingSettings, 'hotkey-change', { fullAccess: false }, 'hotkeyHandler');
            showPopup();
            cy.get('[data-testid="remove-all-hotkeys"]').click();
            cy.get('[data-testid="restore-all-hotkeys"]').click();
            cy.get('@hotkeyHandler').should('have.been.calledWith', { index: 0, code: 'KeyA' });
            cy.get('@hotkeyHandler').should('have.been.calledWith', { index: 1, code: 'KeyS' });
            cy.get('@hotkeyHandler').should('have.been.calledWith', { index: 2, code: 'KeyD' });
        });
    });

    describe('edge cases', () => {
        it('ignores events when the popup is hidden', () => {
            mountWithEmitSpy(GradingSettings, 'setting-change', { fullAccess: false }, 'settingHandler');
            cy.get('#settings-popup').should('not.exist');
            cy.get('[data-testid="close-button"]').should('not.exist');
        });

        it('closes popup even during active remap when Escape is pressed', () => {
            mountWithEmitSpy(GradingSettings, 'setting-change', { fullAccess: false }, 'settingHandler');
            showPopup();
            cy.get('[data-testid="remap-0"]').click();
            cy.get('[data-testid="remap-0"]').should('contain', 'Enter Key...');
            cy.get('body').trigger('keydown', { key: 'Escape' });
            cy.get('#settings-popup').should('not.exist');
        });

        it('tracks visibility correctly via notifySettingsVisibility', () => {
            mountWithEmitSpy(GradingSettings, 'setting-change', { fullAccess: false }, 'settingHandler');
            showPopup();
            cy.then(() => {
                expect(isSettingsVisible()).to.equal(true);
            });
            cy.get('[data-testid="close-button"]').click();
            cy.then(() => {
                expect(isSettingsVisible()).to.equal(false);
            });
        });
    });
});
