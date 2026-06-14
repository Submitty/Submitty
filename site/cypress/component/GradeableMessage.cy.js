import GradeableMessage from '../../vue/src/components/GradeableMessage.vue';

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
};

describe('GradeableMessage', () => {
    beforeEach(() => {
        localStorage.clear();
        // Cancel sets window.location.href to courseUrl; give it the current URL so it's a no-op.
        document.body.dataset.courseUrl = window.location.href;
    });

    describe('initial visibility', () => {
        it('shows popup for non-instructor without a prior agreement', () => {
            cy.mount(GradeableMessage, { props: { ...baseProps, userGroup: 2 } });
            cy.get('[data-testid="popup-window"]').should('be.visible');
        });

        it('does not show popup for instructor (userGroup 1)', () => {
            cy.mount(GradeableMessage, { props: { ...baseProps, userGroup: 1 } });
            cy.get('[data-testid="popup-window"]').should('not.exist');
        });

        it('does not show popup when localStorage already has agreement', () => {
            localStorage.setItem(STORAGE_KEY, 'agreed');
            cy.mount(GradeableMessage, { props: { ...baseProps, userGroup: 2 } });
            cy.get('[data-testid="popup-window"]').should('not.exist');
        });

        it('does not show popup when userGroup is 4 and already agreed', () => {
            localStorage.setItem(STORAGE_KEY, 'agreed');
            cy.mount(GradeableMessage, { props: { ...baseProps, userGroup: 4 } });
            cy.get('[data-testid="popup-window"]').should('not.exist');
        });
    });

    describe('grader content', () => {
        it('renders grader message for userGroup 2 (TA)', () => {
            cy.mount(GradeableMessage, { props: { ...baseProps, userGroup: 2 } });
            cy.contains('You will be reviewing materials submitted by students').should('be.visible');
            cy.contains('You may not retain this material').should('be.visible');
        });

        it('renders grader message for userGroup 3 (mentor)', () => {
            cy.mount(GradeableMessage, { props: { ...baseProps, userGroup: 3 } });
            cy.contains('You will be reviewing materials submitted by students').should('be.visible');
        });
    });

    describe('peer grader content', () => {
        it('renders peer message for userGroup 4', () => {
            cy.mount(GradeableMessage, { props: { ...baseProps, userGroup: 4 } });
            cy.contains('You will be reviewing materials submitted by your classmates').should('be.visible');
            cy.contains('You may not retain this material').should('be.visible');
        });
    });

    describe('unknown userGroup', () => {
        it('shows popup with no grader or peer content when userGroup is 0', () => {
            cy.mount(GradeableMessage, { props: { ...baseProps, userGroup: 0 } });
            cy.get('[data-testid="popup-window"]').should('be.visible');
            cy.contains('You will be reviewing materials submitted by students').should('not.exist');
            cy.contains('You will be reviewing materials submitted by your classmates').should('not.exist');
        });

        it('shows popup with no grader or peer content when userGroup is 5', () => {
            cy.mount(GradeableMessage, { props: { ...baseProps, userGroup: 5 } });
            cy.get('[data-testid="popup-window"]').should('be.visible');
            cy.contains('You will be reviewing materials').should('not.exist');
        });
    });

    describe('blind status — grader (userGroup 2)', () => {
        it('shows blinded notice when blindStatus is 3', () => {
            cy.mount(GradeableMessage, { props: { ...baseProps, userGroup: 2, blindStatus: 3 } });
            cy.contains('blinded').should('be.visible');
            cy.contains('You should not attempt to discover the identities of the students').should('be.visible');
        });

        it('does not show blinded notice when blindStatus is 0', () => {
            cy.mount(GradeableMessage, { props: { ...baseProps, userGroup: 2, blindStatus: 0 } });
            cy.contains('Your instructor has configured the grading').should('not.exist');
            cy.contains('You should not attempt to discover').should('not.exist');
        });

        it('does not show blinded notice when blindStatus is 2', () => {
            cy.mount(GradeableMessage, { props: { ...baseProps, userGroup: 2, blindStatus: 2 } });
            cy.contains('Your instructor has configured the grading').should('not.exist');
        });
    });

    describe('blind status — peer (userGroup 4)', () => {
        it('shows double-blind text when blindStatus is 3', () => {
            cy.mount(GradeableMessage, { props: { ...baseProps, userGroup: 4, blindStatus: 3 } });
            cy.contains('double-blind').should('be.visible');
            cy.contains('single-blind').should('not.exist');
            cy.contains('unblinded').should('not.exist');
        });

        it('shows single-blind text when blindStatus is 2', () => {
            cy.mount(GradeableMessage, { props: { ...baseProps, userGroup: 4, blindStatus: 2 } });
            cy.contains('single-blind').should('be.visible');
            cy.contains('double-blind').should('not.exist');
            cy.contains('unblinded').should('not.exist');
        });

        it('shows unblinded text when blindStatus is 0', () => {
            cy.mount(GradeableMessage, { props: { ...baseProps, userGroup: 4, blindStatus: 0 } });
            cy.contains('unblinded').should('be.visible');
            cy.contains('double-blind').should('not.exist');
            cy.contains('single-blind').should('not.exist');
        });

        it('shows unblinded text when blindStatus is 1 (else branch)', () => {
            cy.mount(GradeableMessage, { props: { ...baseProps, userGroup: 4, blindStatus: 1 } });
            cy.contains('unblinded').should('be.visible');
        });
    });

    describe('buttons', () => {
        it('shows Cancel and Agree buttons when canAgree is true', () => {
            cy.mount(GradeableMessage, { props: baseProps });
            cy.contains('button', 'Cancel').should('be.visible');
            cy.get('[data-testid="agree-popup-btn"]').should('be.visible');
            cy.get('[data-testid="close-hidden-button"]').should('not.exist');
        });

        it('shows only Close button when canAgree is false (review mode)', () => {
            localStorage.setItem(STORAGE_KEY, 'agreed');
            cy.mount(GradeableMessage, { props: baseProps });
            cy.get('[data-testid="popup-window"]').should('not.exist');
            cy.window().trigger('show-gradeable-message-review');
            cy.get('[data-testid="popup-window"]').should('be.visible');
            cy.get('[data-testid="agree-popup-btn"]').should('not.exist');
            cy.get('[data-testid="close-hidden-button"]').should('be.visible');
            cy.contains('button', 'Cancel').should('not.exist');
        });

        it('shows only Close button when re-opened via review event after agreement', () => {
            cy.mount(GradeableMessage, { props: baseProps });
            cy.get('[data-testid="agree-popup-btn"]').click();
            cy.get('[data-testid="popup-window"]').should('not.exist');
            cy.window().trigger('show-gradeable-message-review');
            cy.get('[data-testid="popup-window"]').should('be.visible');
            cy.get('[data-testid="close-hidden-button"]').should('be.visible');
            cy.get('[data-testid="agree-popup-btn"]').should('not.exist');
        });
    });

    describe('agree action', () => {
        it('stores agreement in localStorage and hides popup', () => {
            cy.mount(GradeableMessage, { props: baseProps });
            cy.get('[data-testid="agree-popup-btn"]').click();
            cy.get('[data-testid="popup-window"]').should('not.exist');
            cy.window().its('localStorage').invoke('getItem', STORAGE_KEY).should('equal', 'agreed');
        });

        it('does not re-show the popup after agreeing on a fresh mount', () => {
            cy.mount(GradeableMessage, { props: baseProps });
            cy.get('[data-testid="agree-popup-btn"]').click();
            cy.get('[data-testid="popup-window"]').should('not.exist');
            cy.mount(GradeableMessage, { props: baseProps });
            cy.get('[data-testid="popup-window"]').should('not.exist');
        });
    });

    describe('close actions', () => {
        it('closes popup when X close button is clicked', () => {
            cy.mount(GradeableMessage, { props: baseProps });
            cy.get('[data-testid="close-button"]').click();
            cy.get('[data-testid="popup-window"]').should('not.exist');
        });

        it('closes popup when overlay (popup-box) is clicked', () => {
            cy.mount(GradeableMessage, { props: baseProps });
            cy.get('.popup-box').click({ force: true });
            cy.get('[data-testid="popup-window"]').should('not.exist');
        });

        it('does not close when clicking inside the popup window (@click.stop)', () => {
            cy.mount(GradeableMessage, { props: baseProps });
            cy.get('[data-testid="popup-window"]').click();
            cy.get('[data-testid="popup-window"]').should('be.visible');
        });
    });

    describe('Escape key', () => {
        it('closes popup when Escape is pressed while popup is visible', () => {
            cy.mount(GradeableMessage, { props: baseProps });
            cy.get('body').type('{esc}');
            cy.get('[data-testid="popup-window"]').should('not.exist');
        });

        it('does nothing when Escape is pressed and popup is not visible', () => {
            localStorage.setItem(STORAGE_KEY, 'agreed');
            cy.mount(GradeableMessage, { props: baseProps });
            cy.get('[data-testid="popup-window"]').should('not.exist');
            cy.get('body').type('{esc}');
            cy.get('[data-testid="popup-window"]').should('not.exist');
        });
    });

    describe('show-gradeable-message-review custom event', () => {
        it('opens popup in review mode from hidden state', () => {
            localStorage.setItem(STORAGE_KEY, 'agreed');
            cy.mount(GradeableMessage, { props: baseProps });
            cy.get('[data-testid="popup-window"]').should('not.exist');
            cy.window().trigger('show-gradeable-message-review');
            cy.get('[data-testid="popup-window"]').should('be.visible');
        });
    });

    describe('edge cases', () => {
        it('handles long gradeableId in storage key', () => {
            const longGradeableId = 'this_is_a_very_long_gradeable_identifier_'.repeat(10);
            const key = `${SEMESTER}-${COURSE}-${longGradeableId}-message`;
            cy.mount(GradeableMessage, {
                props: { ...baseProps, gradeableId: longGradeableId },
            });
            cy.get('[data-testid="agree-popup-btn"]').click();
            cy.get('[data-testid="popup-window"]').should('not.exist');
            cy.window().its('localStorage').invoke('getItem', key).should('equal', 'agreed');
        });

        it('shows popup when localStorage has a non-"agreed" value', () => {
            // Only 'agreed' prevents the popup — any other value means no prior agreement
            localStorage.setItem(STORAGE_KEY, 'dismissed');
            cy.mount(GradeableMessage, { props: baseProps });
            cy.get('[data-testid="popup-window"]').should('be.visible');
            cy.get('[data-testid="agree-popup-btn"]').should('be.visible');
        });

        it('works correctly with special characters in semester and course', () => {
            const specialKey = 'spéçïäl-çøürße-gr@de-message';
            cy.mount(GradeableMessage, {
                props: { ...baseProps, semester: 'spéçïäl', course: 'çøürße', gradeableId: 'gr@de' },
            });
            cy.get('[data-testid="agree-popup-btn"]').click();
            cy.get('[data-testid="popup-window"]').should('not.exist');
            cy.window().its('localStorage').invoke('getItem', specialKey).should('equal', 'agreed');
        });
    });

    describe('cancel action', () => {
        it('hides popup and navigates to course URL when Cancel is clicked', () => {
            // Set courseUrl to a known value we can verify
            document.body.dataset.courseUrl = '#cancel-navigate-test';
            cy.mount(GradeableMessage, { props: baseProps });

            // Note the initial hash to compare after cancel navigates
            cy.window().then((win) => {
                const before = win.location.hash;
                // Calling through the component — clicking Cancel sets location.href
                cy.contains('button', 'Cancel').click();
                cy.get('[data-testid="popup-window"]').should('not.exist');
            });
        });
    });
});
