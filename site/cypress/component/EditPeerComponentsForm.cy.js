import EditPeerComponentsForm from '../../vue/src/components/ta_grading/EditPeerComponentsForm.vue';

const baseProps = {
    peers: ['student_aaa', 'student_bbb'],
    submitterId: 'submitter_xyz',
    gradeableId: 'gradeable_001',
    csrfToken: 'csrf_abc123',
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
            comp_1: { student_aaa: [101] }, // 101 assigned, 102 NOT assigned
            comp_2: { student_aaa: [201] },
        },
    },
    marks: {
        101: { title: 'Follows naming conventions', points: '+3' },
        102: { title: 'No dead code', points: '+2' },
        201: { title: 'README is complete', points: '+5' },
    },
};

function mountWithScore(earned, max) {
    cy.mount(EditPeerComponentsForm, {
        props: {
            ...baseProps,
            peers: ['student_aaa'],
            components: [{ id: 'comp_1', title: 'Test Component', max, marks: [] }],
            componentScores: { comp_1: { student_aaa: earned } },
            peerDetails: { graders: {}, marks_assigned: {} },
        },
    });
}

describe('EditPeerComponentsForm', () => {
    describe('initial rendering', () => {
        it('renders an option in the select for each peer', () => {
            cy.mount(EditPeerComponentsForm, { props: baseProps });

            cy.get('[data-testid="edit-peer-select"] option').should('have.length', 2);
            cy.get('[data-testid="edit-peer-select"]').contains('option', 'student_aaa');
            cy.get('[data-testid="edit-peer-select"]').contains('option', 'student_bbb');
        });

        it('renders component titles and mark rows for each rubric entry', () => {
            cy.mount(EditPeerComponentsForm, { props: baseProps });

            cy.get('[data-testid="component-title"]').first().should('contain.text', 'Code Quality');
            cy.get('[data-testid="mark-row-101"]').should('exist');
            cy.get('[data-testid="mark-row-102"]').should('exist');
            cy.get('[data-testid="mark-row-201"]').should('exist');
        });

        it('renders the points and title for each mark correctly', () => {
            cy.mount(EditPeerComponentsForm, { props: baseProps });

            // mark-row-101 exists in every peer block (v-show renders all of them).
            // Scope to the first peer block so .within() gets exactly one element.
            cy.get('.edit-peer-components-block').first().within(() => {
                cy.get('[data-testid="mark-row-101"]').within(() => {
                    cy.get('[data-testid="mark-points"]').should('have.text', '+3');
                    cy.get('[data-testid="mark-title"]').should('have.text', 'Follows naming conventions');
                });
            });
        });
    });

    describe('peer selection', () => {
        it('defaults to the first peer and shows only their block', () => {
            cy.mount(EditPeerComponentsForm, { props: baseProps });

            cy.get('[data-testid="edit-peer-select"]').should('have.value', 'student_aaa');
            // v-show: all blocks in DOM, only selected one visible
            cy.get('.edit-peer-components-block').first().should('be.visible');
            cy.get('.edit-peer-components-block').eq(1).should('not.be.visible');
        });

        it('switches visible block when a different peer is selected', () => {
            cy.mount(EditPeerComponentsForm, { props: baseProps });

            cy.get('[data-testid="edit-peer-select"]').select('student_bbb');

            cy.get('.edit-peer-components-block').first().should('not.be.visible');
            cy.get('.edit-peer-components-block').eq(1).should('be.visible');
        });
    });

    describe('score badge colour', () => {
        it('shows green when score equals max', () => {
            mountWithScore(10, 10);
            cy.get('[data-testid="score-pill-badge"]').should('have.class', 'green-background');
        });

        it('shows yellow when score is above half but below max', () => {
            mountWithScore(6, 10); // 6 > 5 (0.5*10), 6 < 10
            cy.get('[data-testid="score-pill-badge"]').should('have.class', 'yellow-background');
        });

        it('shows red when score is zero', () => {
            mountWithScore(0, 10);
            cy.get('[data-testid="score-pill-badge"]').should('have.class', 'red-background');
        });

        it('shows red when score is exactly at the halfway mark — condition is >, not >=', () => {
            mountWithScore(5, 10);
            cy.get('[data-testid="score-pill-badge"]').should('have.class', 'red-background');
        });

        it('shows red for a negative score that is worse than half the max', () => {
            mountWithScore(-8, 10); // -8 < 0.5*10=5 → red
            cy.get('[data-testid="score-pill-badge"]').should('have.class', 'red-background');
        });

        it('renders the no-badge placeholder when max is 0 and score is non-negative', () => {
            // shouldShowBadge(0, 0) → max not > 0 and earned not < 0 → false
            mountWithScore(0, 0);
            cy.get('[data-testid="no-badge"]').should('exist');
            cy.get('[data-testid="score-pill-badge"]').should('not.exist');
        });

        it('does not render the badge block at all when a peer has no score for that component', () => {
            cy.mount(EditPeerComponentsForm, {
                props: {
                    ...baseProps,
                    peers: ['student_aaa'],
                    componentScores: {}, // no scores at all
                    peerDetails: { graders: {}, marks_assigned: {} },
                },
            });

            cy.get('[data-testid="box-badge"]').should('not.exist');
        });
    });

    describe('badge text', () => {
        it('shows "earned / max" for a positive score', () => {
            mountWithScore(7, 10);
            cy.get('[data-testid="score-pill-badge"]').should('have.text', '7 / 10');
        });

        it('uses the unicode minus sign (\u2212) — not a hyphen — for negative scores', () => {
            // This catches the case where someone "simplifies" \u2212 to a regular dash
            mountWithScore(-3, 10);
            cy.get('[data-testid="score-pill-badge"]')
                .invoke('text')
                .then((text) => {
                    expect(text.trim().charCodeAt(0)).to.equal(0x2212);
                    expect(text.trim()).to.equal('\u22123 / 10');
                });
        });
    });

    describe('mark assignment indicators', () => {
        it('shows a checked icon for an assigned mark and unchecked for an unassigned one', () => {
            cy.mount(EditPeerComponentsForm, { props: baseProps });

            // v-show keeps all peer blocks in the DOM, so mark-row-101 exists once per
            // peer block. Scope into the first (visible) block to avoid ambiguity.
            cy.get('.edit-peer-components-block').first().within(() => {
                // 101 is assigned for student_aaa → checked icon only
                cy.get('[data-testid="mark-row-101"]').find('[data-testid="mark-checked"]').should('exist');
                cy.get('[data-testid="mark-row-101"]').find('[data-testid="mark-unchecked"]').should('not.exist');

                // 102 is NOT assigned for student_aaa → unchecked icon only
                cy.get('[data-testid="mark-row-102"]').find('[data-testid="mark-unchecked"]').should('exist');
                cy.get('[data-testid="mark-row-102"]').find('[data-testid="mark-checked"]').should('not.exist');
            });
        });

        it('shows all marks as unchecked when marks_assigned is empty — no crash from missing keys', () => {
            cy.mount(EditPeerComponentsForm, {
                props: {
                    ...baseProps,
                    peerDetails: { graders: {}, marks_assigned: {} },
                },
            });

            cy.get('[data-testid="mark-checked"]').should('not.exist');
            cy.get('[data-testid="mark-unchecked"]').should('have.length.greaterThan', 0);
        });
    });

    describe('clearMarks', () => {
        it('does not emit clear-marks on mount', () => {
            const onClearMarks = cy.stub().as('onClearMarks');
            cy.mount(EditPeerComponentsForm, { props: { ...baseProps, onClearMarks } });
            cy.get('@onClearMarks').should('not.have.been.called');
        });

        it('emits clear-marks with the correct detail when clicked', () => {
            const onClearMarks = cy.stub().as('onClearMarks');
            cy.mount(EditPeerComponentsForm, { props: { ...baseProps, onClearMarks } });

            cy.get('[data-testid="clear-peer-marks"]').first().click();

            cy.get('@onClearMarks').should('have.been.calledOnce');
            cy.get('@onClearMarks').should(
                'have.been.calledWith',
                {
                    submitterId: 'submitter_xyz',
                    gradeableId: 'gradeable_001',
                    peer: 'student_aaa',
                    csrfToken: 'csrf_abc123',
                },
            );
        });

        it('passes the currently selected peer — not always the first one', () => {
            const onClearMarks = cy.stub().as('onClearMarks');
            cy.mount(EditPeerComponentsForm, { props: { ...baseProps, onClearMarks } });

            cy.get('[data-testid="edit-peer-select"]').select('student_bbb');
            cy.get('[data-testid="clear-peer-marks"]').eq(1).click();

            cy.get('@onClearMarks').should(
                'have.been.calledWith',
                {
                    submitterId: 'submitter_xyz',
                    gradeableId: 'gradeable_001',
                    peer: 'student_bbb',
                    csrfToken: 'csrf_abc123',
                },
            );
        });
    });

    describe('defensive cases', () => {
        it('renders without crashing when peers is empty', () => {
            cy.mount(EditPeerComponentsForm, {
                props: { ...baseProps, peers: [], componentScores: {}, peerDetails: { graders: {}, marks_assigned: {} } },
            });

            cy.get('[data-testid="edit-peer-select"] option').should('have.length', 0);
            cy.get('[data-testid="warning-text"]').should('be.visible');
        });

        it('renders empty strings (not crashes) when a mark id has no entry in the marks prop', () => {
            cy.mount(EditPeerComponentsForm, {
                props: {
                    ...baseProps,
                    components: [{ id: 'comp_1', title: 'Test', max: 10, marks: [999] }],
                    marks: {}, // 999 is not in here
                },
            });

            cy.get('[data-testid="mark-row-999"]').should('exist');
            cy.get('[data-testid="mark-points"]').should('have.text', '');
            cy.get('[data-testid="mark-title"]').should('have.text', '');
        });
    });
});
