import { ref, h, defineComponent } from 'vue';
import EditPeerComponentsForm from '../../vue/src/components/ta_grading/EditPeerComponentsForm.vue';

const defaultProps = {
    peers: ['student1', 'student2'],
    peerNames: {
        student1: 'Student 1',
        student2: 'Student 2',
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
        comp_1: { student1: 7 },
        comp_2: { student1: 3 },
    },
    peerDetails: {
        graders: { comp_1: ['student1'], comp_2: ['student1'] },
        marks_assigned: {
            comp_1: { student1: [101] },
            comp_2: { student1: [201] },
        },
        graded_versions: {
            comp_1: { student1: 2 },
            comp_2: { student1: 2 },
        },
        version_conflicts: {},
    },
    marks: {
        101: { title: 'Follows naming conventions', points: '+3' },
        102: { title: 'No dead code', points: '+2' },
        201: { title: 'README is complete', points: '+5' },
    },
};

// Mounts the component inside a wrapper that flips `visible` on `toggle`, mirroring
// how the real parent (PeerPanel.twig + toggleEditPeerComponentsForm) controls it.
// Pass [eventName, alias] pairs as `emitSpies` to stub emits and assert on them
// with `cy.get('@alias')`.
function mountWithToggleWrapper(props = {}, emitSpies = []) {
    const visible = ref(false);
    const Wrapper = defineComponent({
        setup() {
            const listeners = emitSpies.reduce((acc, [name, alias]) => {
                acc[`on${name.charAt(0).toUpperCase()}${name.slice(1)}`] = cy.stub().as(alias);
                return acc;
            }, {});
            return () => h(EditPeerComponentsForm, {
                ...defaultProps,
                ...props,
                visible: visible.value,
                onToggle: () => { visible.value = !visible.value; },
                ...listeners,
            });
        },
    });
    return cy.mount(Wrapper);
}

function openPopup() {
    cy.get('[data-testid="edit-peer-trigger"]').click();
}

// Mounts a single-peer component with one component and the given earned score, used to exercise the score badge rendering.
function mountScore(earned, max, extraCredit = false) {
    return mountWithToggleWrapper({
        peers: ['student1'],
        components: [{ id: 'comp_1', title: 'Test Component', max, marks: [], extra_credit: extraCredit }],
        componentScores: { comp_1: { student1: earned } },
        peerDetails: { graders: {}, marks_assigned: {} },
    });
}

