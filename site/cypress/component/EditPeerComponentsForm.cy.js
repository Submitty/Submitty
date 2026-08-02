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

// Mounts a single-peer component with one component and the given earned score, used to exercise the score badge rendering.
function mountScore(earned, max, extraCredit = false) {
    return cy.mount(EditPeerComponentsForm, {
        props: {
            ...defaultProps,
            peers: ['student_aaa'],
            components: [{ id: 'comp_1', title: 'Test Component', max, marks: [], extra_credit: extraCredit }],
            componentScores: { comp_1: { student_aaa: earned } },
            peerDetails: { graders: {}, marks_assigned: {} },
        },
    });
}

describe('EditPeerComponentsForm', () => {
    describe('rendering', () => {
        it('opens and closes the popup from the trigger', () => {
            mountDefault();
            cy.get('[data-testid="edit-peer-trigger"]').should('contain.text', 'Edit Peer Components');
            cy.get('[data-testid="edit-peer-select"]').should('not.exist');

            openPopup();
            cy.get('[data-testid="edit-peer-select"]').should('be.visible');

            cy.get('[data-testid="close-button"]').click();
            cy.get('[data-testid="edit-peer-select"]').should('not.exist');
        });

        it('renders each peer as an option using its display name, falling back to the raw id', () => {
            mountDefault();
            openPopup();
            cy.get('[data-testid="edit-peer-select"] option').should('have.length', 2);
            cy.get('[data-testid="edit-peer-select"]').contains('option', 'Alice Aaa (student_aaa)');
            cy.get('[data-testid="edit-peer-select"]').contains('option', 'Bob Bbb (student_bbb)');

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

        it('defaults to the first peer and switches visible blocks on select', () => {
            mountDefault();
            openPopup();
            cy.get('[data-testid="edit-peer-select"]').should('have.value', 'student_aaa');
            cy.get('[data-testid="peer-block"]').first().should('be.visible');
            cy.get('[data-testid="peer-block"]').eq(1).should('not.be.visible');

            cy.get('[data-testid="edit-peer-select"]').select('student_bbb');
            cy.get('[data-testid="peer-block"]').first().should('not.be.visible');
            cy.get('[data-testid="peer-block"]').eq(1).should('be.visible');
        });
    });

    describe('score badge', () => {
        it('colors the badge by score and shows earned / max text', () => {
            mountScore(10, 10);
            openPopup();
            cy.get('[data-testid="score-pill-badge"]').should('have.class', 'green-background');
            cy.get('[data-testid="score-pill-badge"]').should('have.text', '10 / 10');

            mountScore(6, 10);
            openPopup();
            cy.get('[data-testid="score-pill-badge"]').should('have.class', 'yellow-background');

            mountScore(5, 10);
            openPopup();
            cy.get('[data-testid="score-pill-badge"]').should('have.class', 'red-background');

            mountScore(0, 10);
            openPopup();
            cy.get('[data-testid="score-pill-badge"]').should('have.class', 'red-background');
            cy.get('[data-testid="score-pill-badge"]').should('have.text', '0 / 10');

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
            mountScore(0, 10, true);
            openPopup();
            cy.get('[data-testid="score-pill-badge"]').should('have.text', '+0');
            cy.get('[data-testid="score-pill-badge"]').should('have.class', 'gray-background');

            mountScore(4, 10, true);
            openPopup();
            cy.get('[data-testid="score-pill-badge"]').should('have.text', '+4');
            cy.get('[data-testid="score-pill-badge"]').should('have.class', 'green-background');
        });

        it('hides the badge when max is 0 or the peer has no score', () => {
            mountScore(0, 0);
            openPopup();
            cy.get('[data-testid="no-badge"]').should('exist');
            cy.get('[data-testid="score-pill-badge"]').should('not.exist');

            mountDefault({ componentScores: {}, peerDetails: { graders: {}, marks_assigned: {} } });
            openPopup();
            cy.get('[data-testid="box-badge"]').should('not.exist');
        });
    });

    describe('mark checkboxes', () => {
        it('checks assigned marks, leaves unassigned unchecked, and unchecks peers with no data', () => {
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

        it('emits mark-change on toggle and persists toggles across close/reopen', () => {
            mountWithEmitSpy(EditPeerComponentsForm, 'markChange', defaultProps, 'onMarkChange');
            openPopup();

            cy.get('[data-testid="peer-block"]').first().within(() => {
                cy.get('[data-testid="mark-row-102"]').within(() => {
                    cy.get('[data-testid="mark-checkbox"]').check();
                });
            });
            cy.get('@onMarkChange').should('have.been.calledWith', { peer: 'student_aaa', componentId: 'comp_1' });

            // Popup.vue destroys the slot DOM on close (v-if), so reopening must
            // re-render from local state rather than the stale page-load props.
            cy.get('[data-testid="close-button"]').click();
            openPopup();

            cy.get('[data-testid="peer-block"]').first().within(() => {
                cy.get('[data-testid="mark-row-102"]').within(() => {
                    cy.get('[data-testid="mark-checkbox"]').should('be.checked');
                });
                cy.get('[data-testid="mark-row-101"]').within(() => {
                    cy.get('[data-testid="mark-checkbox"]').should('be.checked');
                });
            });

            cy.get('[data-testid="peer-block"]').first().within(() => {
                cy.get('[data-testid="mark-row-101"]').within(() => {
                    cy.get('[data-testid="mark-checkbox"]').uncheck();
                });
            });
            cy.get('[data-testid="close-button"]').click();
            openPopup();

            cy.get('[data-testid="peer-block"]').first().within(() => {
                cy.get('[data-testid="mark-row-101"]').within(() => {
                    cy.get('[data-testid="mark-checkbox"]').should('not.be.checked');
                });
                cy.get('[data-testid="mark-row-102"]').within(() => {
                    cy.get('[data-testid="mark-checkbox"]').should('be.checked');
                });
            });
        });
    });

    describe('version conflicts', () => {
        it('shows warnings and the clear button only for conflicting peers, and emits resolve-version-conflicts', () => {
            mountWithEmitSpy(EditPeerComponentsForm, 'resolveVersionConflicts', {
                ...defaultProps,
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
            }, 'onResolveVersionConflicts');
            openPopup();

            cy.get('[data-testid="version-warning"]').should('have.length', 1);
            cy.get('[data-testid="version-warning"]')
                .should('contain.text', 'Version Conflict: student_aaa graded version 1')
                .should('contain.text', 'but version 2 is active');
            cy.get('[data-testid="clear-version-conflicts"]').should('be.visible');

            cy.get('[data-testid="clear-version-conflicts"]').click();
            cy.get('@onResolveVersionConflicts').should('have.been.calledOnceWith', {
                submitterId: 'submitter_xyz',
                gradeableId: 'gradeable_001',
                peer: 'student_aaa',
                csrfToken: 'csrf_abc123',
            });

            mountDefault();
            openPopup();
            cy.get('[data-testid="version-warning"]').should('not.exist');
            cy.get('[data-testid="clear-version-conflicts"]').should('not.exist');
        });
    });

    describe('emits', () => {
        it('emits clear-marks with the currently selected peer', () => {
            mountWithEmitSpy(EditPeerComponentsForm, 'clearMarks', defaultProps, 'onClearMarks');
            openPopup();

            cy.get('[data-testid="clear-peer-marks"]').first().click();
            cy.get('@onClearMarks').should('have.been.calledOnceWith', {
                submitterId: 'submitter_xyz',
                gradeableId: 'gradeable_001',
                peer: 'student_aaa',
                csrfToken: 'csrf_abc123',
            });

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
        it('handles empty peers and missing mark entries without crashing', () => {
            mountDefault({ peers: [], componentScores: {}, peerDetails: { graders: {}, marks_assigned: {} } });
            openPopup();
            cy.get('[data-testid="edit-peer-select"] option').should('have.length', 0);
            cy.get('[data-testid="warning-text"]').should('be.visible');

            mountDefault({ components: [{ id: 'comp_1', title: 'Test', max: 10, marks: [999] }], marks: {} });
            openPopup();
            cy.get('[data-testid="mark-row-999"]').should('exist');
            cy.get('[data-testid="mark-points"]').should('have.text', '');
            cy.get('[data-testid="mark-title"]').should('have.text', '');
        });
    });
});
