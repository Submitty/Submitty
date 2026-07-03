import GradeableMessage from '../../vue/src/components/GradeableMessage.vue';
import { mountWithEmitSpy } from '../support/component_test_utils';

const SEMESTER = 's25';
const COURSE = 'csci101';
const GRADEABLE_ID = 'hw1';
const STORAGE_KEY = `${SEMESTER}-${COURSE}-${GRADEABLE_ID}-message`;

const baseProps = {
    semester: SEMESTER,
    course: COURSE,
    gradeableId: GRADEABLE_ID,
    userGroup: 2,
    blindStatus: 0,
    courseUrl: window.location.href,
};

describe('GradeableMessage', () => {
    beforeEach(() => {
        localStorage.clear();
    });

    describe('initial visibility', () => {
        it('shows popup for non-instructor without a prior agreement', () => {
            cy.mount(GradeableMessage, { props: { ...baseProps, userGroup: 2 } });
            cy.get('[data-testid="popup-window"]').should('be.visible');
        });

        it('does not show popup for instructor', () => {
            cy.mount(GradeableMessage, { props: { ...baseProps, userGroup: 1 } });
            cy.get('[data-testid="popup-window"]').should('not.exist');
        });

        it('does not show popup when localStorage already has agreement', () => {
            localStorage.setItem(STORAGE_KEY, 'agreed');
            cy.mount(GradeableMessage, { props: { ...baseProps, userGroup: 2 } });
            cy.get('[data-testid="popup-window"]').should('not.exist');
        });
    });

    describe('content', () => {
        it('renders grader message for TA/mentor', () => {
            cy.mount(GradeableMessage, { props: { ...baseProps, userGroup: 2 } });
            cy.contains('You will be reviewing materials submitted by students').should('be.visible');
        });

        it('renders peer message for userGroup 4', () => {
            cy.mount(GradeableMessage, { props: { ...baseProps, userGroup: 4 } });
            cy.contains('You will be reviewing materials submitted by your classmates').should('be.visible');
        });

        it('shows no grader or peer content for unknown userGroup', () => {
            cy.mount(GradeableMessage, { props: { ...baseProps, userGroup: 0 } });
            cy.get('[data-testid="popup-window"]').should('be.visible');
            cy.contains('You will be reviewing materials').should('not.exist');
        });
    });

    describe('blind status', () => {
        it('shows blinded notice for grader when blindStatus is 3', () => {
            cy.mount(GradeableMessage, { props: { ...baseProps, userGroup: 2, blindStatus: 3 } });
            cy.contains('blinded').should('be.visible');
        });

        it('does not show blinded notice for grader when not blinded', () => {
            cy.mount(GradeableMessage, { props: { ...baseProps, userGroup: 2, blindStatus: 2 } });
            cy.contains('Your instructor has configured the grading').should('not.exist');
        });

        it('shows correct blind level for peers', () => {
            cy.mount(GradeableMessage, { props: { ...baseProps, userGroup: 4, blindStatus: 2 } });
            cy.contains('single-blind').should('be.visible');
            cy.mount(GradeableMessage, { props: { ...baseProps, userGroup: 4, blindStatus: 3 } });
            cy.contains('double-blind').should('be.visible');
            cy.mount(GradeableMessage, { props: { ...baseProps, userGroup: 4, blindStatus: 0 } });
            cy.contains('unblinded').should('be.visible');
        });
    });

    describe('buttons', () => {
        it('shows Cancel and Agree when canAgree is true', () => {
            cy.mount(GradeableMessage, { props: baseProps });
            cy.contains('button', 'Cancel').should('be.visible');
            cy.get('[data-testid="agree-popup-btn"]').should('be.visible');
        });

        it('shows only Close when canAgree is false (review mode)', () => {
            localStorage.setItem(STORAGE_KEY, 'agreed');
            cy.mount(GradeableMessage, { props: baseProps });
            cy.window().then((win) => win.dispatchEvent(new CustomEvent('show-gradeable-message-review')));
            cy.get('[data-testid="popup-window"]').should('be.visible');
            cy.get('[data-testid="agree-popup-btn"]').should('not.exist');
            cy.get('[data-testid="close-hidden-button"]').should('be.visible');
        });
    });

    describe('agree action', () => {
        it('stores agreement in localStorage and hides popup', () => {
            cy.mount(GradeableMessage, { props: baseProps });
            cy.get('[data-testid="agree-popup-btn"]').click();
            cy.get('[data-testid="popup-window"]').should('not.exist');
            cy.window().its('localStorage').invoke('getItem', STORAGE_KEY).should('equal', 'agreed');
        });
    });

    describe('close actions', () => {
        it('closes popup when X close button is clicked', () => {
            cy.mount(GradeableMessage, { props: baseProps });
            cy.get('[data-testid="close-button"]').click();
            cy.get('[data-testid="popup-window"]').should('not.exist');
        });

        it('closes popup when overlay is clicked', () => {
            cy.mount(GradeableMessage, { props: baseProps });
            cy.get('.popup-box').click({ force: true });
            cy.get('[data-testid="popup-window"]').should('not.exist');
        });

        it('does not close when clicking inside the popup', () => {
            cy.mount(GradeableMessage, { props: baseProps });
            cy.get('[data-testid="popup-window"]').click();
            cy.get('[data-testid="popup-window"]').should('be.visible');
        });
    });

    describe('cancel action', () => {
        it('emits cancel with courseUrl and hides popup', () => {
            mountWithEmitSpy(GradeableMessage, 'cancel', baseProps);
            cy.contains('button', 'Cancel').click();
            cy.get('[data-testid="popup-window"]').should('not.exist');
            cy.get('@eventHandler').should('have.been.calledOnce');
        });
    });

    describe('Escape key', () => {
        it('closes popup when Escape is pressed while visible', () => {
            cy.mount(GradeableMessage, { props: baseProps });
            cy.window().then((win) => win.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' })));
            cy.get('[data-testid="popup-window"]').should('not.exist');
        });
    });

    describe('show-gradeable-message-review custom event', () => {
        it('opens popup in review mode from hidden state', () => {
            localStorage.setItem(STORAGE_KEY, 'agreed');
            cy.mount(GradeableMessage, { props: baseProps });
            cy.window().then((win) => win.dispatchEvent(new CustomEvent('show-gradeable-message-review')));
            cy.get('[data-testid="popup-window"]').should('be.visible');
            cy.get('[data-testid="agree-popup-btn"]').should('not.exist');
        });
    });

    describe('edge cases', () => {
        it('works with special characters in semester and course', () => {
            const specialKey = 'spéçïäl-çøürße-gr@de-message';
            cy.mount(GradeableMessage, {
                props: { ...baseProps, semester: 'spéçïäl', course: 'çøürße', gradeableId: 'gr@de' },
            });
            cy.get('[data-testid="agree-popup-btn"]').click();
            cy.window().its('localStorage').invoke('getItem', specialKey).should('equal', 'agreed');
        });
    });
});
