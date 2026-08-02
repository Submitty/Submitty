import EditPeerComponentsForm from '../../vue/src/components/ta_grading/EditPeerComponentsForm.vue';
import { mountWithEmitSpy } from '../support/component_test_utils.js';

const defaultProps = {
    peers: ['student_aaa', 'student_bbb'],
    peerNames: {
        student_aaa: 'Alice Aaa (student_aaa)',
        student_bbb: 'Bob Bbb (student_bbb)',
    },
    submitterId: 'submitter_xyz',
    gradeableId: 'gradeable_001',
    csrfToken: 'csrf_abc123',
    activeVersion: 2,
    components: [
        { id: 'comp_1', title: 'Code Quality', max: 10, marks: [101, 102], extra_credit: false },
        { id: 'comp_2', title: 'Documentation', max: 5, marks: [201], extra_credit: false },
    ],
    componentScores: {
        comp_1: { student_aaa: 7 },
        comp_2: { student_aaa: 3 },
    },
    peerDetails: {
        graders: { comp_1: ['student_aaa'], comp_2: ['student_aaa'] },
        marks_assigned: {
            comp_1: { student_aaa: [101] },
            comp_2: { student_aaa: [201] },
        },
        graded_versions: {
            comp_1: { student_aaa: 2 },
            comp_2: { student_aaa: 2 },
        },
        version_conflicts: {},
    },
    marks: {
        101: { title: 'Follows naming conventions', points: '+3' },
        102: { title: 'No dead code', points: '+2' },
        201: { title: 'README is complete', points: '+5' },
    },
};

function mountDefault(props = {}) {
    return cy.mount(EditPeerComponentsForm, { props: { ...defaultProps, ...props } });
}

function openPopup() {
    cy.get('[data-testid="edit-peer-trigger"]').click();
}

function mountScore(earned, max) {
    return cy.mount(EditPeerComponentsForm, {
        props: {
            ...defaultProps,
            peers: ['student_aaa'],
            components: [{ id: 'comp_1', title: 'Test Component', max, marks: [], extra_credit: false }],
            componentScores: { comp_1: { student_aaa: earned } },
            peerDetails: { graders: {}, marks_assigned: {} },
        },
    });
}

function mountExtraCreditScore(earned) {
    return cy.mount(EditPeerComponentsForm, {
        props: {
            ...defaultProps,
            peers: ['student_aaa'],
            components: [{ id: 'comp_1', title: 'Extra Credit Component', max: 10, marks: [], extra_credit: true }],
            componentScores: { comp_1: { student_aaa: earned } },
            peerDetails: { graders: {}, marks_assigned: {} },
        },
    });
}

function mountWithVersionConflict(props = {}) {
    return cy.mount(EditPeerComponentsForm, {
        props: {
            ...defaultProps,
            activeVersion: 2,
            peerDetails: {
                ...defaultProps.peerDetails,
                graded_versions: {
                    comp_1: { student_aaa: 1 },
                    comp_2: { student_aaa: 2 },
                },
                version_conflicts: {
                    comp_1: { student_aaa: true },
                    comp_2: { student_aaa: false },
                },
            },
            ...props,
        },
    });
}

