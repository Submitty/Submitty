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

describe('ReceivedMarkForm', () => {
    describe('visibility', () => {
        it('is hidden when show is false', () => {
            cy.mount(ReceivedMarkForm, {
                props: { show: false, componentTitle: '', markTitle: '', stats: null, studentLinks: [] },
            });
            cy.get('[data-testid="mark-stats-popup"]').should('not.exist');
        });

        it('is visible when show is true', () => {
            cy.mount(ReceivedMarkForm, {
                props: {
                    show: true,
                    componentTitle: 'Q1',
                    markTitle: 'Full Credit',
                    stats: makeStats(),
                    studentLinks: [],
                },
            });
            cy.get('[data-testid="mark-stats-popup"]').should('be.visible');
        });
    });

    describe('rendering', () => {
        beforeEach(() => {
            cy.mount(ReceivedMarkForm, {
                props: {
                    show: true,
                    componentTitle: 'Question 1',
                    markTitle: 'Partial Credit',
                    stats: makeStats(),
                    studentLinks: [],
                },
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
            cy.mount(ReceivedMarkForm, {
                props: {
                    show: true,
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
                    studentLinks: [],
                },
            });
            cy.get('[data-testid="section-submitter-count"]').should('have.text', '0');
            cy.get('[data-testid="total-submitter-count"]').should('have.text', '0');
            cy.get('[data-testid="section-graded-component-count"]').should('have.text', '0');
            cy.get('[data-testid="total-graded-component-count"]').should('have.text', '0');
            cy.get('[data-testid="section-total-component-count"]').should('have.text', '0');
            cy.get('[data-testid="total-total-component-count"]').should('have.text', '0');
        });
    });

    describe('student list', () => {
        it('renders student links when there are students', () => {
            cy.mount(ReceivedMarkForm, {
                props: {
                    show: true,
                    componentTitle: 'Q1',
                    markTitle: 'Full Credit',
                    stats: makeStats(),
                    studentLinks: [
                        { name: 'alice', url: '/grade?who_id=alice' },
                        { name: 'bob', url: '/grade?who_id=bob' },
                        { name: 'carol', url: '/grade?who_id=carol' },
                    ],
                },
            });
            cy.get('[data-testid="student-names"]').within(() => {
                cy.contains('a', 'alice').should('be.visible');
                cy.contains('a', 'bob').should('be.visible');
                cy.contains('a', 'carol').should('be.visible');
            });
        });

        it('shows only <br> when there are no students', () => {
            cy.mount(ReceivedMarkForm, {
                props: {
                    show: true,
                    componentTitle: 'Q1',
                    markTitle: 'Full Credit',
                    stats: makeStats({ submitter_ids: [] }),
                    studentLinks: [],
                },
            });
            cy.get('[data-testid="student-names"]').within(() => {
                cy.get('a').should('not.exist');
                cy.get('br').should('exist');
            });
        });
    });

    describe('close interactions', () => {
        it('closes when overlay behind popup is clicked', () => {
            const onClose = cy.stub().as('onClose');
            cy.mount(ReceivedMarkForm, {
                props: {
                    show: true,
                    componentTitle: 'Q1',
                    markTitle: 'Full Credit',
                    stats: makeStats(),
                    studentLinks: [],
                    onClose,
                },
            });
            cy.get('.popup-box').click({ force: true });
            cy.get('@onClose').should('have.been.calledOnce');
        });

        it('closes when close button is clicked', () => {
            const onClose = cy.stub().as('onClose');
            cy.mount(ReceivedMarkForm, {
                props: {
                    show: true,
                    componentTitle: 'Q1',
                    markTitle: 'Full Credit',
                    stats: makeStats(),
                    studentLinks: [],
                    onClose,
                },
            });
            cy.get('[data-testid="mark-stats-close-button"]').click({ force: true });
            cy.get('@onClose').should('have.been.calledOnce');
        });

        it('closes on Escape key', () => {
            const onClose = cy.stub().as('onClose');
            cy.mount(ReceivedMarkForm, {
                props: {
                    show: true,
                    componentTitle: 'Q1',
                    markTitle: 'Full Credit',
                    stats: makeStats(),
                    studentLinks: [],
                    onClose,
                },
            });
            cy.get('[data-testid="mark-stats-popup"]').focus();
            cy.get('[data-testid="mark-stats-popup"]').type('{esc}');
            cy.get('@onClose').should('have.been.calledOnce');
        });
    });
});
