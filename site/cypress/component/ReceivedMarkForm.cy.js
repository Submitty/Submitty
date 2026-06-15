import ReceivedMarkForm from '../../vue/src/components/ta_grading/ReceivedMarkForm.vue';

function makeStats(overrides = {}) {
    return {
        section_submitter_count: '3',
        total_submitter_count: '10',
        section_graded_component_count: '2',
        total_graded_component_count: '8',
        section_total_component_count: '5',
        total_total_component_count: '20',
        submitter_ids: ['student1', 'student2', 'student3'],
        submitter_anon_ids: {},
        ...overrides,
    };
}

function dispatchShowMarkStats(detail) {
    cy.window().then((win) => {
        win.dispatchEvent(new CustomEvent('show-mark-stats', { detail }));
    });
}

describe('ReceivedMarkForm', () => {
    describe('visibility', () => {
        it('is hidden by default', () => {
            cy.mount(ReceivedMarkForm);
            cy.get('[data-testid="mark-stats-popup"]').should('not.exist');
        });

        it('becomes visible after show-mark-stats event', () => {
            cy.mount(ReceivedMarkForm);
            dispatchShowMarkStats({
                componentTitle: 'Q1',
                markTitle: 'Full Credit',
                stats: makeStats(),
            });
            cy.get('[data-testid="mark-stats-popup"]').should('be.visible');
        });
    });

    describe('rendering', () => {
        beforeEach(() => {
            cy.mount(ReceivedMarkForm);
            dispatchShowMarkStats({
                componentTitle: 'Question 1',
                markTitle: 'Partial Credit',
                stats: makeStats(),
            });
        });

        it('shows the popup window', () => {
            cy.get('[data-testid="popup-window"]').should('be.visible');
        });

        it('shows component title and mark title', () => {
            cy.get('[data-testid="question-title"]').should('have.text', 'Question 1');
            cy.get('[data-testid="mark-title"]').should('have.text', 'Partial Credit');
        });

        it('shows mark statistics counts', () => {
            cy.get('[data-testid="section-submitter-count"]').should('have.text', '3');
            cy.get('[data-testid="total-submitter-count"]').should('have.text', '10');
            cy.get('[data-testid="section-graded-component-count"]').should('have.text', '2');
            cy.get('[data-testid="total-graded-component-count"]').should('have.text', '8');
            cy.get('[data-testid="section-total-component-count"]').should('have.text', '5');
            cy.get('[data-testid="total-total-component-count"]').should('have.text', '20');
        });
    });

    describe('stats default values', () => {
        it('shows zeros when stats fields are null', () => {
            cy.mount(ReceivedMarkForm);
            dispatchShowMarkStats({
                componentTitle: 'Q1',
                markTitle: 'Full Credit',
                stats: {
                    section_submitter_count: null,
                    total_submitter_count: null,
                    section_graded_component_count: null,
                    total_graded_component_count: null,
                    section_total_component_count: null,
                    total_total_component_count: null,
                    submitter_ids: [],
                    submitter_anon_ids: {},
                },
            });
            cy.get('[data-testid="section-submitter-count"]').should('have.text', '0');
            cy.get('[data-testid="total-submitter-count"]').should('have.text', '0');
            cy.get('[data-testid="section-graded-component-count"]').should('have.text', '0');
            cy.get('[data-testid="total-graded-component-count"]').should('have.text', '0');
            cy.get('[data-testid="section-total-component-count"]').should('have.text', '0');
            cy.get('[data-testid="total-total-component-count"]').should('have.text', '0');
        });

        it('shows zeros when stats fields are undefined (except submitter_ids)', () => {
            cy.mount(ReceivedMarkForm);
            dispatchShowMarkStats({
                componentTitle: 'Q1',
                markTitle: 'Full Credit',
                stats: {
                    submitter_ids: [],
                },
            });
            cy.get('[data-testid="section-submitter-count"]').should('have.text', '0');
            cy.get('[data-testid="total-submitter-count"]').should('have.text', '0');
        });
    });

    describe('student list', () => {
        beforeEach(() => {
            cy.mount(ReceivedMarkForm);
        });

        it('renders student links when there are students', () => {
            dispatchShowMarkStats({
                componentTitle: 'Q1',
                markTitle: 'Full Credit',
                stats: makeStats({
                    submitter_ids: ['alice', 'bob', 'carol'],
                }),
            });
            cy.get('[data-testid="student-names"]').within(() => {
                cy.contains('a', 'alice').should('be.visible');
                cy.contains('a', 'bob').should('be.visible');
                cy.contains('a', 'carol').should('be.visible');
                cy.get('br').should('not.exist');
            });
        });

        it('separates student names with commas', () => {
            dispatchShowMarkStats({
                componentTitle: 'Q1',
                markTitle: 'Full Credit',
                stats: makeStats({
                    submitter_ids: ['alice', 'bob'],
                }),
            });
            cy.get('[data-testid="student-names"]').should('contain.text', 'alice, bob');
        });

        it('shows only <br> when there are no students', () => {
            dispatchShowMarkStats({
                componentTitle: 'Q1',
                markTitle: 'Full Credit',
                stats: makeStats({
                    submitter_ids: [],
                }),
            });
            cy.get('[data-testid="student-names"]').within(() => {
                cy.get('a').should('not.exist');
                cy.get('br').should('exist');
            });
        });
    });

    describe('anonymous IDs', () => {
        it('uses anon ID in student link when present', () => {
            cy.mount(ReceivedMarkForm);
            dispatchShowMarkStats({
                componentTitle: 'Q1',
                markTitle: 'Full Credit',
                stats: makeStats({
                    submitter_ids: ['student1'],
                    submitter_anon_ids: { student1: 'anon_student1' },
                }),
            });
            cy.get('[data-testid="student-names"]')
                .find('a')
                .should('have.attr', 'href')
                .and('include', 'who_id=anon_student1');
        });

        it('falls back to raw ID when anon ID is missing', () => {
            cy.mount(ReceivedMarkForm);
            dispatchShowMarkStats({
                componentTitle: 'Q1',
                markTitle: 'Full Credit',
                stats: makeStats({
                    submitter_ids: ['student1'],
                    submitter_anon_ids: {},
                }),
            });
            cy.get('[data-testid="student-names"]')
                .find('a')
                .should('have.attr', 'href')
                .and('include', 'who_id=student1');
        });
    });

    describe('close interactions', () => {
        beforeEach(() => {
            cy.mount(ReceivedMarkForm);
            dispatchShowMarkStats({
                componentTitle: 'Q1',
                markTitle: 'Full Credit',
                stats: makeStats(),
            });
        });

        it('closes when overlay behind popup is clicked', () => {
            cy.get('[data-testid="popup-window"]').should('be.visible');
            cy.get('.popup-box').click({ force: true });
            cy.get('[data-testid="mark-stats-popup"]').should('not.exist');
        });

        it('does not close when clicking inside the popup window', () => {
            cy.get('[data-testid="popup-window"]').click({ force: true });
            cy.get('[data-testid="mark-stats-popup"]').should('be.visible');
        });

        it('closes when close button is clicked', () => {
            cy.get('[data-testid="close-button"]').click({ force: true });
            cy.get('[data-testid="mark-stats-popup"]').should('not.exist');
        });

        it('closes when popup close button is clicked', () => {
            cy.get('[data-testid="popup-close-button"]').click({ force: true });
            cy.get('[data-testid="mark-stats-popup"]').should('not.exist');
        });

        it('closes on Escape key', () => {
            cy.get('[data-testid="popup-window"]').should('be.visible');
            cy.document().trigger('keydown', { key: 'Escape' });
            cy.get('[data-testid="mark-stats-popup"]').should('not.exist');
        });

        it('does not close on non-Escape key', () => {
            cy.document().trigger('keydown', { key: 'Enter' });
            cy.get('[data-testid="mark-stats-popup"]').should('be.visible');
        });
    });

    describe('reopening', () => {
        it('can be reopened after being closed, with new data', () => {
            cy.mount(ReceivedMarkForm);
            dispatchShowMarkStats({
                componentTitle: 'Q1',
                markTitle: 'Full Credit',
                stats: makeStats(),
            });
            cy.get('[data-testid="close-button"]').click({ force: true });
            cy.get('[data-testid="mark-stats-popup"]').should('not.exist');

            dispatchShowMarkStats({
                componentTitle: 'Q2',
                markTitle: 'No Credit',
                stats: makeStats({ section_submitter_count: '0' }),
            });
            cy.get('[data-testid="mark-stats-popup"]').should('be.visible');
            cy.get('[data-testid="question-title"]').should('have.text', 'Q2');
            cy.get('[data-testid="mark-title"]').should('have.text', 'No Credit');
        });
    });

    describe('event listener lifecycle', () => {
        it('cleans up event listeners on unmount', () => {
            cy.mount(ReceivedMarkForm).then(({ wrapper }) => {
                wrapper.unmount();
                dispatchShowMarkStats({
                    componentTitle: 'Q1',
                    markTitle: 'Full Credit',
                    stats: makeStats(),
                });
            });
            cy.get('[data-testid="mark-stats-popup"]').should('not.exist');
        });
    });
});