describe('EditPeerComponentsForm', () => {
    describe('rendering', () => {
        it('renders a trigger button that opens the popup', () => {
            mountDefault();
            cy.get('[data-testid="edit-peer-trigger"]').should('contain.text', 'Edit Peer Components');
            cy.get('[data-testid="edit-peer-select"]').should('not.exist');

            openPopup();
            cy.get('[data-testid="edit-peer-select"]').should('be.visible');
        });

        it('renders each peer as a select option using its display name', () => {
            mountDefault();
            openPopup();
            cy.get('[data-testid="edit-peer-select"] option').should('have.length', 2);
            cy.get('[data-testid="edit-peer-select"]').contains('option', 'Alice Aaa (student_aaa)');
            cy.get('[data-testid="edit-peer-select"]').contains('option', 'Bob Bbb (student_bbb)');
        });

        it('falls back to the raw peer id when no display name is provided', () => {
            mountDefault({ peerNames: {} });
            openPopup();
            cy.get('[data-testid="edit-peer-select"]').contains('option', 'student_aaa');
        });

        it('renders component titles and their mark rows', () => {
            mountDefault();
            openPopup();
            cy.get('[data-testid="component-title"]').first().should('contain.text', 'Code Quality');
            cy.get('[data-testid="mark-row-101"]').should('exist');
            cy.get('[data-testid="mark-row-102"]').should('exist');
            cy.get('[data-testid="mark-row-201"]').should('exist');
        });

        it('defaults to the first peer and shows only their block', () => {
            mountDefault();
            openPopup();
            cy.get('[data-testid="edit-peer-select"]').should('have.value', 'student_aaa');
            cy.get('[data-testid="peer-block"]').first().should('be.visible');
            cy.get('[data-testid="peer-block"]').eq(1).should('not.be.visible');
        });

        it('switches visible block when selecting a different peer', () => {
            mountDefault();
            openPopup();
            cy.get('[data-testid="edit-peer-select"]').select('student_bbb');
            cy.get('[data-testid="peer-block"]').first().should('not.be.visible');
            cy.get('[data-testid="peer-block"]').eq(1).should('be.visible');
        });

        it('closes the popup via the close button', () => {
            mountDefault();
            openPopup();
            cy.get('[data-testid="edit-peer-select"]').should('be.visible');

            cy.get('[data-testid="close-button"]').click();
            cy.get('[data-testid="edit-peer-select"]').should('not.exist');
        });
    });

    describe('score badge', () => {
        it('shows green at max, yellow above half, red at half or below', () => {
            mountScore(10, 10);
            openPopup();
            cy.get('[data-testid="score-pill-badge"]').should('have.class', 'green-background');

            mountScore(6, 10);
            openPopup();
            cy.get('[data-testid="score-pill-badge"]').should('have.class', 'yellow-background');

            mountScore(5, 10);
            openPopup();
            cy.get('[data-testid="score-pill-badge"]').should('have.class', 'red-background');

            mountScore(0, 10);
            openPopup();
            cy.get('[data-testid="score-pill-badge"]').should('have.class', 'red-background');
        });

        it('hides the badge when max is 0 and score is non-negative', () => {
            mountScore(0, 0);
            openPopup();
            cy.get('[data-testid="no-badge"]').should('exist');
            cy.get('[data-testid="score-pill-badge"]').should('not.exist');
        });

        it('omits the badge block entirely when a peer has no score', () => {
            mountDefault({ componentScores: {}, peerDetails: { graders: {}, marks_assigned: {} } });
            openPopup();
            cy.get('[data-testid="box-badge"]').should('not.exist');
        });

        it('shows "earned / max" text and uses unicode minus for negative scores', () => {
            mountScore(7, 10);
            openPopup();
            cy.get('[data-testid="score-pill-badge"]').should('have.text', '7 / 10');

            mountScore(-3, 10);
            openPopup();
            cy.get('[data-testid="score-pill-badge"]')
                .invoke('text')
                .then((text) => {
                    expect(text.trim().charCodeAt(0)).to.equal(0x2212);
                    expect(text.trim()).to.equal('\u22123 / 10');
                });

            mountScore(-3, 0);
            openPopup();
            cy.get('[data-testid="score-pill-badge"]').should('have.text', '\u22123');
            cy.get('[data-testid="score-pill-badge"]').should('have.class', 'red-background');
        });

        it('shows extra credit as "+earned", gray at 0 and green above', () => {
            mountExtraCreditScore(0);
            openPopup();
            cy.get('[data-testid="score-pill-badge"]').should('have.text', '+0');
            cy.get('[data-testid="score-pill-badge"]').should('have.class', 'gray-background');

            mountExtraCreditScore(4);
            openPopup();
            cy.get('[data-testid="score-pill-badge"]').should('have.text', '+4');
            cy.get('[data-testid="score-pill-badge"]').should('have.class', 'green-background');
        });
    });

    describe('mark checkboxes', () => {
        it('checks assigned marks, leaves unassigned unchecked, and unchecks missing-data peers', () => {
            mountDefault();
            openPopup();

            cy.get('[data-testid="peer-block"]').first().within(() => {
                cy.get('[data-testid="mark-row-101"]').within(() => {
                    cy.get('[data-testid="mark-checkbox"]').should('be.checked');
                });

                cy.get('[data-testid="mark-row-102"]').within(() => {
                    cy.get('[data-testid="mark-checkbox"]').should('not.be.checked');
                });
            });

            cy.get('[data-testid="edit-peer-select"]').select('student_bbb');
            cy.get('[data-testid="peer-block"]').eq(1).within(() => {
                cy.get('[data-testid="mark-checkbox"]').should('have.length.greaterThan', 0);
                cy.get('[data-testid="mark-checkbox"]').each(($checkbox) => {
                    cy.wrap($checkbox).should('not.be.checked');
                });
            });
        });

        it('emits mark-change with the peer and component on toggle', () => {
            mountWithEmitSpy(EditPeerComponentsForm, 'markChange', defaultProps, 'onMarkChange');
            openPopup();

            cy.get('[data-testid="peer-block"]').first().within(() => {
                cy.get('[data-testid="mark-row-102"]').within(() => {
                    cy.get('[data-testid="mark-checkbox"]').check();
                });
            });
            cy.get('@onMarkChange').should('have.been.calledWith', { peer: 'student_aaa', componentId: 'comp_1' });
        });
    });

    describe('version conflicts', () => {
        it('shows a version warning for a conflicting component', () => {
            mountWithVersionConflict();
            openPopup();
            cy.get('[data-testid="version-warning"]').should('have.length', 1);
            cy.get('[data-testid="version-warning"]')
                .should('contain.text', 'Version Conflict: student_aaa graded version 1')
                .should('contain.text', 'but version 2 is active');
        });

        it('shows the clear version conflicts button only when the peer has a conflict', () => {
            mountWithVersionConflict();
            openPopup();
            cy.get('[data-testid="clear-version-conflicts"]').should('be.visible');

            mountDefault();
            openPopup();
            cy.get('[data-testid="clear-version-conflicts"]').should('not.exist');
        });

        it('emits resolve-version-conflicts with the correct detail on click', () => {
            mountWithEmitSpy(EditPeerComponentsForm, 'resolveVersionConflicts', {
                ...defaultProps,
                peerDetails: {
                    ...defaultProps.peerDetails,
                    version_conflicts: { comp_1: { student_aaa: true } },
                },
            }, 'onResolveVersionConflicts');
            openPopup();

            cy.get('[data-testid="clear-version-conflicts"]').click();
            cy.get('@onResolveVersionConflicts').should('have.been.calledOnceWith', {
                submitterId: 'submitter_xyz',
                gradeableId: 'gradeable_001',
                peer: 'student_aaa',
                csrfToken: 'csrf_abc123',
            });
        });
    });

    describe('clear-marks emit', () => {
        it('emits clear-marks with the correct detail on click', () => {
            mountWithEmitSpy(EditPeerComponentsForm, 'clearMarks', defaultProps, 'onClearMarks');
            openPopup();

            cy.get('[data-testid="clear-peer-marks"]').first().click();
            cy.get('@onClearMarks').should('have.been.calledOnceWith', {
                submitterId: 'submitter_xyz',
                gradeableId: 'gradeable_001',
                peer: 'student_aaa',
                csrfToken: 'csrf_abc123',
            });
        });

        it('passes the currently selected peer in the emit', () => {
            mountWithEmitSpy(EditPeerComponentsForm, 'clearMarks', defaultProps, 'onClearMarks');
            openPopup();

            cy.get('[data-testid="edit-peer-select"]').select('student_bbb');
            cy.get('[data-testid="peer-block"]:visible').within(() => {
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

    describe('save-component emit', () => {
        it('emits save-component with the correct detail on click', () => {
            mountWithEmitSpy(EditPeerComponentsForm, 'saveComponent', defaultProps, 'onSaveComponent');
            openPopup();

            cy.get('[data-testid="peer-block"]').first().within(() => {
                cy.get('[data-testid="save-peer-component"]').first().click();
            });
            cy.get('@onSaveComponent').should('have.been.calledOnceWith', {
                submitterId: 'submitter_xyz',
                gradeableId: 'gradeable_001',
                peer: 'student_aaa',
                componentId: 'comp_1',
                csrfToken: 'csrf_abc123',
            });
        });
    });

    describe('accessibility', () => {
        it('opens via keyboard and exposes accessible buttons', () => {
            mountWithEmitSpy(EditPeerComponentsForm, 'clearMarks', defaultProps, 'onClearMarks');

            cy.get('[data-testid="edit-peer-trigger"]').should('have.attr', 'title');
            cy.get('[data-testid="edit-peer-trigger"]').focus();
            cy.get('[data-testid="edit-peer-trigger"]').type('{enter}');
            cy.get('[data-testid="edit-peer-select"]').should('be.visible');

            cy.get('[data-testid="clear-peer-marks"]').should('have.attr', 'title');
            cy.get('[data-testid="save-peer-component"]').first().should('have.attr', 'title');
            cy.get('[data-testid="clear-version-conflicts"]').should('not.exist');

            cy.get('[data-testid="peer-block"]').first().within(() => {
                cy.get('[data-testid="clear-peer-marks"]').focus();
                cy.get('[data-testid="clear-peer-marks"]').type('{enter}');
            });
            cy.get('@onClearMarks').should('have.been.called');
        });
    });

    describe('edge cases', () => {
        it('handles empty peers without crashing', () => {
            mountDefault({ peers: [], componentScores: {}, peerDetails: { graders: {}, marks_assigned: {} } });
            openPopup();
            cy.get('[data-testid="edit-peer-select"] option').should('have.length', 0);
            cy.get('[data-testid="warning-text"]').should('be.visible');
        });

        it('handles missing mark entry without crashing', () => {
            mountDefault({ components: [{ id: 'comp_1', title: 'Test', max: 10, marks: [999] }], marks: {} });
            openPopup();
            cy.get('[data-testid="mark-row-999"]').should('exist');
            cy.get('[data-testid="mark-points"]').should('have.text', '');
            cy.get('[data-testid="mark-title"]').should('have.text', '');
        });
    });
});
