import EditPeerComponentsForm from '../../vue/src/components/ta_grading/EditPeerComponentsForm.vue';
import { mountWithEmitSpy } from '../support/component_test_utils.js';

const baseProps = {
    peers: ['student_aaa', 'student_bbb'],
    submitterId: 'submitter_xyz',
    gradeableId: 'gradeable_001',
    csrfToken: 'csrf_abc123',
    visible: true,
    components: [
        { id: 'comp_1', title: 'Code Quality', max: 10, marks: [101, 102] },
        { id: 'comp_2', title: 'Documentation', max: 5, marks: [201] },
    ],
    componentScores: {
        comp_1: { student_aaa: 7 },
        comp_2: { student_aaa: 3 },
    },
    peerDetails: {
        graders: {},
        marks_assigned: {
            comp_1: { student_aaa: [101] },
            comp_2: { student_aaa: [201] },
        },
    },
    marks: {
        101: { title: 'Follows naming conventions', points: '+3' },
        102: { title: 'No dead code', points: '+2' },
        201: { title: 'README is complete', points: '+5' },
    },
};

function mountBase(props = {}) {
    return cy.mount(EditPeerComponentsForm, { props: { ...baseProps, ...props } });
}

function mountScore(earned, max) {
    return cy.mount(EditPeerComponentsForm, {
        props: {
            ...baseProps,
            visible: true,
            peers: ['student_aaa'],
            components: [{ id: 'comp_1', title: 'Test Component', max, marks: [] }],
            componentScores: { comp_1: { student_aaa: earned } },
            peerDetails: { graders: {}, marks_assigned: {} },
        },
    });
}

describe('EditPeerComponentsForm', () => {
    describe('rendering', () => {
        it('renders each peer as a select option', () => {
            mountBase();
            cy.get('[data-testid="edit-peer-select"] option').should('have.length', 2);
            cy.get('[data-testid="edit-peer-select"]').contains('option', 'student_aaa');
            cy.get('[data-testid="edit-peer-select"]').contains('option', 'student_bbb');
        });

        it('renders component titles and their mark rows', () => {
            mountBase();
            cy.get('[data-testid="component-title"]').first().should('contain.text', 'Code Quality');
            cy.get('[data-testid="mark-row-101"]').should('exist');
            cy.get('[data-testid="mark-row-102"]').should('exist');
            cy.get('[data-testid="mark-row-201"]').should('exist');
        });

        it('defaults to the first peer and shows only their block', () => {
            mountBase();
            cy.get('[data-testid="edit-peer-select"]').should('have.value', 'student_aaa');
            cy.get('.edit-peer-components-block').first().should('be.visible');
            cy.get('.edit-peer-components-block').eq(1).should('not.be.visible');
        });

        it('switches visible block when selecting a different peer', () => {
            mountBase();
            cy.get('[data-testid="edit-peer-select"]').select('student_bbb');
            cy.get('.edit-peer-components-block').first().should('not.be.visible');
            cy.get('.edit-peer-components-block').eq(1).should('be.visible');
        });
    });

    describe('score badge', () => {
        it('shows green at max, yellow above half, red at half or below', () => {
            mountScore(10, 10);
            cy.get('[data-testid="score-pill-badge"]').should('have.class', 'green-background');

            mountScore(6, 10);
            cy.get('[data-testid="score-pill-badge"]').should('have.class', 'yellow-background');

            mountScore(5, 10);
            cy.get('[data-testid="score-pill-badge"]').should('have.class', 'red-background');

            mountScore(0, 10);
            cy.get('[data-testid="score-pill-badge"]').should('have.class', 'red-background');
        });

        it('hides the badge when max is 0 and score is non-negative', () => {
            mountScore(0, 0);
            cy.get('[data-testid="no-badge"]').should('exist');
            cy.get('[data-testid="score-pill-badge"]').should('not.exist');
        });

        it('omits the badge block entirely when a peer has no score', () => {
            mountBase({ componentScores: {}, peerDetails: { graders: {}, marks_assigned: {} } });
            cy.get('[data-testid="box-badge"]').should('not.exist');
        });

        it('shows "earned / max" text and uses unicode minus for negative scores', () => {
            mountScore(7, 10);
            cy.get('[data-testid="score-pill-badge"]').should('have.text', '7 / 10');

            mountScore(-3, 10);
            cy.get('[data-testid="score-pill-badge"]')
                .invoke('text')
                .then((text) => {
                    expect(text.trim().charCodeAt(0)).to.equal(0x2212);
                    expect(text.trim()).to.equal('\u22123 / 10');
                });
        });
    });

    describe('mark indicators', () => {
        it('shows checked icon for assigned marks and unchecked for unassigned', () => {
            mountBase();

            cy.get('.edit-peer-components-block').first().within(() => {
                cy.get('[data-testid="mark-row-101"]').within(() => {
                    cy.get('[data-testid="mark-checked"]').should('exist');
                    cy.get('[data-testid="mark-unchecked"]').should('not.exist');
                });

                cy.get('[data-testid="mark-row-102"]').within(() => {
                    cy.get('[data-testid="mark-unchecked"]').should('exist');
                    cy.get('[data-testid="mark-checked"]').should('not.exist');
                });
            });
        });

        it('shows all unchecked when marks_assigned is empty', () => {
            mountBase({ peerDetails: { graders: {}, marks_assigned: {} } });
            cy.get('[data-testid="mark-checked"]').should('not.exist');
            cy.get('[data-testid="mark-unchecked"]').should('have.length.greaterThan', 0);
        });
    });

    describe('clear-marks emit', () => {
        it('emits clear-marks with the correct detail on click', () => {
            mountWithEmitSpy(EditPeerComponentsForm, 'clearMarks', baseProps, 'onClearMarks');

            cy.get('[data-testid="clear-peer-marks"]').first().click();
            cy.get('@onClearMarks').should('have.been.calledOnceWith', {
                submitterId: 'submitter_xyz',
                gradeableId: 'gradeable_001',
                peer: 'student_aaa',
                csrfToken: 'csrf_abc123',
            });
        });

        it('passes the currently selected peer in the emit', () => {
            mountWithEmitSpy(EditPeerComponentsForm, 'clearMarks', baseProps, 'onClearMarks');

            cy.get('[data-testid="edit-peer-select"]').select('student_bbb');
            cy.get('.edit-peer-components-block:visible').within(() => {
                cy.get('[data-testid="clear-peer-marks"]').click();
            });
            cy.get('@onClearMarks').should('have.been.calledWith', {
                submitterId: 'submitter_xyz',
                gradeableId: 'gradeable_001',
                peer: 'student_bbb',
                csrfToken: 'csrf_abc123',
            });
        });
    });

    describe('edge cases', () => {
        it('handles empty peers without crashing', () => {
            mountBase({ peers: [], componentScores: {}, peerDetails: { graders: {}, marks_assigned: {} } });
            cy.get('[data-testid="edit-peer-select"] option').should('have.length', 0);
            cy.get('[data-testid="warning-text"]').should('be.visible');
        });

        it('handles missing mark entry without crashing', () => {
            mountBase({ components: [{ id: 'comp_1', title: 'Test', max: 10, marks: [999] }], marks: {} });
            cy.get('[data-testid="mark-row-999"]').should('exist');
            cy.get('[data-testid="mark-points"]').should('have.text', '');
            cy.get('[data-testid="mark-title"]').should('have.text', '');
        });
    });
});