describe('EditPeerComponentsForm', () => {
    describe('rendering', () => {
        it('opens/closes the popup (mouse and keyboard), lists peers, and renders per-peer blocks with accessible buttons', () => {
            mountWithToggleWrapper();
            cy.get('[data-testid="edit-peer-trigger"]').should('contain.text', 'Edit Peer Components');
            cy.get('[data-testid="edit-peer-trigger"]').should('have.attr', 'title');
            cy.get('[data-testid="edit-peer-select"]').should('not.exist');

            // Opens via keyboard and exposes accessible buttons.
            cy.get('[data-testid="edit-peer-trigger"]').focus();
            cy.get('[data-testid="edit-peer-trigger"]').type('{enter}');
            cy.get('[data-testid="edit-peer-select"]').should('be.visible');
            cy.get('[data-testid="clear-peer-marks"]').should('have.attr', 'title');
            cy.get('[data-testid="save-peer-component"]').first().should('have.attr', 'title');
            cy.get('[data-testid="clear-version-conflicts"]').should('not.exist');

            cy.get('[data-testid="close-button"]').click();
            cy.get('[data-testid="edit-peer-select"]').should('not.exist');

            openPopup();
            cy.get('[data-testid="edit-peer-select"]').should('be.visible');
            cy.get('[data-testid="edit-peer-select"] option').should('have.length', 2);
            cy.get('[data-testid="edit-peer-select"]').contains('option', 'Student 1');
            cy.get('[data-testid="edit-peer-select"]').contains('option', 'Student 2');

            cy.get('[data-testid="component-title"]').first().should('contain.text', 'Code Quality');
            cy.get('[data-testid="mark-row-101"]').should('exist');
            cy.get('[data-testid="mark-row-102"]').should('exist');
            cy.get('[data-testid="mark-row-201"]').should('exist');

            cy.get('[data-testid="edit-peer-select"]').should('have.value', 'student1');
            cy.get('[data-testid="peer-block"]').first().should('be.visible');
            cy.get('[data-testid="peer-block"]').eq(1).should('not.be.visible');

            cy.get('[data-testid="edit-peer-select"]').select('student2');
            cy.get('[data-testid="peer-block"]').first().should('not.be.visible');
            cy.get('[data-testid="peer-block"]').eq(1).should('be.visible');

            cy.get('[data-testid="close-button"]').click();
            cy.get('[data-testid="edit-peer-select"]').should('not.exist');

            mountWithToggleWrapper({ peerNames: {} });
            openPopup();
            cy.get('[data-testid="edit-peer-select"]').contains('option', 'student1');
        });
    });

    describe('score badge', () => {
        it('renders badge color, text, and extra-credit formatting, hiding the badge when unused', () => {
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

            mountScore(0, 10, true);
            openPopup();
            cy.get('[data-testid="score-pill-badge"]').should('have.text', '+0');
            cy.get('[data-testid="score-pill-badge"]').should('have.class', 'gray-background');

            mountScore(4, 10, true);
            openPopup();
            cy.get('[data-testid="score-pill-badge"]').should('have.text', '+4');
            cy.get('[data-testid="score-pill-badge"]').should('have.class', 'green-background');

            mountScore(0, 0);
            openPopup();
            cy.get('[data-testid="no-badge"]').should('exist');
            cy.get('[data-testid="score-pill-badge"]').should('not.exist');

            mountWithToggleWrapper({
                componentScores: {},
                peerDetails: { graders: {}, marks_assigned: {} },
            });
            openPopup();
            cy.get('[data-testid="box-badge"]').should('not.exist');
        });
    });

    describe('mark checkboxes', () => {
        it('reflects assigned marks, persists toggles across close/reopen, and emits mark-change/clear-marks/save-component', () => {
            mountWithToggleWrapper({}, [['markChange', 'onMarkChange'], ['clearMarks', 'onClearMarks'], ['saveComponent', 'onSaveComponent']]);
            openPopup();

            cy.get('[data-testid="peer-block"]').first().within(() => {
                cy.get('[data-testid="mark-row-101"]').within(() => {
                    cy.get('[data-testid="mark-checkbox"]').should('be.checked');
                });

                cy.get('[data-testid="mark-row-102"]').within(() => {
                    cy.get('[data-testid="mark-checkbox"]').should('not.be.checked');
                });
            });

            cy.get('[data-testid="peer-block"]').first().within(() => {
                cy.get('[data-testid="mark-row-102"]').within(() => {
                    cy.get('[data-testid="mark-checkbox"]').check();
                });
            });
            cy.get('@onMarkChange').should('have.been.calledWith', { peer: 'student1', componentId: 'comp_1' });

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

            // clear-marks / save-component emits use the selected peer + component.
            cy.get('[data-testid="clear-peer-marks"]').first().click();
            cy.get('@onClearMarks').should('have.been.calledWith', {
                submitterId: 'submitter_xyz',
                gradeableId: 'gradeable_001',
                peer: 'student1',
                csrfToken: 'csrf_abc123',
            });

            cy.get('[data-testid="peer-block"]').first().within(() => {
                cy.get('[data-testid="save-peer-component"]').first().click();
            });
            cy.get('@onSaveComponent').should('have.been.calledOnceWith', {
                submitterId: 'submitter_xyz',
                gradeableId: 'gradeable_001',
                peer: 'student1',
                componentId: 'comp_1',
                csrfToken: 'csrf_abc123',
            });

            // A peer with no grading data shows all unchecked.
            cy.get('[data-testid="edit-peer-select"]').select('student2');
            cy.get('[data-testid="peer-block"]').eq(1).within(() => {
                cy.get('[data-testid="mark-checkbox"]').should('have.length.greaterThan', 0);
                cy.get('[data-testid="mark-checkbox"]').each(($checkbox) => {
                    cy.wrap($checkbox).should('not.be.checked');
                });
            });

            // clear-marks uses the currently selected peer.
            cy.get('[data-testid="peer-block"]:visible').within(() => {
                cy.get('[data-testid="clear-peer-marks"]').click();
            });
            cy.get('@onClearMarks').should('have.been.calledWith', {
                submitterId: 'submitter_xyz',
                gradeableId: 'gradeable_001',
                peer: 'student2',
                csrfToken: 'csrf_abc123',
            });

            // clear-peer-marks also activates via keyboard.
            cy.get('[data-testid="peer-block"]:visible').within(() => {
                cy.get('[data-testid="clear-peer-marks"]').focus();
                cy.get('[data-testid="clear-peer-marks"]').type('{enter}');
            });
            cy.get('@onClearMarks').should('have.been.called');
        });
    });

    describe('version conflicts', () => {
        it('shows warnings and the clear button only for conflicting peers, and emits resolve-version-conflicts', () => {
            mountWithToggleWrapper({
                peerDetails: {
                    ...defaultProps.peerDetails,
                    graded_versions: {
                        comp_1: { student1: 1 },
                        comp_2: { student1: 2 },
                    },
                    version_conflicts: {
                        comp_1: { student1: true },
                        comp_2: { student1: false },
                    },
                },
            }, [['resolveVersionConflicts', 'onResolveVersionConflicts']]);
            openPopup();

            cy.get('[data-testid="version-warning"]').should('have.length', 1);
            cy.get('[data-testid="version-warning"]')
                .should('contain.text', 'Version Conflict: student1 graded version 1')
                .should('contain.text', 'but version 2 is active');
            cy.get('[data-testid="clear-version-conflicts"]').should('be.visible');

            cy.get('[data-testid="clear-version-conflicts"]').click();
            cy.get('@onResolveVersionConflicts').should('have.been.calledOnceWith', {
                submitterId: 'submitter_xyz',
                gradeableId: 'gradeable_001',
                peer: 'student1',
                csrfToken: 'csrf_abc123',
            });

            mountWithToggleWrapper();
            openPopup();
            cy.get('[data-testid="version-warning"]').should('not.exist');
            cy.get('[data-testid="clear-version-conflicts"]').should('not.exist');
        });
    });

    describe('edge cases', () => {
        it('falls back to empty identifiers and handles empty peers / missing marks without crashing', () => {
            mountWithToggleWrapper({
                submitterId: undefined,
                gradeableId: undefined,
                csrfToken: undefined,
                peerDetails: {
                    ...defaultProps.peerDetails,
                    version_conflicts: { comp_1: { student1: true } },
                },
            }, [['clearMarks', 'onClearMarks']]);
            openPopup();

            cy.get('[data-testid="clear-peer-marks"]').first().click();
            cy.get('@onClearMarks').should('have.been.calledOnceWith', {
                submitterId: '',
                gradeableId: '',
                peer: 'student1',
                csrfToken: '',
            });

            cy.get('[data-testid="clear-version-conflicts"]').click();
            cy.get('[data-testid="save-peer-component"]').first().click();

            mountWithToggleWrapper({
                peers: [],
                componentScores: {},
                peerDetails: { graders: {}, marks_assigned: {} },
            });
            openPopup();
            cy.get('[data-testid="edit-peer-select"] option').should('have.length', 0);
            cy.get('[data-testid="warning-text"]').should('be.visible');

            mountWithToggleWrapper({
                components: [{ id: 'comp_1', title: 'Test', max: 10, marks: [999] }],
                marks: {},
            });
            openPopup();
            cy.get('[data-testid="mark-row-999"]').should('exist');
            cy.get('[data-testid="mark-points"]').should('have.text', '');
            cy.get('[data-testid="mark-title"]').should('have.text', '');
        });
    });
});
