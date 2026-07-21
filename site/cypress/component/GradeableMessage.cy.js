import GradeableMessage from '../../vue/src/components/GradeableMessage.vue';
import { mountWithEmitSpy } from '../support/component_test_utils';

const STORAGE_KEY = 's25-csci101-hw1-message';

describe('GradeableMessage', () => {
    beforeEach(() => {
        localStorage.clear();
    });

    describe('initial visibility', () => {
        it('shows popup for non-instructor without a prior agreement', () => {
            cy.mount(GradeableMessage, { props: { storageKey: STORAGE_KEY, userGroup: 2, blindStatus: 0 } });
            cy.get('[data-testid="popup-window"]').should('be.visible');
        });

        it('does not show popup for instructor', () => {
            cy.mount(GradeableMessage, { props: { storageKey: STORAGE_KEY, userGroup: 1, blindStatus: 0 } });
            cy.get('[data-testid="popup-window"]').should('not.exist');
        });

        it('does not show popup when localStorage already has agreement', () => {
            localStorage.setItem(STORAGE_KEY, 'agreed');
            cy.mount(GradeableMessage, { props: { storageKey: STORAGE_KEY, userGroup: 2, blindStatus: 0 } });
            cy.get('[data-testid="popup-window"]').should('not.exist');
            cy.get('[data-testid="grader-responsibility"]').should('be.visible');
        });
    });

    describe('content', () => {
        it('renders grader message for TA/mentor', () => {
            cy.mount(GradeableMessage, { props: { storageKey: STORAGE_KEY, userGroup: 2, blindStatus: 0 } });
            cy.contains('You will be reviewing materials submitted by students').should('be.visible');
        });

        it('renders peer message for userGroup 4', () => {
            cy.mount(GradeableMessage, { props: { storageKey: STORAGE_KEY, userGroup: 4, blindStatus: 0 } });
            cy.contains('You will be reviewing materials submitted by your classmates').should('be.visible');
        });

        it('shows no grader or peer content for unknown userGroup', () => {
            cy.mount(GradeableMessage, { props: { storageKey: STORAGE_KEY, userGroup: 0, blindStatus: 0 } });
            cy.get('[data-testid="popup-window"]').should('be.visible');
            cy.contains('You will be reviewing materials').should('not.exist');
        });
    });

    describe('blind status', () => {
        it('shows blinded notice for grader when blindStatus is 3', () => {
            cy.mount(GradeableMessage, { props: { storageKey: STORAGE_KEY, userGroup: 2, blindStatus: 3 } });
            cy.contains('blinded').should('be.visible');
        });

        it('does not show blinded notice for grader when not blinded', () => {
            cy.mount(GradeableMessage, { props: { storageKey: STORAGE_KEY, userGroup: 2, blindStatus: 2 } });
            cy.contains('Your instructor has configured the grading').should('not.exist');
        });

        it('shows correct blind level for peers', () => {
            cy.mount(GradeableMessage, { props: { storageKey: STORAGE_KEY, userGroup: 4, blindStatus: 2 } });
            cy.contains('single-blind').should('be.visible');
            cy.mount(GradeableMessage, { props: { storageKey: STORAGE_KEY, userGroup: 4, blindStatus: 3 } });
            cy.contains('double-blind').should('be.visible');
            cy.mount(GradeableMessage, { props: { storageKey: STORAGE_KEY, userGroup: 4, blindStatus: 0 } });
            cy.contains('unblinded').should('be.visible');
        });
    });

    describe('buttons', () => {
        it('shows Cancel and Agree when canAgree is true', () => {
            cy.mount(GradeableMessage, { props: { storageKey: STORAGE_KEY, userGroup: 2, blindStatus: 0 } });
            cy.contains('button', 'Cancel').should('be.visible');
            cy.get('[data-testid="agree-popup-btn"]').should('be.visible');
        });

        it('shows only Close when canAgree is false', () => {
            localStorage.setItem(STORAGE_KEY, 'agreed');
            cy.mount(GradeableMessage, { props: { storageKey: STORAGE_KEY, userGroup: 2, blindStatus: 0 } });
            cy.get('[data-testid="popup-window"]').should('not.exist');
            cy.get('[data-testid="close-hidden-button"]').should('not.exist');
        });
    });

    describe('grader responsibility button', () => {
        it('shows button when popup is hidden and not instructor', () => {
            localStorage.setItem(STORAGE_KEY, 'agreed');
            cy.mount(GradeableMessage, { props: { storageKey: STORAGE_KEY, userGroup: 2, blindStatus: 0 } });
            cy.get('[data-testid="grader-responsibility"]').should('be.visible');
        });

        it('does not show button for instructor', () => {
            cy.mount(GradeableMessage, { props: { storageKey: STORAGE_KEY, userGroup: 1, blindStatus: 0 } });
            cy.get('[data-testid="grader-responsibility"]').should('not.exist');
        });

        it('opens popup with Close button when clicked after agreement', () => {
            localStorage.setItem(STORAGE_KEY, 'agreed');
            cy.mount(GradeableMessage, { props: { storageKey: STORAGE_KEY, userGroup: 2, blindStatus: 0 } });
            cy.get('[data-testid="grader-responsibility"]').click();
            cy.get('[data-testid="popup-window"]').should('be.visible');
            cy.get('[data-testid="agree-popup-btn"]').should('not.exist');
            cy.get('[data-testid="close-hidden-button"]').should('be.visible');
        });
    });

    describe('agree action', () => {
        it('stores agreement in localStorage and hides popup', () => {
            cy.mount(GradeableMessage, { props: { storageKey: STORAGE_KEY, userGroup: 2, blindStatus: 0 } });
            cy.get('[data-testid="agree-popup-btn"]').click();
            cy.get('[data-testid="popup-window"]').should('not.exist');
            cy.window().its('localStorage').invoke('getItem', STORAGE_KEY).should('equal', 'agreed');
        });
    });

    describe('cancel action', () => {
        it('emits cancel and hides popup', () => {
            mountWithEmitSpy(GradeableMessage, 'cancel', { storageKey: STORAGE_KEY, userGroup: 2, blindStatus: 0 });
            cy.contains('button', 'Cancel').click();
            cy.get('[data-testid="popup-window"]').should('not.exist');
            cy.get('@eventHandler').should('have.been.calledOnce');
        });
    });
});
