import GradingSettings from '../../vue/src/components/ta_grading/GradingSettings.vue';
import { registerKeyHandler, getKeymap, showSettings, hideSettings, isSettingsVisible } from '../../ts/ta-grading-keymap';
import { mountWithEmitSpy } from '../support/component_test_utils.js';

function showPopup() {
    showSettings();
}

describe('GradingSettings', () => {
    beforeEach(() => {
        getKeymap().length = 0;
        hideSettings();

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
            mountWithEmitSpy(GradingSettings, 'close', { fullAccess: false }, 'closeHandler');
            cy.get('[data-testid="settings-popup"]').should('not.exist');
        });

        it('shows when showSettings() is called', () => {
            mountWithEmitSpy(GradingSettings, 'close', { fullAccess: false }, 'closeHandler');
            cy.then(() => showPopup());
            cy.get('[data-testid="settings-popup"]').should('be.visible');
            cy.get('[data-testid="close-button"]').should('be.visible');
        });

        it('closes when the close button is clicked and hideSettings is called', () => {
            mountWithEmitSpy(GradingSettings, 'close', { fullAccess: false }, 'closeHandler');
            cy.then(() => showPopup());
            cy.get('[data-testid="close-button"]').click();
            cy.get('@closeHandler').should('have.been.calledOnce');
            cy.then(() => hideSettings());
            cy.get('[data-testid="settings-popup"]').should('not.exist');
        });

        it('closes when Escape is pressed and hideSettings is called', () => {
            mountWithEmitSpy(GradingSettings, 'close', { fullAccess: false }, 'closeHandler');
            cy.then(() => showPopup());
            cy.get('[data-testid="settings-popup"]').trigger('keydown', { key: 'Escape' });
            cy.get('@closeHandler').should('have.been.calledOnce');
            cy.then(() => hideSettings());
            cy.get('[data-testid="settings-popup"]').should('not.exist');
        });

        it('closes when the overlay is clicked and hideSettings is called', () => {
            mountWithEmitSpy(GradingSettings, 'close', { fullAccess: false }, 'closeHandler');
            cy.then(() => showPopup());
            cy.get('[data-testid="popup-overlay"]').click({ force: true });
            cy.get('@closeHandler').should('have.been.calledOnce');
            cy.then(() => hideSettings());
            cy.get('[data-testid="settings-popup"]').should('not.exist');
        });

        it('hides when hideSettings() is called', () => {
            mountWithEmitSpy(GradingSettings, 'close', { fullAccess: false }, 'closeHandler');
            cy.then(() => showPopup());
            cy.get('[data-testid="settings-popup"]').should('be.visible');
            cy.then(() => hideSettings());
            cy.get('[data-testid="settings-popup"]').should('not.exist');
        });
    });

    describe('settings rendering', () => {
        it('renders all setting groups from settingsData', () => {
            mountWithEmitSpy(GradingSettings, 'close', { fullAccess: false }, 'closeHandler');
            cy.then(() => showPopup());
            cy.contains('h2', 'General').should('be.visible');
            cy.contains('h2', 'Notebook').should('be.visible');
            cy.contains('h2', 'Hotkeys').should('be.visible');
        });

        it('shows setting selects with correct options', () => {
            mountWithEmitSpy(GradingSettings, 'close', { fullAccess: false }, 'closeHandler');
            cy.then(() => showPopup());
            cy.get('[data-testid="ta-grading-setting-option-general-setting-arrow-function"]')
                .should('be.visible')
                .find('option')
                .should('have.length.at.least', 4);
        });

        it('hides conditional settings when fullAccess is false', () => {
            mountWithEmitSpy(GradingSettings, 'close', { fullAccess: false }, 'closeHandler');
            cy.then(() => showPopup());
            cy.get(
                '[data-testid="ta-grading-setting-option-general-setting-navigate-assigned-students-only"]',
            ).should('not.exist');
        });

        it('shows conditional settings when fullAccess is true', () => {
            mountWithEmitSpy(GradingSettings, 'close', { fullAccess: true }, 'closeHandler');
            cy.then(() => showPopup());
            cy.get(
                '[data-testid="ta-grading-setting-option-general-setting-navigate-assigned-students-only"]',
            ).should('be.visible');
        });
    });

    describe('settings interaction', () => {
        it('changes a General section setting and updates localStorage', () => {
            mountWithEmitSpy(GradingSettings, 'close', { fullAccess: false }, 'closeHandler');
            cy.then(() => showPopup());
            cy.window().then((win) => {
                cy.stub(win.localStorage, 'setItem').as('localStorageSet');
            });
            cy.get('[data-testid="ta-grading-setting-option-general-setting-arrow-function"]')
                .select('ungraded');
            cy.get('@localStorageSet').should('have.been.calledWith', 'general-setting-arrow-function', 'ungraded');
        });

        it('changes a Notebook section setting and updates localStorage', () => {
            mountWithEmitSpy(GradingSettings, 'close', { fullAccess: false }, 'closeHandler');
            cy.then(() => showPopup());
            cy.window().then((win) => {
                cy.stub(win.localStorage, 'setItem').as('localStorageSet');
            });
            cy.get('[data-testid="ta-grading-setting-option-notebook-setting-file-submission-expand"]')
                .select('true');
            cy.get('@localStorageSet').should('have.been.calledWith', 'notebook-setting-file-submission-expand', 'true');
        });

        it('dispatches settings-changed event on window when a setting changes', () => {
            mountWithEmitSpy(GradingSettings, 'close', { fullAccess: false }, 'closeHandler');
            cy.then(() => showPopup());
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
            mountWithEmitSpy(GradingSettings, 'close', { fullAccess: false }, 'closeHandler');
            cy.then(() => showPopup());
            cy.get('[data-testid="hotkeys-list"]').should('be.visible');
            cy.get('[data-testid="hotkeys-list"]').find('tr').should('have.length', 4);
        });

        it('enters remap mode when a remap button is clicked', () => {
            mountWithEmitSpy(GradingSettings, 'close', { fullAccess: false }, 'closeHandler');
            cy.then(() => showPopup());
            cy.get('[data-testid="remap-0"]').click();
            cy.get('[data-testid="remap-0"]').should('contain', 'Enter Key...');
            cy.get('[data-testid="remap-0"]').should('have.class', 'btn-success');
        });

        it('captures a keypress during remap and updates the hotkey', () => {
            mountWithEmitSpy(GradingSettings, 'close', { fullAccess: false }, 'closeHandler');
            cy.then(() => showPopup());
            cy.get('[data-testid="remap-0"]').click();
            cy.get('[data-testid="remap-0"]').should('contain', 'Enter Key...');
            cy.get('[data-testid="settings-popup"]').trigger('keyup', { key: 'b', code: 'KeyB' });
            cy.get('[data-testid="remap-0"]').should('contain', 'KeyB');
            cy.get('[data-testid="remap-0"]').should('have.class', 'btn-default');
        });

        it('does not enter remap when already remapping (startRemap guard)', () => {
            mountWithEmitSpy(GradingSettings, 'close', { fullAccess: false }, 'closeHandler');
            cy.then(() => showPopup());
            cy.get('[data-testid="remap-0"]').click();
            cy.get('[data-testid="remap-0"]').should('contain', 'Enter Key...');
            cy.get('[data-testid="remap-1"]').click();
            cy.get('[data-testid="remap-1"]').should('not.contain', 'Enter Key...');
        });

        it('rejects a key already bound to another hotkey (isKeyAlreadyBound guard)', () => {
            mountWithEmitSpy(GradingSettings, 'close', { fullAccess: false }, 'closeHandler');
            cy.then(() => showPopup());
            cy.get('[data-testid="remap-1"]').click();
            cy.get('[data-testid="settings-popup"]').trigger('keyup', { key: 'a', code: 'KeyA' });
            cy.get('[data-testid="remap-1"]').should('contain', 'Enter Key...');
        });

        it('unsets a hotkey when the unset button is clicked', () => {
            mountWithEmitSpy(GradingSettings, 'close', { fullAccess: false }, 'closeHandler');
            cy.then(() => showPopup());
            cy.get('[data-testid="remap-unset-0"]').click();
            cy.get('[data-testid="remap-0"]').should('contain', 'Unassigned');
        });

        it('removes all hotkeys when Remove All is clicked', () => {
            mountWithEmitSpy(GradingSettings, 'close', { fullAccess: false }, 'closeHandler');
            cy.then(() => showPopup());
            cy.get('[data-testid="remove-all-hotkeys"]').click();
            cy.get('[data-testid="remap-0"]').should('contain', 'Unassigned');
            cy.get('[data-testid="remap-1"]').should('contain', 'Unassigned');
            cy.get('[data-testid="remap-2"]').should('contain', 'Unassigned');
        });

        it('restores all hotkeys when Restore Default is clicked', () => {
            mountWithEmitSpy(GradingSettings, 'close', { fullAccess: false }, 'closeHandler');
            cy.then(() => showPopup());
            cy.get('[data-testid="remove-all-hotkeys"]').click();
            cy.get('[data-testid="remap-0"]').should('contain', 'Unassigned');
            cy.get('[data-testid="restore-all-hotkeys"]').click();
            cy.get('[data-testid="remap-0"]').should('contain', 'KeyA');
            cy.get('[data-testid="remap-1"]').should('contain', 'KeyS');
            cy.get('[data-testid="remap-2"]').should('contain', 'KeyD');
        });
    });

    describe('edge cases', () => {
        it('ignores events when the popup is hidden', () => {
            mountWithEmitSpy(GradingSettings, 'close', { fullAccess: false }, 'closeHandler');
            cy.get('[data-testid="settings-popup"]').should('not.exist');
            cy.get('[data-testid="close-button"]').should('not.exist');
        });

        it('closes popup even during active remap when Escape is pressed', () => {
            mountWithEmitSpy(GradingSettings, 'close', { fullAccess: false }, 'closeHandler');
            cy.then(() => showPopup());
            cy.get('[data-testid="remap-0"]').click();
            cy.get('[data-testid="remap-0"]').should('contain', 'Enter Key...');
            cy.get('[data-testid="settings-popup"]').trigger('keydown', { key: 'Escape' });
            cy.get('@closeHandler').should('have.been.calledOnce');
            cy.then(() => hideSettings());
            cy.get('[data-testid="settings-popup"]').should('not.exist');
        });

        it('tracks visibility correctly via the bridge', () => {
            mountWithEmitSpy(GradingSettings, 'close', { fullAccess: false }, 'closeHandler');
            cy.then(() => showPopup());
            cy.then(() => {
                expect(isSettingsVisible()).to.equal(true);
                hideSettings();
                expect(isSettingsVisible()).to.equal(false);
            });
        });
    });
});
